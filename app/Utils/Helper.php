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

class Helper
{
    /**
     * Formats size from bytes to KB/MB/GB.
     */

    public static function formatSize($bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Checks if the current time is within the allowed backup window.
     */

    public static function isAllowedTime($start = '01:00', $end = '06:00'): bool
    {
        $now = date('H:i');
        return ($now >= $start && $now <= $end);
    }

    /**
     * Extracts username from backup filename (format: user.YYYY-MM-DD_HH-MM-SS.ext)
     */

    public static function extractUser($filename): ?string
    {
        if (preg_match('/^([a-zA-Z0-9_.-]+)\.\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\./', $filename, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Gets the value of an environment variable.
     *
     * @param string $key The environment variable key.
     * @param mixed|null $default The default value to return if the key is not found.
     * @return mixed The value of the environment variable or the default value.
     */

    public static function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? false;

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }

    /**
     * Creates a unique archive name.
     */

    public static function createArchiveName($prefix = 'backup', $suffix = 'zip'): string
    {
        $date = date('Y-m-d_H-i-s');
        return "{$prefix}.{$date}.{$suffix}";
    }
}
