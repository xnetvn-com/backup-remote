<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
        $usersToBackup = $this->localFinder->findHestiaUsers();
        if (empty($usersToBackup)) {
            $this->logger->info('No Hestia users found to backup.');
            return;
        }

        $this->logger->info('Found users to backup: ' . implode(', ', array_keys($usersToBackup)));

        $storage = StorageFactory::create($this->config, $this->logger);
        $archiveHandler = new ArchiveHandler($this->config, $this->logger);

        foreach ($usersToBackup as $username => $userPath) {
            $this->logger->info("--- Starting backup for user: {$username} ---");
            try {
                $archivePath = $archiveHandler->create($username, $userPath, $isDryRun);

                if ($archivePath) {
                    $this->uploadBackup($storage, $archivePath, $isDryRun);
                    $this->cleanupLocal($archivePath, $isDryRun);
                }
            } catch (Throwable $e) {
                $this->logger->error("Failed to backup user {$username}: " . $e->getMessage(), ['exception' => $e]);
                $this->notificationManager->sendAlert("Backup failed for user: {$username}", $e->getMessage());
            }
            $this->logger->info("--- Finished backup for user: {$username} ---");
        }

        $this->performRotation($storage, $isDryRun);
    }

    private function uploadBackup(Filesystem $storage, string $archivePath, bool $isDryRun): void
    {
        $remotePath = ($this->config['remote']['path'] ?? '') . '/' . basename($archivePath);
        $this->logger->info("Uploading '{$archivePath}' to '{$remotePath}'.");

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
            $this->logger->info("Upload successful.");
        } catch (Throwable $e) {
            throw new \Exception("Failed to upload to remote storage: " . $e->getMessage(), 0, $e);
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

    private function performRotation(Filesystem $storage, bool $isDryRun): void
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
