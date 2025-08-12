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
class HelperCompressionTest extends TestCase
{
    private function createTempFile(string $content = 'testdata'): string
    {
        $file = tempnam(sys_get_temp_dir(), 'cmp_');
        file_put_contents($file, $content);
        return $file;
    }

    /**
     * @dataProvider compressionProvider
     */
    public function test_compress_and_decompress_pipeline(string $method, int $level): void
    {
        $input = $this->createTempFile(str_repeat('abc123', 10000));
        $compressed = $input . ".{$method}";
        $decompressed = $input . ".out";
        $ok = false;
        switch ($method) {
            case 'gzip':
                $ok = Helper::gzipCompressFile($input, $compressed, $level);
                $this->assertTrue($ok);
                $ok = Helper::gzipDecompressFile($compressed, $decompressed);
                break;
            case 'bzip2':
                $ok = Helper::bzip2CompressFile($input, $compressed, $level);
                $this->assertTrue($ok);
                $ok = Helper::bzip2DecompressFile($compressed, $decompressed);
                break;
            case 'xz':
                $ok = Helper::xzCompressFile($input, $compressed, $level);
                $this->assertTrue($ok);
                $ok = Helper::xzDecompressFile($compressed, $decompressed);
                break;
            case 'zip':
                $ok = Helper::zipCompressFile($input, $compressed, $level);
                $this->assertTrue($ok);
                $ok = Helper::zipDecompressFile($compressed, $decompressed);
                break;
            case '7z':
                $ok = Helper::sevenZipCompressFile($input, $compressed, $level);
                $this->assertTrue($ok);
                $ok = Helper::sevenZipDecompressFile($compressed, $decompressed);
                break;
        }
        $this->assertTrue($ok, "Decompression failed for $method");
        $this->assertFileExists($decompressed);
        $this->assertSame(file_get_contents($input), file_get_contents($decompressed));
        @unlink($input);
        @unlink($compressed);
        @unlink($decompressed);
    }

    /**
     * @dataProvider allCompressionLevelsProvider
     */
    public function test_should_preserve_content_integrity_when_compress_and_decompress_all_methods_levels(string $method, int $level)
    {
        $content = random_bytes(512 * 1024) . str_repeat('xNetVN-Backup-Test', 1000);
        $input = tempnam(sys_get_temp_dir(), 'cmpint_');
        file_put_contents($input, $content);
        $compressed = $input . ".{$method}";
        $decompressed = $input . ".out";
        $ok = false;
        switch ($method) {
            case 'gzip':
                $ok = Helper::gzipCompressFile($input, $compressed, $level);
                $this->assertTrue($ok, 'gzip compress failed');
                $ok = Helper::gzipDecompressFile($compressed, $decompressed);
                break;
            case 'bzip2':
                $ok = Helper::bzip2CompressFile($input, $compressed, $level);
                $this->assertTrue($ok, 'bzip2 compress failed');
                $ok = Helper::bzip2DecompressFile($compressed, $decompressed);
                break;
            case 'xz':
                $ok = Helper::xzCompressFile($input, $compressed, $level);
                $this->assertTrue($ok, 'xz compress failed');
                $ok = Helper::xzDecompressFile($compressed, $decompressed);
                break;
            case 'zip':
                $ok = Helper::zipCompressFile($input, $compressed, $level);
                $this->assertTrue($ok, 'zip compress failed');
                $ok = Helper::zipDecompressFile($compressed, $decompressed);
                break;
            case '7z':
                $ok = Helper::sevenZipCompressFile($input, $compressed, $level);
                $this->assertTrue($ok, '7z compress failed');
                $ok = Helper::sevenZipDecompressFile($compressed, $decompressed);
                break;
        }
        $this->assertTrue($ok, "Decompression failed for $method");
        $this->assertFileExists($decompressed);
        $this->assertEquals(hash_file('sha256', $input), hash_file('sha256', $decompressed), "Hash mismatch for $method at level $level");
        @unlink($input);
        @unlink($compressed);
        @unlink($decompressed);
    }

    public function test_compress_decompress_with_xbk(): void
    {
        $input = $this->createTempFile(str_repeat('abc123', 10000));
        $xbk = \App\Utils\Helper::addXbkExtension($input);
        copy($input, $xbk);
        $compressed = $xbk . '.zip';
        $decompressed = $xbk . '.out';
        $ok = Helper::zipCompressFile($xbk, $compressed, 6);
        $this->assertTrue($ok, 'zip compress failed');
        $ok = Helper::zipDecompressFile($compressed, $decompressed);
        $this->assertTrue($ok, 'zip decompress failed');
        $this->assertFileExists($decompressed);
        $original = \App\Utils\Helper::removeXbkExtension($decompressed);
        rename($decompressed, $original);
        $this->assertEquals(hash_file('sha256', $input), hash_file('sha256', $original), 'Hash mismatch after decompress .xbk');
        @unlink($input); @unlink($xbk); @unlink($compressed); @unlink($original);
    }

    public function compressionProvider(): array
    {
        return [
            ['gzip', 6],
            ['bzip2', 6],
            ['xz', 6],
            ['zip', 6],
            ['7z', 5],
        ];
    }

    public function allCompressionLevelsProvider(): array
    {
        $levels = [1, 5, 9];
        $methods = ['gzip', 'bzip2', 'xz', 'zip', '7z'];
        $out = [];
        foreach ($methods as $m) {
            foreach ($levels as $l) {
                $out[] = [$m, $l];
            }
        }
        return $out;
    }
}
