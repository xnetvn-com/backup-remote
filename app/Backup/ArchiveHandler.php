<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
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
     * @param array|null $remoteStorages Optional array of remote storages to check for existing files
     * @return string|array|null The path(s) to the created archive(s), or null on failure/dry-run.
     * @throws \Exception
     */
    public function create(string $username, string $userPath, bool $isDryRun, ?array $remoteStorages = null, bool $isUploadFileByFile = false): string|array|null
    {
        // log entry into archive creation
        $this->logger->info('ArchiveHandler.create called', [
            'username' => $username,
            'userPath' => $userPath,
            'dryRun' => $isDryRun,
            'remoteStoragesCount' => is_array($remoteStorages) ? count($remoteStorages) : 0,
        ]);
        $tempDir = Helper::getTmpDir();
        $this->logger->debug('Temporary directory for archives', ['tmpDir' => $tempDir]);
        // Get encryption password from environment (ENCRYPTION_PASSWORD or fallback to BACKUP_PASSWORD)
        $password = Helper::env('ENCRYPTION_PASSWORD', Helper::env('BACKUP_PASSWORD', $_ENV['BACKUP_PASSWORD'] ?? null));
        $this->logger->debug('Encryption password source checked', ['passwordConfigured' => $password !== null]);
        if (!$password) {
            $this->logger->error('ENCRYPTION_PASSWORD is not set. Encryption is required.');
            throw new \RuntimeException('ENCRYPTION_PASSWORD is not set.');
        }
        $this->logger->info('Password configured for encryption');
        $compression = strtolower(Helper::env('BACKUP_COMPRESSION', 'gzip'));
        $encryption = strtolower(Helper::env('BACKUP_ENCRYPTION', 'aes'));
        $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));
        $this->logger->info('Compression and encryption settings', [
            'compression' => $compression,
            'encryption' => $encryption,
            'compressionLevel' => $compressionLevel ?? 'none',
        ]);

        // Check AES-256 support for all encryption methods
        $this->logger->debug('Verifying AES support for chosen methods');
        if ($encryption === 'aes' || $encryption === 'openssl') {
            Helper::assertAesSupport('aes');
        } elseif ($encryption === 'gpg' || $encryption === 'gpg2' || $encryption === 'gnupg') {
            Helper::assertAesSupport('gpg');
        } elseif (in_array($compression, ['zip', '7z', '7zip']) && $encryption === 'aes') {
            Helper::assertAesSupport($compression);
        }
        $this->logger->debug('ArchiveHandler.create post-AES check', ['compression' => $compression, 'encryption' => $encryption]);

        // If backing up files directly in the root directory, process each file
        if ($username === '__root__') {
            $this->logger->info('Processing root backups', ['userPath' => $userPath]);
             $backupFiles = glob(rtrim($userPath, '/') . '/*.{tar,zip,gz,zst}', GLOB_BRACE);
            $this->logger->debug('Found backup files in root', ['filesCount' => $backupFiles ? count($backupFiles) : 0]);
             if (!$backupFiles || count($backupFiles) === 0) {
                 $this->logger->warning("No backup files found in root directory: {$userPath}");
                 return null;
             }
            $this->logger->info('Limiting backups after sort', ['keepLatest' => $keepN = (int) ($this->config['rotation']['policies']['keep_latest'] ?? Helper::env('ROTATION_KEEP_LATEST', 7))]);
             // Sort and limit to latest backups
             usort($backupFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
             $keepN = (int) ($this->config['rotation']['policies']['keep_latest'] ?? Helper::env('ROTATION_KEEP_LATEST', 7));
            $this->logger->debug('BackupFiles sorted and sliced', ['totalFound' => count($backupFiles), 'sliceSize' => $keepN]);
             $backupFiles = array_slice($backupFiles, 0, $keepN);
             $processedFiles = [];
             foreach ($backupFiles as $file) {
                 $fileSize = filesize($file) ?: 0;
                 $this->logger->info("Processing file: {$file} (size: " . number_format($fileSize) . " bytes)");
                 $tmpFile = rtrim($tempDir, '/') . '/' . basename($file);

                 // Create expected final filename
                 $finalFilename = Helper::createXbkFilename($tmpFile, $compression, $encryption);
                 $finalPath = rtrim($tempDir, '/') . '/' . basename($finalFilename);
                
                 // Check if final output already exists locally
                 if (file_exists($finalPath) && (filesize($finalPath) > ceil($fileSize * 0.5))) {
                     $this->logger->info("Skipping compress/encrypt for {$file}: output already exists locally: {$finalPath}");
                     $processedFiles[] = $finalPath;
                     continue;
                 }
                
                 // Check remote existence with size threshold: only skip if remote file size >= 50% of original
                 $threshold = (int) ceil($fileSize * 0.5);
                 if ($remoteStorages && $this->checkRemoteFileExists($finalFilename, $remoteStorages, $threshold)) {
                     $this->logger->info("Skipping processing for {$file}: final backup already exists on remote storage(s)");
                    
                    // Skip adding to processedFiles since file already exists on remote - no need to upload
                    continue;
                 }

                 // Copy the original file to tmp (preserve source files)
                 $tmpDir = \App\Utils\Helper::getTmpDir();
                 $realTmpDir = realpath($tmpDir) ?: $tmpDir;
                 $realFile = realpath($file);
                 if (!$realFile || !str_starts_with($realFile, realpath(dirname($file)))) {
                     $this->logger->error("Invalid or unsafe file path: {$file}");
                     continue;
                 }
                 $tmpFile = rtrim($realTmpDir, '/') . '/' . basename($file);
                
                 // Actually copy the file to tmp directory
                 if (!@copy($realFile, $tmpFile)) {
                     $this->logger->error("Failed to copy {$realFile} to {$tmpFile}");
                     continue;
                 }

                 // Create .xbk filename based on compression and encryption
                 $finalFilename = Helper::createXbkFilename($tmpFile, $compression, $encryption);
                 $finalPath = rtrim($tempDir, '/') . '/' . basename($finalFilename);

                 if ($isDryRun) {
                     $this->logger->info("[DRY-RUN] Would process {$tmpFile} to {$finalPath} (compression: {$compression}, encryption: {$encryption})");
                     $processedFiles[] = $finalPath;
                     continue;
                 }

                 // Create final filename based on compression and encryption combination
                 $finalFilename = Helper::createXbkFilename($tmpFile, $compression, $encryption);
                 $finalPath = rtrim($tempDir, '/') . '/' . basename($finalFilename);

                 // Special handling for combined compression+encryption methods
                 $this->logger->debug("Checking combined methods: compression='{$compression}', encryption='{$encryption}'");
                 if (($compression === '7z' || $compression === '7zip') && ($encryption === '7z' || $encryption === '7zip')) {
                     // 7z can compress and encrypt in one step
                     $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));
                     $ok = Helper::sevenZipCompressEncryptFile($tmpFile, $finalPath, $password, $compressionLevel ?? 5);
                     if ($ok) {
                         $this->logger->info("Compressed and encrypted {$tmpFile} to {$finalPath} using 7z");
                         
                         if($isUploadFileByFile) {
                            $GLOBALS['backupManager']->uploadBackupAllFilesToAllStorages([$finalPath], $remoteStorages, $isDryRun, []);
                         }

                         if(file_exists($finalPath) && is_file($finalPath) && filesize($finalPath) > 0) {
                             $processedFiles[] = $finalPath;
                         }
                         
                     } else {
                         $this->logger->error("Failed to compress and encrypt {$tmpFile} with 7z");
                     }
                     // Cleanup temporary file
                     @unlink($tmpFile);
                 } elseif ($compression === 'zip' && $encryption === 'zip') {
                     // zip can compress and encrypt in one step
                     $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));
                     $ok = Helper::zipCompressEncryptFile($tmpFile, $finalPath, $password, $compressionLevel ?? 6);
                     if ($ok) {
                         $this->logger->info("Compressed and encrypted {$tmpFile} to {$finalPath} using zip");

                         if($isUploadFileByFile) {
                            $GLOBALS['backupManager']->uploadBackupAllFilesToAllStorages([$finalPath], $remoteStorages, $isDryRun, []);
                         }

                         if(file_exists($finalPath) && is_file($finalPath) && filesize($finalPath) > 0) {
                             $processedFiles[] = $finalPath;
                         }
                         
                         //$processedFiles[] = $finalPath;
                     } else {
                         $this->logger->error("Failed to compress and encrypt {$tmpFile} with zip");
                     }
                     // Cleanup temporary file
                     @unlink($tmpFile);
                 } else {
                     // Separate compression and encryption steps

                     // Step 1: Apply compression first (if enabled)
                     $compressedFile = $tmpFile;
                     if ($compression !== 'none') {
                         $compExt = match ($compression) {
                             'gzip', 'gz' => 'gz',
                             'bzip2', 'bz2' => 'bz2',
                             'xz' => 'xz',
                             'zstd', 'zst' => 'zst',
                             'zip' => 'zip',
                             '7z', '7zip' => '7z',
                             default => 'gz',
                         };
                         $compressedFile = $tmpFile . '.xbk.' . $compExt;
                         $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));

                         $ok = match ($compression) {
                             'gzip', 'gz' => Helper::gzipCompressFile($tmpFile, $compressedFile, $compressionLevel ?? 6),
                             'bzip2', 'bz2' => Helper::bzip2CompressFile($tmpFile, $compressedFile, $compressionLevel ?? 6),
                             'xz' => Helper::xzCompressFile($tmpFile, $compressedFile, $compressionLevel ?? 6),
                             'zstd', 'zst' => Helper::zstdCompressFile($tmpFile, $compressedFile, $compressionLevel ?? 19),
                             'zip' => Helper::zipCompressFile($tmpFile, $compressedFile, $compressionLevel ?? 6),
                             '7z', '7zip' => Helper::sevenZipCompressFile($tmpFile, $compressedFile, $compressionLevel ?? 5),
                             default => Helper::gzipCompressFile($tmpFile, $compressedFile, $compressionLevel ?? 6),
                         };

                         if (!$ok) {
                             $this->logger->error("Failed to compress {$tmpFile} with {$compression}");
                             @unlink($tmpFile);
                             continue;
                         }
                         $this->logger->info("Compressed {$tmpFile} to {$compressedFile} using {$compression}");
                     } else {
                         // No compression, add .xbk marker
                         $compressedFile = $tmpFile . '.xbk';
                         if (!@copy($tmpFile, $compressedFile)) {
                             $this->logger->error("Failed to create .xbk marker file: {$compressedFile}");
                             @unlink($tmpFile);
                             continue;
                         }
                     }

                    // Step 2: Apply encryption (if enabled)
                    $encryptedFile = $compressedFile;
                    if ($encryption !== 'none') {
                        $encExt = match ($encryption) {
                            'aes', 'openssl' => 'aes',
                            'gpg', 'gpg2', 'gnupg' => 'gpg',
                            default => 'aes',
                        };
                        $encryptedFile = $compressedFile . '.' . $encExt;

                        $ok = match ($encryption) {
                            'gpg', 'gpg2', 'gnupg' => Helper::gpgEncryptFile($compressedFile, $encryptedFile, $password),
                            default => Helper::encryptFile($compressedFile, $encryptedFile, $password),
                        };

                        if ($ok) {
                            $size = is_file($encryptedFile) ? filesize($encryptedFile) : 0;
                            $this->logger->info("Encrypted {$compressedFile} to {$encryptedFile} using {$encryption}, size={$size}");
                            if ($size === 0) {
                                $this->logger->warning("[ArchiveHandler] Output file is empty after encryption: {$encryptedFile}");
                            } elseif ($size < 64) {
                                $this->logger->warning("[ArchiveHandler] Output file is suspiciously small: {$encryptedFile}, size={$size}");
                            }
                        } else {
                            $this->logger->error("Failed to encrypt {$compressedFile} with {$encryption}");
                            @unlink($tmpFile);
                            continue;
                        }
                    }

                    //$processedFiles[] = $encryptedFile;

                    if($isUploadFileByFile) {
                    $GLOBALS['backupManager']->uploadBackupAllFilesToAllStorages([$finalPath], $remoteStorages, $isDryRun, []);
                    }

                    if(file_exists($finalPath) && is_file($finalPath) && filesize($finalPath) > 0) {
                        $processedFiles[] = $finalPath;
                    }
                    
                }
                
                // Cleanup temporary file
                try {
                    \App\Utils\Helper::assertInTmpDir($tmpFile);
                    @unlink($tmpFile);
                } catch (\RuntimeException $e) {
                    $this->logger->warning("Skipping cleanup of non-temp file: {$tmpFile}");
                }
            }
            return $processedFiles;
        }

        // Rename the original file to *.xbk before compression/encryption
        $xbkUserPath = Helper::addXbkExtension($userPath);
        if ($userPath !== $xbkUserPath && is_file($userPath)) {
            // Physically rename the file if it is a single file
            @copy($userPath, $xbkUserPath);
            $userPath = $xbkUserPath;
        }

        // Create .xbk filename based on compression and encryption
        $originalBasename = Helper::createArchiveName($username, 'tar');
        $finalFilename = Helper::createXbkFilename($originalBasename, $compression, $encryption);
        $finalArchivePath = rtrim($tempDir, '/') . '/' . $finalFilename;

        // Check if archive already exists locally
        if (file_exists($finalArchivePath) && filesize($finalArchivePath) > 0) {
            $this->logger->info("Archive already exists for user '{$username}' at '{$finalArchivePath}'. Skipping archive creation.");
            return $finalArchivePath;
        }
        
        // Check if file already exists on remote storages (optimization)
        // For directory archives, default threshold 0 (any existing remote)
        if ($remoteStorages && $this->checkRemoteFileExists($finalFilename, $remoteStorages, 0)) {
            $this->logger->info("Skipping archive creation for user '{$username}': final backup already exists on remote storage(s)");
            // Return null to indicate no local file needs to be uploaded
            return null;
        }

        $this->logger->info("Preparing to create archive for user '{$username}' at '{$finalArchivePath}'.");
        if ($isDryRun) {
            $this->logger->info("[DRY-RUN] Skipping archive creation for {$username}.");
            return $finalArchivePath;
        }

        try {
            $this->logger->debug("Creating archive...");
            $exclude = $this->config['archive']['exclude'] ?? [];

            // Special handling for combined compression+encryption methods
            $this->logger->debug("Checking combined methods for user backup: compression='{$compression}', encryption='{$encryption}'");
            if (($compression === '7z' || $compression === '7zip') && ($encryption === '7z' || $encryption === '7zip')) {
                // 7z can compress and encrypt directory in one step
                $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));
                $ok = Helper::sevenZipCompressEncryptFile($userPath, $finalArchivePath, $password, $compressionLevel ?? 5);
                if (!$ok) {
                    throw new \Exception("Failed to compress and encrypt directory with 7z: $userPath");
                }
                $this->logger->info("Archive for {$username} created, compressed and encrypted with 7z in one step.");
                return $finalArchivePath;
            } elseif ($compression === 'zip' && $encryption === 'zip') {
                // zip can compress and encrypt directory in one step
                $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));
                $ok = Helper::zipCompressEncryptFile($userPath, $finalArchivePath, $password, $compressionLevel ?? 6);
                if (!$ok) {
                    throw new \Exception("Failed to compress and encrypt directory with zip: $userPath");
                }
                $this->logger->info("Archive for {$username} created, compressed and encrypted with zip in one step.");
                return $finalArchivePath;
            }

            // For other combinations, use separate steps
            // Always create tar for all except zip/7z
            $needTar = !in_array($compression, ['zip', '7z', '7zip']);
            $tarPath = rtrim($tempDir, '/') . '/' . $originalBasename;

            if ($needTar) {
                $result = \wapmorgan\UnifiedArchive\UnifiedArchive::archiveDirectory(
                    $userPath,
                    $tarPath,
                    'tar',
                    $exclude
                );
                if ($result !== true) {
                    throw new \Exception("UnifiedArchive failed: " . print_r($result, true));
                }
            }

            // Step 1: Apply compression
            $compressedPath = $tarPath;
            if ($compression !== 'none') {
                $baseFileName = pathinfo($tarPath, PATHINFO_FILENAME);
                $compExt = match ($compression) {
                    'gzip', 'gz' => 'gz',
                    'bzip2', 'bz2' => 'bz2',
                    'xz' => 'xz',
                    'zstd', 'zst' => 'zst',
                    'zip' => 'zip',
                    '7z', '7zip' => '7z',
                    default => 'gz',
                };
                $compressedPath = rtrim($tempDir, '/') . '/' . $baseFileName . '.xbk.' . $compExt;
                $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));

                $ok = match ($compression) {
                    'zstd', 'zst' => Helper::zstdCompressFile($tarPath, $compressedPath, $compressionLevel ?? 19),
                    'gzip', 'gz' => Helper::gzipCompressFile($tarPath, $compressedPath, $compressionLevel ?? 6),
                    'bzip2', 'bz2' => Helper::bzip2CompressFile($tarPath, $compressedPath, $compressionLevel ?? 6),
                    'xz' => Helper::xzCompressFile($tarPath, $compressedPath, $compressionLevel ?? 6),
                    'zip' => Helper::zipCompressFile($userPath, $compressedPath, $compressionLevel ?? 6),
                    '7z', '7zip' => Helper::sevenZipCompressFile($userPath, $compressedPath, $compressionLevel ?? 5),
                    default => Helper::gzipCompressFile($tarPath, $compressedPath, $compressionLevel ?? 6),
                };

                if (!$ok) {
                    $this->logger->error("Failed to compress archive with {$compression}: {$tarPath}");
                    return null;
                }
                $this->logger->info("Archive for {$username} created and compressed with {$compression} (level: " . ($compressionLevel ?? '-') . ").");
            } else {
                // No compression, add .xbk marker
                $compressedPath = rtrim($tempDir, '/') . '/' . pathinfo($tarPath, PATHINFO_FILENAME) . '.xbk';
                if (!@copy($tarPath, $compressedPath)) {
                    $this->logger->error("Failed to create .xbk marker file: {$compressedPath}");
                    return null;
                }
            }

            // Step 2: Apply encryption (if needed)
            $encryptedFile = $compressedPath;
            if ($encryption !== 'none') {
                $encExt = match ($encryption) {
                    'aes', 'openssl' => 'aes',
                    'gpg', 'gpg2', 'gnupg' => 'gpg',
                    default => 'aes',
                };
                $encryptedFile = $compressedPath . '.' . $encExt;

                $ok = match ($encryption) {
                    'gpg', 'gpg2', 'gnupg' => Helper::gpgEncryptFile($compressedPath, $encryptedFile, $password),
                    default => Helper::encryptFile($compressedPath, $encryptedFile, $password),
                };

                if ($ok) {
                    $size = is_file($encryptedFile) ? filesize($encryptedFile) : 0;
                    $this->logger->info("Encrypted archive {$compressedPath} to {$encryptedFile} using {$encryption}, size={$size}");
                    if ($size === 0) {
                        $this->logger->warning("[ArchiveHandler] Output file is empty after encryption: {$encryptedFile}");
                    } elseif ($size < 64) {
                        $this->logger->warning("[ArchiveHandler] Output file is suspiciously small: {$encryptedFile}, size={$size}");
                    }
                } else {
                    $this->logger->error("Failed to encrypt archive {$compressedPath} with {$encryption}");
                    return null;
                }
            }

            return $encryptedFile;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to create archive for {$username}: " . $e->getMessage(), ['exception' => $e]);
            throw new \Exception("Failed to create archive for {$username}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a file exists on any of the remote storages and meets size threshold
     *
     * @param string $filename The filename to check
     * @param array $remoteStorages Array of remote storage instances
     * @param int $minSize Minimum required size in bytes on remote to consider as existing
     * @return bool True if file exists on all remotes with size >= minSize
     */
    private function checkRemoteFileExists(string $filename, array $remoteStorages, int $minSize = 0): bool
    {
        $existsOnRemotes = [];
        $totalRemotes = count($remoteStorages);
        foreach ($remoteStorages as $storageInfo) {
            try {
                $remotePath = ($this->config['remote']['path'] ?? '') . '/' . basename($filename);
                if ($storageInfo['storage']->fileExists($remotePath)) {
                    try {
                        $remoteSize = $storageInfo['storage']->fileSize($remotePath);
                        if ($remoteSize >= $minSize) {
                            $this->logger->debug("File {$filename} exists on remote storage {$storageInfo['driver']} (size: " . number_format($remoteSize) . " bytes >= threshold: " . number_format($minSize) . " bytes)");
                            $existsOnRemotes[] = $storageInfo['driver'];
                        } else {
                            $this->logger->debug("File {$filename} exists on remote storage {$storageInfo['driver']} but is below threshold (size: " . number_format($remoteSize) . " bytes < threshold: " . number_format($minSize) . " bytes)");
                        }
                    } catch (\Throwable $e) {
                        $this->logger->debug("File {$filename} exists on remote storage {$storageInfo['driver']} but could not get size: " . $e->getMessage());
                        // Assume existence when size check fails
                        $existsOnRemotes[] = $storageInfo['driver'];
                    }
                } else {
                    $this->logger->debug("File {$filename} does not exist on remote storage {$storageInfo['driver']}");
                }
            } catch (\Throwable $e) {
                $this->logger->debug("Could not check file existence on {$storageInfo['driver']}: " . $e->getMessage());
            }
        }
        $existsCount = count($existsOnRemotes);
        if ($existsCount > 0) {
            $this->logger->info("File {$filename} exists on {$existsCount}/{$totalRemotes} remote storage(s): " . implode(', ', $existsOnRemotes));
            // Return true only if all remotes satisfy existence and size threshold
            return $existsCount === $totalRemotes;
        }
        $this->logger->debug("File {$filename} does not exist on any remote storage");
        return false; // none met existence & threshold
    }
}
