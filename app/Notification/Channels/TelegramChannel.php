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

// Telegram notification channel
namespace App\Notification\Channels;

use GuzzleHttp\Client;

class TelegramChannel {
    private $config;
    public function __construct($config) { $this->config = $config; }
    public function getName() { return 'telegram'; }
    public function send($level, $subject, $message, $details = null) {
        $token = $this->config['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId = $this->config['TELEGRAM_CHAT_ID'] ?? null;
        if (!$token || !$chatId) return;

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
