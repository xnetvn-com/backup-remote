<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Utils\Helper;

/**
 * Test class for performance optimization and large file handling.
 * @covers App\Utils\Helper
 */
/**
 * @covers AppUtilsHelper
 */
class PerformanceOptimizationTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/perf_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @covers App\Utils\Helper::encryptFile
     * @covers App\Utils\Helper::decryptFile
     */
    public function test_encrypt_decrypt_large_file_should_use_streaming(): void
    {
        $testFile = $this->testDir . '/large_test.bin';
        $encFile = $testFile . '.enc';
        $decFile = $testFile . '.dec';
        $password = 'test_password_123';
        
        // Create a 10MB test file
        $fileSize = 10 * 1024 * 1024; // 10MB
        $this->createTestFile($testFile, $fileSize);
        
        // Monitor memory usage
        $memoryBefore = memory_get_usage(true);
        
        // Encrypt
        $startTime = microtime(true);
        $encryptResult = Helper::encryptFile($testFile, $encFile, $password);
        $encryptTime = microtime(true) - $startTime;
        
        $this->assertTrue($encryptResult, 'Encryption should succeed');
        $this->assertFileExists($encFile);
        
        // Decrypt
        $startTime = microtime(true);
        $decryptResult = Helper::decryptFile($encFile, $decFile, $password);
        $decryptTime = microtime(true) - $startTime;
        
        $this->assertTrue($decryptResult, 'Decryption should succeed');
        $this->assertFileExists($decFile);
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        // Verify integrity
        $originalHash = hash_file('sha256', $testFile);
        $decryptedHash = hash_file('sha256', $decFile);
        $this->assertEquals($originalHash, $decryptedHash, 'File integrity should be maintained');
        
        // Performance assertions
        $this->assertLessThan(30.0, $encryptTime, 'Encryption should complete within 30 seconds');
        $this->assertLessThan(30.0, $decryptTime, 'Decryption should complete within 30 seconds');
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be less than 50MB for 10MB file');
        
        // Performance metrics (commented to avoid test output)
        // echo "\nPerformance Metrics:\n";
        // echo "- File size: " . Helper::formatBytes($fileSize) . "\n";
        // echo "- Encrypt time: " . number_format($encryptTime, 2) . "s\n";
        // echo "- Decrypt time: " . number_format($decryptTime, 2) . "s\n";
        // echo "- Memory used: " . Helper::formatBytes($memoryUsed) . "\n";
    }

    /**
     * @covers App\Utils\Helper::gzipCompressFile
     * @covers App\Utils\Helper::gzipDecompressFile
     */
    public function test_compression_performance_with_large_files(): void
    {
        $testFile = $this->testDir . '/compress_test.txt';
        $compFile = $testFile . '.gz';
        $decompFile = $testFile . '.decomp';
        
        // Create a test file with repetitive data (compresses well)
        $fileSize = 5 * 1024 * 1024; // 5MB
        $this->createRepetitiveTestFile($testFile, $fileSize);
        
        $memoryBefore = memory_get_usage(true);
        
        // Compress
        $startTime = microtime(true);
        $compressResult = Helper::gzipCompressFile($testFile, $compFile, 6);
        $compressTime = microtime(true) - $startTime;
        
        $this->assertTrue($compressResult, 'Compression should succeed');
        $this->assertFileExists($compFile);
        
        // Decompress
        $startTime = microtime(true);
        $decompressResult = Helper::gzipDecompressFile($compFile, $decompFile);
        $decompressTime = microtime(true) - $startTime;
        
        $this->assertTrue($decompressResult, 'Decompression should succeed');
        $this->assertFileExists($decompFile);
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        // Verify integrity
        $originalHash = hash_file('sha256', $testFile);
        $decompressedHash = hash_file('sha256', $decompFile);
        $this->assertEquals($originalHash, $decompressedHash, 'File integrity should be maintained');
        
        // Performance and compression ratio
        $originalSize = filesize($testFile);
        $compressedSize = filesize($compFile);
        $compressionRatio = ($originalSize - $compressedSize) / $originalSize * 100;
        
        $this->assertLessThan(15.0, $compressTime, 'Compression should complete within 15 seconds');
        $this->assertLessThan(10.0, $decompressTime, 'Decompression should complete within 10 seconds');
        $this->assertGreaterThan(50, $compressionRatio, 'Compression ratio should be better than 50%');
        
        // Compression performance metrics (commented to avoid test output)
        // echo "\nCompression Performance:\n";
        // echo "- Original size: " . Helper::formatBytes($originalSize) . "\n";
        // echo "- Compressed size: " . Helper::formatBytes($compressedSize) . "\n";
        // echo "- Compression ratio: " . number_format($compressionRatio, 1) . "%\n";
        // echo "- Compress time: " . number_format($compressTime, 2) . "s\n";
        // echo "- Decompress time: " . number_format($decompressTime, 2) . "s\n";
        // echo "- Memory used: " . Helper::formatBytes($memoryUsed) . "\n";
    }

    /**
     * @covers App\Utils\Helper::testEncryptDecryptLargeFile
     */
    public function test_encrypt_decrypt_stress_test(): void
    {
        // This is the built-in stress test for encryption
        $result = Helper::testEncryptDecryptLargeFile();
        $this->assertTrue($result, 'Built-in encryption stress test should pass');
    }

    /**
     * Test concurrent file operations
     */
    public function test_concurrent_file_operations_should_not_interfere(): void
    {
        $file1 = $this->testDir . '/concurrent1.txt';
        $file2 = $this->testDir . '/concurrent2.txt';
        $enc1 = $file1 . '.enc';
        $enc2 = $file2 . '.enc';
        
        $this->createTestFile($file1, 1024 * 1024); // 1MB
        $this->createTestFile($file2, 1024 * 1024); // 1MB
        
        $password = 'concurrent_test';
        
        // Simulate concurrent operations
        $result1 = Helper::encryptFile($file1, $enc1, $password);
        $result2 = Helper::encryptFile($file2, $enc2, $password);
        
        $this->assertTrue($result1, 'First encryption should succeed');
        $this->assertTrue($result2, 'Second encryption should succeed');
        $this->assertFileExists($enc1);
        $this->assertFileExists($enc2);
        
        // Files should be different
        $this->assertNotEquals(
            hash_file('sha256', $enc1),
            hash_file('sha256', $enc2),
            'Encrypted files should be different'
        );
    }

    /**
     * Create a test file with random data
     */
    private function createTestFile(string $filePath, int $size): void
    {
        $fp = fopen($filePath, 'wb');
        $written = 0;
        $chunkSize = 64 * 1024; // 64KB chunks
        
        while ($written < $size) {
            $remaining = $size - $written;
            $currentChunk = min($chunkSize, $remaining);
            $data = random_bytes($currentChunk);
            fwrite($fp, $data);
            $written += $currentChunk;
        }
        
        fclose($fp);
    }

    /**
     * Create a test file with repetitive data (compresses well)
     */
    private function createRepetitiveTestFile(string $filePath, int $size): void
    {
        $pattern = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 100);
        $patternLength = strlen($pattern);
        
        $fp = fopen($filePath, 'wb');
        $written = 0;
        
        while ($written < $size) {
            $remaining = $size - $written;
            $writeSize = min($patternLength, $remaining);
            fwrite($fp, substr($pattern, 0, $writeSize));
            $written += $writeSize;
        }
        
        fclose($fp);
    }

    /**
     * Test memory usage stays within limits during operations
     */
    public function test_memory_usage_should_stay_within_limits(): void
    {
        $memoryLimit = 64 * 1024 * 1024; // 64MB limit
        
        $testFile = $this->testDir . '/memory_test.bin';
        $this->createTestFile($testFile, 20 * 1024 * 1024); // 20MB file
        
        $memoryBefore = memory_get_usage(true);
        
        // Perform memory-intensive operation
        $encFile = $testFile . '.enc';
        Helper::encryptFile($testFile, $encFile, 'test_password');
        
        $memoryPeak = memory_get_peak_usage(true);
        $memoryUsed = $memoryPeak - $memoryBefore;
        
        $this->assertLessThan(
            $memoryLimit,
            $memoryUsed,
            "Memory usage ({$memoryUsed} bytes) should stay below limit ({$memoryLimit} bytes)"
        );
        
        // Memory usage metrics (commented to avoid test output)
        // echo "\nMemory Usage Test:\n";
        // echo "- File size: 20MB\n";
        // echo "- Memory used: " . Helper::formatBytes($memoryUsed) . "\n";
        // echo "- Memory limit: " . Helper::formatBytes($memoryLimit) . "\n";
    }
}
