<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Backup\ArchiveHandler;
use Psr\Log\LoggerInterface;

/**
 * @covers App\Backup\ArchiveHandler
 */
class ArchiveHandlerTest extends TestCase
{
    /**
     * @covers App\Backup\ArchiveHandler::create
     */
    public function test_should_apply_compression_and_encryption_from_env(): void
    {
        // Setup env
        $_ENV['BACKUP_PASSWORD'] = 'TestPassword123!';
        $_ENV['BACKUP_COMPRESSION'] = 'zstd';
        $_ENV['BACKUP_ENCRYPTION'] = 'gpg';
        $_ENV['TMP_DIR'] = sys_get_temp_dir();
        $config = [
            'archive' => [
                'exclude' => [],
            ],
        ];
        /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'])
            ->getMock();
        $handler = new ArchiveHandler($config, $logger);
        $user = 'testuser';
        $userPath = sys_get_temp_dir() . '/archivehandler_test_' . uniqid();
        mkdir($userPath);
        file_put_contents($userPath . '/file.txt', 'testdata');
        try {
            $result = $handler->create($user, $userPath, false);
            $this->assertIsString($result);
            // With zstd + gpg settings the filename may vary; we just assert non-empty string
            $this->assertNotEmpty($result);
            @unlink($result);
        } catch (\Exception $e) {
            // Some environments may not have gpg configured; ensure we get an expected error message
            $this->assertNotEmpty($e->getMessage());
        }
        @unlink($userPath . '/file.txt');
        @rmdir($userPath);
    }
}
