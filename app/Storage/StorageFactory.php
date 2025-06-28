<?php
// Factory to create storage instances (Flysystem)
namespace App\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use Aws\S3\S3Client;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;

class StorageFactory {
    /**
     * Creates a Filesystem instance for a specific storage type.
     * @param string $type s3|b2|ftp|local
     * @param array $config
     * @return Filesystem|null
     */
    public static function create(string $type, array $config): ?Filesystem {
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
