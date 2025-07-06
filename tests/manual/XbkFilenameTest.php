<?php

/**
 * Test case for .xbk filename processing functionality
 * Tests the createXbkFilename and parseXbkFilename functions
 */

declare(strict_types=1);

require_once __DIR__ . '/../../libs/vendor/autoload.php';

use App\Utils\Helper;

class XbkFilenameTest
{
    public static function testCreateXbkFilename(): void
    {
        echo "Testing createXbkFilename():\n";
        
        $testCases = [
            // [originalFile, compression, encryption, expected]
            ['user.2025-01-01_12-00-00.tar', 'gzip', 'aes', 'user.2025-01-01_12-00-00.tar.xbk.gz.aes'],
            ['user.2025-01-01_12-00-00.tar', '7z', 'none', 'user.2025-01-01_12-00-00.tar.xbk.7z'],
            ['user.2025-01-01_12-00-00.tar', 'zstd', 'gpg', 'user.2025-01-01_12-00-00.tar.xbk.zst.gpg'],
            ['user.2025-01-01_12-00-00.tar', 'none', 'aes', 'user.2025-01-01_12-00-00.tar.xbk.aes'],
            ['user.2025-01-01_12-00-00.tar', 'none', 'none', 'user.2025-01-01_12-00-00.tar.xbk'],
            ['user.2025-01-01_12-00-00.tar', 'bzip2', 'gpg', 'user.2025-01-01_12-00-00.tar.xbk.bz2.gpg'],
            ['user.2025-01-01_12-00-00.tar', 'xz', 'aes', 'user.2025-01-01_12-00-00.tar.xbk.xz.aes'],
            ['user.2025-01-01_12-00-00.tar', 'zip', 'none', 'user.2025-01-01_12-00-00.tar.xbk.zip'],
        ];
        
        foreach ($testCases as [$original, $compression, $encryption, $expected]) {
            $result = Helper::createXbkFilename($original, $compression, $encryption);
            $status = $result === $expected ? '✓' : '✗';
            echo "  {$status} {$original} + {$compression} + {$encryption} => {$result}\n";
            if ($result !== $expected) {
                echo "    Expected: {$expected}\n";
            }
        }
    }
    
    public static function testParseXbkFilename(): void
    {
        echo "\nTesting parseXbkFilename():\n";
        
        $testCases = [
            // [filename, expectedOriginal, expectedCompression, expectedEncryption, expectedHasXbk]
            ['user.2025-01-01_12-00-00.tar.xbk.gz.aes', 'user.2025-01-01_12-00-00.tar', 'gz', 'aes', true],
            ['user.2025-01-01_12-00-00.tar.xbk.7z', 'user.2025-01-01_12-00-00.tar', '7zip', 'none', true],
            ['user.2025-01-01_12-00-00.tar.xbk.zst.gpg', 'user.2025-01-01_12-00-00.tar', 'zst', 'gpg', true],
            ['user.2025-01-01_12-00-00.tar.xbk.aes', 'user.2025-01-01_12-00-00.tar', 'none', 'aes', true],
            ['user.2025-01-01_12-00-00.tar.xbk', 'user.2025-01-01_12-00-00.tar', 'none', 'none', true],
            ['user.2025-01-01_12-00-00.tar.xbk.bz2.gpg', 'user.2025-01-01_12-00-00.tar', 'bz2', 'gpg', true],
            ['user.2025-01-01_12-00-00.tar.xbk.xz.aes', 'user.2025-01-01_12-00-00.tar', 'xz', 'aes', true],
            ['user.2025-01-01_12-00-00.tar.xbk.zip', 'user.2025-01-01_12-00-00.tar', 'zip', 'none', true],
            
            // Non-.xbk files (legacy)
            ['user.2025-01-01_12-00-00.tar.gz', 'user.2025-01-01_12-00-00.tar.gz', 'none', 'none', false],
            ['user.2025-01-01_12-00-00.tar.aes', 'user.2025-01-01_12-00-00.tar.aes', 'none', 'none', false],
        ];
        
        foreach ($testCases as [$filename, $expectedOriginal, $expectedCompression, $expectedEncryption, $expectedHasXbk]) {
            $result = Helper::parseXbkFilename($filename);
            $statusOriginal = $result['original'] === $expectedOriginal ? '✓' : '✗';
            $statusCompression = $result['compression'] === $expectedCompression ? '✓' : '✗';
            $statusEncryption = $result['encryption'] === $expectedEncryption ? '✓' : '✗';
            $statusHasXbk = $result['hasXbk'] === $expectedHasXbk ? '✓' : '✗';
            
            echo "  {$filename}:\n";
            echo "    {$statusOriginal} Original: {$result['original']} (expected: {$expectedOriginal})\n";
            echo "    {$statusCompression} Compression: {$result['compression']} (expected: {$expectedCompression})\n";
            echo "    {$statusEncryption} Encryption: {$result['encryption']} (expected: {$expectedEncryption})\n";
            echo "    {$statusHasXbk} HasXbk: " . ($result['hasXbk'] ? 'true' : 'false') . " (expected: " . ($expectedHasXbk ? 'true' : 'false') . ")\n";
        }
    }
    
    public static function testGetOriginalFilename(): void
    {
        echo "\nTesting getOriginalFilename():\n";
        
        $testCases = [
            ['user.2025-01-01_12-00-00.tar.xbk.gz.aes', 'user.2025-01-01_12-00-00.tar'],
            ['user.2025-01-01_12-00-00.tar.xbk.7z', 'user.2025-01-01_12-00-00.tar'],
            ['user.2025-01-01_12-00-00.tar.xbk', 'user.2025-01-01_12-00-00.tar'],
            ['user.2025-01-01_12-00-00.tar.gz', 'user.2025-01-01_12-00-00.tar.gz'], // legacy file
        ];
        
        foreach ($testCases as [$processedFile, $expected]) {
            $result = Helper::getOriginalFilename($processedFile);
            $status = $result === $expected ? '✓' : '✗';
            echo "  {$status} {$processedFile} => {$result}\n";
            if ($result !== $expected) {
                echo "    Expected: {$expected}\n";
            }
        }
    }
    
    public static function run(): void
    {
        echo "=== XBK Filename Processing Test ===\n\n";
        
        self::testCreateXbkFilename();
        self::testParseXbkFilename();
        self::testGetOriginalFilename();
        
        echo "\n=== Test Completed ===\n";
    }
}

// Run the test
XbkFilenameTest::run();
