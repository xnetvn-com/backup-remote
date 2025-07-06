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
     * @return string|array|null The path(s) to the created archive(s), or null on failure/dry-run.
     * @throws \Exception
     */
    public function create(string $username, string $userPath, bool $isDryRun): string|array|null
    {
        $tempDir = Helper::getTmpDir();
        // Get encryption password from environment (ENCRYPTION_PASSWORD or fallback to BACKUP_PASSWORD)
        $password = Helper::env('ENCRYPTION_PASSWORD', Helper::env('BACKUP_PASSWORD', $_ENV['BACKUP_PASSWORD'] ?? null));
        if (!$password) {
            $this->logger->error('ENCRYPTION_PASSWORD is not set. Encryption is required.');
            throw new \RuntimeException('ENCRYPTION_PASSWORD is not set.');
        }
        $compression = strtolower(Helper::env('BACKUP_COMPRESSION', 'gzip'));
        $encryption = strtolower(Helper::env('BACKUP_ENCRYPTION', 'aes'));
        $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));
        $this->logger->info("Compression method: {$compression}, Encryption method: {$encryption}, Level: " . (is_null($compressionLevel) ? 'none' : $compressionLevel));

        // Check AES-256 support for all encryption methods
        if ($encryption === 'aes' || $encryption === 'openssl') {
            Helper::assertAesSupport('aes');
        } elseif ($encryption === 'gpg' || $encryption === 'gpg2' || $encryption === 'gnupg') {
            Helper::assertAesSupport('gpg');
        } elseif (in_array($compression, ['zip', '7z', '7zip']) && $encryption === 'aes') {
            Helper::assertAesSupport($compression);
        }

        // If backing up files directly in the root directory, process each file
        if ($username === '__root__') {
            $backupFiles = glob(rtrim($userPath, '/') . '/*.{tar,zip,gz,zst}', GLOB_BRACE);
            if (!$backupFiles || count($backupFiles) === 0) {
                $this->logger->warning("No backup files found in root directory: {$userPath}");
                return null;
            }
            $processedFiles = [];
            foreach ($backupFiles as $file) {
                $basename = basename($file);
                $tmpFile = rtrim($tempDir, '/') . '/' . $basename;
                
                // Copy the original file to tmp (preserve source files)
                if (!file_exists($tmpFile)) {
                    if (@copy($file, $tmpFile)) {
                        $this->logger->info("Copied file {$file} to tmp: {$tmpFile}");
                    } else {
                        $this->logger->error("Failed to copy file {$file} to tmp: {$tmpFile}");
                        continue;
                    }
                } else {
                    $this->logger->warning("Tmp file already exists: {$tmpFile}, skipping copy.");
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
                        $processedFiles[] = $finalPath;
                    } else {
                        $this->logger->error("Failed to compress and encrypt {$tmpFile} with 7z");
                        continue;
                    }
                } elseif ($compression === 'zip' && $encryption === 'zip') {
                    // zip can compress and encrypt in one step
                    $compressionLevel = Helper::normalizeCompressionLevel($compression, Helper::env('BACKUP_COMPRESSION_LEVEL', 1));
                    $ok = Helper::zipCompressEncryptFile($tmpFile, $finalPath, $password, $compressionLevel ?? 6);
                    if ($ok) {
                        $this->logger->info("Compressed and encrypted {$tmpFile} to {$finalPath} using zip");
                        $processedFiles[] = $finalPath;
                    } else {
                        $this->logger->error("Failed to compress and encrypt {$tmpFile} with zip");
                        continue;
                    }
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
                            continue;
                        }
                        $this->logger->info("Compressed {$tmpFile} to {$compressedFile} using {$compression}");
                    } else {
                        // No compression, add .xbk marker
                        $compressedFile = $tmpFile . '.xbk';
                        if (!@copy($tmpFile, $compressedFile)) {
                            $this->logger->error("Failed to create .xbk marker file: {$compressedFile}");
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
                            continue;
                        }
                    }
                    
                    $processedFiles[] = $encryptedFile;
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

        if (file_exists($finalArchivePath) && filesize($finalArchivePath) > 0) {
            $this->logger->info("Archive already exists for user '{$username}' at '{$finalArchivePath}'. Skipping archive creation.");
            return $finalArchivePath;
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
}
