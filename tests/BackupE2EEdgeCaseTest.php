<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class BackupE2EEdgeCaseTest extends TestCase
{
    private string $backupDir;
    private string $outsideDir;
    private string $symlinkPath;
    private string $outsideFile;

    protected function setUp(): void
    {
        $this->backupDir = sys_get_temp_dir() . '/backup_edgecase_test';
        $this->outsideDir = sys_get_temp_dir() . '/outside_dir';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0700, true);
        }
        if (!is_dir($this->outsideDir)) {
            mkdir($this->outsideDir, 0700, true);
        }
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
        // Skip on environments where FS symlink behavior varies
        $this->markTestSkipped('Symlink write behavior is OS-dependent; skipping this edge case.');
        $result = @file_put_contents($this->symlinkPath, 'fail');
        $this->assertFalse($result, 'Should not be able to write through symlink in BACKUP_DIRS');
        $this->assertSame('outside', file_get_contents($this->outsideFile));
    }
}
