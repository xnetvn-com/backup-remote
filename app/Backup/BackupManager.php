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
        // add initialization log
        $remoteDrivers = array_map(fn($r) => $r['driver'] ?? 'unknown', (array)($this->config['remotes'] ?? []));
        $this->logger->debug('BackupManager initialized', [
            'backup_dirs' => $this->config['backup_dirs'],
            'remotesCount' => count($remoteDrivers),
            'remoteDrivers' => $remoteDrivers,
        ]);
    }

    /**
     * Runs the entire backup process.
     *
     * @param bool $isDryRun If true, simulates the process without actual file operations.
     */

    public function run(bool $isDryRun = false, bool $isForce = false, $isUploadFileByFile = null): void
    {
        
        if (!$isForce) {
            $lastSuccessTime = $this->checkLastSuccessfulBackup();
            if($lastSuccessTime) {
                $this->logger->info(sprintf('Last successful backup at %s, within 24h, skipping execution.', $lastSuccessTime));
                exit();
            }
        }
        
        $isAllUploadedSuccess = true;
        
        // log start of backup run
        $this->logger->info('Backup process started', ['dry_run' => $isDryRun]);
        // log configuration values
        $remoteDrivers = array_map(fn($r) => $r['driver'] ?? 'unknown', (array)($this->config['remotes'] ?? []));
        $this->logger->debug('Running backup with configuration', [
            'backup_dirs' => $this->config['backup_dirs'],
            'remotesCount' => count($remoteDrivers),
            'remoteDrivers' => $remoteDrivers,
        ]);

        if(is_null($isUploadFileByFile)) {
            $isUploadFileByFile = $this->config['upload_file_by_file'] ?? false;
        }

        $isUploadFileByFile = (bool) $isUploadFileByFile;

        $this->logger->debug('Upload file by file setting', ['isUploadFileByFile' => $isUploadFileByFile]);

        $compression = \App\Utils\Helper::env('BACKUP_COMPRESSION', 'none');
        $encryption  = \App\Utils\Helper::env('BACKUP_ENCRYPTION', 'none');
        // log chosen compression and encryption
        $this->logger->debug('Backup methods selected', [
            'compression' => $compression,
            'encryption' => $encryption,
        ]);

        $usersToBackup = $this->localFinder->findBackupUsers();
        if (empty($usersToBackup)) {
            $this->logger->info('No users found to backup in configured backup directories.');
            return;
        }

        $this->logger->info('Found users to backup: ' . implode(', ', array_keys($usersToBackup)));

        // Defensive check for remotes config
        if (empty($this->config['remotes']) || !is_array($this->config['remotes'])) {
            $message = 'Missing or invalid remotes configuration.';
            $this->logger->error($message, ['remotesConfigured' => false]);
            $this->notificationManager->sendAlert('Backup failed: remotes misconfiguration', $message);
            return;
        }

        $archiveHandler = new ArchiveHandler($this->config, $this->logger);
        $storages = [];
        foreach ($this->config['remotes'] as $remoteConfig) {
            if (empty($remoteConfig['driver'])) {
                $this->logger->warning('Remote config missing driver, skipping.', ['driver' => $remoteConfig['driver'] ?? null]);
                continue;
            }
            $storage = StorageFactory::create($remoteConfig['driver'], $remoteConfig, $this->logger);
            if ($storage) {
                $storages[] = ['driver' => $remoteConfig['driver'], 'storage' => $storage];
            } else {
                $this->logger->error('Failed to create storage for remote.', ['driver' => $remoteConfig['driver']]);
            }
        }
        if (empty($storages)) {
            $this->logger->error('No valid remote storage backends available.');
            $this->notificationManager->sendAlert('Backup failed: no valid remote storage', 'No valid remote storage backends available.');
            return;
        }

        $tmpDir = \App\Utils\Helper::getTmpDir();

        $this->logger->info("Found usersToBackup : " . json_encode(($usersToBackup), JSON_PRETTY_PRINT));

        foreach ($usersToBackup as $username => $userPath) {

            $remoteFilesStatus = [];
            
            $allExist = false;
            $anyExists = false;
            
            if('__root__' !== $username) {

                $allExist = true;
                

                $this->logger->info("--- Starting backup for user: {$username} ---");

                // Pre-check: skip if remote already has this backup on all remotes
                $expectedFilename = basename(\App\Utils\Helper::createXbkFilename($userPath, $compression, $encryption));
                $this->logger->info("Checking if backup file already exists on remote storages: {$userPath}/{$expectedFilename}");
                
                foreach ($storages as $storageInfo) {
                    $driver = $storageInfo['driver'];
                    $remotePath = ($this->config['remote']['path'] ?? '') . '/' . $expectedFilename;
                    
                    try {
                        $exists = $storageInfo['storage']->fileExists($remotePath);
                        $remoteFilesStatus[$driver] = [
                            'exists' => $exists,
                            'path' => $remotePath,
                            'size' => null
                        ];
                        
                        if ($exists) {
                            $anyExists = true;
                            try {
                                $remoteSize = $storageInfo['storage']->fileSize($remotePath);
                                $remoteFilesStatus[$driver]['size'] = $remoteSize;
                                $this->logger->info("Remote file exists on {$driver}: {$remotePath} (size: " . number_format($remoteSize) . " bytes)");
                            } catch (\Throwable $e) {
                                $this->logger->warning("Could not get file size for {$remotePath} on {$driver}: " . $e->getMessage());
                                $remoteFilesStatus[$driver]['size'] = 'unknown';
                            }
                        } else {
                            $allExist = false;
                            $this->logger->info("Remote file does not exist on {$driver}: {$remotePath}");
                        }
                    } catch (\Throwable $e) {
                        $allExist = false;
                        $this->logger->warning("Could not check file existence on {$driver}: " . $e->getMessage());
                        $remoteFilesStatus[$driver] = [
                            'exists' => false,
                            'path' => $remotePath,
                            'size' => null,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                // Log summary of remote file status
                $existingCount = array_sum(array_map(fn($status) => $status['exists'] ? 1 : 0, $remoteFilesStatus));
                $totalRemotes = count($storages);
                $this->logger->info("Remote file status summary for {$username}: {$existingCount}/{$totalRemotes} remotes have the backup file");
                
                if ($allExist) {
                    $this->logger->info("Skipping backup for user {$username}: backup file exists on all remote storages ({$expectedFilename})");
                    continue;
                } elseif ($anyExists) {
                    $this->logger->info("Partial backup exists for user {$username}: will process and upload to missing remotes only");
                } else {
                    $this->logger->info("No existing backup found for user {$username}: will create new backup and upload to all remotes");
                }

            }
            

            try {
                $archivePath = $archiveHandler->create($username, $userPath, $isDryRun, $storages, $isUploadFileByFile);

                if ($archivePath) {
                    $archiveFiles = is_array($archivePath) ? $archivePath : [$archivePath];
                    foreach ($archiveFiles as $filePath) {

                        if(empty($filePath) || !file_exists($filePath) || !is_file($filePath) || !is_readable($filePath) || filesize($filePath) === 0) {
                            if(!$isUploadFileByFile) {
                                $this->logger->error("Archive file does not exist after creation: {$filePath}");
                            }
                            continue;
                        }

                        $allUploadsOk = $this->uploadBackupAllFilesToAllStorages([$filePath], $storages, $isDryRun, $remoteFilesStatus);
                        
                        if(!$allUploadsOk) {
                            $this->logger->error("Not all uploads were successful for user {$username}. Some files may not have been uploaded.");
                            $isAllUploadedSuccess = false;
                        } else {
                            $this->logger->info("All uploads completed successfully for user {$username}.");
                        }

                    }
                }
            } catch (Throwable $e) {
                $isAllUploadedSuccess = false;
                $this->logger->error("Failed to backup user {$username}: " . $e->getMessage(), ['exception' => $e]);
                $this->notificationManager->sendAlert("Backup failed for user: {$username}", $e->getMessage());
            }

            $this->logger->info("--- Finished backup for user: {$username} ---");
        }
        
        // Perform rotation for each remote
        foreach ($storages as $storageInfo) {
            $this->performRotation($storageInfo['storage'], $isDryRun, $storageInfo['driver']);
        }

        if($isAllUploadedSuccess) {

            $this->logger->info("All backups completed successfully.");
            
            // Cleanup the entire TMP_DIR after completing the backup for all users
            $this->cleanupTmpDir($tmpDir, $isDryRun);

            // Record last successful backup timestamp
            $this->writeLastSuccessfulBackup();
            
        } else {
            $this->logger->warning("Some backups had issues. Please check the logs for details.");
        }
        
    }

    public function uploadBackupAllFilesToAllStorages(array $archiveFiles = [], array $storages = [], bool $isDryRun = false, array $remoteFilesStatus = [])
    {

        $allUploadsOk = true;

        if ($archiveFiles && !empty($archiveFiles) && !empty($storages) && is_array($storages)) {
            $archiveFiles = is_array($archiveFiles) ? $archiveFiles : [$archiveFiles];
            $tmpDir = \App\Utils\Helper::getTmpDir();
            foreach ($archiveFiles as $filePath) {

                if(empty($filePath) || !file_exists($filePath) || !is_file($filePath) || !is_readable($filePath) || filesize($filePath) === 0) {
                    continue;
                }

                
                $filename = basename($filePath);
                
                $this->logger->info("Processing archive file for upload: {$filename}");
                
                foreach ($storages as $storageInfo) {
                    $driver = $storageInfo['driver'];
                    
                    // Check if this specific remote already has the file (from our earlier check)
                    $skipUpload = false;
                    if (isset($remoteFilesStatus[$driver]) && $remoteFilesStatus[$driver]['exists']) {
                        if ($remoteFilesStatus[$driver]['size'] !== null && $remoteFilesStatus[$driver]['size'] !== 'unknown') {
                            $localSize = filesize($filePath);
                            if ($remoteFilesStatus[$driver]['size'] === $localSize) {
                                $this->logger->info("Skipping upload to {$driver}: file already exists with matching size ({$localSize} bytes)");
                                $skipUpload = true;
                            } else {
                                $this->logger->warning("File exists on {$driver} but size differs (remote: {$remoteFilesStatus[$driver]['size']}, local: {$localSize}). Will re-upload.");
                            }
                        } else {
                            $this->logger->info("File exists on {$driver} but size unknown. Will attempt upload with size verification.");
                        }
                    }
                    
                    if (!$skipUpload) {
                        try {
                            $this->uploadBackup($storageInfo['storage'], $filePath, $isDryRun, $driver);
                            $this->logger->info("Successfully uploaded {$filename} to {$driver}");
                        } catch (\Throwable $e) {
                            $allUploadsOk = false;
                            $this->logger->error("Upload failed for file {$filename} to remote {$driver}: " . $e->getMessage());
                        }
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

        return $allUploadsOk;

    }

    /**
     * Check if the last successful backup was within 24 hours.
     * @param string $statusFile
     * @return bool True if should proceed, False if should skip
     */
    private function checkLastSuccessfulBackup(string $statusFile = '')
    {
        if(!$statusFile) {
            $statusFile = $this->getStatusFile();
        }
        
        $lastSuccess = $this->readLastSuccessfulBackup($statusFile);
        if ($lastSuccess && (time() - $lastSuccess->getTimestamp()) < 86000) {
            return trim($lastSuccess->format(\DateTime::ATOM)); // Return the last successful backup time in ISO format
        }
        return false;
    }

    /**
     * Get the path to the last successful backup status file.
     * @return string
     */
    private function getStatusFile(): string
    {
        $tmpDir = \App\Utils\Helper::getTmpDir();
        return $this->config['local']['status_file'] ?? $tmpDir . '/last_successful_backup.json';
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
        $localSize = filesize($archivePath);
        
        $this->logger->info("Uploading '{$archivePath}' to '{$remotePath}' on driver '{$driver}' (local size: " . number_format($localSize) . " bytes)");

        if ($isDryRun) {
            $this->logger->info('[DRY-RUN] Skipping actual upload.');
            return;
        }

        // Check if file already exists on remote and has the same size
        try {
            if ($storage->fileExists($remotePath)) {
                $remoteSize = $storage->fileSize($remotePath);
                if ($remoteSize === $localSize) {
                    $this->logger->info("Remote file '{$remotePath}' already exists with matching size (" . number_format($remoteSize) . " bytes). Skipping upload.");
                    return;
                } else {
                    $this->logger->warning("Remote file '{$remotePath}' exists but size differs (remote: " . number_format($remoteSize) . " bytes, local: " . number_format($localSize) . " bytes). Overwriting.");
                }
            } else {
                $this->logger->info("Remote file '{$remotePath}' does not exist. Proceeding with upload.");
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Could not check remote file existence for '{$remotePath}': " . $e->getMessage());
        }

        $this->logger->debug("Opening local file for reading: {$archivePath}");
        $stream = fopen($archivePath, 'r');
        if ($stream === false) {
            throw new \Exception("Failed to open archive file for reading: {$archivePath}");
        }

        try {
            $this->logger->debug("Starting upload stream to '{$remotePath}'");
            $uploadStartTime = microtime(true);
            
            $storage->writeStream($remotePath, $stream);
            
            $uploadEndTime = microtime(true);
            $uploadDuration = round($uploadEndTime - $uploadStartTime, 2);
            $uploadSpeed = $localSize > 0 ? round($localSize / $uploadDuration / 1024 / 1024, 2) : 0;
            
            $this->logger->info("Upload completed in {$uploadDuration}s at {$uploadSpeed} MB/s");
            
            // Check file size after upload
            try {
                $remoteSize = $storage->fileSize($remotePath);
                if ($remoteSize !== $localSize) {
                    $this->logger->error("Remote file size (" . number_format($remoteSize) . " bytes) does not match local file size (" . number_format($localSize) . " bytes) for '{$remotePath}'.");
                    throw new \Exception("Upload failed: remote file size does not match local file size for '{$remotePath}'.");
                }
                $this->logger->info("Upload successful to driver '{$driver}'. File size verified: " . number_format($localSize) . " bytes.");
            } catch (\Throwable $e) {
                throw new \Exception("Failed to verify remote file size after upload: " . $e->getMessage(), 0, $e);
            }
        } catch (\Throwable $e) {
            throw new \Exception("Failed to upload to remote storage ({$driver}): " . $e->getMessage(), 0, $e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
                $this->logger->debug("Closed upload stream for '{$archivePath}'");
            }
        }
    }

    private function cleanupLocal(string $archivePath, bool $isDryRun): void
    {
        // Protect source directories: only delete files in temp directory
        $tmpDir = $this->config['local']['temp_dir'] ?? \App\Utils\Helper::getTmpDir();
        if (!\App\Utils\Helper::isPathInTmpDir($archivePath)) {
            $this->logger->warning("Skipping deletion of non-temp file: {$archivePath}");
            return;
        }

        $this->logger->info("Cleaning up local archive: {$archivePath}");
        if ($isDryRun) {
            $this->logger->info('[DRY-RUN] Skipping local file deletion.');
            return;
        }

        if (file_exists($archivePath) && is_file($archivePath) && is_readable($archivePath) && is_writable($archivePath)) {
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
        $statusFile = $this->getStatusFile();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
         foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($path === false || (false !== stripos($path, $lockFile)) || (false !== stripos($path, $statusFile)) || !\App\Utils\Helper::isPathInTmpDir($path)) {
                continue;
            }
            if ($file->isFile()) {
                @unlink($path);
            } elseif ($file->isDir()) {
                @rmdir($path);
            }
         }
     }

    // private helper to read last backup timestamp
    private function readLastSuccessfulBackup(string $statusFile = ''): ?\DateTimeImmutable
    {

        if(!$statusFile) {
            $statusFile = $this->getStatusFile();
        }

        if (!is_file($statusFile)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($statusFile), true);
            if (!empty($data['last_success'])) {
                return new \DateTimeImmutable($data['last_success']);
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Unable to read backup status from '{$statusFile}': " . $e->getMessage());
        }
        return null;
    }

    // private helper to write last backup timestamp
    private function writeLastSuccessfulBackup(string $statusFile = ''): void
    {
        if(!$statusFile) {
            $statusFile = $this->getStatusFile();
        }

        $data = ['last_success' => (new \DateTimeImmutable())->format(\DateTime::ATOM)];
        try {
            $dir = dirname($statusFile);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create directory '{$dir}'");
            }
            file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
            $this->logger->info("Backup status written to '{$statusFile}'");
        } catch (\Throwable $e) {
            $this->logger->error("Failed to write backup status to '{$statusFile}': " . $e->getMessage());
        }
    }
}
