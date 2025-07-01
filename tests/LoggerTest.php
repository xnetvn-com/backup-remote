<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */
use PHPUnit\Framework\TestCase;
use App\Utils\Logger;

class LoggerTest extends TestCase
{
    public function test_should_log_info_message()
    {
        // Gọi hàm log, không có exception là pass (Monolog sẽ ghi file)
        $this->expectNotToPerformAssertions();
        Logger::info('Test info');
    }
    public function test_should_log_error_message()
    {
        $this->expectNotToPerformAssertions();
        Logger::error('Test error');
    }
}
