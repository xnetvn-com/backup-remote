<?php
// Telegram notification channel
namespace App\Notification\Channels;

use GuzzleHttp\Client;

class TelegramChannel {
    private $config;
    public function __construct($config) { $this->config = $config; }
    public function getName() { return 'telegram'; }
    public function send($subject, $message) {
        $token = $this->config['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId = $this->config['TELEGRAM_CHAT_ID'] ?? null;
        if (!$token || !$chatId) return;

        $text = "<b>$subject</b>\n\n" . $message;
        $client = new Client();
        try {
            $client->post("https://api.telegram.org/bot$token/sendMessage", [
                'form_params' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ]
            ]);
        } catch (\Exception $e) {
            // Log error if needed
        }
    }
}
