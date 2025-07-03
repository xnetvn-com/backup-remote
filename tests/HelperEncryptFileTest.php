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

class HelperEncryptFileTest extends TestCase
{
    public function testEncryptDecryptLargeFile()
    {
        $testFile = sys_get_temp_dir() . '/test_largefile_' . uniqid() . '.bin';
        $encFile = $testFile . '.xenc';
        $decFile = $testFile . '.dec';
        $password = 'TestPassword123!';
        $size = 2 * 1024 * 1024; // 2MB instead of 50MB to speed up the test
        // Create random file
        $fp = fopen($testFile, 'wb');
        if (!$fp) $this->fail('Cannot create test file');
        for ($written = 0; $written < $size; $written += 1048576) {
            $chunk = random_bytes(min(1048576, $size - $written));
            fwrite($fp, $chunk);
        }
        fclose($fp);
        $this->assertTrue(Helper::encryptFile($testFile, $encFile, $password), 'Encrypt failed');
        $this->assertTrue(Helper::decryptFile($encFile, $decFile, $password), 'Decrypt failed');
        $this->assertFileExists($decFile);
        $this->assertEquals(hash_file('sha256', $testFile), hash_file('sha256', $decFile), 'Hash mismatch after decrypt');
        unlink($testFile);
        unlink($encFile);
        unlink($decFile);
    }
}
