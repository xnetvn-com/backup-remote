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

// Handles notification anti-spam logic
namespace App\Notification;

class AlertThrottler {
    private $interval;
    private $stateFile;
    private $state = [];
    public function __construct($config) {
        $this->interval = $config['NOTIFY_INTERVAL_MINUTES'] ?? 180;
        $this->stateFile = sys_get_temp_dir().'/backup_notify_state.json';
        if (file_exists($this->stateFile)) {
            $this->state = json_decode(file_get_contents($this->stateFile), true) ?: [];
        }
    }
    public function canSend($channel) {
        $last = $this->state[$channel] ?? 0;
        return (time() - $last) > $this->interval * 60;
    }
    public function markSent($channel) {
        $this->state[$channel] = time();
        file_put_contents($this->stateFile, json_encode($this->state));
    }
}
