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

// Factory to create storage instances (Flysystem)
namespace App\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use Aws\S3\S3Client;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;

class StorageFactory
{
    /**
     * Creates a Filesystem instance for a specific storage type.
     * @param string $type s3|b2|ftp|local
     * @param array $config
     * @return Filesystem|null
     */

    public static function create(string $type, array $config): ?Filesystem
    {
        switch ($type) {
            case 's3':
                $client = new S3Client([
                    'credentials' => [
                        'key' => $config['key'],
                        'secret' => $config['secret'],
                    ],
                    'region' => $config['region'],
                    'version' => 'latest',
                    'endpoint' => $config['endpoint'] ?? null,
                ]);
                $adapter = new AwsS3V3Adapter($client, $config['bucket']);
                return new Filesystem($adapter);
            case 'ftp':
                $adapter = new FtpAdapter(FtpConnectionOptions::fromArray([
                    'host' => $config['host'],
                    'username' => $config['user'],
                    'password' => $config['pass'],
                    'port' => (int)($config['port'] ?? 21),
                    'root' => $config['path'] ?? '/',
                    'ssl' => (bool)($config['ssl'] ?? false),
                ]));
                return new Filesystem($adapter);
            case 'local':
                $adapter = new LocalFilesystemAdapter($config['root'] ?? '/');
                return new Filesystem($adapter);
            // B2 can use the S3 adapter with a custom endpoint
            case 'b2':
                $client = new S3Client([
                    'credentials' => [
                        'key' => $config['key'],
                        'secret' => $config['secret'],
                    ],
                    'region' => $config['region'] ?? 'us-west-002',
                    'version' => 'latest',
                    'endpoint' => $config['endpoint'] ?? 'https://s3.us-west-002.backblazeb2.com',
                ]);
                $adapter = new AwsS3V3Adapter($client, $config['bucket']);
                return new Filesystem($adapter);
        }
        return null;
    }
}
