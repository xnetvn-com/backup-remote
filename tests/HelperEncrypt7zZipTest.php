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
 * @covers App\Utils\Helper
 */
class HelperEncrypt7zZipTest extends TestCase
{
    /**
     * @dataProvider methodProvider
     */
    public function testEncryptDecryptWith7zAndZip(string $method)
    {
        $testFile = sys_get_temp_dir() . '/test_' . $method . '_' . uniqid() . '.bin';
        $encFile = $testFile . '.' . $method;
        $decFile = $testFile . '.out';
        $password = 'TestPassword7zZip!';
        file_put_contents($testFile, random_bytes(1024 * 1024)); // 1MB
        if ($method === '7z') {
            // Test without password
            $ok = Helper::sevenZipCompressFile($testFile, $encFile, 5);
            $this->assertTrue($ok, '7z compress failed');
            $ok = Helper::sevenZipDecompressFile($encFile, $decFile);
            $this->assertTrue($ok, '7z decompress failed');
            $this->assertFileExists($decFile);
            $this->assertEquals(hash_file('sha256', $testFile), hash_file('sha256', $decFile), 'Hash mismatch after decompress');
            unlink($encFile); unlink($decFile);
            // Test with password
            $ok = Helper::sevenZipCompressFile($testFile, $encFile, 5, $password);
            $this->assertTrue($ok, '7z compress with password failed');
            // Decompress with password
            $cmd = ['7z', 'e', '-so', "-p$password", $encFile];
            $out = fopen($decFile, 'wb');
            // Capture stderr to avoid noisy test output
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertTrue(is_resource($proc), '7z proc open failed');
            while (!feof($pipes[1])) {
                $data = fread($pipes[1], 8192);
                if ($data === false) break;
                fwrite($out, $data);
            }
            fclose($pipes[1]); fclose($out);
            proc_close($proc);
            $this->assertFileExists($decFile);
            $this->assertEquals(hash_file('sha256', $testFile), hash_file('sha256', $decFile), 'Hash mismatch after decompress (password)');
        } elseif ($method === 'zip') {
            // Test without password
            $ok = Helper::zipCompressFile($testFile, $encFile, 6);
            $this->assertTrue($ok, 'zip compress failed');
            $ok = Helper::zipDecompressFile($encFile, $decFile);
            $this->assertTrue($ok, 'zip decompress failed');
            $this->assertFileExists($decFile);
            $this->assertEquals(hash_file('sha256', $testFile), hash_file('sha256', $decFile), 'Hash mismatch after decompress');
            unlink($encFile); unlink($decFile);
            // Test with password
            $ok = Helper::zipCompressFile($testFile, $encFile, 6, $password);
            $this->assertTrue($ok, 'zip compress with password failed');
            // Decompress with password
            $cmd = ['unzip', '-P', $password, '-p', $encFile];
            $out = fopen($decFile, 'wb');
            // Capture stderr to avoid noisy test output
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertTrue(is_resource($proc), 'zip proc open failed');
            while (!feof($pipes[1])) {
                $data = fread($pipes[1], 8192);
                if ($data === false) break;
                fwrite($out, $data);
            }
            fclose($pipes[1]); fclose($out);
            proc_close($proc);
            $this->assertFileExists($decFile);
            $this->assertEquals(hash_file('sha256', $testFile), hash_file('sha256', $decFile), 'Hash mismatch after decompress (password)');
        }
        unlink($testFile);
        unlink($encFile);
        unlink($decFile);
    }

    public function methodProvider(): array
    {
        return [
            ['7z'],
            ['zip'],
        ];
    }

    /**
     * @dataProvider edgeCaseProvider
     */
    public function testEdgeCases7zZip(string $method, string $case, $content = null)
    {
        $testFile = sys_get_temp_dir() . '/test_edge_' . $method . '_' . $case . '_' . uniqid();
        $encFile = $testFile . '.' . $method;
        $decFile = $testFile . '.out';
        // 1. Empty file
        if ($case === 'empty') {
            file_put_contents($testFile, '');
            $ok = $method === '7z' ? Helper::sevenZipCompressFile($testFile, $encFile, 5) : Helper::zipCompressFile($testFile, $encFile, 6);
            $this->assertTrue($ok, "$method compress empty failed");
            $ok = $method === '7z' ? Helper::sevenZipDecompressFile($encFile, $decFile) : Helper::zipDecompressFile($encFile, $decFile);
            $this->assertTrue($ok, "$method decompress empty failed");
            $this->assertFileExists($decFile);
            $this->assertSame('', file_get_contents($decFile));
            unlink($testFile); unlink($encFile); unlink($decFile);
        }
        // 2. One-byte file
        elseif ($case === 'onebyte') {
            file_put_contents($testFile, 'A');
            $ok = $method === '7z' ? Helper::sevenZipCompressFile($testFile, $encFile, 5) : Helper::zipCompressFile($testFile, $encFile, 6);
            $this->assertTrue($ok, "$method compress 1byte failed");
            $ok = $method === '7z' ? Helper::sevenZipDecompressFile($encFile, $decFile) : Helper::zipDecompressFile($encFile, $decFile);
            $this->assertTrue($ok, "$method decompress 1byte failed");
            $this->assertFileExists($decFile);
            $this->assertSame('A', file_get_contents($decFile));
            unlink($testFile); unlink($encFile); unlink($decFile);
        }
        // 3. Decompress invalid file
        elseif ($case === 'invalid') {
            file_put_contents($encFile, 'not-a-valid-archive');
            $ok = $method === '7z' ? Helper::sevenZipDecompressFile($encFile, $decFile) : Helper::zipDecompressFile($encFile, $decFile);
            $this->assertFalse($ok, "$method should fail to decompress invalid file");
            if (file_exists($decFile)) unlink($decFile);
            unlink($encFile);
        }
        // 4. Decompress corrupted (truncated) file
        elseif ($case === 'corrupt') {
            // Create valid compressed file first
            file_put_contents($testFile, 'corruptdata');
            $ok = $method === '7z' ? Helper::sevenZipCompressFile($testFile, $encFile, 5) : Helper::zipCompressFile($testFile, $encFile, 6);
            $this->assertTrue($ok, "$method compress for corrupt failed");
            // Truncate compressed file
            $data = file_get_contents($encFile);
            file_put_contents($encFile, substr($data, 0, (int)(strlen($data)/2)));
            $ok = $method === '7z' ? Helper::sevenZipDecompressFile($encFile, $decFile) : Helper::zipDecompressFile($encFile, $decFile);
            $this->assertFalse($ok, "$method should fail to decompress corrupt file");
            if (file_exists($decFile)) unlink($decFile);
            unlink($testFile); unlink($encFile);
        }
        // 5. Decompress non-existent file
        elseif ($case === 'notfound') {
            $ok = $method === '7z' ? Helper::sevenZipDecompressFile($encFile, $decFile) : Helper::zipDecompressFile($encFile, $decFile);
            $this->assertFalse($ok, "$method should fail to decompress not found file");
        }
    }

    public function edgeCaseProvider(): array
    {
        return [
            ['7z', 'empty'],
            ['zip', 'empty'],
            ['7z', 'onebyte'],
            ['zip', 'onebyte'],
            ['7z', 'invalid'],
            ['zip', 'invalid'],
            ['7z', 'corrupt'],
            ['zip', 'corrupt'],
            ['7z', 'notfound'],
            ['zip', 'notfound'],
        ];
    }

    /**
     * @dataProvider encryptMethodProvider
     */
    public function test_should_preserve_content_integrity_when_encrypt_and_decrypt_7z_zip(string $method)
    {
        $content = random_bytes(256 * 1024) . 'xNetVN-Encrypt-Test';
        $testFile = sys_get_temp_dir() . '/test_encint_' . $method . '_' . uniqid() . '.bin';
        $encFile = $testFile . '.' . $method;
        $decFile = $testFile . '.out';
        file_put_contents($testFile, $content);
        if ($method === '7z') {
            $ok = Helper::sevenZipCompressFile($testFile, $encFile, 5);
            $this->assertTrue($ok, '7z compress failed');
            $ok = Helper::sevenZipDecompressFile($encFile, $decFile);
            $this->assertTrue($ok, '7z decompress failed');
        } elseif ($method === 'zip') {
            $ok = Helper::zipCompressFile($testFile, $encFile, 6);
            $this->assertTrue($ok, 'zip compress failed');
            $ok = Helper::zipDecompressFile($encFile, $decFile);
            $this->assertTrue($ok, 'zip decompress failed');
        }
        $this->assertFileExists($decFile);
        $this->assertEquals(hash_file('sha256', $testFile), hash_file('sha256', $decFile), 'Hash mismatch after decrypt');
        unlink($testFile);
        unlink($encFile);
        unlink($decFile);
    }

    public function encryptMethodProvider(): array
    {
        return [
            ['7z'],
            ['zip'],
        ];
    }

    public function test_encrypt_decrypt_with_xbk_7z_zip(): void
    {
        foreach (["7z", "zip"] as $method) {
            $testFile = sys_get_temp_dir() . '/test_xbk_' . $method . '_' . uniqid() . '.bin';
            $xbk = \App\Utils\Helper::addXbkExtension($testFile);
            file_put_contents($testFile, random_bytes(1024 * 128));
            copy($testFile, $xbk);
            $encFile = $xbk . '.' . $method;
            $decFile = $xbk . '.out';
            if ($method === '7z') {
                $ok = Helper::sevenZipCompressFile($xbk, $encFile, 5);
                $this->assertTrue($ok, '7z compress failed');
                $ok = Helper::sevenZipDecompressFile($encFile, $decFile);
                $this->assertTrue($ok, '7z decompress failed');
            } else {
                $ok = Helper::zipCompressFile($xbk, $encFile, 6);
                $this->assertTrue($ok, 'zip compress failed');
                $ok = Helper::zipDecompressFile($encFile, $decFile);
                $this->assertTrue($ok, 'zip decompress failed');
            }
            $this->assertFileExists($decFile);
            $original = \App\Utils\Helper::removeXbkExtension($decFile);
            rename($decFile, $original);
            $this->assertEquals(hash_file('sha256', $testFile), hash_file('sha256', $original), 'Hash mismatch after decompress .xbk');
            @unlink($testFile); @unlink($xbk); @unlink($encFile); @unlink($original);
        }
    }
}
