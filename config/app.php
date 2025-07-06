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
        'allowed_start_time' => Helper::env('ALLOWED_START_TIME', ''),
        'allowed_end_time'   => Helper::env('ALLOWED_END_TIME', ''),

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
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notification channels for backup status alerts. Multiple
    | channels can be enabled simultaneously.
    |
    */
    'notification' => [
        'channels' => [
            'email' => [
                'enabled' => !empty(Helper::env('EMAIL_SMTP_HOST')),
                'smtp_host' => Helper::env('EMAIL_SMTP_HOST'),
                'smtp_port' => (int) Helper::env('EMAIL_SMTP_PORT', 587),
                'smtp_user' => Helper::env('EMAIL_SMTP_USER'),
                'smtp_pass' => Helper::env('EMAIL_SMTP_PASS'),
                'smtp_encryption' => Helper::env('EMAIL_SMTP_ENCRYPTION', 'tls'),
                'admin_email' => Helper::env('ADMIN_EMAIL'),
            ],
            'telegram' => [
                'enabled' => !empty(Helper::env('TELEGRAM_BOT_TOKEN')),
                'bot_token' => Helper::env('TELEGRAM_BOT_TOKEN'),
                'chat_id' => Helper::env('TELEGRAM_CHAT_ID'),
            ],
            'webhooks' => [
                'discord' => Helper::env('DISCORD_WEBHOOK'),
                'slack' => Helper::env('SLACK_WEBHOOK'),
                'teams' => Helper::env('TEAMS_WEBHOOK'),
                'google_chat' => Helper::env('GOOGLE_CHAT_WEBHOOK'),
            ],
        ],
        'throttle' => [
            'interval_minutes' => (int) Helper::env('NOTIFY_INTERVAL_MINUTES', 180),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Settings
    |--------------------------------------------------------------------------
    |
    | Configure local filesystem paths and environment settings.
    |
    */
    'local' => [
        'temp_dir' => Helper::env('TMP_DIR', sys_get_temp_dir()),
        'environment' => Helper::env('APP_ENV', 'production'),
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
    | Backup Directories
    |--------------------------------------------------------------------------
    |
    | The directories where all backup data will be stored. Supports multiple
    | directories (comma-separated in BACKUP_DIRS). Make sure all paths are
    | writable and have sufficient space for backup operations.
    |
    */
    'backup_dirs' => array_map('trim', explode(',', Helper::env('BACKUP_DIRS', '/backup'))),

    /*
    |--------------------------------------------------------------------------
    | Remote Storage Settings (Multi-remote)
    |--------------------------------------------------------------------------
    |
    | Configure multiple remote storage backends for backup. Each remote is an
    | array with a 'driver' key and its config. If REMOTE_DRIVER is set, only that
    | driver is used. Otherwise, all remotes detected from env will be used.
    |
    */
    'remotes' => Helper::env('REMOTE_DRIVER')
        ? [[
            'driver' => Helper::env('REMOTE_DRIVER'),
            // Optionally, add config mapping here if needed
        ]]
        : Helper::detectAllRemotes(),

    /*
    |--------------------------------------------------------------------------
    | Rotation Settings
    |--------------------------------------------------------------------------
    |
    | Configure backup rotation/retention policies. You can enable/disable rotation
    | and set the number of latest backups to keep per user. Override via env if needed.
    |
    */
    'rotation' => [
        'enabled' => Helper::env('ROTATION_ENABLED', true),
        'policies' => [
            'keep_latest' => (int) Helper::env('ROTATION_KEEP_LATEST', 7),
        ],
    ],
];
