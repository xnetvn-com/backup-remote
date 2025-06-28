#!/usr/bin/env php
<?php

declare(strict_types=1);

// --- Use Statements ---
use App\Backup\BackupManager;
use App\Notification\NotificationManager;
use App\System\SystemChecker;
use App\Utils\Logger;
use Dotenv\Dotenv;

// --- Argument Parsing ---
$options = getopt('', ['dry-run']);
$isDryRun = isset($options['dry-run']);

// --- Bootstrap ---
require_once __DIR__ . '/libs/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Load configuration
$config = require __DIR__ . '/config/app.php';

// --- Dependency Injection ---
$logger = Logger::getLogger(); // Corrected from getInstance()
$notificationManager = new NotificationManager($config, $logger);

if ($isDryRun) {
    $logger->info("Executing in dry-run mode. No files will be created, uploaded, or deleted.");
}

// --- Main Execution ---
try {
    // 1. Lock Process
    // ... (Implementation of a lock file to prevent concurrent runs)

    // 2. System Checks
    $logger->info("Performing system checks...");
    $systemChecker = new SystemChecker($config, $logger);
    $systemChecker->runChecks();
    $logger->info("System checks passed successfully.");

    // 3. Execute Backup
    $logger->info("Starting backup process...");
    $backupManager = new BackupManager($config, $logger, $notificationManager);
    $backupManager->run($isDryRun);

    $logger->info("Backup process completed successfully.");
    if (!$isDryRun) {
        $notificationManager->sendSuccess("Backup process completed successfully.");
    }
} catch (Exception $e) {
    $errorMessage = 'An error occurred: ' . $e->getMessage();
    $logger->error($errorMessage, ['exception' => $e]);
    if (!$isDryRun) {
        $notificationManager->sendFailure($errorMessage);
    }
} finally {
    // 5. Unlock Process
    // ... (Remove the lock file)
    $logger->info("Backup script finished.");
}
