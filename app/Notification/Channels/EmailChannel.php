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

// Email notification channel
namespace App\Notification\Channels;

// Note: You might need to run `composer require phpmailer/phpmailer` in libs/
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailChannel {
    private $config;
    public function __construct($config) { $this->config = $config; }
    public function getName() { return 'email'; }
    public function send($level, $subject, $message, $details = null) {
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
