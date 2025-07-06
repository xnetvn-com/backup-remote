<?php

/**
 * Security Enhancement: Credential Manager
 * Encrypts sensitive configuration data at rest
 * Created as part of GIAI ĐOẠN 2 security improvements
 */

declare(strict_types=1);

namespace App\Security;

use App\Exceptions\ValidationException;

/**
 * Manages encryption and decryption of sensitive configuration data
 */
class CredentialManager
{
    private const ENCRYPTION_METHOD = 'aes-256-gcm';
    private const KEY_LENGTH = 32; // 256 bits
    
    private string $encryptionKey;
    
    public function __construct()
    {
        $this->encryptionKey = $this->getEncryptionKey();
    }
    
    /**
     * Encrypt sensitive configuration values
     */
    public function encryptConfig(array $config): array
    {
        $sensitiveKeys = $this->getSensitiveConfigKeys();
        
        return $this->recursivelyEncryptValues($config, $sensitiveKeys);
    }
    
    /**
     * Decrypt sensitive configuration values
     */
    public function decryptConfig(array $config): array
    {
        return $this->recursivelyDecryptValues($config);
    }
    
    /**
     * Encrypt a single value
     */
    public function encryptValue(string $value): string
    {
        if (empty($value)) {
            return $value;
        }
        
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';
        
        $encrypted = openssl_encrypt($value, self::ENCRYPTION_METHOD, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($encrypted === false) {
            throw new ValidationException('Failed to encrypt value', ValidationException::ERROR_INVALID_CONFIG);
        }
        
        // Combine IV + tag + encrypted data and base64 encode
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt a single value
     */
    public function decryptValue(string $encryptedValue): string
    {
        if (empty($encryptedValue) || !$this->isEncryptedValue($encryptedValue)) {
            return $encryptedValue; // Return as-is if not encrypted
        }
        
        $data = base64_decode($encryptedValue);
        if ($data === false) {
            throw new ValidationException('Invalid encrypted value format', ValidationException::ERROR_INVALID_CONFIG);
        }
        
        if (strlen($data) < 28) { // IV(12) + tag(16) = 28 minimum
            throw new ValidationException('Encrypted value too short', ValidationException::ERROR_INVALID_CONFIG);
        }
        
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $encrypted = substr($data, 28);
        
        $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($decrypted === false) {
            throw new ValidationException('Failed to decrypt value', ValidationException::ERROR_INVALID_CONFIG);
        }
        
        return $decrypted;
    }
    
    /**
     * Check if a value appears to be encrypted
     */
    public function isEncryptedValue(string $value): bool
    {
        return str_starts_with($value, 'ENC:') || preg_match('/^[A-Za-z0-9+\/]+=*$/', $value);
    }
    
    /**
     * Get the master encryption key from environment or generate one
     */
    private function getEncryptionKey(): string
    {
        $keyFile = __DIR__ . '/../../storage/.encryption_key';
        
        // Try to get key from environment first
        $envKey = getenv('BACKUP_ENCRYPTION_KEY');
        if ($envKey && strlen($envKey) === self::KEY_LENGTH * 2) { // Hex encoded
            return hex2bin($envKey);
        }
        
        // Try to load from key file
        if (file_exists($keyFile)) {
            $keyData = file_get_contents($keyFile);
            if ($keyData && strlen($keyData) === self::KEY_LENGTH) {
                return $keyData;
            }
        }
        
        // Generate new key
        $key = random_bytes(self::KEY_LENGTH);
        
        // Save to key file with proper permissions
        if (file_put_contents($keyFile, $key, LOCK_EX) === false) {
            throw new ValidationException('Failed to save encryption key', ValidationException::ERROR_INVALID_CONFIG);
        }
        
        chmod($keyFile, 0600); // Owner read/write only
        
        return $key;
    }
    
    /**
     * Get list of sensitive configuration keys that should be encrypted
     */
    private function getSensitiveConfigKeys(): array
    {
        return [
            'database.password',
            'remotes.*.key',
            'remotes.*.secret',
            'remotes.*.pass',
            'notification.channels.email.EMAIL_SMTP_PASS',
            'notification.channels.telegram.TELEGRAM_BOT_TOKEN',
            'encryption.gpg_passphrase',
            'encryption.aes_key'
        ];
    }
    
    /**
     * Recursively encrypt values in configuration array
     */
    private function recursivelyEncryptValues(array $config, array $sensitiveKeys, string $keyPath = ''): array
    {
        foreach ($config as $key => $value) {
            $currentKeyPath = $keyPath ? $keyPath . '.' . $key : $key;
            
            if (is_array($value)) {
                $config[$key] = $this->recursivelyEncryptValues($value, $sensitiveKeys, $currentKeyPath);
            } elseif (is_string($value) && $this->shouldEncryptKey($currentKeyPath, $sensitiveKeys)) {
                $config[$key] = 'ENC:' . $this->encryptValue($value);
            }
        }
        
        return $config;
    }
    
    /**
     * Recursively decrypt values in configuration array
     */
    private function recursivelyDecryptValues(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->recursivelyDecryptValues($value);
            } elseif (is_string($value) && str_starts_with($value, 'ENC:')) {
                $config[$key] = $this->decryptValue(substr($value, 4));
            }
        }
        
        return $config;
    }
    
    /**
     * Check if a configuration key should be encrypted
     */
    private function shouldEncryptKey(string $keyPath, array $sensitiveKeys): bool
    {
        foreach ($sensitiveKeys as $pattern) {
            if (fnmatch($pattern, $keyPath)) {
                return true;
            }
            // Also check simple key name for nested structures
            $keyName = basename($keyPath);
            if (fnmatch($pattern, $keyName) || 
                strpos($pattern, $keyName) !== false ||
                strpos($keyName, 'password') !== false ||
                strpos($keyName, 'secret') !== false ||
                strpos($keyName, 'key') !== false) {
                return true;
            }
        }
        return false;
    }
}
