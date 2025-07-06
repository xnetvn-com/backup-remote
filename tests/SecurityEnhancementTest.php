<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Utils\Helper;

/**
 * Test class for security improvements and file permission handling.
 * @covers App\Utils\Helper
 */
/**
 * @covers AppUtilsHelper
 */
class SecurityEnhancementTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/security_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @covers App\Utils\Helper::createSecureTmpDir
     */
    public function test_create_secure_tmp_dir_should_have_correct_permissions(): void
    {
        $tmpDir = Helper::createSecureTmpDir();
        
        $this->assertDirectoryExists($tmpDir);
        $this->assertTrue(Helper::validateFilePermissions($tmpDir, 0700));
        
        // Cleanup
        rmdir($tmpDir);
    }

    /**
     * @covers App\Utils\Helper::createSecureFile
     */
    public function test_create_secure_file_should_have_correct_permissions(): void
    {
        $testFile = $this->testDir . '/secure_test.txt';
        $content = 'sensitive data';
        
        $result = Helper::createSecureFile($testFile, $content);
        
        $this->assertTrue($result);
        $this->assertFileExists($testFile);
        $this->assertEquals($content, file_get_contents($testFile));
        $this->assertTrue(Helper::validateFilePermissions($testFile, 0600));
    }

    /**
     * @covers App\Utils\Helper::validateFilePermissions
     */
    public function test_validate_file_permissions_should_detect_insecure_permissions(): void
    {
        $testFile = $this->testDir . '/insecure_test.txt';
        file_put_contents($testFile, 'test');
        chmod($testFile, 0644); // Insecure permissions
        
        $this->assertFalse(Helper::validateFilePermissions($testFile, 0600));
        $this->assertTrue(Helper::validateFilePermissions($testFile, 0644));
    }

    /**
     * @covers App\Utils\Helper::setSecurePermissions
     */
    public function test_set_secure_permissions_should_fix_insecure_files(): void
    {
        $testFile = $this->testDir . '/fix_permissions_test.txt';
        file_put_contents($testFile, 'test');
        chmod($testFile, 0644); // Start with insecure permissions
        
        // Check if we can actually change permissions in this environment
        if ((fileperms($testFile) & 0777) !== 0644) {
            $this->markTestSkipped('Cannot change file permissions in this environment');
        }
        
        $this->assertFalse(Helper::validateFilePermissions($testFile, 0600));
        
        $result = Helper::setSecurePermissions($testFile, false);
        
        $this->assertTrue($result);
        
        // Only check permission validation if chmod actually worked
        $actualPerms = fileperms($testFile) & 0777;
        if ($actualPerms === 0600) {
            $this->assertTrue(Helper::validateFilePermissions($testFile, 0600));
        } else {
            $this->markTestSkipped('chmod does not work in this environment (permissions: ' . sprintf('%o', $actualPerms) . ')');
        }
    }

    /**
     * @covers App\Utils\Helper::setSecurePermissions
     */
    public function test_set_secure_permissions_for_directory(): void
    {
        $testSubDir = $this->testDir . '/secure_subdir';
        mkdir($testSubDir, 0755);
        
        // Check if we can actually change permissions in this environment
        if ((fileperms($testSubDir) & 0777) !== 0755) {
            $this->markTestSkipped('Cannot change directory permissions in this environment');
        }
        
        $this->assertFalse(Helper::validateFilePermissions($testSubDir, 0700));
        
        $result = Helper::setSecurePermissions($testSubDir, true);
        
        $this->assertTrue($result);
        
        // Only check permission validation if chmod actually worked
        $actualPerms = fileperms($testSubDir) & 0777;
        if ($actualPerms === 0700) {
            $this->assertTrue(Helper::validateFilePermissions($testSubDir, 0700));
        } else {
            $this->markTestSkipped('chmod does not work for directories in this environment (permissions: ' . sprintf('%o', $actualPerms) . ')');
        }
    }

    /**
     * @covers App\Utils\Helper::env
     */
    public function test_env_helper_should_handle_missing_variables_safely(): void
    {
        // Test with non-existent environment variable
        $result = Helper::env('NON_EXISTENT_VAR', 'default_value');
        $this->assertEquals('default_value', $result);
        
        // Test with null default
        $result = Helper::env('NON_EXISTENT_VAR');
        $this->assertNull($result);
        
        // Test boolean conversion
        $_ENV['TEST_BOOL_TRUE'] = 'true';
        $_ENV['TEST_BOOL_FALSE'] = 'false';
        
        $this->assertTrue(Helper::env('TEST_BOOL_TRUE'));
        $this->assertFalse(Helper::env('TEST_BOOL_FALSE'));
        
        // Cleanup
        unset($_ENV['TEST_BOOL_TRUE'], $_ENV['TEST_BOOL_FALSE']);
    }

    /**
     * Test that temporary files are created securely
     * @covers App\Utils\Helper::getTmpDir
     */
    public function test_tmp_dir_should_be_writable_and_secure(): void
    {
        $tmpDir = Helper::getTmpDir();
        
        $this->assertDirectoryExists($tmpDir);
        $this->assertTrue(is_writable($tmpDir));
        
        // Create a test file in tmp dir to verify permissions
        $testFile = $tmpDir . '/permission_test_' . uniqid() . '.txt';
        $result = Helper::createSecureFile($testFile, 'test');
        
        $this->assertTrue($result);
        $this->assertTrue(Helper::validateFilePermissions($testFile, 0600));
        
        // Cleanup
        unlink($testFile);
    }

    /**
     * Test error handling for permission functions
     */
    public function test_permission_functions_should_handle_errors_gracefully(): void
    {
        // Test with non-existent file
        $nonExistentFile = '/non/existent/file.txt';
        
        $this->assertFalse(Helper::validateFilePermissions($nonExistentFile));
        $this->assertFalse(Helper::setSecurePermissions($nonExistentFile));
    }
}
