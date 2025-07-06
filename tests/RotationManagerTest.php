<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Backup\RotationManager;
use Psr\Log\LoggerInterface;
use League\Flysystem\Filesystem;

/**
 * @covers AppBackupRotationManager
 */
class RotationManagerTest extends TestCase
{
    public function test_should_group_files_by_user_and_apply_keep_latest_policy(): void
    {
        $config = [
            'rotation' => [
                'enabled' => true,
                'policies' => [
                    'keep_latest' => 3,
                ],
            ],
            'remote' => [
                'path' => '/remote',
            ],
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $storage = $this->getMockBuilder(Filesystem::class)
            ->setConstructorArgs([$this->createMock(\League\Flysystem\FilesystemAdapter::class)])
            ->onlyMethods(['listContents', 'delete'])
            ->getMock();

        // Fake file list for two users
        $files = [
            ['path' => 'user1.2025-07-01_10-00-00.tar.gz', 'lastModified' => 1720000000],
            ['path' => 'user1.2025-07-02_10-00-00.tar.gz', 'lastModified' => 1720086400],
            ['path' => 'user1.2025-07-03_10-00-00.tar.gz', 'lastModified' => 1720172800],
            ['path' => 'user1.2025-07-04_10-00-00.tar.gz', 'lastModified' => 1720259200],
            ['path' => 'user2.2025-07-01_10-00-00.tar.gz', 'lastModified' => 1720000000],
            ['path' => 'user2.2025-07-02_10-00-00.tar.gz', 'lastModified' => 1720086400],
        ];

        // Fake DirectoryListing mock
        $directoryListing = $this->getMockBuilder(\League\Flysystem\DirectoryListing::class)
            ->setConstructorArgs([ $files ])
            ->onlyMethods(['filter', 'sortByPath', 'toArray'])
            ->getMock();
        $directoryListing->method('filter')->willReturnSelf();
        $directoryListing->method('sortByPath')->willReturnSelf();
        $directoryListing->method('toArray')->willReturn($files);
        $storage->method('listContents')->willReturn($directoryListing);
        $deleted = [];
        $storage->method('delete')
            ->willReturnCallback(function ($path) use (&$deleted) { $deleted[] = $path; });

        $manager = new RotationManager($config, $logger, $storage);
        $manager->run(false);

        // user1 has 4 files, keep_latest=3 => 1 file will be deleted (the oldest file)
        $this->assertContains('user1.2025-07-01_10-00-00.tar.gz', $deleted);
        // user2 has 2 files, keep_latest=3 => no file will be deleted
        $this->assertNotContains('user2.2025-07-01_10-00-00.tar.gz', $deleted);
        $this->assertNotContains('user2.2025-07-02_10-00-00.tar.gz', $deleted);
    }
}
