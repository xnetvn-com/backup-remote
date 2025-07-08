<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

namespace App\Notification;

use App\Notification\Channels\EmailChannel;
use App\Notification\Channels\TelegramChannel;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Manages sending notifications through various channels.
 */

class NotificationManager
{
    private array $config;
    private LoggerInterface $logger;
    private array $channels = [];
    private AlertThrottler $throttler;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        // log NotificationManager initialization
        $this->logger->debug('NotificationManager initialized', ['channelsConfigured' => $config['notification']['channels'] ?? []]);
        $throttleConfig = $config['notification']['throttle'] ?? [];
        $this->throttler = new AlertThrottler($throttleConfig);
        $this->registerChannels();
    }

    /**
     * Registers notification channels based on the configuration.
     */

    private function registerChannels(): void
    {
        if (!empty($this->config['notification']['channels'])) {
            foreach ($this->config['notification']['channels'] as $name => $channelConfig) {
                if (empty($channelConfig['enabled'])) {
                    continue;
                }

                switch ($name) {
                    case 'email':
                        $this->channels[] = new EmailChannel($channelConfig, $this->logger);
                        break;
                    case 'telegram':
                        $this->channels[] = new TelegramChannel($channelConfig, $this->logger);
                        break;
                    default:
                        $this->logger->warning("Unknown notification channel: {$name}");
                }
            }
        }
    }

    /**
     * Sends a success notification.
     *
     * @param string $message The main message content.
     */

    public function sendSuccess(string $message): void
    {
        $this->send('info', 'Backup Successful', $message);
    }

    /**
     * Sends a failure notification.
     *
     * @param string $message The main message content.
     * @param string|null $details Optional details about the failure.
     */

    public function sendFailure(string $message, ?string $details = null): void
    {
        $this->send('error', 'Backup Failed', $message, $details);
    }

    /**
     * Sends an alert notification, respecting the throttle limit.
     *
     * @param string $subject The subject of the alert.
     * @param string|null $details Optional details for the alert.
     */

    public function sendAlert(string $subject, ?string $details = null): void
    {
        $channel = 'alert';
        if ($this->throttler->canSend($channel)) {
            $this->send('warning', $subject, $details ?? 'No details provided.');
            $this->throttler->markSent($channel);
        } else {
            $this->logger->info("Alert throttling is active. Skipping notification for: {$subject}");
        }
    }

    /**
     * Generic send method to dispatch notifications to all registered channels.
     *
     * @param string $level The notification level (e.g., 'info', 'error', 'warning').
     * @param string $subject The subject/title of the notification.
     * @param string $message The main message content.
     * @param string|null $details Optional extended details.
     */
    private function send(string $level, string $subject, string $message, ?string $details = null): void
    {
        $this->logger->debug('Notification send called', ['level' => $level, 'subject' => $subject, 'details' => $details]);
        // Only allow valid PSR-3 levels for logger
        $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $logLevel = in_array($level, $validLevels, true) ? $level : 'info';
        $this->logger->log($logLevel, $message, ['subject' => $subject, 'details' => $details]);
        foreach ($this->channels as $channel) {
            $channelIdentifier = get_class($channel);
            if ($this->throttler->canSend($channelIdentifier)) {
                try {
                    $this->logger->debug("Attempting to send notification via {$channelIdentifier}");
                    $channel->send($level, $subject, $message, $details);
                    $this->throttler->markSent($channelIdentifier);
                    $this->logger->info("Notification sent successfully via {$channelIdentifier}");
                } catch (Throwable $e) {
                    $this->logger->error("Failed to send notification via {$channelIdentifier}", [
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                $this->logger->info("Notification skipped for {$channelIdentifier} due to throttling.");
            }
        }
    }
}
