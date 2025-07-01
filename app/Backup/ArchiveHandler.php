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

// Handles backup compression and encryption
namespace App\Backup;

use App\Utils\Logger;
use App\Utils\Helper;
use Throwable;
use Psr\Log\LoggerInterface;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class ArchiveHandler
{
    private array $config;
    private LoggerInterface $logger;
    private string $tempDir;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Creates a compressed and optionally encrypted archive for a user.
     *
     * @param string $username The user to back up.
     * @param string $userPath The path to the user's home directory.
     * @param bool $isDryRun If true, simulates the process.
     * @return string|null The path to the created archive, or null on failure/dry-run.
     * @throws \Exception
     */
    public function create(string $username, string $userPath, bool $isDryRun): ?string
    {
        $tempDir = $this->config['local']['temp_dir'];
        $archivePath = Helper::createArchiveName($tempDir, $username);
        $this->logger->info("Preparing to create archive for user '{$username}' at '{$archivePath}'.");

        if ($isDryRun) {
            $this->logger->info("[DRY-RUN] Skipping archive creation for {$username}.");
            return "/tmp/{$username}-dry-run.tar.gz.enc"; // Return a dummy path for simulation
        }

        try {
            $this->logger->debug("Creating archive...");
            $exclude = $this->config['archive']['exclude'] ?? [];
            $result = UnifiedArchive::archiveDirectory(
                $userPath,
                $archivePath,
                'tar.gz',
                $exclude
            );
            if ($result === true) {
                $this->logger->info("Archive for {$username} created successfully.");
                // TODO: Implement encryption if needed, e.g., using openssl_encrypt or system call
                return $archivePath;
            } else {
                throw new \Exception("UnifiedArchive failed: " . print_r($result, true));
            }
        } catch (Throwable $e) {
            throw new \Exception("Failed to create archive for {$username}: " . $e->getMessage(), 0, $e);
        }
    }
}
