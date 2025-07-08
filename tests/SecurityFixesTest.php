<?php

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 *
 * Security Test Suite
 * Tests for security fixes implemented in Phase 2
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Notification\AlertThrottler;
use App\Security\CredentialManager;
use App\Exceptions\BackupException;
use App\Exceptions\EncryptionException;
use App\Exceptions\ValidationException;
use App\Exceptions\StorageException;

/**
 * @covers AppUtilsHelper
 */
class SecurityFixesTest extends TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/backup_security_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }
    
    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
    }
    
    /**
     * Test that AlertThrottler is thread-safe and prevents race conditions
     */
    public function testAlertThrottlerThreadSafety(): void
    {
        $config = ['NOTIFY_INTERVAL_MINUTES' => 1];
        $throttler = new AlertThrottler($config);
        
        // Simulate concurrent access by rapidly calling markSent
        $channels = ['email', 'telegram', 'slack'];
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($channels as $channel) {
                $throttler->markSent($channel . '_' . $i);
            }
        }
        
        // Verify state file is valid JSON and contains expected entries
        $stateFile = \App\Utils\Helper::getTmpDir() . '/backup_notify_state.json';
        $this->assertFileExists($stateFile);
        
        $content = file_get_contents($stateFile);
        $state = json_decode($content, true);
        
        $this->assertNotNull($state, 'State file should contain valid JSON');
        $this->assertCount($iterations * count($channels), $state, 'All entries should be preserved');
    }
    
    /**
     * Test CredentialManager encryption/decryption
     */
    public function testCredentialManagerBasicOperations(): void
    {
        $manager = new CredentialManager();
        
        $sensitiveValue = 'super_secret_password_123!@#';
        $encrypted = $manager->encryptValue($sensitiveValue);
        $decrypted = $manager->decryptValue($encrypted);
        
        $this->assertNotEquals($sensitiveValue, $encrypted, 'Value should be encrypted');
        $this->assertEquals($sensitiveValue, $decrypted, 'Decrypted value should match original');
        $this->assertTrue($manager->isEncryptedValue($encrypted), 'Should detect encrypted values');
    }
    
    /**
     * Test configuration encryption for sensitive keys
     */
    public function testConfigurationEncryption(): void
    {
        $manager = new CredentialManager();
        
        $config = [
            'database' => [
                'host' => 'localhost',
                'password' => 'secret_db_pass'
            ],
            'remotes' => [
                's3' => [
                    'driver' => 's3',
                    'key' => 'AKIAIOSFODNN7EXAMPLE',
                    'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'
                ]
            ],
            'public_setting' => 'not_sensitive'
        ];
        
        $encrypted = $manager->encryptConfig($config);
        $decrypted = $manager->decryptConfig($encrypted);
        
        // Verify sensitive values are encrypted
        $this->assertStringStartsWith('ENC:', $encrypted['database']['password']);
        $this->assertStringStartsWith('ENC:', $encrypted['remotes']['s3']['key']);
        $this->assertStringStartsWith('ENC:', $encrypted['remotes']['s3']['secret']);
        
        // Verify non-sensitive values are unchanged
        $this->assertEquals('localhost', $encrypted['database']['host']);
        $this->assertEquals('s3', $encrypted['remotes']['s3']['driver']);
        $this->assertEquals('not_sensitive', $encrypted['public_setting']);
        
        // Verify decryption restores original values
        $this->assertEquals($config, $decrypted);
    }
    
    /**
     * Test Exception hierarchy provides proper error categorization
     */
    public function testExceptionHierarchy(): void
    {
        // Test base BackupException
        $baseException = new BackupException('Base error', 0, null, ['context' => 'test']);
        $this->assertEquals(['context' => 'test'], $baseException->getContext());
        
        // Test specialized exceptions
        $storageException = new StorageException(
            'Storage failed', 
            StorageException::ERROR_UPLOAD_FAILED
        );
        $this->assertInstanceOf(BackupException::class, $storageException);
        $this->assertEquals(StorageException::ERROR_UPLOAD_FAILED, $storageException->getCode());
        
        $encryptionException = new EncryptionException(
            'Encryption failed',
            EncryptionException::ERROR_ENCRYPTION_FAILED
        );
        $this->assertInstanceOf(BackupException::class, $encryptionException);
        $this->assertEquals(EncryptionException::ERROR_ENCRYPTION_FAILED, $encryptionException->getCode());
    }
    
    /**
     * Test that empty/null values are handled properly in encryption
     */
    public function testEncryptionEdgeCases(): void
    {
        $manager = new CredentialManager();
        
        // Empty string should remain empty
        $this->assertEquals('', $manager->encryptValue(''));
        $this->assertEquals('', $manager->decryptValue(''));
        
        // Non-encrypted values should pass through
        $plainValue = 'not_encrypted';
        $this->assertEquals($plainValue, $manager->decryptValue($plainValue));
    }
    
    /**
     * Test encryption key generation and persistence
     */
    public function testEncryptionKeyPersistence(): void
    {
        // First manager instance generates key
        $manager1 = new CredentialManager();
        $encrypted1 = $manager1->encryptValue('test');
        
        // Second manager instance should use same key
        $manager2 = new CredentialManager();
        $decrypted = $manager2->decryptValue($encrypted1);
        
        $this->assertEquals('test', $decrypted, 'Different manager instances should use same key');
    }
    
    /**
     * Test invalid encrypted data handling
     */
    public function testInvalidEncryptedDataHandling(): void
    {
        $manager = new CredentialManager();
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionCode(ValidationException::ERROR_INVALID_CONFIG);
        
        // Try to decrypt obviously invalid data
        $manager->decryptValue('ENC:invalid_base64_!!!');
    }
    
    /**
     * Helper method to remove temporary directory
     */
    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeTempDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
