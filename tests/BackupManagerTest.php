<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Backup\BackupManager;
use Psr\Log\LoggerInterface;
use App\Notification\NotificationManager;

/**
 * Test class for BackupManager functionality.
 * @covers App\Backup\BackupManager
 */
class BackupManagerTest extends TestCase
{
    /**
     * @covers App\Backup\BackupManager::__construct
     */
    public function test_should_instantiate_backup_manager_with_valid_dependencies(): void
    {
        $config = [
            'backup_dirs' => ['/tmp'],
            // ... add more config as needed
        ];
        /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'])
            ->getMock();
        /** @var NotificationManager&\PHPUnit\Framework\MockObject\MockObject $notificationManager */
        $notificationManager = $this->getMockBuilder(NotificationManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendAlert'])
            ->getMock();
        $backupManager = new BackupManager($config, $logger, $notificationManager);
        $this->assertInstanceOf(BackupManager::class, $backupManager);
    }

    /**
     * @covers App\Backup\BackupManager::__construct
     */
    public function test_should_throw_exception_when_config_is_missing_backup_dirs(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/backup dir/i');
        $config = [];
        /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'])
            ->getMock();
        /** @var NotificationManager&\PHPUnit\Framework\MockObject\MockObject $notificationManager */
        $notificationManager = $this->getMockBuilder(NotificationManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendAlert'])
            ->getMock();
        new BackupManager($config, $logger, $notificationManager);
    }

    /**
     * @covers App\Backup\BackupManager::run
     */
    public function test_should_log_and_alert_when_missing_remote_driver(): void
    {
        // Create a temporary directory with backup files to simulate users
        $tempDir = sys_get_temp_dir() . '/test_backup_' . uniqid();
        mkdir($tempDir, 0755, true);
        // Create backup files to trigger __root__ user logic
        file_put_contents($tempDir . '/backup.tar.gz', 'dummy backup content');
        
        $config = [
            'backup_dirs' => [$tempDir],
            // deliberately missing 'remotes' key
        ];
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'])
            ->getMock();
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('remote'));
        $notificationManager = $this->getMockBuilder(NotificationManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendAlert'])
            ->getMock();
        $notificationManager->expects($this->once())
            ->method('sendAlert');
        $backupManager = new BackupManager($config, $logger, $notificationManager);
        $backupManager->run();
        
        // Cleanup
        unlink($tempDir . '/backup.tar.gz');
        rmdir($tempDir);
    }

    /**
     * @covers App\Backup\BackupManager::cleanupTmpDir
     */
    public function test_should_cleanup_tmp_dir_remove_files(): void
    {
        $tmpDir = sys_get_temp_dir() . '/test_backup_tmp_' . uniqid();
        mkdir($tmpDir, 0700, true);
        $file1 = $tmpDir . '/file1.txt';
        $file2 = $tmpDir . '/file2.log';
        file_put_contents($file1, 'test1');
        file_put_contents($file2, 'test2');
        $config = [
            'backup_dirs' => ['/tmp'],
        ];
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'])
            ->getMock();
        $notificationManager = $this->getMockBuilder(NotificationManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendAlert'])
            ->getMock();
        $backupManager = new BackupManager($config, $logger, $notificationManager);
        $reflection = new \ReflectionClass($backupManager);
        $cleanupMethod = $reflection->getMethod('cleanupTmpDir');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($backupManager, $tmpDir, false);
        $this->assertFileDoesNotExist($file1);
        $this->assertFileDoesNotExist($file2);
        @rmdir($tmpDir);
    }
}
