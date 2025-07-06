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

        $tmpDir = \App\Utils\Helper::getTmpDir();

        foreach ($usersToBackup as $username => $userPath) {
            $this->logger->info("--- Starting backup for user: {$username} ---");
            try {
                $archivePath = $archiveHandler->create($username, $userPath, $isDryRun);

                if ($archivePath) {
                    $archiveFiles = is_array($archivePath) ? $archivePath : [$archivePath];
                    foreach ($archiveFiles as $filePath) {
                        $allUploadsOk = true;
                        foreach ($storages as $storageInfo) {
                            try {
                                $this->uploadBackup($storageInfo['storage'], $filePath, $isDryRun, $storageInfo['driver']);
                            } catch (\Throwable $e) {
                                $allUploadsOk = false;
                                $this->logger->error("Upload failed for file {$filePath} to remote {$storageInfo['driver']}: " . $e->getMessage());
                            }
                        }
                        // Only cleanup if all uploads succeeded and file is in TMP_DIR
                        if ($allUploadsOk && $this->isInTmpDir($filePath, $tmpDir)) {
                            $this->cleanupLocal($filePath, $isDryRun);
                        } elseif (!$allUploadsOk) {
                            $this->logger->warning("File {$filePath} was NOT deleted because not all uploads succeeded.");
                        } elseif (!$this->isInTmpDir($filePath, $tmpDir)) {
                            $this->logger->info("File {$filePath} is not in TMP_DIR, skipping deletion.");
                        }
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

        // Cleanup the entire TMP_DIR after completing the backup for all users
        $this->cleanupTmpDir($tmpDir, $isDryRun);
    }

    /**
     * Check if a file is in TMP_DIR
     */
    private function isInTmpDir(string $filePath, string $tmpDir): bool
    {
        $realTmp = realpath($tmpDir);
        $realFile = realpath($filePath);
        if ($realTmp && $realFile && str_starts_with($realFile, $realTmp)) {
            return true;
        }
        return false;
    }

    private function uploadBackup(Filesystem $storage, string $archivePath, bool $isDryRun, string $driver = ''): void
    {
        $remotePath = ($this->config['remote']['path'] ?? '') . '/' . basename($archivePath);
        $this->logger->info("Uploading '{$archivePath}' to '{$remotePath}' on driver '{$driver}'.");

        if ($isDryRun) {
            $this->logger->info('[DRY-RUN] Skipping actual upload.');
            return;
        }

        // Check if file already exists on remote and has the same size
        try {
            if ($storage->fileExists($remotePath)) {
                $remoteSize = $storage->fileSize($remotePath);
                $localSize = filesize($archivePath);
                if ($remoteSize === $localSize) {
                    $this->logger->info("Remote file '{$remotePath}' already exists with matching size. Skipping upload.");
                    return;
                } else {
                    $this->logger->warning("Remote file '{$remotePath}' exists but size differs (remote: {$remoteSize}, local: {$localSize}). Overwriting.");
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Could not check remote file existence: " . $e->getMessage());
        }

        $stream = fopen($archivePath, 'r');
        if ($stream === false) {
            throw new \Exception("Failed to open archive file for reading: {$archivePath}");
        }

        try {
            $storage->writeStream($remotePath, $stream);
            // Check file size after upload
            $localSize = filesize($archivePath);
            try {
                $remoteSize = $storage->fileSize($remotePath);
            } catch (\Throwable $e) {
                throw new \Exception("Failed to get remote file size after upload: " . $e->getMessage(), 0, $e);
            }
            if ($remoteSize !== $localSize) {
                $this->logger->error("Remote file size ({$remoteSize}) does not match local file size ({$localSize}) for '{$remotePath}'.");
                throw new \Exception("Upload failed: remote file size does not match local file size for '{$remotePath}'.");
            }
            $this->logger->info("Upload successful to driver '{$driver}'. File size verified: {$localSize} bytes.");
        } catch (\Throwable $e) {
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

    /**
     * Cleanup all files/subdirectories in TMP_DIR (except lock files or files currently in use).
     */
    private function cleanupTmpDir(string $tmpDir, bool $isDryRun): void
    {
        $this->logger->info("Cleaning up all files in TMP_DIR: {$tmpDir}");
        if ($isDryRun) {
            $this->logger->info('[DRY-RUN] Skipping TMP_DIR cleanup.');
            return;
        }
        if (!is_dir($tmpDir)) {
            return;
        }
        $lockFile = $tmpDir . '/.backup.lock';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($path === false || $path === $lockFile) {
                continue;
            }
            if ($file->isFile()) {
                @unlink($path);
            } elseif ($file->isDir()) {
                @rmdir($path);
            }
        }
    }
}
