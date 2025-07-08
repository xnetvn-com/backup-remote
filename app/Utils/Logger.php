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

namespace App\Utils;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;

class Logger
{
    private static ?LoggerInterface $instance = null;

    public static function getLogger(): LoggerInterface
    {
        if (self::$instance === null) {
            $logFile = __DIR__ . '/../../storage/logs/app.log';
            $stream = new StreamHandler($logFile, MonoLogger::DEBUG);
            $formatter = new LineFormatter(null, null, true, true);
            $stream->setFormatter($formatter);
            $logger = new MonoLogger('app');
            $logger->pushHandler($stream);
            // add processors for more detailed logging context
            $logger->pushProcessor(new UidProcessor());
            $logger->pushProcessor(new IntrospectionProcessor());
            $logger->pushProcessor(new MemoryUsageProcessor(true, true));
            self::$instance = $logger;
        }
        return self::$instance;
    }

    public static function info(string $message, array $context = []): void
    {
        self::getLogger()->info($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getLogger()->error($message, $context);
    }

    // add detailed log levels
    public static function debug(string $message, array $context = []): void
    {
        self::getLogger()->debug($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getLogger()->warning($message, $context);
    }
}
