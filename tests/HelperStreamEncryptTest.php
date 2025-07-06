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
 * Test class for Helper streaming encryption functionality.
 * @covers App\Utils\Helper
 */
class HelperStreamEncryptTest extends TestCase
{
    /**
     * @covers App\Utils\Helper::testEncryptDecryptLargeFile
     */
    public function test_encrypt_decrypt_large_file_streaming(): void
    {
        $result = Helper::testEncryptDecryptLargeFile();
        $this->assertTrue($result, 'Streaming encryption/decryption for large file failed.');
    }
}
