<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

namespace App\System;

use Psr\Log\LoggerInterface;

/**
 * Performs pre-flight checks to ensure the system is ready for backup.
 */

class SystemChecker
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function runChecks(): void
    {
        $this->logger->info('Running system checks...');

        if (
            !empty($this->config['performance']['allowed_start_time']) &&
            !$this->checkAllowedTime(
                $this->config['performance']['allowed_start_time'],
                $this->config['performance']['allowed_end_time']
            )
        ) {
            throw new \Exception("Backup is not allowed at this time as it is outside the configured backup window.");
        }

        if (!$this->checkCpuLoad((float) $this->config['performance']['max_cpu_load'])) {
            throw new \Exception("CPU load is too high for backup operation.");
        }

        if (
            !$this->checkDiskFree(
                $this->config['local']['temp_dir'],
                (int) $this->config['performance']['min_disk_free_percent']
            )
        ) {
            throw new \Exception("Not enough free disk space in the temporary directory.");
        }

        $this->logger->info('System checks passed successfully.');
    }

    private function checkAllowedTime(string $start, string $end): bool
    {
        $now = date('H:i');
        $this->logger->debug("Checking time window. Current time: {$now}. Allowed: {$start}-{$end}.");
        return ($now >= $start && $now <= $end);
    }

    private function checkCpuLoad(float $maxLoad): bool
    {
        if (!function_exists('sys_getloadavg')) {
            $this->logger->warning('Function sys_getloadavg() is not available. Skipping CPU load check.');
            return true;
        }
        $load = sys_getloadavg();
        $this->logger->debug("Checking CPU load. Current 1-min avg: {$load[0]}. Max allowed: {$maxLoad}.");
        return $load[0] <= $maxLoad;
    }

    private function checkDiskFree(string $path, int $minPercent): bool
    {
        if (!file_exists($path) || !is_dir($path)) {
            $this->logger->error("Temporary directory '{$path}' does not exist.");
            return false;
        }
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        if ($total === false || $free === false || $total == 0) {
            $this->logger->warning("Could not determine disk space for path: {$path}");
            return false; // Cannot determine, fail safely
        }
        $percent = ($free / $total) * 100;
        $msg = "Checking disk space for '{$path}'. Current free: "
            . round($percent, 2)
            . "%. Required: {$minPercent}%.";
        $this->logger->debug($msg);
        return $percent >= $minPercent;
    }
}
