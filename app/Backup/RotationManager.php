<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

// Handles backup rotation logic, deleting old backups
namespace App\Backup;

use App\Utils\Logger;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Manages the rotation of old backups based on retention policies.
 */

class RotationManager
{
    private array $config;
    private LoggerInterface $logger;
    private Filesystem $storage;

    public function __construct(array $config, LoggerInterface $logger, Filesystem $storage)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->storage = $storage;
        // log initialization of RotationManager
        $this->logger->debug('RotationManager initialized', [
            'keep_latest' => $this->config['rotation']['keep_latest'] ?? null,
            'remote_path' => $this->config['remote']['path'] ?? null,
            'storageClass' => get_class($storage),
        ]);
    }

    /**
     * Runs the backup rotation process.
     *
     * @param bool $isDryRun If true, only lists files that would be deleted.
     */

    public function run(bool $isDryRun): void
    {
        // Use direct keep_latest setting for retention
        $keepCount = (int) ($this->config['rotation']['keep_latest'] ?? 5);
        $remotePath = $this->config['remote']['path'] ?? '';

        $this->logger->info("Starting backup rotation process" . ($isDryRun ? " [DRY-RUN MODE]" : ""));
        // detailed config logging
        $this->logger->debug('RotationManager.run parameters', [
            'keepCount' => $keepCount,
            'remotePath' => $remotePath,
            'dryRun' => $isDryRun,
        ]);
        
        $startTime = microtime(true);
        
        try {
            $fileObjects = $this->storage->listContents($remotePath, true)
                ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
                ->sortByPath()
                ->toArray();
            
            // Convert StorageAttributes objects to arrays for compatibility
            $files = [];
            foreach ($fileObjects as $fileObj) {
                $fileSize = null;
                if ($fileObj instanceof \League\Flysystem\FileAttributes) {
                    $fileSize = $fileObj->fileSize();
                }
                $files[] = [
                    'path' => $fileObj->path(),
                    'lastModified' => $fileObj->lastModified() ?? 0,
                    'fileSize' => $fileSize,
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error("Failed to list remote files: " . $e->getMessage());
            throw $e;
        }

        $listTime = microtime(true);
        $listDuration = round($listTime - $startTime, 2);
        
        $this->logger->info("Found " . count($files) . " total files in remote storage (fetched in {$listDuration}s)");

        if (empty($files)) {
            $this->logger->info("No files found in remote storage. Nothing to rotate.");
            return;
        }

        $groups = $this->groupFilesByUser($files);

        if (empty($groups)) {
            $this->logger->warning("No files could be grouped by username. Check filename patterns.");
            return;
        }

        $processStartTime = microtime(true);
        
        foreach ($groups as $username => $userFiles) {
            $this->logger->info("Processing rotation for user: {$username}");
            $this->applyPolicies($username, $userFiles, $keepCount, $isDryRun);
        }
        
        $processEndTime = microtime(true);
        $processDuration = round($processEndTime - $processStartTime, 2);
        $totalDuration = round($processEndTime - $startTime, 2);
        
        $this->logger->info("Backup rotation completed in {$totalDuration}s (processing: {$processDuration}s, listing: {$listDuration}s)");
    }

    private function groupFilesByUser(array $files): array
    {
        $groups = [];
        $totalFiles = count($files);
        $this->logger->info("Grouping {$totalFiles} files by username for rotation analysis");
        
        foreach ($files as $file) {
            // hestia-user.2025-06-28_10-30-00.tar.gz.xenc
            if (preg_match('/(.+?)\.\d{4}-\d{2}-\d{2}/i', basename($file['path']), $matches)) {
                $username = $matches[1];
                if (!isset($groups[$username])) {
                    $groups[$username] = [];
                }
                $groups[$username][] = $file;
                $this->logger->debug("Grouped file {$file['path']} under user: {$username}");
            } else {
                $this->logger->warning("Could not extract username from file: {$file['path']}");
            }
        }
        
        $userCount = count($groups);
        $this->logger->info("Found backups for {$userCount} users");
        foreach ($groups as $username => $userFiles) {
            $this->logger->debug("User '{$username}' has " . count($userFiles) . " backup files");
        }
        
        return $groups;
    }

    private function applyPolicies(string $username, array $files, int $keepCount, bool $isDryRun): void
    {
        $fileCount = count($files);
        $this->logger->info("Applying retention policies for user '{$username}' ({$fileCount} total files)");
        
        // Sort files by date, newest first
        usort($files, fn ($a, $b) => $b['lastModified'] <=> $a['lastModified']);

        $keep = [];
        $delete = [];

        // Apply retention: keep the latest N backups
        // $keepCount passed as argument

        $this->logger->info("Retention policy for {$username}: Keep latest {$keepCount} backups from {$fileCount} total files");

        $keep = array_slice($files, 0, $keepCount);
        $delete = array_slice($files, $keepCount);

        // Log details about files to keep
        $this->logger->info("Files to keep for user '{$username}': " . count($keep));
        foreach ($keep as $keepFile) {
            $fileSize = isset($keepFile['fileSize']) ? number_format($keepFile['fileSize']) . ' bytes' : 'unknown size';
            $this->logger->debug("KEEP: {$keepFile['path']} ({$fileSize})");
        }

        // Log details about files to delete
        $deleteCount = count($delete);
        $this->logger->info("Files to delete for user '{$username}': {$deleteCount}");
        
        $keepPaths = array_map(fn ($f) => $f['path'], $keep);
        $totalSizeToDelete = 0;

        foreach ($delete as $fileToDelete) {
            if (!in_array($fileToDelete['path'], $keepPaths)) {
                $path = $fileToDelete['path'];
                $fileSize = $fileToDelete['fileSize'] ?? 0;
                $totalSizeToDelete += $fileSize;
                $fileSizeStr = $fileSize > 0 ? number_format($fileSize) . ' bytes' : 'unknown size';
                
                $this->logger->info("Marked for deletion: {$path} ({$fileSizeStr})");
                if (!$isDryRun) {
                    // Ensure only temp remote paths are deleted
                    try {
                        \App\Utils\Helper::assertInTmpDir($path);
                        $this->storage->delete($path);
                    } catch (\RuntimeException $e) {
                        $this->logger->warning("Skipping deletion of non-temp path: {$path}");
                    } catch (\Throwable $e) {
                        $this->logger->warning("Failed to delete file on remote: {$path}");
                    }
                } else {
                    $this->logger->info("[DRY-RUN] Would delete: {$fileToDelete['path']}");
                }
            }
        }

        if ($deleteCount === 0) {
            $this->logger->info("No old backups to delete for user '{$username}' (within retention limit)");
        } else {
            $totalSizeStr = $totalSizeToDelete > 0 ? number_format($totalSizeToDelete) . ' bytes' : 'unknown total size';
            if ($isDryRun) {
                $this->logger->info("[DRY-RUN] Would delete {$deleteCount} files for user '{$username}' (total size: {$totalSizeStr})");
            } else {
                $this->logger->info("Rotation completed for user '{$username}': deleted {$deleteCount} files (total size: {$totalSizeStr})");
            }
        }
    }
}
