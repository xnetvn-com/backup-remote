<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Utils\Logger;

/**
 * @covers AppUtilsLogger
 */
class LoggerTest extends TestCase
{
    public function test_should_log_info_message(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::info('Test info');
    }

    public function test_should_log_error_message(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::error('Test error');
    }
}
