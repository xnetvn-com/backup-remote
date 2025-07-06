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
 * @covers AppUtilsHelper
 */
class HelperEncryptFileEdgeTest extends TestCase
{
    /**
     * @dataProvider fileSizeProvider
     */
    public function testEncryptDecryptVariousSizes(int $size)
    {
        $testFile = sys_get_temp_dir() . '/test_edge_' . uniqid() . '.bin';
        $encFile = $testFile . '.xenc';
        $decFile = $testFile . '.dec';
        $password = 'EdgeTestPassword!';
        // Create random file
        $fp = fopen($testFile, 'wb');
        $written = 0;
        while ($written < $size) {
            $chunk = random_bytes(min(1048576, $size - $written));
            fwrite($fp, $chunk);
            $written += strlen($chunk);
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

    public function fileSizeProvider()
    {
        return [
            [0], [1], [16], [1024], [4096], [1048576]
        ];
    }
}
