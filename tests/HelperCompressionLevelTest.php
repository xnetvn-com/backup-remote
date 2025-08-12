<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

use PHPUnit\Framework\TestCase;
use App\Utils\Helper;

/**
 * @covers App\Utils\Helper
 */
class HelperCompressionLevelTest extends TestCase
{
    /**
     * @dataProvider compressionLevelProvider
     */
    public function testNormalizeCompressionLevel($method, $input, $expected)
    {
        $result = Helper::normalizeCompressionLevel($method, $input);
        $this->assertSame($expected, $result);
    }

    public function compressionLevelProvider()
    {
        return [
            // gzip
            ['gzip', 0, 1],
            ['gzip', 1, 1],
            ['gzip', 5, 5],
            ['gzip', 9, 9],
            ['gzip', 10, 9],
            ['gzip', null, 1],
            // zstd
            ['zstd', 0, 1],
            ['zstd', 1, 1],
            ['zstd', 19, 19],
            ['zstd', 22, 22],
            ['zstd', 23, 22],
            ['zstd', null, 19],
            // bzip2
            ['bzip2', 0, 1],
            ['bzip2', 1, 1],
            ['bzip2', 9, 9],
            ['bzip2', 10, 9],
            ['bzip2', null, 1],
            // xz
            ['xz', -1, 0],
            ['xz', 0, 0],
            ['xz', 6, 6],
            ['xz', 9, 9],
            ['xz', 10, 9],
            ['xz', null, 6],
            // none
            ['none', 5, null],
            ['none', null, null],
            // fallback
            ['unknown', 5, null],
            // zip
            ['zip', -1, 0],
            ['zip', 0, 0],
            ['zip', 6, 6],
            ['zip', 9, 9],
            ['zip', 10, 9],
            ['zip', null, 6],
            // 7z
            ['7z', 0, 1],
            ['7z', 1, 1],
            ['7z', 5, 5],
            ['7z', 9, 9],
            ['7z', 10, 9],
            ['7z', null, 5],
        ];
    }
}
