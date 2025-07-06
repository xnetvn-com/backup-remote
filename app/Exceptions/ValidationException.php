<?php

declare(strict_types=1);

namespace App\Exceptions;

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
