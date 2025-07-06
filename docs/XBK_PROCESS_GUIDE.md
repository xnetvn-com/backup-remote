# XBK Backup File Processing Guide

## Overview

The backup system has been enhanced to use the `.xbk` (xNetVN Backup) marker to identify and process files that have been compressed and encrypted. This ensures that the restoration process is performed accurately according to the correct order and methods used when creating the backup.

## Backup Creation Process (Upload/Run)

### File Name Structure

```text
{original_filename}.xbk[.{compression_ext}][.{encryption_ext}]
```

### Format Examples

| Method | Original File | Processed File | Description |
|---------|---------------|----------------|-------------|
| **7z + None** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.7z` | Compressed with 7z, no encryption |
| **Gzip + GPG** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.gz.gpg` | Compressed with gzip, encrypted with GPG |
| **Zstd + AES** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.zst.aes` | Compressed with zstd, encrypted with AES |
| **None + AES** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.aes` | No compression, AES encryption only |
| **Zip + None** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.zip` | Compressed with zip, no encryption |
| **Bzip2 + GPG** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.bz2.gpg` | Compressed with bzip2, encrypted with GPG |

### Processing Order (Backup)

1. **Create TAR Archive** (if needed)
2. **Compression** - if enabled
3. **Encryption** - if enabled
4. **Upload** to remote storage

## Restoration Process (Download)

### File Recognition

The system uses the following pattern to identify backup files:

```regex
/^([a-zA-Z0-9_.-]+)\.(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.tar(?:\.xbk)?(?:\.(gz|bz2|xz|zst|zip|7z))?(?:\.(aes|gpg))?$/
```

### Processing Order (Restoration)

1. **Download** file from remote storage
2. **Analyze** filename to determine processing method
3. **Decryption** - if encryption exists
4. **Decompression** - if compression exists
5. **Restore** original filename (remove `.xbk` marker)

### Restoration Process Examples

#### Example 1: File `xtest.2025-06-02_06-02-16.tar.xbk.gz.gpg`

```bash
# Original file: xtest.2025-06-02_06-02-16.tar.xbk.gz.gpg
# Analysis: compression=gz, encryption=gpg

# Step 1: GPG Decryption
gpg --decrypt file.tar.xbk.gz.gpg > file.tar.xbk.gz

# Step 2: Gzip Decompression
gunzip file.tar.xbk.gz → file.tar.xbk

# Step 3: Restore original name
mv file.tar.xbk → file.tar
```

#### Example 2: File `xtest.2025-06-02_06-02-16.tar.xbk.7z`

```bash
# Original file: xtest.2025-06-02_06-02-16.tar.xbk.7z
# Analysis: compression=7z, encryption=none

# Step 1: 7z Decompression
7z x file.tar.xbk.7z → file.tar.xbk

# Step 2: Restore original name
mv file.tar.xbk → file.tar
```

#### Example 3: File `xtest.2025-06-02_06-02-16.tar.xbk.zst.aes`

```bash
# Original file: xtest.2025-06-02_06-02-16.tar.xbk.zst.aes
# Analysis: compression=zst, encryption=aes

# Step 1: AES Decryption
openssl_decrypt(file.tar.xbk.zst.aes) → file.tar.xbk.zst

# Step 2: Zstd Decompression
zstd -d file.tar.xbk.zst → file.tar.xbk

# Step 3: Restore original name
mv file.tar.xbk → file.tar
```

## Supported Compression Methods

| Method | Extension | Compression Command | Decompression Command |
|--------|-----------|--------------------|-----------------------|
| **Gzip** | `.gz` | `gzip` | `gunzip` |
| **Bzip2** | `.bz2` | `bzip2` | `bunzip2` |
| **XZ** | `.xz` | `xz` | `unxz` |
| **Zstd** | `.zst` | `zstd` | `zstd -d` |
| **Zip** | `.zip` | `zip` | `unzip` |
| **7-Zip** | `.7z` | `7z a` | `7z x` |

## Supported Encryption Methods

| Method | Extension | Description |
|--------|-----------|-------------|
| **AES** | `.aes` | AES-256-CBC with OpenSSL |
| **GPG** | `.gpg` | GNU Privacy Guard |

## Environment Configuration

```bash
# Compression
BACKUP_COMPRESSION=gzip      # none, gzip, bzip2, xz, zstd, zip, 7z
BACKUP_COMPRESSION_LEVEL=6   # Compression level (1-9 for most, 1-19 for zstd)

# Encryption
BACKUP_ENCRYPTION=aes        # none, aes, gpg
ENCRYPTION_PASSWORD=your_password
```

## New Helper Functions

### `Helper::createXbkFilename()`

Creates filename with `.xbk` marker based on compression and encryption methods.

```php
$filename = Helper::createXbkFilename(
    'user.2025-01-01_12-00-00.tar',  // Original file
    'gzip',                          // Compression method
    'aes'                           // Encryption method
);
// Result: user.2025-01-01_12-00-00.tar.xbk.gz.aes
```

### `Helper::parseXbkFilename()`

Analyzes filename to determine processing method.

```php
$info = Helper::parseXbkFilename('user.2025-01-01_12-00-00.tar.xbk.gz.aes');
// Result:
// [
//     'original' => 'user.2025-01-01_12-00-00.tar',
//     'compression' => 'gz',
//     'encryption' => 'aes', 
//     'hasXbk' => true
// ]
```

### `Helper::getOriginalFilename()`

Gets original filename from processed file.

```php
$original = Helper::getOriginalFilename('user.2025-01-01_12-00-00.tar.xbk.gz.aes');
// Result: user.2025-01-01_12-00-00.tar
```

## Backward Compatibility

The system still supports old backup files without the `.xbk` marker through legacy logic in `download.php`. However, it's recommended to use the new format for all new backups.

## Important Notes

1. **Processing order**: Always compress first, then encrypt during backup. And vice versa during restoration: decrypt first, then decompress.

2. **Marker .xbk**: Inserted between the original filename and processing extensions for easy identification.

3. **Compatibility**: Files without `.xbk` are still processed using old logic to ensure backward compatibility.

4. **Security**: Encryption password must be stored securely in the `ENCRYPTION_PASSWORD` environment variable.

5. **Testing**: Always test backup and restore processes before production deployment.
