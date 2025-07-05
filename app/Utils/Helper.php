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

namespace App\Utils;

class Helper
{
    /**
     * Formats size from bytes to KB/MB/GB.
     */

    public static function formatSize($bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Checks if the current time is within the allowed backup window.
     */

    public static function isAllowedTime($start = '01:00', $end = '06:00'): bool
    {
        $now = date('H:i');
        return ($now >= $start && $now <= $end);
    }

    /**
     * Extracts username from backup filename (format: user.YYYY-MM-DD_HH-MM-SS.ext)
     */

    public static function extractUser($filename): ?string
    {
        if (preg_match('/^([a-zA-Z0-9_.-]+)\.\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\./', $filename, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Gets the value of an environment variable.
     *
     * @param string $key The environment variable key.
     * @param mixed|null $default The default value to return if the key is not found.
     * @return mixed The value of the environment variable or the default value.
     */

    public static function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? false;

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }

    /**
     * Creates a unique archive name.
     */

    public static function createArchiveName($prefix = 'backup', $suffix = 'zip'): string
    {
        $date = date('Y-m-d_H-i-s');
        return "{$prefix}.{$date}.{$suffix}";
    }

    /**
     * Detects the remote storage driver based on environment variables.
     * Priority: S3 > B2 > FTP > Local
     * @return string
     */
    public static function detectRemoteDriver(): string
    {
        if (!empty($_ENV['S3_KEY']) && !empty($_ENV['S3_SECRET']) && !empty($_ENV['S3_BUCKET'])) {
            return 's3';
        }
        if (!empty($_ENV['B2_KEY']) && !empty($_ENV['B2_SECRET']) && !empty($_ENV['B2_BUCKET'])) {
            return 'b2';
        }
        if (!empty($_ENV['FTP_HOST']) && !empty($_ENV['FTP_USER']) && !empty($_ENV['FTP_PASS'])) {
            return 'ftp';
        }
        return 'local';
    }

    /**
     * Detects all configured remote storage backends based on environment variables.
     * Returns an array of remote configs, each with a driver and its config.
     * Supports: s3, b2, ftp, local (always fallback if none set).
     * @return array
     */
    public static function detectAllRemotes(): array
    {
        $remotes = [];
        // AWS S3 via AWS_ environment variables
        if (!empty($_ENV['AWS_ACCESS_KEY_ID']) && !empty($_ENV['AWS_SECRET_ACCESS_KEY']) && !empty($_ENV['AWS_BUCKET'])) {
            $region = $_ENV['AWS_DEFAULT_REGION'] ?? '';
            if ($region) {
                $remotes[] = [
                    'driver' => 's3',
                    'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
                    'region' => $region,
                    'bucket' => $_ENV['AWS_BUCKET'],
                    'endpoint' => $_ENV['AWS_ENDPOINT'] ?? null,
                ];
            } else {
                error_log('[BackupRemote] Warning: AWS S3 remote detected but missing region. Skipping S3 remote.');
            }
        }
        // Legacy S3_* variables support
        if (!empty($_ENV['S3_KEY']) && !empty($_ENV['S3_SECRET']) && !empty($_ENV['S3_BUCKET'])) {
            $region = $_ENV['S3_REGION'] ?? '';
            if ($region) {
                $remotes[] = [
                    'driver' => 's3',
                    'key' => $_ENV['S3_KEY'],
                    'secret' => $_ENV['S3_SECRET'],
                    'region' => $region,
                    'bucket' => $_ENV['S3_BUCKET'],
                    'endpoint' => $_ENV['S3_ENDPOINT'] ?? null,
                ];
            } else {
                error_log('[BackupRemote] Warning: S3 remote detected but missing region. Skipping S3 remote.');
            }
        }
        if (!empty($_ENV['B2_KEY']) && !empty($_ENV['B2_SECRET']) && !empty($_ENV['B2_BUCKET'])) {
            $remotes[] = [
                'driver' => 'b2',
                'key' => $_ENV['B2_KEY'],
                'secret' => $_ENV['B2_SECRET'],
                'bucket' => $_ENV['B2_BUCKET'],
                'region' => $_ENV['B2_REGION'] ?? 'us-west-002',
                'endpoint' => $_ENV['B2_ENDPOINT'] ?? null,
            ];
        }
        if (!empty($_ENV['FTP_HOST']) && !empty($_ENV['FTP_USER']) && !empty($_ENV['FTP_PASS'])) {
            $remotes[] = [
                'driver' => 'ftp',
                'host' => $_ENV['FTP_HOST'],
                'user' => $_ENV['FTP_USER'],
                'pass' => $_ENV['FTP_PASS'],
                'port' => $_ENV['FTP_PORT'] ?? 21,
                'path' => $_ENV['FTP_ROOT'] ?? '/',
                'ssl' => $_ENV['FTP_SSL'] ?? false,
                'passive' => isset($_ENV['FTP_PASSIVE']) ? filter_var($_ENV['FTP_PASSIVE'], FILTER_VALIDATE_BOOLEAN) : true, // default true
            ];
        }
        // Always add local as fallback if no remote is set
        if (empty($remotes)) {
            $remotes[] = [
                'driver' => 'local',
                'root' => $_ENV['LOCAL_ROOT'] ?? '/backup',
            ];
        }
        return $remotes;
    }

    /**
     * Encrypts a file using AES-256-CBC and a password, outputs to $outputFile.
     * Streaming-safe, supports extremely large files (Petabyte scale).
     * Returns true on success, false on failure.
     *
     * @param string $inputFile  Path to the source file.
     * @param string $outputFile Path to the encrypted file (.xenc).
     * @param string $password   Encryption password.
     * @param int    $chunkSize  Size of each chunk (bytes), default 4MB.
     * @return bool
     */
    public static function encryptFile(string $inputFile, string $outputFile, string $password, int $chunkSize = 4194304): bool
    {
        $cipher = 'AES-256-CBC';
        $key = hash('sha256', $password, true);
        $ivLength = openssl_cipher_iv_length($cipher);

        if (!is_readable($inputFile)) {
            return false;
        }
        $plaintext = file_get_contents($inputFile);
        if ($plaintext === false) {
            return false;
        }
        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return false;
        }
        // Prepend IV and write to output file
        $data = $iv . $ciphertext;
        if (file_put_contents($outputFile, $data) === false) {
            return false;
        }
        return true;
    }

    /**
     * Decrypts a file encrypted by encryptFile() using AES-256-CBC and a password, outputs to $outputFile.
     * Streaming-safe, supports extremely large files (Petabyte scale).
     * Returns true on success, false on failure.
     *
     * @param string $inputFile  Path to the encrypted file (.xenc).
     * @param string $outputFile Path to the decrypted file.
     * @param string $password   Encryption password.
     * @param int    $chunkSize  Size of each chunk (bytes), default 4MB.
     * @return bool
     */
    public static function decryptFile(string $inputFile, string $outputFile, string $password, int $chunkSize = 4194304): bool
    {
        $cipher = 'AES-256-CBC';
        $key = hash('sha256', $password, true);
        $ivLength = openssl_cipher_iv_length($cipher);

        if (!is_readable($inputFile)) {
            return false;
        }
        $data = file_get_contents($inputFile);
        if ($data === false || strlen($data) < $ivLength) {
            return false;
        }
        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);
        // Handle empty ciphertext (no data beyond IV)
        if (strlen($ciphertext) === 0) {
            $plaintext = '';
        } else {
            $plaintext = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($plaintext === false) {
                return false;
            }
        }
        if (file_put_contents($outputFile, $plaintext) === false) {
            return false;
        }
        return true;
    }

    /**
     * Unit test for encryptFile() and decryptFile() with large file support.
     * Creates a random file, encrypts, decrypts, and compares hash.
     * Returns true if test passes, false otherwise.
     */
    public static function testEncryptDecryptLargeFile(): bool
    {
        $testFile = self::getTmpDir() . '/test_largefile_' . uniqid() . '.bin';
        $encFile = $testFile . '.xenc';
        $decFile = $testFile . '.dec';
        $password = 'TestPassword123!';
        $size = 50 * 1024 * 1024; // 50MB for test (adjustable)
        // Create random file
        $fp = fopen($testFile, 'wb');
        if (!$fp) return false;
        for ($written = 0; $written < $size; $written += 1048576) {
            $chunk = random_bytes(min(1048576, $size - $written));
            fwrite($fp, $chunk);
        }
        fclose($fp);
        // Encrypt
        if (!self::encryptFile($testFile, $encFile, $password)) {
            @unlink($testFile); @unlink($encFile); @unlink($decFile);
            return false;
        }
        // Decrypt
        if (!self::decryptFile($encFile, $decFile, $password)) {
            @unlink($testFile); @unlink($encFile); @unlink($decFile);
            return false;
        }
        // Compare hash
        $hash1 = hash_file('sha256', $testFile);
        $hash2 = hash_file('sha256', $decFile);
        // Cleanup
        @unlink($testFile); @unlink($encFile); @unlink($decFile);
        return $hash1 === $hash2;
    }

    /**
     * Encrypts a file using zstd with password (via zstd CLI, streaming, safe for huge files).
     * Requires zstd >= 1.5.0 (with --long and --password support).
     *
     * @param string $inputFile  Path to the source file.
     * @param string $outputFile Path to the encrypted file (.zst).
     * @param string $password   Password for encryption.
     * @param int    $level      Compression level (default 19).
     * @return bool
     */
    public static function zstdEncryptFile(string $inputFile, string $outputFile, string $password, int $level = 19): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = [
            'zstd',
            '--long=31', // max window for huge files
            "-$level",
            '--password', '-', // read password from stdin
            '-o', $outputFile,
            $inputFile
        ];
        $descriptors = [
            0 => ['pipe', 'r'], // stdin (for password)
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) return false;
        fwrite($pipes[0], $password . "\n");
        fclose($pipes[0]);
        // Read output to avoid blocking (zstd writes to file, but may output warnings)
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Decrypts a zstd-encrypted file with password (via zstd CLI, streaming, safe for huge files).
     *
     * @param string $inputFile  Path to the encrypted file (.zst).
     * @param string $outputFile Path to the decrypted file.
     * @param string $password   Password for decryption.
     * @return bool
     */
    public static function zstdDecryptFile(string $inputFile, string $outputFile, string $password): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = [
            'zstd',
            '-d',
            '--long=31',
            '--password', '-',
            '-o', $outputFile,
            $inputFile
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) return false;
        fwrite($pipes[0], $password . "\n");
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Encrypts a file using GPG symmetric encryption (via gpg CLI, streaming, safe for huge files).
     *
     * @param string $inputFile  Path to the source file.
     * @param string $outputFile Path to the encrypted file (.gpg).
     * @param string $password   Password for encryption.
     * @return bool
     */
    public static function gpgEncryptFile(string $inputFile, string $outputFile, string $password): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = [
            'gpg',
            '--batch',
            '--yes',
            '--symmetric',
            '--cipher-algo', 'AES256',
            '--passphrase-fd', '0', // read passphrase from stdin
            '-o', $outputFile,
            $inputFile
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) return false;
        fwrite($pipes[0], $password . "\n");
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Decrypts a GPG-encrypted file with password (via gpg CLI, streaming, safe for huge files).
     *
     * @param string $inputFile  Path to the encrypted file (.gpg).
     * @param string $outputFile Path to the decrypted file.
     * @param string $password   Password for decryption.
     * @return bool
     */
    public static function gpgDecryptFile(string $inputFile, string $outputFile, string $password): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = [
            'gpg',
            '--batch',
            '--yes',
            '--decrypt',
            '--passphrase-fd', '0',
            '-o', $outputFile,
            $inputFile
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) return false;
        fwrite($pipes[0], $password . "\n");
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Get the normalized temporary directory for backup (priority: TMP_DIR, then /tmp, then ./tmp).
     * Ensure the directory exists and is writable.
     * @return string Absolute path to the temporary directory.
     */
    public static function getTmpDir(): string
    {
        $envTmp = self::env('TMP_DIR', $_ENV['TMP_DIR'] ?? null);
        if ($envTmp && is_string($envTmp) && trim($envTmp) !== '') {
            $tmpDir = $envTmp;
        } elseif (is_dir('/tmp') && is_writable('/tmp')) {
            $tmpDir = '/tmp';
        } else {
            $tmpDir = realpath(__DIR__ . '/../../tmp') ?: (__DIR__ . '/../../tmp');
        }
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0770, true);
        }
        return rtrim($tmpDir, '/');
    }

    /**
     * Normalize compression level for supported methods.
     * @param string $method Compression method (gzip, zstd, bzip2, xz, none)
     * @param int|string|null $level Input level (from env or user)
     * @return int|null Normalized level or null if not applicable
     */
    public static function normalizeCompressionLevel(string $method, $level): ?int
    {
        $method = strtolower($method);
        $level = is_numeric($level) ? (int)$level : null;
        switch ($method) {
            case 'gzip':
                if ($level === null) return 1;
                return max(1, min(9, $level));
            case 'zstd':
            case 'zst':
                if ($level === null) return 19;
                return max(1, min(22, $level));
            case 'bzip2':
                if ($level === null) return 1;
                return max(1, min(9, $level));
            case 'xz':
                if ($level === null) return 6;
                return max(0, min(9, $level));
            case 'zip':
                if ($level === null) return 6;
                return max(0, min(9, $level));
            case '7z':
                if ($level === null) return 5;
                return max(1, min(9, $level));
            case 'none':
            default:
                return null;
        }
    }

    /**
     * Compresses a file using gzip CLI (streaming, safe for huge files).
     * @param string $inputFile Path to the source file.
     * @param string $outputFile Path to the compressed file (.gz).
     * @param int $level Compression level (1-9).
     * @return bool
     */
    public static function gzipCompressFile(string $inputFile, string $outputFile, int $level = 6): bool
    {
        if (!is_readable($inputFile)) return false;
        $level = max(1, min(9, $level));
        $cmd = [
            'gzip',
            '-c',
            "-$level",
            $inputFile
        ];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Decompresses a gzip file using CLI (streaming).
     */
    public static function gzipDecompressFile(string $inputFile, string $outputFile): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = ['gzip', '-d', '-c', $inputFile];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Compresses a file using bzip2 CLI (streaming, safe for huge files).
     */
    public static function bzip2CompressFile(string $inputFile, string $outputFile, int $level = 6): bool
    {
        if (!is_readable($inputFile)) return false;
        $level = max(1, min(9, $level));
        $cmd = ['bzip2', '-c', "-$level", $inputFile];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Decompresses a bzip2 file using CLI (streaming).
     */
    public static function bzip2DecompressFile(string $inputFile, string $outputFile): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = ['bzip2', '-d', '-c', $inputFile];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Compresses a file using xz CLI (streaming, safe for huge files).
     */
    public static function xzCompressFile(string $inputFile, string $outputFile, int $level = 6): bool
    {
        if (!is_readable($inputFile)) return false;
        $level = max(0, min(9, $level));
        $cmd = ['xz', '-c', "-$level", $inputFile];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Decompresses a xz file using CLI (streaming).
     */
    public static function xzDecompressFile(string $inputFile, string $outputFile): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = ['xz', '-d', '-c', $inputFile];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Compresses a file using zip CLI (single file, streaming, safe for huge files).
     * Supports AES-256 encryption if $password is provided and system zip supports it.
     */
    public static function zipCompressFile(string $inputFile, string $outputFile, int $level = 6, string $password = null): bool
    {
        if (!is_readable($inputFile)) return false;
        $level = max(0, min(9, $level));
        $tmpDir = self::getTmpDir() . '/zip_' . uniqid();
        if (!mkdir($tmpDir, 0700, true)) return false;
        $baseName = basename($inputFile);
        $tmpInput = $tmpDir . '/' . $baseName;
        if (!copy($inputFile, $tmpInput)) {
            rmdir($tmpDir);
            return false;
        }
        $cmd = ['zip', '-j', "-$level"];
        if ($password !== null && $password !== '') {
            // Use legacy password encryption
            $cmd[] = '-P';
            $cmd[] = $password;
        }
        $cmd[] = $outputFile;
        $cmd[] = $tmpInput;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            unlink($tmpInput);
            rmdir($tmpDir);
            return false;
        }
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($proc);
        unlink($tmpInput);
        rmdir($tmpDir);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Decompresses a zip file (single file, streaming).
     */
    public static function zipDecompressFile(string $inputFile, string $outputFile): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = ['unzip', '-p', $inputFile];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Compresses a file using 7z CLI (single file, streaming, safe for huge files).
     * Supports AES-256 encryption if $password is provided.
     */
    public static function sevenZipCompressFile(string $inputFile, string $outputFile, int $level = 5, string $password = null): bool
    {
        if (!is_readable($inputFile)) return false;
        $level = max(1, min(9, $level));
        $cmd = ['7z', 'a', '-t7z', "-mx=$level"];
        if ($password !== null && $password !== '') {
            $cmd[] = "-p$password";
            $cmd[] = '-mhe=on';
        }
        $cmd[] = $outputFile;
        $cmd[] = $inputFile;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) return false;
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Decompresses a 7z file (single file, streaming).
     */
    public static function sevenZipDecompressFile(string $inputFile, string $outputFile): bool
    {
        if (!is_readable($inputFile)) return false;
        $cmd = ['7z', 'e', '-so', $inputFile];
        $out = fopen($outputFile, 'wb');
        if (!$out) return false;
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            fclose($out);
            return false;
        }
        while (!feof($pipes[1])) {
            $data = fread($pipes[1], 8192);
            if ($data === false) break;
            fwrite($out, $data);
        }
        fclose($pipes[1]);
        fclose($out);
        $exit = proc_close($proc);
        return $exit === 0 && is_file($outputFile);
    }

    /**
     * Check if the system supports AES-256 for the given encryption/compression method.
     * Throws exception if not supported.
     * @param string $method Encryption/compression method (aes|gpg|zip|7z)
     * @throws \RuntimeException
     */
    public static function assertAesSupport(string $method): void
    {
        $method = strtolower($method);
        if ($method === 'zip') {
            // Check if 'zip' command supports AES (zip >= 3.0 with -e -Z aes-256)
            $help = shell_exec('zip --help 2>&1');
            if (strpos($help, '-Z') === false || strpos($help, 'aes') === false) {
                throw new \RuntimeException("Your system's 'zip' command does not support AES encryption. Please install zip >= 3.0 with AES support or use 7z.");
            }
        } elseif ($method === '7z' || $method === '7zip') {
            // Check if '7z' command exists and supports AES-256
            $help = shell_exec('7z --help 2>&1');
            if (strpos($help, 'AES-256') === false) {
                throw new \RuntimeException("Your system's '7z' command does not support AES-256 encryption. Please install 7-Zip with AES support.");
            }
        } elseif ($method === 'aes' || $method === 'openssl') {
            // Check openssl supports AES-256-CBC
            $ciphers = shell_exec('openssl list -cipher-algorithms 2>&1');
            if (strpos($ciphers, 'aes-256-cbc') === false) {
                throw new \RuntimeException("OpenSSL does not support AES-256-CBC. Please install OpenSSL with AES-256 support.");
            }
        } elseif ($method === 'gpg' || $method === 'gpg2' || $method === 'gnupg') {
            // Check gpg supports AES256
            $gpg = shell_exec('gpg --version 2>&1');
            if (strpos($gpg, 'Cipher: AES256') === false) {
                // Indicate encryption cannot proceed due to missing AES256 support
                throw new \RuntimeException("Backup can not be encrypted: GPG does not support AES256. Please install GnuPG with AES256 support.");
            }
        } else {
            throw new \RuntimeException("Unknown or unsupported encryption/compression method: $method");
        }
    }

    /**
     * Add .xbk extension to filename before compression/encryption.
     */
    public static function addXbkExtension(string $filename): string
    {
        if (str_ends_with($filename, '.xbk')) return $filename;
        return $filename . '.xbk';
    }

    /**
     * Remove .xbk extension from filename (if present).
     */
    public static function removeXbkExtension(string $filename): string
    {
        if (str_ends_with($filename, '.xbk')) {
            return substr($filename, 0, -4);
        }
        return $filename;
    }
}
