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

declare(strict_types=1);

use App\Utils\Helper;

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | The name of your application, which will be used in various places
    | throughout the system.
    |
    */
    'app_name' => $_ENV['APP_NAME'] ?? 'BackupRemote',

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configure the logging settings for your application. You can configure
    | the log level (e.g., 'debug', 'info', 'error') and the path to the
    | log file.
    |
    */
    'logging' => [
        'level' => Helper::env('LOG_LEVEL', 'info'),
        'path' => __DIR__ . '/../storage/logs/app.log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings to avoid overloading the server.
    |
    */
    'performance' => [
        // Set a backup window. Backups will only run within this time frame.
        // Use 24-hour format 'HH:MM'. Leave empty to disable.
        'allowed_start_time' => Helper::env('ALLOWED_START_TIME', '01:00'),
        'allowed_end_time'   => Helper::env('ALLOWED_END_TIME', '05:00'),

        // Maximum 1-minute average CPU load. If the load exceeds this value,
        // the backup will be aborted. Set to 0 to disable.
        'max_cpu_load' => (float) Helper::env('MAX_CPU_LOAD', 2.5),

        // Minimum percentage of free disk space required in the temporary directory.
        'min_disk_free_percent' => (int) Helper::env('MIN_DISK_FREE_PERCENT', 15),

        // PHP memory limit for the script. Examples: '256M', '1G'.
        'memory_limit' => Helper::env('MEMORY_LIMIT', '256M'),

        // Maximum execution time in seconds. 0 for no limit.
        'time_limit' => (int) Helper::env('TIME_LIMIT', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Archive Settings
    |--------------------------------------------------------------------------
    |
    | Configure settings related to archive creation and management.
    |
    */
    'archive' => [
        'enabled' => true,
        'path' => '/path/to/archive',
        'frequency' => 'daily',
    ],

    /*
    |--------------------------------------------------------------------------
    | HestiaCP Settings
    |--------------------------------------------------------------------------
    |
    | HestiaCP specific settings.
    |
    */
    'hestia' => [
        'path' => Helper::env('HESTIA_PATH', '/usr/local/hestia'),
    ],

    // ... other configurations ...
];
