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
        } else if (in_array($compression, ['zip', '7z', '7zip']) && $encryption === 'aes') {
            Helper::assertAesSupport($compression);
        }

        // If backing up files directly in the root directory, encrypt each file
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
                // Move the original file to tmp (prefer rename, fallback to copy+unlink)
                if (!file_exists($tmpFile)) {
                    if (@rename($file, $tmpFile) === false) {
                        if (@copy($file, $tmpFile)) {
                            @unlink($file);
                        } else {
                            $this->logger->error("Failed to move file {$file} to tmp: {$tmpFile}");
                            continue;
                        }
                    }
                    $this->logger->info("Moved file {$file} to tmp: {$tmpFile}");
                } else {
                    $this->logger->warning("Tmp file already exists: {$tmpFile}, skipping move.");
                }
                $encFile = $tmpFile;
                // Reset the encryption extension to the correct standard
                if ($encryption === 'none') {
                    if ($isDryRun) {
                        $this->logger->info("[DRY-RUN] Would keep {$tmpFile} as is");
                    } else {
                        $this->logger->info("No encryption applied to {$tmpFile}");
                    }
                } elseif ($encryption === 'gpg') {
                    $encFile .= '.gpg';
                    if ($isDryRun) {
                        $this->logger->info("[DRY-RUN] Would GPG encrypt {$tmpFile} to {$encFile}");
                    } else {
                        $ok = Helper::gpgEncryptFile($tmpFile, $encFile, $password);
                        if ($ok) {
                            $this->logger->info("GPG encrypted {$tmpFile} to {$encFile}");
                        } else {
                            $this->logger->error("Failed to GPG encrypt {$tmpFile}");
                        }
                    }
                } else { // default aes
                    $encFile .= '.aes';
                    if ($isDryRun) {
                        $this->logger->info("[DRY-RUN] Would AES encrypt {$tmpFile} to {$encFile}");
                    } else {
                        $ok = Helper::encryptFile($tmpFile, $encFile, $password);
                        if ($ok) {
                            $size = is_file($encFile) ? filesize($encFile) : 0;
                            $this->logger->info("AES encrypted {$tmpFile} to {$encFile}, size={$size}");
                            if ($size === 0) {
                                $this->logger->warning("[ArchiveHandler] Output file is empty after encryption: {$encFile}");
                            } elseif ($size < 64) {
                                $this->logger->warning("[ArchiveHandler] Output file is suspiciously small: {$encFile}, size={$size}");
                            }
                        } else {
                            $this->logger->error("Failed to AES encrypt {$tmpFile}");
                        }
                    }
                }
                // DO NOT delete the temp file here, just return the path for the upload step to handle
                $processedFiles[] = $encFile;
            }
            return $processedFiles;
        }

        // Create archive with compression method
        $archiveExt = match ($compression) {
            'zstd', 'zst' => 'tar.zst',
            'gzip', 'gz' => 'tar.gz',
            'bzip2', 'bz2' => 'tar.bz2',
            'xz' => 'tar.xz',
            'zip' => 'tar.zip',
            '7z', '7zip' => 'tar.7z',
            'none' => 'tar',
            default => 'tar.gz',
        };
        $archivePath = rtrim($tempDir, '/') . '/' . Helper::createArchiveName($username, $archiveExt);
        $this->logger->info("Preparing to create archive for user '{$username}' at '{$archivePath}'.");
        if ($isDryRun) {
            $this->logger->info("[DRY-RUN] Skipping archive creation for {$username}.");
            return $archivePath . ($encryption !== 'none' ? '.xenc' : '');
        }
        try {
            $this->logger->debug("Creating archive...");
            $exclude = $this->config['archive']['exclude'] ?? [];
            // Always create tar for all except zip/7z
            $needTar = !in_array($compression, ['zip', '7z', '7zip']);
            $tarPath = $needTar ? preg_replace('/\.[^.]+$/', '.tar', $archivePath) : null;
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
            $compressedPath = $archivePath;
            $ok = true;
            switch ($compression) {
                case 'zstd':
                case 'zst':
                    $ok = Helper::zstdEncryptFile($tarPath, $archivePath, $password, $compressionLevel ?? 19);
                    // Do not delete the temp file here, just return the path for upload processing
                    break;
                case 'gzip':
                case 'gz':
                    $ok = Helper::gzipCompressFile($tarPath, $archivePath, $compressionLevel ?? 6);
                    // Do not delete the temp file here
                    break;
                case 'bzip2':
                case 'bz2':
                    $ok = Helper::bzip2CompressFile($tarPath, $archivePath, $compressionLevel ?? 6);
                    // Do not delete the temp file here
                    break;
                case 'xz':
                    $ok = Helper::xzCompressFile($tarPath, $archivePath, $compressionLevel ?? 6);
                    // Do not delete the temp file here
                    break;
                case 'zip':
                    $ok = Helper::zipCompressFile($userPath, $archivePath, $compressionLevel ?? 6);
                    break;
                case '7z':
                case '7zip':
                    $ok = Helper::sevenZipCompressFile($userPath, $archivePath, $compressionLevel ?? 5);
                    break;
                case 'none':
                    $compressedPath = $tarPath;
                    break;
                default:
                    $ok = Helper::gzipCompressFile($tarPath, $archivePath, $compressionLevel ?? 6);
                    // Do not delete the temp file here
            }
            if (!$ok) {
                $this->logger->error("Failed to compress archive with {$compression}: " . ($tarPath ?? $userPath));
                return null;
            }
            $this->logger->info("Archive for {$username} created and compressed with {$compression} (level: " . ($compressionLevel ?? '-') . ").");
            // Apply encryption if needed
            $finalFile = $compressedPath;
            if ($encryption === 'none') {
                // No encryption, keep the compressed file as is
            } elseif ($encryption === 'gpg') {
                $finalFile .= '.gpg';
                $ok = Helper::gpgEncryptFile($compressedPath, $finalFile, $password);
                if ($ok) {
                    $this->logger->info("GPG encrypted archive {$compressedPath} to {$finalFile}");
                    // Do not delete the temp file here
                } else {
                    $this->logger->error("Failed to GPG encrypt archive {$compressedPath}");
                    return null;
                }
            } else { // default aes
                $finalFile .= '.aes';
                $ok = Helper::encryptFile($compressedPath, $finalFile, $password);
                if ($ok) {
                    $size = is_file($finalFile) ? filesize($finalFile) : 0;
                    $this->logger->info("AES encrypted archive {$compressedPath} to {$finalFile}, size={$size}");
                    if ($size === 0) {
                        $this->logger->warning("[ArchiveHandler] Output file is empty after encryption: {$finalFile}");
                    } elseif ($size < 64) {
                        $this->logger->warning("[ArchiveHandler] Output file is suspiciously small: {$finalFile}, size={$size}");
                    }
                    // Do not delete the temp file here
                } else {
                    $this->logger->error("Failed to AES encrypt archive {$compressedPath}");
                    return null;
                }
            }
            return $finalFile;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to create archive for {$username}: " . $e->getMessage(), ['exception' => $e]);
            throw new \Exception("Failed to create archive for {$username}: " . $e->getMessage(), 0, $e);
        }
    }
}
