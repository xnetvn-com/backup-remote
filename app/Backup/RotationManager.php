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
    }

    /**
     * Runs the backup rotation process.
     *
     * @param bool $isDryRun If true, only lists files that would be deleted.
     */

    public function run(bool $isDryRun): void
    {
        $policies = $this->config['rotation']['policies'];
        $remotePath = $this->config['remote']['path'] ?? '';

        $this->logger->info("Fetching file list from remote path: '{$remotePath}'");
        $files = $this->storage->listContents($remotePath, true)
            ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
            ->sortByPath()
            ->toArray();

        $this->logger->info("Found " . count($files) . " total files in remote storage.");

        $groups = $this->groupFilesByUser($files);

        foreach ($groups as $username => $userFiles) {
            $this->logger->info("Processing rotation for user: {$username}");
            $this->applyPolicies($username, $userFiles, $policies, $isDryRun);
        }
    }

    private function groupFilesByUser(array $files): array
    {
        $groups = [];
        foreach ($files as $file) {
            // hestia-user.2025-06-28_10-30-00.tar.gz.enc
            if (preg_match('/(.+?)\.\d{4}-\d{2}-\d{2}/i', basename($file['path']), $matches)) {
                $username = $matches[1];
                $groups[$username][] = $file;
            }
        }
        return $groups;
    }

    private function applyPolicies(string $username, array $files, array $policies, bool $isDryRun): void
    {
        // Sort files by date, newest first
        usort($files, fn ($a, $b) => $b['lastModified'] <=> $a['lastModified']);

        $keep = [];
        $delete = [];

        // Apply policies (e.g., keep daily, weekly, monthly)
        // For simplicity, we'll just keep the latest N backups for now.
        $keepCount = $policies['keep_latest'] ?? 7;

        $this->logger->info("Retention policy for {$username}: Keep latest {$keepCount} backups.");

        $keep = array_slice($files, 0, $keepCount);
        $delete = array_slice($files, $keepCount);

        $keepPaths = array_map(fn ($f) => $f['path'], $keep);

        foreach ($delete as $fileToDelete) {
            if (!in_array($fileToDelete['path'], $keepPaths)) {
                $this->logger->info("Marked for deletion: {$fileToDelete['path']}");
                if (!$isDryRun) {
                    try {
                        $this->storage->delete($fileToDelete['path']);
                        $this->logger->info("Deleted: {$fileToDelete['path']}");
                    } catch (Throwable $e) {
                        $this->logger->error("Failed to delete {$fileToDelete['path']}: " . $e->getMessage());
                    }
                }
            }
        }

        if (empty($delete)) {
            $this->logger->info("No old backups to delete for user {$username}.");
        }
    }
}
