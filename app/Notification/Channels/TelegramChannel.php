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
use Monolog\Logger;

class TelegramChannel
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
        return 'telegram';
    }

    public function send(string $level, string $subject, string $message, ?string $details = null): void
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
