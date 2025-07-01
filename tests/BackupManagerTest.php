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

class BackupManagerTest extends TestCase
{
    public function test_should_instantiate_backup_manager_with_valid_dependencies()
    {
        $config = [
            'backup_path' => '/tmp',
            // ... add more config as needed
        ];
        $logger = $this->createMock(LoggerInterface::class);
        /** @var NotificationManager&\PHPUnit\Framework\MockObject\MockObject $notificationManager */
        $notificationManager = $this->getMockBuilder(NotificationManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $backupManager = new BackupManager($config, $logger, $notificationManager);
        $this->assertInstanceOf(BackupManager::class, $backupManager);
    }
    // TODO: Add more tests for run(), error handling, etc.
}
