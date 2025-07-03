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

// Finds local backup files
namespace App\System;

use App\Utils\Logger;
use Psr\Log\LoggerInterface;

/**
 * Finds backup users and files in the configured backup directories.
 */

class LocalFinder
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Finds all users (subdirectories) in all backup directories.
     *
     * @return array An associative array of [username => path].
     */
    public function findBackupUsers(): array
    {
        $dirs = $this->config['backup_dirs'] ?? ['/backup'];
        $users = [];
        foreach ($dirs as $baseDir) {
            $this->logger->info("Searching for users in backup dir: {$baseDir}");
            if (!is_dir($baseDir)) {
                $this->logger->warning("Backup directory does not exist: {$baseDir}");
                continue;
            }
            $items = scandir($baseDir);
            if ($items === false) {
                $this->logger->warning("Could not read backup directory: {$baseDir}");
                continue;
            }
            $hasUser = false;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $baseDir . '/' . $item;
                if (is_dir($path)) {
                    $users[$item] = $path;
                    $hasUser = true;
                }
            }
            // Nếu không có subdirectory, kiểm tra file backup trực tiếp
            if (!$hasUser) {
                $backupFiles = glob(rtrim($baseDir, '/') . '/*.{tar,zip,gz,zst}', GLOB_BRACE);
                if ($backupFiles && count($backupFiles) > 0) {
                    $users['__root__'] = $baseDir;
                }
            }
        }
        return $users;
    }

    /**
     * Finds all backup files for a specific user in a directory.
     * @param string $dir
     * @param string $user
     * @return array
     */

    public static function findUserBackups($dir, $user): array
    {
        $files = glob(rtrim($dir, '/') . '/' . $user . '.*.{tar,zip,gz,zst}', GLOB_BRACE);
        return $files ?: [];
    }

    /**
     * Finds all backup files in a directory.
     * @param string $dir
     * @return array
     */

    public static function findAllBackups($dir): array
    {
        $files = glob(rtrim($dir, '/') . '/*.{tar,zip,gz,zst}', GLOB_BRACE);
        return $files ?: [];
    }

    /**
     * Finds all backup files (tar, zip, gz, zst) in all configured backup directories.
     *
     * @return array An array of absolute file paths.
     */
    public function findBackupFiles(): array
    {
        $dirs = $this->config['backup_dirs'] ?? ['/backup'];
        $files = [];
        foreach ($dirs as $baseDir) {
            if (!is_dir($baseDir)) {
                $this->logger->warning("Backup directory does not exist: {$baseDir}");
                continue;
            }
            $found = glob(rtrim($baseDir, '/') . '/*.{tar,zip,gz,zst}', GLOB_BRACE);
            if ($found !== false) {
                $files = array_merge($files, $found);
            } else {
                $this->logger->warning("Could not read backup files in directory: {$baseDir}");
            }
        }
        return $files;
    }
}
