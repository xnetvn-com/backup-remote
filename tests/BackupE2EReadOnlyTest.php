<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class BackupE2EReadOnlyTest extends TestCase
{
    private string $backupDir;
    private string $tmpDir;
    private string $testFile;
    private string $logFile;

    protected function setUp(): void
    {
        $this->backupDir = sys_get_temp_dir() . '/backup_e2e_readonly';
        $this->tmpDir = sys_get_temp_dir() . '/backup_e2e_tmp';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0700, true);
        }
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0700, true);
        }
        $this->testFile = $this->backupDir . '/important.txt';
        file_put_contents($this->testFile, 'important-data');
        chmod($this->backupDir, 0500); // read & execute only
        $this->logFile = $this->tmpDir . '/backup.log';
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    protected function tearDown(): void
    {
        chmod($this->backupDir, 0700);
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        if (is_dir($this->backupDir)) {
            rmdir($this->backupDir);
        }
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testE2EBackupPipelineDoesNotModifyBackupDirs(): void
    {
        // Simulate backup pipeline: detect, copy to tmp, compress, encrypt, upload, rotate, notify
        $copied = @copy($this->testFile, $this->tmpDir . '/important.txt');
        $this->assertTrue($copied, 'Should be able to copy file to TMP_DIR');
        $compressed = @file_put_contents($this->tmpDir . '/important.txt.gz', gzencode(file_get_contents($this->tmpDir . '/important.txt')));
        $this->assertNotFalse($compressed, 'Should be able to compress file in TMP_DIR');
        // Simulate encryption (write to tmp only)
        $encrypted = @file_put_contents($this->tmpDir . '/important.txt.enc', strrev(file_get_contents($this->tmpDir . '/important.txt')));
        $this->assertNotFalse($encrypted, 'Should be able to encrypt file in TMP_DIR');
        // Simulate upload (just move in tmp)
        $uploaded = @rename($this->tmpDir . '/important.txt.enc', $this->tmpDir . '/uploaded.enc');
        $this->assertTrue($uploaded, 'Should be able to move encrypted file in TMP_DIR');
        // Simulate rotation (delete from tmp only)
        $deleted = @unlink($this->tmpDir . '/important.txt');
        $this->assertTrue($deleted, 'Should be able to delete file in TMP_DIR');
        // Simulate notification (write log)
        $notified = @file_put_contents($this->logFile, 'Backup completed');
        $this->assertNotFalse($notified, 'Should be able to write log in TMP_DIR');
        // Assert BACKUP_DIRS remains unchanged
        $this->assertFileExists($this->testFile, 'Original file in BACKUP_DIRS must not be modified');
        $this->assertEquals('important-data', file_get_contents($this->testFile), 'Original file content must not change');
        $this->assertFileDoesNotExist($this->backupDir . '/important.txt.gz');
        $this->assertFileDoesNotExist($this->backupDir . '/important.txt.enc');
        $this->assertFileDoesNotExist($this->backupDir . '/uploaded.enc');
        $this->assertFileDoesNotExist($this->backupDir . '/backup.log');
    }

    public function testConcurrentBackupCannotModifyBackupDirs(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension missing; skipping concurrency test.');
        }
        $processes = [];
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('Failed to fork process');
        } elseif ($pid === 0) {
            // Child process: try to write to BACKUP_DIRS (should fail)
            $fail = @file_put_contents($this->backupDir . '/evil.txt', 'malicious');
            exit($fail === false ? 0 : 1);
        } else {
            // Parent process: wait and check
            $processes[] = $pid;
        }
        foreach ($processes as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertEquals(0, pcntl_wexitstatus($status), 'Concurrent process must not be able to write to BACKUP_DIRS');
        }
        $this->assertFileDoesNotExist($this->backupDir . '/evil.txt');
    }
}
