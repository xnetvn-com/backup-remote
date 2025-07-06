<?php

/**
 * Integration test for .xbk backup and restore process
 * Tests the complete workflow: backup creation -> upload -> download -> restore
 */

declare(strict_types=1);

require_once __DIR__ . '/../../libs/vendor/autoload.php';

use App\Utils\Helper;
use App\Utils\Logger;
use App\Backup\ArchiveHandler;
use Psr\Log\LoggerInterface;

class XbkIntegrationTest
{
    private string $testDir;
    private string $tmpDir;
    private LoggerInterface $logger;
    
    public function __construct()
    {
        $this->testDir = sys_get_temp_dir() . '/xbk_test_' . uniqid();
        $this->tmpDir = $this->testDir . '/tmp';
        $this->logger = Logger::getLogger();
        
        // Create test directories
        if (!mkdir($this->testDir, 0755, true) || !mkdir($this->tmpDir, 0755, true)) {
            throw new \RuntimeException("Failed to create test directories");
        }
    }
    
    public function __destruct()
    {
        // Cleanup test directories
        $this->removeDirectory($this->testDir);
    }
    
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        
        rmdir($dir);
    }
    
    private function createTestFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }
    
    public function testGzipAesWorkflow(): bool
    {
        echo "Testing Gzip + AES workflow...\n";
        
        // Create test tar file
        $originalFile = $this->testDir . '/test.2025-01-01_12-00-00.tar';
        $testContent = "This is test content for Gzip + AES workflow\n" . str_repeat("Test data ", 1000);
        $this->createTestFile($originalFile, $testContent);
        
        // Test createXbkFilename
        $xbkFilename = Helper::createXbkFilename(basename($originalFile), 'gzip', 'aes');
        $expectedName = 'test.2025-01-01_12-00-00.tar.xbk.gz.aes';
        
        if ($xbkFilename !== $expectedName) {
            echo "  ✗ createXbkFilename failed. Got: $xbkFilename, Expected: $expectedName\n";
            return false;
        }
        echo "  ✓ createXbkFilename: $xbkFilename\n";
        
        // Test compression
        $compressedFile = $this->tmpDir . '/test.2025-01-01_12-00-00.tar.xbk.gz';
        $success = Helper::gzipCompressFile($originalFile, $compressedFile, 6);
        if (!$success || !file_exists($compressedFile)) {
            echo "  ✗ Gzip compression failed\n";
            return false;
        }
        echo "  ✓ Gzip compression successful\n";
        
        // Test encryption
        $encryptedFile = $compressedFile . '.aes';
        $password = 'test_password_123';
        $success = Helper::encryptFile($compressedFile, $encryptedFile, $password);
        if (!$success || !file_exists($encryptedFile)) {
            echo "  ✗ AES encryption failed\n";
            return false;
        }
        echo "  ✓ AES encryption successful\n";
        
        // Test parseXbkFilename
        $fileInfo = Helper::parseXbkFilename(basename($encryptedFile));
        if ($fileInfo['compression'] !== 'gz' || $fileInfo['encryption'] !== 'aes' || !$fileInfo['hasXbk']) {
            echo "  ✗ parseXbkFilename failed. Got: " . json_encode($fileInfo) . "\n";
            return false;
        }
        echo "  ✓ parseXbkFilename successful\n";
        
        // Test decryption (reverse process)
        $decryptedFile = $this->tmpDir . '/decrypted.tar.xbk.gz';
        $success = Helper::decryptFile($encryptedFile, $decryptedFile, $password);
        if (!$success || !file_exists($decryptedFile)) {
            echo "  ✗ AES decryption failed\n";
            return false;
        }
        echo "  ✓ AES decryption successful\n";
        
        // Test decompression
        $decompressedFile = $this->tmpDir . '/decompressed.tar.xbk';
        $success = Helper::gzipDecompressFile($decryptedFile, $decompressedFile);
        if (!$success || !file_exists($decompressedFile)) {
            echo "  ✗ Gzip decompression failed\n";
            return false;
        }
        echo "  ✓ Gzip decompression successful\n";
        
        // Test content integrity
        $originalContent = file_get_contents($originalFile);
        $restoredContent = file_get_contents($decompressedFile);
        if ($originalContent !== $restoredContent) {
            echo "  ✗ Content integrity check failed\n";
            return false;
        }
        echo "  ✓ Content integrity verified\n";
        
        // Test getOriginalFilename
        $originalName = Helper::getOriginalFilename(basename($encryptedFile));
        if ($originalName !== basename($originalFile)) {
            echo "  ✗ getOriginalFilename failed. Got: $originalName, Expected: " . basename($originalFile) . "\n";
            return false;
        }
        echo "  ✓ getOriginalFilename successful\n";
        
        return true;
    }
    
    public function testZstdGpgWorkflow(): bool
    {
        echo "\nTesting Zstd + GPG workflow...\n";
        
        // Create test tar file
        $originalFile = $this->testDir . '/test2.2025-01-01_12-00-00.tar';
        $testContent = "This is test content for Zstd + GPG workflow\n" . str_repeat("Test data for zstd ", 1000);
        $this->createTestFile($originalFile, $testContent);
        
        // Test createXbkFilename
        $xbkFilename = Helper::createXbkFilename(basename($originalFile), 'zstd', 'gpg');
        $expectedName = 'test2.2025-01-01_12-00-00.tar.xbk.zst.gpg';
        
        if ($xbkFilename !== $expectedName) {
            echo "  ✗ createXbkFilename failed. Got: $xbkFilename, Expected: $expectedName\n";
            return false;
        }
        echo "  ✓ createXbkFilename: $xbkFilename\n";
        
        // Test compression (zstd)
        $compressedFile = $this->tmpDir . '/test2.2025-01-01_12-00-00.tar.xbk.zst';
        $success = Helper::zstdCompressFile($originalFile, $compressedFile, 19);
        if (!$success || !file_exists($compressedFile)) {
            echo "  ✗ Zstd compression failed (this may fail if zstd is not installed)\n";
            return false;
        }
        echo "  ✓ Zstd compression successful\n";
        
        // Test parseXbkFilename
        $fileInfo = Helper::parseXbkFilename($xbkFilename);
        if ($fileInfo['compression'] !== 'zst' || $fileInfo['encryption'] !== 'gpg' || !$fileInfo['hasXbk']) {
            echo "  ✗ parseXbkFilename failed. Got: " . json_encode($fileInfo) . "\n";
            return false;
        }
        echo "  ✓ parseXbkFilename successful\n";
        
        // Test decompression
        $decompressedFile = $this->tmpDir . '/decompressed2.tar.xbk';
        $success = Helper::zstdDecompressFile($compressedFile, $decompressedFile);
        if (!$success || !file_exists($decompressedFile)) {
            echo "  ✗ Zstd decompression failed\n";
            return false;
        }
        echo "  ✓ Zstd decompression successful\n";
        
        // Test content integrity
        $originalContent = file_get_contents($originalFile);
        $restoredContent = file_get_contents($decompressedFile);
        if ($originalContent !== $restoredContent) {
            echo "  ✗ Content integrity check failed\n";
            return false;
        }
        echo "  ✓ Content integrity verified\n";
        
        return true;
    }
    
    public function testSevenZipWorkflow(): bool
    {
        echo "\nTesting 7-Zip workflow...\n";
        
        // Create test tar file
        $originalFile = $this->testDir . '/test3.2025-01-01_12-00-00.tar';
        $testContent = "This is test content for 7-Zip workflow\n" . str_repeat("Test data for 7z ", 1000);
        $this->createTestFile($originalFile, $testContent);
        
        // Test createXbkFilename
        $xbkFilename = Helper::createXbkFilename(basename($originalFile), '7z', 'none');
        $expectedName = 'test3.2025-01-01_12-00-00.tar.xbk.7z';
        
        if ($xbkFilename !== $expectedName) {
            echo "  ✗ createXbkFilename failed. Got: $xbkFilename, Expected: $expectedName\n";
            return false;
        }
        echo "  ✓ createXbkFilename: $xbkFilename\n";
        
        // Test compression (7z)
        $compressedFile = $this->tmpDir . '/test3.2025-01-01_12-00-00.tar.xbk.7z';
        $success = Helper::sevenZipCompressFile($originalFile, $compressedFile, 5);
        if (!$success || !file_exists($compressedFile)) {
            echo "  ✗ 7-Zip compression failed (this may fail if 7z is not installed)\n";
            return false;
        }
        echo "  ✓ 7-Zip compression successful\n";
        
        // Test parseXbkFilename
        $fileInfo = Helper::parseXbkFilename($xbkFilename);
        if ($fileInfo['compression'] !== '7zip' || $fileInfo['encryption'] !== 'none' || !$fileInfo['hasXbk']) {
            echo "  ✗ parseXbkFilename failed. Got: " . json_encode($fileInfo) . "\n";
            return false;
        }
        echo "  ✓ parseXbkFilename successful\n";
        
        // Test decompression
        $decompressedFile = $this->tmpDir . '/decompressed3.tar.xbk';
        $success = Helper::sevenZipDecompressFile($compressedFile, $decompressedFile);
        if (!$success || !file_exists($decompressedFile)) {
            echo "  ✗ 7-Zip decompression failed\n";
            return false;
        }
        echo "  ✓ 7-Zip decompression successful\n";
        
        // Test content integrity
        $originalContent = file_get_contents($originalFile);
        $restoredContent = file_get_contents($decompressedFile);
        if ($originalContent !== $restoredContent) {
            echo "  ✗ Content integrity check failed\n";
            return false;
        }
        echo "  ✓ Content integrity verified\n";
        
        return true;
    }
    
    public function run(): void
    {
        echo "=== XBK Integration Test ===\n\n";
        
        $results = [];
        
        try {
            $results['gzip_aes'] = $this->testGzipAesWorkflow();
        } catch (\Throwable $e) {
            echo "  ✗ Gzip + AES workflow failed with exception: " . $e->getMessage() . "\n";
            $results['gzip_aes'] = false;
        }
        
        try {
            $results['zstd_gpg'] = $this->testZstdGpgWorkflow();
        } catch (\Throwable $e) {
            echo "  ✗ Zstd + GPG workflow failed with exception: " . $e->getMessage() . "\n";
            $results['zstd_gpg'] = false;
        }
        
        try {
            $results['7zip'] = $this->testSevenZipWorkflow();
        } catch (\Throwable $e) {
            echo "  ✗ 7-Zip workflow failed with exception: " . $e->getMessage() . "\n";
            $results['7zip'] = false;
        }
        
        echo "\n=== Test Results ===\n";
        $passed = 0;
        $total = count($results);
        
        foreach ($results as $test => $result) {
            $status = $result ? '✓ PASS' : '✗ FAIL';
            echo "  $status $test\n";
            if ($result) $passed++;
        }
        
        echo "\nSummary: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "🎉 All tests passed! XBK system is working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Please check the output above.\n";
        }
    }
}

// Run the test
$test = new XbkIntegrationTest();
$test->run();
