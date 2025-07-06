<?php

declare(strict_types=1);

namespace App\Exceptions;

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
