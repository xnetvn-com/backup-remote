<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

// Email notification channel
namespace App\Notification\Channels;

// Note: You might need to run `composer require phpmailer/phpmailer` in libs/
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Monolog\Logger;

class EmailChannel
{
    private array $config;
    private Logger $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'email';
    }

    public function send(string $level, string $subject, string $message, ?string $details = null): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['EMAIL_SMTP_HOST'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['EMAIL_SMTP_USER'] ?? '';
            $mail->Password = $this->config['EMAIL_SMTP_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom($this->config['EMAIL_SMTP_USER'] ?? 'noreply@example.com');
            $mail->addAddress($this->config['ADMIN_EMAIL'] ?? 'admin@example.com');
            $mail->Subject = $subject;
            $mail->Body = $message . ($details ? "\n\n" . $details : '');
            $mail->send();
        } catch (Exception $e) {
            // Log error if needed
        }
    }
}
