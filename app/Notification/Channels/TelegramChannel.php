<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

// Telegram notification channel
namespace App\Notification\Channels;

use GuzzleHttp\Client;

class TelegramChannel
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getName()
    {
        return 'telegram';
    }

    public function send($level, $subject, $message, $details = null)
    {
        $token = $this->config['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId = $this->config['TELEGRAM_CHAT_ID'] ?? null;
        if (!$token || !$chatId) {
            return;
        }

        $text = "<b>$subject</b>\n\n" . $message . ($details ? "\n\n" . $details : '');
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
