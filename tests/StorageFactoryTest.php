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
use App\Storage\StorageFactory;

class StorageFactoryTest extends TestCase
{
    public function testFtpPassiveOptionCastsToBoolean()
    {
        $config = [
            'host' => 'localhost',
            'user' => 'user',
            'pass' => 'pass',
            'port' => 21,
            'path' => '/',
            'ssl' => false,
            'passive' => 'false', // string, should be cast to boolean false
        ];
        $fs = StorageFactory::create('ftp', $config);
        $this->assertInstanceOf(\League\Flysystem\Filesystem::class, $fs);
        // Reflection to check the adapter config
        $adapter = (new \ReflectionObject($fs))->getProperty('adapter');
        $adapter->setAccessible(true);
        $ftpAdapter = $adapter->getValue($fs);
        $options = (new \ReflectionObject($ftpAdapter))->getProperty('connectionOptions');
        $options->setAccessible(true);
        $connOptions = $options->getValue($ftpAdapter);
        // Check that the passive property is the correct type (object or value)
        if (method_exists($connOptions, 'getPassive')) {
            $this->assertFalse((bool)$connOptions->getPassive());
        } elseif (property_exists($connOptions, 'passive')) {
            $ref = new \ReflectionProperty($connOptions, 'passive');
            $ref->setAccessible(true);
            $this->assertFalse($ref->getValue($connOptions));
        } else {
            $this->markTestIncomplete('Cannot check passive property');
        }
    }
}
