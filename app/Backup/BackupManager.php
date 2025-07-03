<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

namespace App\Backup;

use App\Notification\NotificationManager;
use App\Storage\StorageFactory;
use App\System\LocalFinder;
use Psr\Log\LoggerInterface;
use League\Flysystem\Filesystem;
use Throwable;

/**
 * Main coordinator for the backup process.
 * Manages finding users, creating archives, uploading, and rotating backups.
 */

class BackupManager
{
    private array $config;
    private LoggerInterface $logger;
    private NotificationManager $notificationManager;
    private LocalFinder $localFinder;

    public function __construct(array $config, LoggerInterface $logger, NotificationManager $notificationManager)
    {
        // Validate backup_dirs configuration
        if (empty($config['backup_dirs']) || !is_array($config['backup_dirs'])) {
            throw new \InvalidArgumentException('Missing or invalid backup dirs configuration.');
        }
        $this->config = $config;
        $this->logger = $logger;
        $this->notificationManager = $notificationManager;
        $this->localFinder = new LocalFinder($this->config, $this->logger);
    }

    /**
     * Runs the entire backup process.
     *
     * @param bool $isDryRun If true, simulates the process without actual file operations.
     */

    public function run(bool $isDryRun = false): void
    {
        $usersToBackup = $this->localFinder->findBackupUsers();
        if (empty($usersToBackup)) {
            $this->logger->info('No users found to backup in configured backup directories.');
            return;
        }

        $this->logger->info('Found users to backup: ' . implode(', ', array_keys($usersToBackup)));

        // Defensive check for remotes config
        if (empty($this->config['remotes']) || !is_array($this->config['remotes'])) {
            $message = 'Missing or invalid remotes configuration.';
            $this->logger->error($message, ['config' => $this->config['remotes'] ?? null]);
            $this->notificationManager->sendAlert('Backup failed: remotes misconfiguration', $message);
            return;
        }

        $archiveHandler = new ArchiveHandler($this->config, $this->logger);
        $storages = [];
        foreach ($this->config['remotes'] as $remoteConfig) {
            if (empty($remoteConfig['driver'])) {
                $this->logger->warning('Remote config missing driver, skipping.', ['remote' => $remoteConfig]);
                continue;
            }
            $storage = StorageFactory::create($remoteConfig['driver'], $remoteConfig, $this->logger);
            if ($storage) {
                $storages[] = ['driver' => $remoteConfig['driver'], 'storage' => $storage];
            } else {
                $this->logger->error('Failed to create storage for remote.', ['remote' => $remoteConfig]);
            }
        }
        if (empty($storages)) {
            $this->logger->error('No valid remote storage backends available.');
            $this->notificationManager->sendAlert('Backup failed: no valid remote storage', 'No valid remote storage backends available.');
            return;
        }

        foreach ($usersToBackup as $username => $userPath) {
            $this->logger->info("--- Starting backup for user: {$username} ---");
            try {
                $archivePath = $archiveHandler->create($username, $userPath, $isDryRun);

                if ($archivePath) {
                    if (is_array($archivePath)) {
                        // Trường hợp backup file trực tiếp trong thư mục gốc
                        foreach ($archivePath as $filePath) {
                            foreach ($storages as $storageInfo) {
                                $this->uploadBackup($storageInfo['storage'], $filePath, $isDryRun, $storageInfo['driver']);
                            }
                            $this->cleanupLocal($filePath, $isDryRun);
                        }
                    } else {
                        foreach ($storages as $storageInfo) {
                            $this->uploadBackup($storageInfo['storage'], $archivePath, $isDryRun, $storageInfo['driver']);
                        }
                        $this->cleanupLocal($archivePath, $isDryRun);
                    }
                }
            } catch (Throwable $e) {
                $this->logger->error("Failed to backup user {$username}: " . $e->getMessage(), ['exception' => $e]);
                $this->notificationManager->sendAlert("Backup failed for user: {$username}", $e->getMessage());
            }
            $this->logger->info("--- Finished backup for user: {$username} ---");
        }

        // Perform rotation for each remote
        foreach ($storages as $storageInfo) {
            $this->performRotation($storageInfo['storage'], $isDryRun, $storageInfo['driver']);
        }
    }

    private function uploadBackup(Filesystem $storage, string $archivePath, bool $isDryRun, string $driver = ''): void
    {
        $remotePath = ($this->config['remote']['path'] ?? '') . '/' . basename($archivePath);
        $this->logger->info("Uploading '{$archivePath}' to '{$remotePath}' on driver '{$driver}'.");

        if ($isDryRun) {
            $this->logger->info('[DRY-RUN] Skipping actual upload.');
            return;
        }

        $stream = fopen($archivePath, 'r');
        if ($stream === false) {
            throw new \Exception("Failed to open archive file for reading: {$archivePath}");
        }

        try {
            $storage->writeStream($remotePath, $stream);
            $this->logger->info("Upload successful to driver '{$driver}'.");
        } catch (Throwable $e) {
            throw new \Exception("Failed to upload to remote storage ({$driver}): " . $e->getMessage(), 0, $e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function cleanupLocal(string $archivePath, bool $isDryRun): void
    {
        $this->logger->info("Cleaning up local archive: {$archivePath}");
        if ($isDryRun) {
            $this->logger->info('[DRY-RUN] Skipping local file deletion.');
            return;
        }

        if (file_exists($archivePath)) {
            if (!unlink($archivePath)) {
                $this->logger->warning("Could not delete local archive file: {$archivePath}");
            }
        }
    }

    private function performRotation(Filesystem $storage, bool $isDryRun, string $driver = ''): void
    {
        if (empty($this->config['rotation']['enabled'])) {
            $this->logger->info('Backup rotation is disabled. Skipping.');
            return;
        }

        $this->logger->info('Starting backup rotation...');
        try {
            $rotationManager = new RotationManager($this->config, $this->logger, $storage);
            $rotationManager->run($isDryRun);
            $this->logger->info('Backup rotation completed.');
        } catch (Throwable $e) {
            $this->logger->error('Backup rotation failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->notificationManager->sendAlert('Backup rotation failed', $e->getMessage());
        }
    }
}
