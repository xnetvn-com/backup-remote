<?php
// Finds local backup files
namespace App\System;

use App\Utils\Logger;
use Psr\Log\LoggerInterface;

/**
 * Finds HestiaCP users on the local system.
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
     * Finds all HestiaCP users based on the base directory.
     *
     * @return array An associative array of [username => path].
     */
    public function findHestiaUsers(): array
    {
        $baseDir = $this->config['hestia']['base_path'];
        $excludeUsers = $this->config['hestia']['exclude_users'] ?? [];
        $this->logger->info("Searching for Hestia users in: {$baseDir}");

        if (!is_dir($baseDir)) {
            $this->logger->error("Hestia base path does not exist: {$baseDir}");
            return [];
        }

        $users = [];
        $items = scandir($baseDir);
        if ($items === false) {
            $this->logger->error("Could not read Hestia base path: {$baseDir}");
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $baseDir . '/' . $item;
            if (is_dir($path) && !in_array($item, $excludeUsers)) {
                $users[$item] = $path;
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
    public static function findUserBackups($dir, $user): array {
        $files = glob(rtrim($dir,'/').'/'.$user.'.*.{tar,zip,gz,zst}', GLOB_BRACE);
        return $files ?: [];
    }

    /**
     * Finds all backup files in a directory.
     * @param string $dir
     * @return array
     */
    public static function findAllBackups($dir): array {
        $files = glob(rtrim($dir,'/').'/*.{tar,zip,gz,zst}', GLOB_BRACE);
        return $files ?: [];
    }
}
