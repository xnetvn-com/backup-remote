<?php

declare(strict_types=1);

namespace App\Exceptions;

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
