#!/usr/bin/env php
<?php
/**
 * Test script to verify .xbk filename logic
 */

require_once __DIR__ . '/libs/vendor/autoload.php';
use App\Utils\Helper;

echo "=== Testing .xbk filename logic ===\n\n";

// Test cases for different compression/encryption combinations
$testCases = [
    // Combined methods
    ['gzip', 'aes', 'file.tar', 'file.tar.xbk.gz.aes'],
    ['7z', '7z', 'file.tar', 'file.tar.xbk.7z'],
    ['zip', 'zip', 'file.tar', 'file.tar.xbk.zip'],
    ['zstd', 'aes', 'file.tar', 'file.tar.xbk.zst.aes'],
    ['gzip', 'gpg', 'file.tar', 'file.tar.xbk.gz.gpg'],
    ['none', 'aes', 'file.tar', 'file.tar.xbk.aes'],
    ['gzip', 'none', 'file.tar', 'file.tar.xbk.gz'],
    ['none', 'none', 'file.tar', 'file.tar.xbk'],
];

echo "1. Testing createXbkFilename:\n";
foreach ($testCases as $i => [$comp, $enc, $input, $expected]) {
    $result = Helper::createXbkFilename($input, $comp, $enc);
    $status = ($result === $expected) ? '✅ PASS' : '❌ FAIL';
    echo sprintf("   %d. %s + %s: %s -> %s [%s]\n", 
        $i + 1, $comp, $enc, $input, $result, $status);
    if ($result !== $expected) {
        echo sprintf("      Expected: %s\n", $expected);
    }
}

echo "\n2. Testing parseXbkFilename:\n";
foreach ($testCases as $i => [$expectedComp, $expectedEnc, $originalFile, $xbkFile]) {
    $result = Helper::parseXbkFilename($xbkFile);
    
    // Normalize comparison values (allow both full and short forms)
    $compMatch = ($result['compression'] === $expectedComp || 
                  ($expectedComp === 'gzip' && $result['compression'] === 'gz') ||
                  ($expectedComp === 'zstd' && $result['compression'] === 'zst') ||
                  ($expectedComp === '7z' && $result['compression'] === '7zip'));
    $encMatch = ($result['encryption'] === $expectedEnc || 
                 ($expectedEnc === '7z' && $result['encryption'] === '7zip'));
    $origMatch = ($result['original'] === $originalFile);
    $hasXbkMatch = $result['hasXbk'] === true;
    
    $status = ($compMatch && $encMatch && $origMatch && $hasXbkMatch) ? '✅ PASS' : '❌ FAIL';
    echo sprintf("   %d. %s: comp=%s, enc=%s, orig=%s, xbk=%s [%s]\n", 
        $i + 1, $xbkFile, $result['compression'], $result['encryption'], 
        $result['original'], $result['hasXbk'] ? 'yes' : 'no', $status);
    
    if (!$compMatch || !$encMatch || !$origMatch || !$hasXbkMatch) {
        echo sprintf("      Expected: comp=%s, enc=%s, orig=%s, xbk=yes\n", 
            $expectedComp, $expectedEnc, $originalFile);
    }
}

echo "\n3. Testing round-trip (create -> parse):\n";
foreach ($testCases as $i => [$comp, $enc, $input, $expected]) {
    $created = Helper::createXbkFilename($input, $comp, $enc);
    $parsed = Helper::parseXbkFilename($created);
    
    $compMatch = ($parsed['compression'] === $comp || 
                  ($comp === 'gzip' && $parsed['compression'] === 'gz') ||
                  ($comp === 'zstd' && $parsed['compression'] === 'zst') ||
                  ($comp === '7z' && $parsed['compression'] === '7zip'));
    $encMatch = ($parsed['encryption'] === $enc || 
                 ($enc === '7z' && $parsed['encryption'] === '7zip'));
    $origMatch = ($parsed['original'] === $input);
    
    $status = ($compMatch && $encMatch && $origMatch) ? '✅ PASS' : '❌ FAIL';
    echo sprintf("   %d. %s + %s -> %s [%s]\n", $i + 1, $comp, $enc, $input, $status);
    
    if (!$compMatch || !$encMatch || !$origMatch) {
        echo sprintf("      Created: %s\n", $created);
        echo sprintf("      Parsed: comp=%s, enc=%s, orig=%s\n", 
            $parsed['compression'], $parsed['encryption'], $parsed['original']);
    }
}

echo "\n=== Test completed ===\n";
