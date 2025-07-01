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

class EmailChannel
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getName()
    {
        return 'email';
    }

    public function send($level, $subject, $message, $details = null)
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['EMAIL_SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['EMAIL_SMTP_USER'];
            $mail->Password = $this->config['EMAIL_SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom($this->config['EMAIL_SMTP_USER']);
            $mail->addAddress($this->config['ADMIN_EMAIL']);
            $mail->Subject = $subject;
            $mail->Body = $message . ($details ? "\n\n" . $details : '');
            $mail->send();
        } catch (Exception $e) {
            // Log error if needed
        }
    }
}
