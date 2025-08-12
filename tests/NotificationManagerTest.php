<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */
use PHPUnit\Framework\TestCase;
use App\Notification\NotificationManager;
use Psr\Log\LoggerInterface;

/**
 * @covers App\Notification\NotificationManager
 */
class NotificationManagerTest extends TestCase
{
    public function test_sendSuccess_should_not_throw()
    {
        $config = [
            'notification' => [
                'channels' => [],
                'throttle' => [],
            ],
        ];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method($this->anything())->willReturn(null);
        /** @var LoggerInterface $logger */
        $manager = new NotificationManager($config, $logger);
        $this->expectNotToPerformAssertions();
        $manager->sendSuccess('Backup completed');
    }
}
