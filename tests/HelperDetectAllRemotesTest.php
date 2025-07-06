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

use PHPUnit\Framework\TestCase;
use App\Utils\Helper;

/**
 * @covers AppUtilsHelper
 */
class HelperDetectAllRemotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear env for isolation
        $_ENV = [];
    }

    public function test_b2_region_default_is_used_when_not_set()
    {
        $_ENV['B2_KEY'] = 'test-key';
        $_ENV['B2_SECRET'] = 'test-secret';
        $_ENV['B2_BUCKET'] = 'test-bucket';
        unset($_ENV['B2_REGION']);
        $remotes = Helper::detectAllRemotes();
        $this->assertNotEmpty($remotes);
        $b2 = null;
        foreach ($remotes as $remote) {
            if ($remote['driver'] === 'b2') {
                $b2 = $remote;
                break;
            }
        }
        $this->assertNotNull($b2, 'B2 remote should be detected');
        $this->assertEquals('us-west-002', $b2['region'], 'Default region should be us-west-002');
    }

    public function test_b2_region_respects_env()
    {
        $_ENV['B2_KEY'] = 'test-key';
        $_ENV['B2_SECRET'] = 'test-secret';
        $_ENV['B2_BUCKET'] = 'test-bucket';
        $_ENV['B2_REGION'] = 'custom-region';
        $remotes = Helper::detectAllRemotes();
        $b2 = null;
        foreach ($remotes as $remote) {
            if ($remote['driver'] === 'b2') {
                $b2 = $remote;
                break;
            }
        }
        $this->assertNotNull($b2, 'B2 remote should be detected');
        $this->assertEquals('custom-region', $b2['region'], 'Should use env region if set');
    }
}
