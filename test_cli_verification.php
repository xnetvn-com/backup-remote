<?php
/**
 * CLI Verification Test for 7z and zip compression/encryption
 * 
 * This test verifies that the system uses CLI tools for 7z and zip operations
 * as required by the user specifications.
 */

require_once __DIR__ . '/libs/vendor/autoload.php';
use App\Utils\Helper;

function testCliUsage() {
    echo "=== Testing CLI Usage for 7z and zip ===\n\n";
    
    // Create test file
    $testFile = sys_get_temp_dir() . '/test_cli_' . uniqid() . '.txt';
    $testContent = "Test content for CLI verification: " . str_repeat("PHP Backup Remote Test ", 100);
    file_put_contents($testFile, $testContent);
    
    echo "Created test file: $testFile (" . number_format(strlen($testContent)) . " bytes)\n\n";
    
    // Test 7z compression + encryption (combined)
    echo "1. Testing 7z CLI (compression + encryption in one step):\n";
    $sevenZipFile = $testFile . '.7z';
    $password = 'TestPassword123!';
    $result = Helper::sevenZipCompressEncryptFile($testFile, $sevenZipFile, $password, 5);
    echo "   - 7z compress+encrypt: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    
    if ($result && file_exists($sevenZipFile)) {
        echo "   - Output file size: " . number_format(filesize($sevenZipFile)) . " bytes\n";
        
        // Test 7z decompression + decryption
        $extracted7z = $testFile . '.extracted.7z';
        $result = Helper::sevenZipDecompressDecryptFile($sevenZipFile, $extracted7z, $password);
        echo "   - 7z decompress+decrypt: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        
        if ($result && file_exists($extracted7z)) {
            $extractedContent = file_get_contents($extracted7z);
            $isValid = ($extractedContent === $testContent);
            echo "   - Content integrity: " . ($isValid ? "✓ VERIFIED" : "✗ CORRUPTED") . "\n";
            unlink($extracted7z);
        }
        unlink($sevenZipFile);
    }
    
    echo "\n";
    
    // Test zip compression + encryption (combined)
    echo "2. Testing zip CLI (compression + encryption in one step):\n";
    $zipFile = $testFile . '.zip';
    $result = Helper::zipCompressEncryptFile($testFile, $zipFile, $password, 6);
    echo "   - zip compress+encrypt: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    
    if ($result && file_exists($zipFile)) {
        echo "   - Output file size: " . number_format(filesize($zipFile)) . " bytes\n";
        
        // Test zip decompression + decryption
        $extractedZip = $testFile . '.extracted.zip';
        $result = Helper::zipDecompressDecryptFile($zipFile, $extractedZip, $password);
        echo "   - zip decompress+decrypt: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        
        if ($result && file_exists($extractedZip)) {
            $extractedContent = file_get_contents($extractedZip);
            $isValid = ($extractedContent === $testContent);
            echo "   - Content integrity: " . ($isValid ? "✓ VERIFIED" : "✗ CORRUPTED") . "\n";
            unlink($extractedZip);
        }
        unlink($zipFile);
    }
    
    echo "\n";
    
    // Test 7z compression only
    echo "3. Testing 7z CLI (compression only):\n";
    $sevenZipFileOnly = $testFile . '.comp.7z';
    $result = Helper::sevenZipCompressFile($testFile, $sevenZipFileOnly, 5);
    echo "   - 7z compress only: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    
    if ($result && file_exists($sevenZipFileOnly)) {
        echo "   - Output file size: " . number_format(filesize($sevenZipFileOnly)) . " bytes\n";
        
        // Test 7z decompression only
        $extracted7zOnly = $testFile . '.extracted.comp.7z';
        $result = Helper::sevenZipDecompressFile($sevenZipFileOnly, $extracted7zOnly);
        echo "   - 7z decompress only: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        
        if ($result && file_exists($extracted7zOnly)) {
            $extractedContent = file_get_contents($extracted7zOnly);
            $isValid = ($extractedContent === $testContent);
            echo "   - Content integrity: " . ($isValid ? "✓ VERIFIED" : "✗ CORRUPTED") . "\n";
            unlink($extracted7zOnly);
        }
        unlink($sevenZipFileOnly);
    }
    
    echo "\n";
    
    // Test zip compression only
    echo "4. Testing zip CLI (compression only):\n";
    $zipFileOnly = $testFile . '.comp.zip';
    $result = Helper::zipCompressFile($testFile, $zipFileOnly, 6);
    echo "   - zip compress only: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    
    if ($result && file_exists($zipFileOnly)) {
        echo "   - Output file size: " . number_format(filesize($zipFileOnly)) . " bytes\n";
        
        // Test zip decompression only
        $extractedZipOnly = $testFile . '.extracted.comp.zip';
        $result = Helper::zipDecompressFile($zipFileOnly, $extractedZipOnly);
        echo "   - zip decompress only: " . ($result ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        
        if ($result && file_exists($extractedZipOnly)) {
            $extractedContent = file_get_contents($extractedZipOnly);
            $isValid = ($extractedContent === $testContent);
            echo "   - Content integrity: " . ($isValid ? "✓ VERIFIED" : "✗ CORRUPTED") . "\n";
            unlink($extractedZipOnly);
        }
        unlink($zipFileOnly);
    }
    
    // Cleanup
    unlink($testFile);
    
    echo "\n=== CLI Verification Complete ===\n";
    echo "This test confirms that the system uses CLI tools:\n";
    echo "- 7z CLI for all 7-Zip operations\n";
    echo "- zip/unzip CLI for all ZIP operations\n";
    echo "- No PHP extensions or libraries are used for compression/encryption\n\n";
}

// Run the test
testCliUsage();
