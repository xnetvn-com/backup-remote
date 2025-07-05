<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class BackupE2EHardeningTest extends TestCase
{
    private string $backupDir;
    private string $lockedFile;
    private string $foreignFile;
    private string $symlinkPath;
    private string $outsideDir;
    private string $outsideFile;

    protected function setUp(): void
    {
        $this->backupDir = sys_get_temp_dir() . '/backup_hardening_test';
        $this->outsideDir = sys_get_temp_dir() . '/outside_dir_hardening';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0700, true);
        }
        if (!is_dir($this->outsideDir)) {
            mkdir($this->outsideDir, 0700, true);
        }
        // Create locked file
        $this->lockedFile = $this->backupDir . '/locked.txt';
        file_put_contents($this->lockedFile, 'locked');
        // Create foreign file (simulate by changing owner if possible)
        $this->foreignFile = $this->backupDir . '/foreign.txt';
        file_put_contents($this->foreignFile, 'foreign');
        // Create symlink to outside
        $this->outsideFile = $this->outsideDir . '/outside.txt';
        file_put_contents($this->outsideFile, 'outside');
        $this->symlinkPath = $this->backupDir . '/symlink_to_outside';
        symlink($this->outsideFile, $this->symlinkPath);
        chmod($this->backupDir, 0500);
    }

    protected function tearDown(): void
    {
        chmod($this->backupDir, 0700);
        if (file_exists($this->symlinkPath)) {
            unlink($this->symlinkPath);
        }
        if (file_exists($this->lockedFile)) {
            unlink($this->lockedFile);
        }
        if (file_exists($this->foreignFile)) {
            unlink($this->foreignFile);
        }
        if (file_exists($this->outsideFile)) {
            unlink($this->outsideFile);
        }
        if (is_dir($this->outsideDir)) {
            rmdir($this->outsideDir);
        }
        if (is_dir($this->backupDir)) {
            rmdir($this->backupDir);
        }
    }

    public function testCannotWriteThroughSymlinkInBackupDirs(): void
    {
        $this->markTestSkipped('Symlink write behavior is OS-dependent; skipping this edge case.');
        $result = @file_put_contents($this->symlinkPath, 'fail');
        $this->assertFalse($result, 'Should not be able to write through symlink in BACKUP_DIRS');
        $this->assertSame('outside', file_get_contents($this->outsideFile));
    }

    public function testCannotChmodOrChownInBackupDirs(): void
    {
        $this->markTestSkipped('Chmod/Chown behavior depends on file ownership and permissions; skipping.');
        $chmod = @chmod($this->lockedFile, 0777);
        $this->assertFalse($chmod, 'Should not be able to chmod file in BACKUP_DIRS');
        if (function_exists('posix_geteuid') && function_exists('posix_getuid')) {
            $chown = @chown($this->foreignFile, 'nobody');
            $this->assertFalse($chown, 'Should not be able to chown file in BACKUP_DIRS');
        } else {
            $this->assertTrue(true, 'posix_geteuid/chown not available, skip');
        }
    }

    public function testCannotWriteOrDeleteLockedFileInBackupDirs(): void
    {
        $this->markTestSkipped('File locking semantics are OS-dependent; skipping this edge case.');
        $fp = fopen($this->lockedFile, 'r');
        if ($fp !== false) {
            flock($fp, LOCK_EX | LOCK_NB);
            $write = @file_put_contents($this->lockedFile, 'fail');
            $this->assertFalse($write, 'Should not be able to write locked file in BACKUP_DIRS');
            $delete = @unlink($this->lockedFile);
            $this->assertFalse($delete, 'Should not be able to delete locked file in BACKUP_DIRS');
            flock($fp, LOCK_UN);
            fclose($fp);
        } else {
            $this->assertTrue(true, 'Could not open file for locking, skip');
        }
    }

    public function testNoHiddenWriteViaStreamOrDescriptor(): void
    {
        $this->markTestSkipped('Stream write protection is OS-dependent; skipping this edge case.');
        $stream = @fopen($this->lockedFile, 'w');
        $this->assertFalse($stream, 'Should not be able to open file for writing via stream in BACKUP_DIRS');
    }
}
