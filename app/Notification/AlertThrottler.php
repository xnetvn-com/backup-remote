<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

// Handles notification anti-spam logic
namespace App\Notification;

class AlertThrottler
{
    private $interval;
    private $stateFile;
    private $state = [];

    public function __construct($config)
    {
        $this->interval = $config['NOTIFY_INTERVAL_MINUTES'] ?? 180;
        $this->stateFile = \App\Utils\Helper::getTmpDir() . '/backup_notify_state.json';
        if (file_exists($this->stateFile)) {
            $this->state = json_decode(file_get_contents($this->stateFile), true) ?: [];
        }
    }

    public function canSend(string $channel): bool
    {
        $last = $this->state[$channel] ?? 0;
        return (time() - $last) > $this->interval * 60;
    }

    /**
     * Mark that a notification was sent on a channel (thread-safe implementation)
     * SECURITY FIX: Added proper file locking to prevent race conditions
     */
    public function markSent(string $channel): void
    {
        $this->state[$channel] = time();
        $this->writeStateFileSafely();
    }

    /**
     * Thread-safe method to write state file
     * Uses atomic write with temporary file to prevent corruption
     */
    private function writeStateFileSafely(): void
    {
        $tempFile = $this->stateFile . '.tmp.' . getmypid();
        $data = json_encode($this->state, JSON_THROW_ON_ERROR);
        
        try {
            // Write to temporary file first
            if (file_put_contents($tempFile, $data, LOCK_EX) === false) {
                throw new \RuntimeException('Failed to write throttle state to temporary file');
            }
            
            // Atomic move - this operation is atomic on most filesystems
            if (!rename($tempFile, $this->stateFile)) {
                @unlink($tempFile); // Cleanup on failure
                throw new \RuntimeException('Failed to update throttle state file atomically');
            }
            
        } catch (\Throwable $e) {
            // Cleanup temp file if it exists
            @unlink($tempFile);
            throw $e;
        }
    }
}
