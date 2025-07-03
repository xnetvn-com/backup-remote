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

$options = getopt('', ['user:', 'version:', 'remote::', 'outdir::']);
$username = $options['user'] ?? null;
$version = $options['version'] ?? null;
$remote = $options['remote'] ?? null;
$outdir = $options['outdir'] ?? null;

$env = getenv('APP_ENV') ?: 'development';
$envFile = ".env.$env";
if (file_exists(__DIR__ . "/$envFile")) {
    Dotenv\Dotenv::createImmutable(__DIR__, $envFile)->safeLoad();
} else {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$config = require __DIR__ . '/config/app.php';
$logger = Logger::getLogger();
$tmpDir = Helper::getTmpDir();
$downloadDir = rtrim($tmpDir, '/') . '/download';
if (!is_dir($downloadDir)) {
    if (!mkdir($downloadDir, 0770, true) && !is_dir($downloadDir)) {
        $logger->error("Failed to create download directory: $downloadDir");
        echo "[ERROR] Failed to create download directory: $downloadDir\n";
        exit(11);
    }
}
$outdir = $outdir ?: $downloadDir;

function cli_select($title, $options, $default = 0) {
    echo "\n$title\n";
    foreach ($options as $i => $opt) {
        echo "  [" . ($i+1) . "] $opt\n";
    }
    echo "Select an option (1-" . count($options) . ") [default: " . ($default+1) . "]: ";
    $input = trim(fgets(STDIN));
    if ($input === '' && isset($options[$default])) return $options[$default];
    $idx = (int)$input - 1;
    if ($idx >= 0 && $idx < count($options)) return $options[$idx];
    echo "Invalid selection.\n";
    return cli_select($title, $options, $default);
}

try {
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
    // Quét danh sách file backup hợp lệ
    $listing = $storage->listContents('', true);
    $validExt = '(xenc|zst|gz|bz2|xz|zip|7z|tar|gpg)';
    $pattern = "/^([a-zA-Z0-9_.-]+)\\.(\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2})\\..*\\.$validExt$/";
    $backups = [];
    foreach ($listing as $item) {
        if ($item->isFile() && preg_match($pattern, $item->path(), $m)) {
            $user = $m[1];
            $ver = $m[2];
            $backups[$user][$ver][] = $item->path();
        }
    }
    // Nếu thiếu user hoặc version thì cho chọn
    if (!$username || !isset($backups[$username])) {
        $users = array_keys($backups);
        if (empty($users)) {
            $logger->error("No backup files found on remote.");
            exit(10);
        }
        // Select user to restore
        $username = cli_select('Select user to restore:', $users);
    }
    if (!$version || !isset($backups[$username][$version])) {
        $versions = array_keys($backups[$username]);
        rsort($versions); // newest first
        // Select backup version
        $version = cli_select('Select backup version:', $versions, 0);
    }
    // Chọn file backup phù hợp (ưu tiên file mới nhất, đúng pattern)
    $files = $backups[$username][$version];
    // Nếu có nhiều file (nén/mã hóa khác nhau), cho chọn
    if (count($files) > 1) {
        // Select backup file by compression/encryption format
        $file = cli_select('Select backup file (compression/encryption):', $files, 0);
    } else {
        $file = $files[0];
    }
    $logger->info("Đang tải file: $file");
    $localFile = rtrim($outdir, '/') . '/' . basename($file);
    $skipDownload = false;
    if (file_exists($localFile)) {
        echo "\n[WARNING] File $localFile already exists. Overwrite? (y/N): ";
        $input = strtolower(trim(fgets(STDIN)));
        if ($input !== 'y' && $input !== 'yes') {
            $logger->warning("User chose not to overwrite existing file: $localFile. Using existing file for processing.");
            echo "[SKIP] Skipping download, processing existing file: $localFile\n";
            $skipDownload = true;
        } else {
            $logger->info("Overwriting existing file: $localFile");
        }
    }
    if (!$skipDownload) {
        $stream = $storage->readStream($file);
        if (!$stream) {
            $logger->error("Failed to download file from remote: $file");
            exit(5);
        }
        $out = fopen($localFile, 'wb');
        if (!$out) {
            fclose($stream);
            $logger->error("Failed to open local file for writing: $localFile");
            exit(6);
        }
        while (!feof($stream)) {
            fwrite($out, fread($stream, 8192));
        }
        fclose($stream);
        fclose($out);
        $logger->info("Downloaded file: $localFile");
    }
    // Giải mã và giải nén tự động
    // Get encryption password from environment
    $password = Helper::env('ENCRYPTION_PASSWORD');
    $decrypted = $localFile;
    $decryptOk = true;
    if (str_ends_with($localFile, '.xenc')) {
        $decrypted = preg_replace('/\.xenc$/', '', $localFile);
        $decryptOk = Helper::decryptFile($localFile, $decrypted, $password);
        if ($decryptOk) {
            $logger->info("Decrypted: $decrypted");
        } else {
            $logger->error("AES decryption failed. Check password or source file.");
            echo "[ERROR] AES decryption failed. Output file may be invalid!\n";
        }
    } elseif (str_ends_with($localFile, '.gpg')) {
        $decrypted = preg_replace('/\.gpg$/', '', $localFile);
        $decryptOk = Helper::gpgDecryptFile($localFile, $decrypted, $password);
        if ($decryptOk) {
            $logger->info("GPG decrypted: $decrypted");
        } else {
            $logger->error("GPG decryption failed. Check password or source file.");
            echo "[ERROR] GPG decryption failed. Output file may be invalid!\n";
        }
    } elseif (str_ends_with($localFile, '.zst')) {
        $decrypted = preg_replace('/\.zst$/', '', $localFile);
        $decryptOk = Helper::zstdDecryptFile($localFile, $decrypted, $password);
        if ($decryptOk) {
            $logger->info("Zstd decrypted: $decrypted");
        } else {
            $logger->error("Zstd decryption failed. Check password or source file.");
            echo "[ERROR] Zstd decryption failed. Output file may be invalid!\n";
        }
    }
    // Giải nén nếu cần (chưa implement extraction thực tế)
    $final = $decrypted;
    $extractionNeeded = preg_match('/\.(tar\.gz|tar\.zst|tar\.bz2|tar\.xz|tar|zip|7z)$/', $decrypted);
    if ($extractionNeeded) {
        // TODO: implement extraction logic if needed
        $logger->warning("Automatic extraction not implemented for archive files. Archive remains unchanged.");
        echo "[WARNING] Backup file is an archive (tar/zip/7z...). Please extract manually if needed.\n";
        $logger->info("Backup archive ready: $decrypted");
    }
    $logger->info("Backup file for user=$username, version=$version is ready at $final");
    clearstatcache();
    if (is_file($final)) {
        $size = filesize($final);
        $sizeStr = Helper::formatSize($size);
        if ($size === 0) {
            echo "\n[WARNING] Output file size is 0 B. Decryption or extraction may have failed or source file is empty.\n";
            $logger->warning("Output file $final has size 0 B. Check decryption/extraction process.");
        }
        echo "\n[OK] Completed. File available at: $final\n";
        echo "Size: $sizeStr\n";
    } else {
        echo "\n[WARNING] Output file not found: $final\n";
        $logger->error("Output file not found: $final");
    }
    exit(0);
} catch (Throwable $e) {
    $logger->error('Download error: ' . $e->getMessage(), ['exception' => $e]);
    exit(7);
}
