<?php

declare(strict_types=1);

namespace App\Exceptions;

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
