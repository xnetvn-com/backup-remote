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
}
