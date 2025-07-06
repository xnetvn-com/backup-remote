<?php

declare(strict_types=1);

namespace App\Exceptions;

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
