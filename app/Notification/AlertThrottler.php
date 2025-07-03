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

    public function canSend($channel)
    {
        $last = $this->state[$channel] ?? 0;
        return (time() - $last) > $this->interval * 60;
    }

    public function markSent($channel)
    {
        $this->state[$channel] = time();
        file_put_contents($this->stateFile, json_encode($this->state));
    }
}
