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
    // Create storage adapters for all remotes (or filter by --remote if provided)  
    $storages = [];  
    foreach ($remotes as $r) {  
        if ($remote && $r['driver'] !== $remote) {  
            continue;  
        }  
        $st = StorageFactory::create($r['driver'], $r);  
        if ($st) {  
            $storages[] = ['driver' => $r['driver'], 'storage' => $st];  
        } else {  
            $logger->warning("Failed to create storage adapter for remote: {$r['driver']}");  
        }  
    }  
    if (empty($storages)) {  
        $logger->error("No valid remote storage adapters available.");  
        exit(2);  
    }  
    // Aggregate contents from all remotes  
    $listing = [];  
    foreach ($storages as $info) {  
        try {  
            $items = $info['storage']->listContents('', true);  
        } catch (Throwable $e) {  
            $logger->warning("Could not list contents on '{$info['driver']}': " . $e->getMessage());  
            continue;  
        }  
        foreach ($items as $item) {  
            if ($item->isFile()) {  
                $listing[] = $item->path();  
            }  
        }  
    }  
    // Scan list of valid backup files across all remotes  
    // Updated pattern to recognize .xbk files with single or double compression+encryption extensions  
    $pattern = "/^([a-zA-Z0-9_.-]+)\\.(\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2})\\.tar\\.xbk(?:\\.(?:gz|bz2|xz|zst|zip|7z)(?:\\.(?:gz|bz2|xz|zst|zip|7z))?)?(?:\\.(?:aes|gpg))?$/";  
    $backups = [];  
    foreach ($listing as $path) {  
        if (preg_match($pattern, $path, $m)) {  
            $user = $m[1];  
            $ver = $m[2];  
            $backups[$user][$ver][] = $path;  
        }  
    }  
    // If user or version is missing, prompt for selection
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
    // Select the appropriate backup file (prioritize the newest, matching pattern)
    $files = $backups[$username][$version];
    // If there are multiple files (different compression/encryption), prompt for selection
    if (count($files) > 1) {
        // Select backup file by compression/encryption format
        $file = cli_select('Select backup file (compression/xbk format):', $files, 0);
    } else {
        $file = $files[0];
    }
    // Determine which remote adapter holds the selected file
    $storage = null;
    foreach ($storages as $info) {
        try {
            if ($info['storage']->fileExists($file)) {
                $storage = $info['storage'];
                break;
            }
        } catch (Throwable $e) {
            // ignore and continue
        }
    }
    if (!$storage) {
        $logger->error("Selected backup file {$file} not found on any remote storage.");
        exit(5);
    }
    $logger->info("Downloading file: $file");
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
    // Auto decrypt and decompress using .xbk filename analysis
    // Get encryption password from environment
    $password = Helper::env('ENCRYPTION_PASSWORD', Helper::env('BACKUP_PASSWORD'));
    
    // Parse the filename to understand processing steps
    $fileInfo = Helper::parseXbkFilename($localFile);
    $currentFile = $localFile;
    
    $logger->info("Processing backup file: {$localFile}");
    $logger->info("File analysis - Original: {$fileInfo['original']}, Compression: {$fileInfo['compression']}, Encryption: {$fileInfo['encryption']}, Has XBK: " . ($fileInfo['hasXbk'] ? 'yes' : 'no'));
    
    if ($fileInfo['hasXbk']) {
        // Handle combined compression+encryption methods first
        if (($fileInfo['compression'] === '7zip' || $fileInfo['compression'] === '7z') && 
            ($fileInfo['encryption'] === '7zip' || $fileInfo['encryption'] === '7z')) {
            // 7z combined: decompress and decrypt in one step
            $finalFile = rtrim($outdir, '/') . '/' . $fileInfo['original'];
            $archiveFile = $currentFile;
            $ok = Helper::sevenZipDecompressDecryptFile($currentFile, $finalFile, $password);
            if ($ok) {
                $logger->info("Decompressed and decrypted {$archiveFile} to {$finalFile} using 7z");
                @unlink($archiveFile); // cleanup intermediate archive
                $currentFile = $finalFile;
            } else {
                $logger->error("7z decompression/decryption failed. Check password or source file.");
                echo "[ERROR] 7z decompression/decryption failed. Output file may be invalid!\n";
            }
        } elseif ($fileInfo['compression'] === 'zip' && $fileInfo['encryption'] === 'zip') {
            // zip combined: decompress and decrypt in one step
            $finalFile = rtrim($outdir, '/') . '/' . $fileInfo['original'];
            $archiveFile = $currentFile;
            $ok = Helper::zipDecompressDecryptFile($currentFile, $finalFile, $password);
            if ($ok) {
                $logger->info("Decompressed and decrypted {$archiveFile} to {$finalFile} using zip");
                @unlink($archiveFile); // cleanup intermediate archive
                $currentFile = $finalFile;
            } else {
                $logger->error("zip decompression/decryption failed. Check password or source file.");
                echo "[ERROR] zip decompression/decryption failed. Output file may be invalid!\n";
            }
        } else {
            // Separate steps: decrypt first, then decompress
            
            // Step 1: Decrypt if needed (reverse order - decrypt first)
            if ($fileInfo['encryption'] !== 'none') {
                $extension = '.' . $fileInfo['encryption'];
                if (str_ends_with($currentFile, $extension)) {
                    $decryptedFile = preg_replace('/' . preg_quote($extension, '/') . '$/', '', $currentFile);
                    $decryptOk = match ($fileInfo['encryption']) {
                        'gpg' => Helper::gpgDecryptFile($currentFile, $decryptedFile, $password),
                        'aes' => Helper::decryptFile($currentFile, $decryptedFile, $password),
                        default => false,
                    };
                    
                    if ($decryptOk) {
                        $logger->info("Decrypted {$currentFile} to {$decryptedFile} using {$fileInfo['encryption']}");
                        @unlink($currentFile); // cleanup encrypted file
                        $currentFile = $decryptedFile;
                    } else {
                        $logger->error("{$fileInfo['encryption']} decryption failed. Check password or source file.");
                        echo "[ERROR] {$fileInfo['encryption']} decryption failed. Output file may be invalid!\n";
                    }
                }
            }
            
            // Step 2: Decompress if needed (after decryption)
            if ($fileInfo['compression'] !== 'none') {
                $extension = '.' . ($fileInfo['compression'] === '7zip' ? '7z' : $fileInfo['compression']);
                if (str_ends_with($currentFile, $extension)) {
                    $decompressedFile = preg_replace('/' . preg_quote($extension, '/') . '$/', '', $currentFile);
                    $decompressOk = match ($fileInfo['compression']) {
                        'gz', 'gzip' => Helper::gzipDecompressFile($currentFile, $decompressedFile),
                        'bz2', 'bzip2' => Helper::bzip2DecompressFile($currentFile, $decompressedFile),
                        'xz' => Helper::xzDecompressFile($currentFile, $decompressedFile),
                        'zst', 'zstd' => Helper::zstdDecompressFile($currentFile, $decompressedFile),
                        'zip' => Helper::zipDecompressFile($currentFile, $decompressedFile),
                        '7zip', '7z' => Helper::sevenZipDecompressFile($currentFile, $decompressedFile),
                        default => false,
                    };
                    
                    if ($decompressOk) {
                        $logger->info("Decompressed {$currentFile} to {$decompressedFile} using {$fileInfo['compression']}");
                        @unlink($currentFile); // cleanup compressed file
                        $currentFile = $decompressedFile;
                    } else {
                        $logger->error("{$fileInfo['compression']} decompression failed.");
                        echo "[ERROR] {$fileInfo['compression']} decompression failed. Output file may be invalid!\n";
                    }
                }
            }
        }
        
        // Step 3: Remove .xbk marker to get original filename (for .xbk files)
        if (str_contains($currentFile, '.xbk')) {
            $originalFilename = $fileInfo['original'];
            $originalPath = dirname($currentFile) . '/' . $originalFilename;
            if (@rename($currentFile, $originalPath)) {
                $logger->info("Restored original filename: {$originalFilename}");
                $currentFile = $originalPath;
            } else {
                $logger->warning("Failed to restore original filename, keeping: {$currentFile}");
            }
        }
    }
    
    // Assign currentFile to final for further processing
    // Assign currentFile to final for further processing (translated from Vietnamese)
    $final = $currentFile;
    
    // Legacy processing for non-.xbk files (backward compatibility)
    if (!$fileInfo['hasXbk']) {
        $logger->info("Processing legacy (non-.xbk) file: {$localFile}");
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
        
        // Extract if needed (automatic extraction for zip, 7z, tar, xz, gz, bz2)
        $extracted = false;
        $extractedFile = null;
        if (preg_match('/\.(zip|7z)$/', $decrypted, $m)) {
            $base = preg_replace('/\.(zip|7z)$/', '', $decrypted);
            if ($m[1] === 'zip') {
                $ok = Helper::zipDecompressFile($decrypted, $base);
                if ($ok) {
                    $logger->info("ZIP extracted: $base");
                    $final = $base;
                    $extracted = true;
                    $extractedFile = $base;
                } else {
                    $logger->error("ZIP extraction failed: $decrypted");
                    echo "[ERROR] ZIP extraction failed. Output file may be invalid!\n";
                }
            } elseif ($m[1] === '7z') {
                $ok = Helper::sevenZipDecompressFile($decrypted, $base);
                if ($ok) {
                    $logger->info("7z extracted: $base");
                    $final = $base;
                    $extracted = true;
                    $extractedFile = $base;
                } else {
                    $logger->error("7z extraction failed: $decrypted");
                    echo "[ERROR] 7z extraction failed. Output file may be invalid!\n";
                }
            }
        } elseif (preg_match('/\.(tar\.gz|tar\.bz2|tar\.xz|tar)$/', $decrypted, $m)) {
            // TODO: implement tar extraction if needed
            $logger->warning("Automatic extraction for tar archives not implemented. Archive remains unchanged.");
            echo "[WARNING] Backup file is a tar archive. Please extract manually if needed.\n";
            $final = $decrypted;
        } else {
            $final = $decrypted;
        }
        
        // After extraction, if file has .xbk extension, rename back to original
        if (str_ends_with($final, '.xbk')) {
            $original = \App\Utils\Helper::removeXbkExtension($final);
            if (@rename($final, $original)) {
                $logger->info("Renamed .xbk file back to original: $original");
                $final = $original;
            } else {
                $logger->warning("Failed to rename .xbk file back to original: $final");
            }
        }
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
