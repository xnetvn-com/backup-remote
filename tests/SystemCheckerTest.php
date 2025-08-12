<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */
use PHPUnit\Framework\TestCase;
use App\System\SystemChecker;
use Psr\Log\LoggerInterface;

/**
 * @covers App\System\SystemChecker
 */
class SystemCheckerTest extends TestCase
{
    public function test_runChecks_should_not_throw_with_valid_config()
    {
        $config = [
            'performance' => [
                'allowed_start_time' => '00:00',
                'allowed_end_time' => '23:59',
                'max_cpu_load' => 100.0,
                'min_disk_free_percent' => 0,
            ],
            'local' => [
                'temp_dir' => sys_get_temp_dir(),
            ],
        ];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method($this->anything())->willReturn(null);
        /** @var LoggerInterface $logger */
        $checker = new SystemChecker($config, $logger);
        $this->expectNotToPerformAssertions();
        $checker->runChecks();
    }
}
