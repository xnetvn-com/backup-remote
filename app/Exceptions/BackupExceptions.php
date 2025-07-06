<?php

/**
 * Security Enhancement: Custom Exception Hierarchy
 * Created as part of GIAI ĐOẠN 2 security improvements
 */

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Base exception for all backup-related errors
 */
class BackupException extends \Exception
{
    protected array $context = [];

    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}

/**
 * Storage-related exceptions (upload, download, remote operations)
 */
class StorageException extends BackupException
{
    public const ERROR_CONNECTION_FAILED = 1001;
    public const ERROR_AUTHENTICATION_FAILED = 1002;
    public const ERROR_UPLOAD_FAILED = 1003;
    public const ERROR_DOWNLOAD_FAILED = 1004;
    public const ERROR_FILE_NOT_FOUND = 1005;
    public const ERROR_INSUFFICIENT_SPACE = 1006;
}

/**
 * Encryption and compression exceptions
 */
class EncryptionException extends BackupException
{
    public const ERROR_ENCRYPTION_FAILED = 2001;
    public const ERROR_DECRYPTION_FAILED = 2002;
    public const ERROR_KEY_INVALID = 2003;
    public const ERROR_COMPRESSION_FAILED = 2004;
    public const ERROR_DECOMPRESSION_FAILED = 2005;
    public const ERROR_ARCHIVE_CORRUPTED = 2006;
}

/**
 * Configuration and validation exceptions
 */
class ValidationException extends BackupException
{
    public const ERROR_INVALID_CONFIG = 3001;
    public const ERROR_MISSING_DEPENDENCY = 3002;
    public const ERROR_INVALID_PATH = 3003;
    public const ERROR_PERMISSION_DENIED = 3004;
    public const ERROR_INVALID_CREDENTIALS = 3005;
}

/**
 * System and resource exceptions
 */
class SystemException extends BackupException
{
    public const ERROR_INSUFFICIENT_MEMORY = 4001;
    public const ERROR_INSUFFICIENT_DISK_SPACE = 4002;
    public const ERROR_CPU_LOAD_TOO_HIGH = 4003;
    public const ERROR_BACKUP_WINDOW_CLOSED = 4004;
    public const ERROR_PROCESS_LOCK_FAILED = 4005;
}

/**
 * Notification system exceptions
 */
class NotificationException extends BackupException
{
    public const ERROR_SEND_FAILED = 5001;
    public const ERROR_CHANNEL_UNAVAILABLE = 5002;
    public const ERROR_THROTTLED = 5003;
    public const ERROR_INVALID_RECIPIENT = 5004;
}
