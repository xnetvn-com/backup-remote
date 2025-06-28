<?php
// Email notification channel
namespace App\Notification\Channels;

// Note: You might need to run `composer require phpmailer/phpmailer` in libs/
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailChannel {
    private $config;
    public function __construct($config) { $this->config = $config; }
    public function getName() { return 'email'; }
    public function send($subject, $message) {
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
            $mail->Body = $message;
            $mail->send();
        } catch (Exception $e) {
            // Log error if needed
        }
    }
}
