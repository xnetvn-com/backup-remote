<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test class for backup directory read-only functionality.
 * @coversNothing
 */
class BackupDirsReadOnlyTest extends TestCase
{
    private string $backupDir;
    private string $testFile;

    protected function setUp(): void
    {
        $this->backupDir = sys_get_temp_dir() . '/backup_readonly_test';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0700, true);
        }
        $this->testFile = $this->backupDir . '/testfile.txt';
        file_put_contents($this->testFile, 'readonly');
        chmod($this->backupDir, 0500); // read & execute only
    }

    protected function tearDown(): void
    {
        chmod($this->backupDir, 0700);
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        rmdir($this->backupDir);
    }

    public function testCannotDeleteFileInBackupDirs(): void
    {
        $result = @unlink($this->testFile);
        $this->assertFalse($result, 'Should not be able to delete file in BACKUP_DIRS');
        $this->assertFileExists($this->testFile);
    }

    public function testCannotWriteFileInBackupDirs(): void
    {
        $result = @file_put_contents($this->backupDir . '/newfile.txt', 'fail');
        $this->assertFalse($result, 'Should not be able to write file in BACKUP_DIRS');
        $this->assertFileDoesNotExist($this->backupDir . '/newfile.txt');
    }

    public function testCannotMoveFileInBackupDirs(): void
    {
        $result = @rename($this->testFile, $this->backupDir . '/moved.txt');
        $this->assertFalse($result, 'Should not be able to move file in BACKUP_DIRS');
        $this->assertFileExists($this->testFile);
        $this->assertFileDoesNotExist($this->backupDir . '/moved.txt');
    }
}
