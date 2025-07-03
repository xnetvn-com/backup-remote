#!/usr/bin/env php
<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

require_once __DIR__ . '/libs/vendor/autoload.php';

use App\Utils\Logger;
use App\Utils\Helper;
use App\Storage\StorageFactory;

$options = getopt('', ['file:', 'remote::', 'dest::']);
$file = $options['file'] ?? null;
$remote = $options['remote'] ?? null;
$dest = $options['dest'] ?? null;

if (!$file || !is_readable($file)) {
    fwrite(STDERR, "[ERROR] Please provide a valid --file to upload.\n");
    exit(1);
}

$env = getenv('APP_ENV') ?: 'development';
$envFile = ".env.$env";
if (file_exists(__DIR__ . "/$envFile")) {
    Dotenv\Dotenv::createImmutable(__DIR__, $envFile)->safeLoad();
} else {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$config = require __DIR__ . '/config/app.php';
$logger = Logger::getLogger();

try {
    $logger->info("Uploading file: $file");
    $remotes = Helper::detectAllRemotes();
    $targetRemote = null;
    if ($remote) {
        foreach ($remotes as $r) {
            if ($r['driver'] === $remote) {
                $targetRemote = $r;
                break;
            }
        }
    } else {
        $targetRemote = $remotes[0] ?? null;
    }
    if (!$targetRemote) {
        $logger->error("No valid remote storage found.");
        exit(2);
    }
    $storage = StorageFactory::create($targetRemote['driver'], $targetRemote);
    if (!$storage) {
        $logger->error("Failed to create storage adapter for remote: " . $targetRemote['driver']);
        exit(3);
    }
    $remotePath = $dest ?: basename($file);
    $result = $storage->writeStream($remotePath, fopen($file, 'rb'));
    if ($result) {
        $logger->info("Upload successful: $remotePath");
        exit(0);
    } else {
        $logger->error("Upload failed: $remotePath");
        exit(4);
    }
} catch (Throwable $e) {
    $logger->error('Upload error: ' . $e->getMessage(), ['exception' => $e]);
    exit(5);
}
