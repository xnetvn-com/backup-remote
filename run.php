#!/usr/bin/env php
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

// --- Use Statements ---
use App\Backup\BackupManager;
use App\Notification\NotificationManager;
use App\System\SystemChecker;
use App\Utils\Logger;
use Dotenv\Dotenv;

// --- Argument Parsing ---
$options = getopt('f', ['dry-run', 'force']);
$isDryRun = isset($options['dry-run']);
$isForce = isset($options['f']) || isset($options['force']);

// --- Bootstrap ---
require_once __DIR__ . '/libs/vendor/autoload.php';

// --- Environment Detection & Loading ---
$env = getenv('APP_ENV') ?: 'development';
$envFile = ".env.$env";
if (file_exists(__DIR__ . "/$envFile")) {
    $dotenv = Dotenv::createUnsafeMutable(__DIR__, $envFile);
    $dotenv->safeLoad();
} else {
    $dotenv = Dotenv::createUnsafeMutable(__DIR__);
    $dotenv->safeLoad();
}

// Load configuration
$config = require __DIR__ . '/config/app.php';

// --- Dependency Injection ---
$logger = Logger::getLogger(); // Corrected from getInstance()
$notificationManager = new NotificationManager($config, $logger);

if ($isDryRun) {
    $logger->info("Executing in dry-run mode. No files will be created, uploaded, or deleted.");
}

// --- Main Execution ---
// Use Helper::getTmpDir() to determine the temporary directory
$tmpDir = App\Utils\Helper::getTmpDir();
$lockFile = $tmpDir . '/.backup.lock';
$lockAcquired = false;
try {
    // 1. Lock Process
    if (file_exists($lockFile)) {
        $pid = trim(@file_get_contents($lockFile));
        $pid = is_numeric($pid) ? (int)$pid : 0;
        $isRunning = false;
        if ($pid > 0) {
            // Check if the process is still running (Linux)
            $isRunning = posix_kill($pid, 0);
        }
        if ($pid > 0 && $isRunning) {
            $logger->error("Another backup process is already running (PID: $pid). Exiting.");
            exit(2);
        } else {
            $logger->warning("Stale lock file detected (PID: $pid). Removing orphaned lock and continuing.");
            @unlink($lockFile);
        }
    }
    if (file_put_contents($lockFile, getmypid() ?: "unknown") === false) {
        $logger->error("Failed to create lock file. Exiting.");
        exit(3);
    }
    $lockAcquired = true;

    // 2. System Checks
    if ($isForce) {
        $logger->warning("Force mode enabled: Skipping all system checks (CPU, disk, time window, etc.)");
    } else {
        $logger->info("Performing system checks...");
        $systemChecker = new SystemChecker($config, $logger);
        $systemChecker->runChecks();
        $logger->info("System checks passed successfully.");
    }

    // 3. Execute Backup
    $logger->info("Starting backup process...");
    $backupManager = new BackupManager($config, $logger, $notificationManager);
    $GLOBALS['backupManager'] = $backupManager; // Make it globally accessible for ArchiveHandler
    $backupManager->run($isDryRun, $isForce);

    $logger->info("Backup process completed successfully.");
    if (!$isDryRun) {
        $notificationManager->sendSuccess("Backup process completed successfully.");
    }
    exit(0);
} catch (Exception $e) {
    $errorMessage = 'An error occurred: ' . $e->getMessage();
    $logger->error($errorMessage, ['exception' => $e]);
    if (!$isDryRun) {
        $notificationManager->sendFailure($errorMessage);
    }
    exit(1);
} finally {
    // 5. Unlock Process
    if ($lockAcquired && file_exists($lockFile)) {
        unlink($lockFile);
    }
    $logger->info("Backup script finished.");
}
