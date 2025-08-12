<?php

declare(strict_types=1);

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

namespace App\Exceptions;

/**
 * Archive-related exceptions for backup operations
 */
class ArchiveException extends BackupException
{
    public static function compressionFailed(string $method, string $error): self
    {
        return new self("Compression failed using method '{$method}': {$error}");
    }
    
    public static function extractionFailed(string $file, string $error): self
    {
        return new self("Extraction failed for file '{$file}': {$error}");
    }
    
    public static function invalidArchiveFormat(string $file): self
    {
        return new self("Invalid archive format for file: {$file}");
    }
    
    public static function corruptedArchive(string $file): self
    {
        return new self("Archive appears to be corrupted: {$file}");
    }
}

/**
 * System-related exceptions
 */
class SystemException extends BackupException
{
    public static function commandFailed(string $command, int $exitCode, string $error): self
    {
        return new self("System command failed: '{$command}' (exit code: {$exitCode}): {$error}");
    }
    
    public static function insufficientPermissions(string $path): self
    {
        return new self("Insufficient permissions to access: {$path}");
    }
    
    public static function diskSpaceInsufficient(string $path, int $required, int $available): self
    {
        $requiredMB = round($required / 1024 / 1024, 2);
        $availableMB = round($available / 1024 / 1024, 2);
        return new self("Insufficient disk space at '{$path}': required {$requiredMB}MB, available {$availableMB}MB");
    }
    
    public static function toolNotFound(string $tool): self
    {
        return new self("Required system tool not found: {$tool}");
    }
}

/**
 * Backup manager specific exceptions
 */
class BackupManagerException extends BackupException
{
    public static function invalidConfiguration(string $key, string $reason): self
    {
        return new self("Invalid configuration for '{$key}': {$reason}");
    }
    
    public static function operationTimeout(string $operation, int $timeoutSeconds): self
    {
        return new self("Operation '{$operation}' timed out after {$timeoutSeconds} seconds");
    }
    
    public static function backupProcessFailed(string $step, string $error): self
    {
        return new self("Backup process failed at step '{$step}': {$error}");
    }
    
    public static function sourcePathNotFound(string $path): self
    {
        return new self("Source path does not exist or is not accessible: {$path}");
    }
}
