<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// Logging. Configures and provides a Monolog instance.
namespace App\Utils;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static ?MonologLogger $logger = null;

    public static function getLogger(): MonologLogger
    {
        if (self::$logger === null) {
            self::$logger = new MonologLogger('app');
            $logFile = __DIR__ . '/../../storage/logs/app.log';
            $logLevel = $_ENV['LOG_LEVEL'] ?? 'INFO';

            $handler = new StreamHandler($logFile, MonologLogger::toMonologLevel($logLevel));
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                null,
                true,
                true
            ));
            self::$logger->pushHandler($handler);
        }
        return self::$logger;
    }
    public static function info($msg, $context = []) {
        self::getLogger()->info($msg, $context);
    }
    public static function error($msg, $context = []) {
        self::getLogger()->error($msg, $context);
    }
}
