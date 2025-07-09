# Update Guide - HestiaCP Remote Backup Tool

## Overview

This guide provides step-by-step instructions for updating the HestiaCP Remote Backup Tool to newer versions.

## Version Compatibility

| Version | PHP Requirement | Key Changes | Migration Required |
|---------|----------------|-------------|-------------------|
| 2.x.x   | PHP 8.2+      | Modern architecture, Flysystem 3.x | Yes |
| 1.x.x   | PHP 7.4+      | Legacy version | No |

## Before You Begin

### 1. Backup Current Installation

```bash
# Backup your current installation
cp -r /path/to/php-backup-remote /path/to/php-backup-remote.backup.$(date +%Y%m%d)

# Backup configuration files
cp .env .env.backup.$(date +%Y%m%d)
cp config/app.php config/app.php.backup.$(date +%Y%m%d)
```

### 2. Check System Requirements

```bash
# Check PHP version
php --version

# Check required extensions
php -m | grep -E "(openssl|curl|zip|mbstring|json)"

# Check available commands
which 7z gzip bzip2 xz
```

### 3. Stop Running Backups

```bash
# Check for running backup processes
ps aux | grep "php.*run.php"

# Stop any running backups (if safe to do so)
# Kill processes only if they're not in critical phases
```

## Update Procedures

### Option 1: Git Update (Recommended for Development)

```bash
# Navigate to installation directory
cd /path/to/php-backup-remote

# Fetch latest changes
git fetch origin

# Check available versions
git tag -l | sort -V

# Backup current state
git branch backup-$(date +%Y%m%d) HEAD

# Switch to latest stable version
git checkout tags/v2.1.0  # Replace with desired version

# Update dependencies
cd libs
composer install --no-dev --optimize-autoloader
cd ..
```

### Option 2: Download and Replace

```bash
# Download latest release
wget https://github.com/xnetvn-com/php-backup-remote/archive/refs/tags/v2.1.0.tar.gz

# Extract to temporary location
tar -xzf v2.1.0.tar.gz

# Backup current files and replace
mv php-backup-remote php-backup-remote.old
mv php-backup-remote-2.1.0 php-backup-remote

# Copy back configuration files
cp php-backup-remote.old/.env php-backup-remote/
cp php-backup-remote.old/config/app.php php-backup-remote/config/
```

### Option 3: Auto Update Script

```bash
# Use the built-in auto update script
./auto_update.sh --check     # Check for updates
./auto_update.sh --preview   # Preview changes
./auto_update.sh --update    # Perform update
```

## Version-Specific Migration Instructions

### Updating from 1.x to 2.x

#### 1. Configuration Changes

**Environment Variables:**
```bash
# New variables in 2.x
BACKUP_COMPRESSION=gzip          # New: compression method
BACKUP_COMPRESSION_LEVEL=6       # New: compression level
BACKUP_ENCRYPTION=aes            # Updated: encryption method
UPLOAD_FILE_BY_FILE=false        # New: upload strategy

# Deprecated variables
# LEGACY_OPTION_1  # Remove this
# LEGACY_OPTION_2  # Remove this
```

**Config File Structure:**
```php
// config/app.php changes
[
    // New performance settings
    'performance' => [
        'allowed_start_time' => '',
        'allowed_end_time' => '',
        'max_cpu_load' => 80.0,
        'min_disk_free_percent' => 10,
    ],
    
    // Updated storage configuration
    'remotes' => [
        // New structure for multiple remotes
    ],
]
```

#### 2. Storage Configuration Migration

**Old Format (1.x):**
```php
'storage' => [
    'driver' => 's3',
    'region' => 'us-east-1',
    // ...
]
```

**New Format (2.x):**
```php
'remotes' => [
    [
        'driver' => 's3',
        'region' => 'us-east-1',
        'enabled' => true,
        // ...
    ],
    // Multiple remotes supported
]
```

#### 3. Command Line Changes

**Old Commands (1.x):**
```bash
php run.php --backup
php run.php --rotate
```

**New Commands (2.x):**
```bash
php run.php --dry-run    # Preview mode
php run.php --force      # Skip system checks
php run.php              # Default: full backup + rotation
```

### Updating within 2.x versions

#### From 2.0.x to 2.1.x
- **Dependencies:** Run `composer update` in `libs/` directory
- **Configuration:** No breaking changes
- **Features:** New XBK file format support

#### From 2.1.x to 2.2.x
- **Dependencies:** Update Flysystem to 3.x
- **Configuration:** Check storage adapter configurations
- **Features:** Enhanced security and performance

## Post-Update Verification

### 1. Configuration Validation

```bash
# Test configuration
php run.php --dry-run

# Check system requirements
php test_cli_verification.php

# Verify remote connections
php test_remote_check_optimization.php
```

### 2. Run Test Suite

```bash
# Navigate to project directory
cd /path/to/php-backup-remote

# Run all tests
php libs/vendor/bin/phpunit tests/

# Run specific test categories
php libs/vendor/bin/phpunit tests/ --group integration
```

### 3. Perform Test Backup

```bash
# Run a test backup in dry-run mode
php run.php --dry-run

# If successful, run actual backup
php run.php
```

## Rollback Procedures

### Quick Rollback

```bash
# If using Git
git checkout backup-$(date +%Y%m%d)

# If using manual backup
rm -rf php-backup-remote
mv php-backup-remote.backup.$(date +%Y%m%d) php-backup-remote

# Restore configuration
cp .env.backup.$(date +%Y%m%d) .env
cp config/app.php.backup.$(date +%Y%m%d) config/app.php
```

### Verify Rollback

```bash
# Test the rollback
php run.php --dry-run

# Check logs for errors
tail -f storage/logs/app.log
```

## Troubleshooting Common Issues

### Composer Dependency Issues

```bash
# Clear composer cache
composer clear-cache

# Remove vendor directory and reinstall
rm -rf libs/vendor
cd libs && composer install --no-dev
```

### PHP Extension Missing

```bash
# Install missing extensions (Ubuntu/Debian)
sudo apt-get install php8.2-curl php8.2-zip php8.2-mbstring

# Install missing extensions (CentOS/RHEL)
sudo yum install php-curl php-zip php-mbstring
```

### Permission Issues

```bash
# Fix file permissions
chmod +x run.php auto_update.sh
chmod 755 app/ config/ tests/
chmod 644 .env config/app.php

# Fix storage permissions
chmod 755 storage/ storage/logs/
chmod 644 storage/logs/*.log
```

### Configuration Migration Issues

```bash
# Validate configuration syntax
php -f config/app.php

# Check environment variables
php -r "var_dump(getenv());" | grep BACKUP
```

## Best Practices

### 1. Scheduled Updates

```bash
# Create update check script
cat > /etc/cron.d/backup-tool-update << 'EOF'
# Check for updates weekly
0 2 * * 1 root /path/to/php-backup-remote/auto_update.sh --check
EOF
```

### 2. Testing Strategy

- Always test in staging environment first
- Use `--dry-run` mode for validation
- Monitor logs during first few backups after update
- Keep rollback plan ready

### 3. Monitoring

```bash
# Monitor backup success after update
tail -f storage/logs/app.log | grep -E "(SUCCESS|ERROR|FAIL)"

# Check system resource usage
iostat -x 1 5
free -h
```

## Support and Documentation

### Getting Help

- **Issues:** https://github.com/xnetvn-com/php-backup-remote/issues
- **Documentation:** https://github.com/xnetvn-com/php-backup-remote/wiki
- **Email:** support@xnetvn.net

### Reporting Update Issues

When reporting update-related issues, include:

1. **Current version** and **target version**
2. **System information** (OS, PHP version, extensions)
3. **Error messages** from logs
4. **Configuration files** (anonymized)
5. **Steps to reproduce** the issue

## Changelog

For detailed changes between versions, see [CHANGELOG.md](CHANGELOG.md).

## Version History

| Version | Release Date | Status | Support End |
|---------|-------------|--------|-------------|
| 2.2.x   | 2025-01-15  | Current | TBD |
| 2.1.x   | 2024-12-01  | Stable | 2025-12-01 |
| 2.0.x   | 2024-06-01  | LTS | 2026-06-01 |
| 1.x.x   | 2023-01-01  | EOL | 2024-12-31 |

---

**Note:** Always backup your data and test updates in non-production environment first.
