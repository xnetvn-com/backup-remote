# HestiaCP Remote Backup Tool

[![Build Status](https://github.com/xnetvn-com/php-backup-remote/actions/workflows/ci.yml/badge.svg)](https://github.com/xnetvn-com/php-backup-remote/actions)
[![Coverage Status](https://codecov.io/gh/xnetvn-com/php-backup-remote/branch/main/graph/badge.svg)](https://codecov.io/gh/xnetvn-com/php-backup-remote)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](LICENSE)

A robust PHP command-line utility to automate the backup of Hestia Control Panel user data to various remote storage providers. It supports encryption, compression, retention policies, and multi-channel notifications, optimized for servers with limited resources.

## Project Structure

```text
├── app/                # Application source code (Backup, Notification, Storage, System, Utils)
├── config/             # Configuration files (app.php, constant.php)
├── libs/               # Composer dependencies and autoload
├── storage/            # Logs and runtime data
├── tests/              # PHPUnit test cases (unit, integration, E2E, hardening)
├── tmp/                # Temporary working directory for backup operations
├── .github/            # GitHub Actions, issue templates, CODEOWNERS, PR template
├── .env.example        # Example environment configuration
├── run.php             # Main entry point for backup
├── download.php        # Restore utility
├── upload.php          # (Optional) Upload utility
├── README.md           # Project documentation
├── LICENSE             # Apache License 2.0
├── NOTICE              # Attribution notice
└── CONTRIBUTING.md     # Contribution guidelines
```

## Features

- **Multiple Storage Backends**: Upload encrypted backups to any S3-compatible service (AWS S3, Backblaze B2, DigitalOcean Spaces) or traditional FTP servers via [Flysystem](https://flysystem.thephpleague.com/).
- **Strong Encryption**: AES-256-CBC encryption (via OpenSSL) or GPG encryption before upload.
- **Flexible Compression**: Support for gzip, zstd, bzip2, xz, zip, 7z formats.
- **Smart Rotation**: Retention policies to keep a configured number of daily, weekly, or custom backups and prune old archives automatically.
- **Dry-Run Mode**: Safe simulation without creating, uploading, or deleting files.
- **Detailed Logging**: Structured logs with Monolog stored in `storage/logs/app.log`.
- **Pre-flight Checks**: CPU load, disk space, and time-window checks to prevent resource exhaustion.
- **Notifications**: Email and Telegram notifications on success or failure.
- **PSR-12 & Modern PHP**: Written in PHP 8.2+, fully object-oriented, following best practices.

## Requirements

- PHP 8.2 or higher
- Composer
- PHP Extensions: `ctype`, `mbstring`, `openssl`, `ftp` (for FTP driver)
- CLI Tools for compression/encryption:
  - gzip (install: `sudo apt install gzip`)
  - zstd (install: `sudo apt install zstd`)
  - bzip2 (install: `sudo apt install bzip2`)
  - xz-utils (install: `sudo apt install xz-utils`)
  - zip & unzip (install: `sudo apt install zip unzip`)
  - p7zip-full (install: `sudo apt install p7zip-full`)
  - gnupg (install: `sudo apt install gnupg`)

### Supported Platforms

This tool has been tested on the following operating systems:

- **Debian/Ubuntu**

  ```bash
  sudo apt update && sudo apt install -y php-cli php-mbstring php-common \
    unzip zip gzip zstd bzip2 xz-utils p7zip-full gnupg composer
  ```

- **CentOS/RHEL**

  ```bash
  sudo yum install -y epel-release && sudo yum install -y php-cli php-mbstring php-common \
    unzip zip gzip zstd bzip2 xz p7zip p7zip-plugins gnupg composer
  ```

- **macOS**

  ```bash
  brew update && brew install php composer zstd xz p7zip gnupg
  ```

## Installation

```bash
git clone https://github.com/xnetvn-com/php-backup-remote.git
cd php-backup-remote
cd libs
composer install --no-dev --optimize-autoloader
cd ..
```

### Auto-Update (Recommended)

For safe and convenient updates to the latest version:

```bash
./auto_update.sh
```

**Note:**

- The script will automatically detect the default branch (`main` or `master`) from the remote repository.

- If the current directory is a Git repository, it will fetch and **hard reset** to match the remote branch (all local changes will be overwritten).

- If the current directory is not a Git repository, it will clone the correct branch and copy files into the current directory.

- Your configuration files and logs will be preserved.

- Requires `git` to be installed.

> ⚠️ **Warning:** This script will overwrite any local changes in the repository directory. Make sure to back up your work if needed.

See [UPDATE_GUIDE.md](UPDATE_GUIDE.md) for detailed instructions.

### Quick Start

1. Clone the repository and enter directory:

   ```bash
   git clone https://github.com/xnetvn-com/php-backup-remote.git
   cd php-backup-remote
   ```

2. Install PHP dependencies (production):

   ```bash
   cd libs
   composer install --no-dev --optimize-autoloader
   cd ..
   ```

   For development (with dev dependencies):

   ```bash
   cd libs
   composer install
   cd ..
   ```

3. Copy and customize environment settings:

   ```bash
   cp .env.example .env
   # Edit .env to configure backup paths, encryption, compression, and storage drivers
   ```

4. Run a dry-run to verify settings:

   ```bash
   php run.php --dry-run
   ```

5. Execute a real backup:

   ```bash
   php run.php
   ```

## Example .env Configuration

```ini
# Core backup settings
BACKUP_PASSWORD=your-super-secret-encryption-password
BACKUP_DIRS=/backup,/home,/var/www
BACKUP_COMPRESSION=gzip   # Options: none, gzip, zstd, bzip2, xz, zip, 7z
BACKUP_COMPRESSION_LEVEL=6
BACKUP_ENCRYPTION=aes     # Options: none, aes, gpg, zip, 7z
BACKUP_ENCRYPTION_KEY_PATH=/path/to/public.key # For GPG (optional)
TMP_DIR=/tmp/php-backup-remote

# Advanced compression and encryption combinations:
# - BACKUP_COMPRESSION=7z + BACKUP_ENCRYPTION=7z: Uses 7z CLI for both compression and encryption in one step
# - BACKUP_COMPRESSION=zip + BACKUP_ENCRYPTION=zip: Uses zip CLI with AES-256 encryption in one step
# - BACKUP_COMPRESSION=7z + BACKUP_ENCRYPTION=none: Uses 7z CLI for compression only
# - BACKUP_COMPRESSION=zip + BACKUP_ENCRYPTION=none: Uses zip CLI for compression only
# - Separate steps: BACKUP_COMPRESSION=gzip + BACKUP_ENCRYPTION=aes (compress first, then encrypt)

# Rotation & retention
ROTATION_ENABLED=true
ROTATION_KEEP_LATEST=7
ROTATION_KEEP_DAILY=7
ROTATION_KEEP_WEEKLY=4
ROTATION_KEEP_MONTHLY=12
ROTATION_KEEP_YEARLY=3
ROTATION_PATTERN=*.xbk.*

# Remote storage (S3, B2, FTP, Local)
REMOTE_DRIVER=s3          # Options: s3, b2, ftp, local

# S3 settings
S3_KEY=your-s3-access-key
S3_SECRET=your-s3-secret-key
S3_REGION=ap-southeast-1
S3_BUCKET=your-s3-bucket-name
S3_ENDPOINT=https://s3.example.com
S3_USE_PATH_STYLE=true

# Backblaze B2 settings
B2_KEY=your-b2-application-key-id
B2_SECRET=your-b2-application-key
B2_BUCKET=your-b2-bucket-name
B2_REGION=us-west-002

# FTP settings
FTP_HOST=your-ftp-host
FTP_USER=your-ftp-username
FTP_PASS=your-ftp-password
FTP_ROOT=/backups
FTP_SSL=true
FTP_PASSIVE=true

# Local storage
LOCAL_PATH=/mnt/backup-disk

# Performance & resource limits
ALLOWED_START_TIME=01:00
ALLOWED_END_TIME=05:00
MAX_CPU_LOAD=2.5
MIN_DISK_FREE_PERCENT=15
MEMORY_LIMIT=256M
TIME_LIMIT=3600

# Notification settings
EMAIL_SMTP_HOST=smtp.example.com
EMAIL_SMTP_PORT=587
EMAIL_SMTP_USER=your-email@example.com
EMAIL_SMTP_PASS=your-smtp-password
EMAIL_SMTP_ENCRYPTION=tls
ADMIN_EMAIL=admin@example.com

TELEGRAM_BOT_TOKEN=your-telegram-bot-token
TELEGRAM_CHAT_ID=your-telegram-chat-id

DISCORD_WEBHOOK=your-discord-webhook-url
SLACK_WEBHOOK=your-slack-webhook-url
TEAMS_WEBHOOK=your-teams-webhook-url
GOOGLE_CHAT_WEBHOOK=your-google-chat-webhook-url
NOTIFY_INTERVAL_MINUTES=180

# Advanced options
DRY_RUN=false
LOG_CHANNEL=app
LOG_PATH=storage/logs/app.log
LOG_LEVEL=info
LOCK_FILE=storage/.backup.lock

# Security
ENFORCE_READONLY=true
SAFE_MODE=true

# Developer/debug
DEBUG=false
VERBOSE=false

# Custom user hooks (optional)
PRE_BACKUP_HOOK=/path/to/pre-backup.sh
POST_BACKUP_HOOK=/path/to/post-backup.sh

# Example for multiple backup sources
# BACKUP_DIRS=/backup,/home/user1,/home/user2,/var/www/html
```

## Configuration

Copy the example environment file and edit your settings:

```bash
cp .env.example .env
```

Open `.env` and configure:

| Variable                  | Description                                                         | Default             |
|---------------------------|---------------------------------------------------------------------|---------------------|
| BACKUP_PASSWORD           | Password for AES or GPG encryption (choose a strong secret)         | *REQUIRED*          |
| BACKUP_DIRS               | Comma-separated absolute paths to backup user directories           | `/backup`           |
| BACKUP_COMPRESSION        | Compression method (`none`, `gzip`, `zstd`, `bzip2`, `xz`, `zip`, `7z`) | `none`              |
| BACKUP_COMPRESSION_LEVEL  | Compression level (1-9, default 6)                                  | `6`                  |
| BACKUP_ENCRYPTION         | Encryption method (`none`, `aes`, `gpg`, `zip`, `7z`)              | `aes`               |
| BACKUP_ENCRYPTION_KEY_PATH| Path to public key for GPG encryption (optional)                   | *Not set*         |
| REMOTE_DRIVER             | Override to use a single storage driver: `s3`, `b2`, `ftp` or `local` | *Not set*         |
| TMP_DIR                   | Temporary directory for backup operations                            | `/tmp/php-backup-remote` |

### Compression & Encryption CLI Usage

| Configuration                                | CLI Tools Used              | Description                                    |
|----------------------------------------------|------------------------------|------------------------------------------------|
| `BACKUP_COMPRESSION=7z` + `BACKUP_ENCRYPTION=7z` | `7z a` (single step)    | 7z CLI compresses and encrypts in one command |
| `BACKUP_COMPRESSION=zip` + `BACKUP_ENCRYPTION=zip` | `zip -e` (single step) | zip CLI compresses with traditional encryption |
| `BACKUP_COMPRESSION=7z` + `BACKUP_ENCRYPTION=none` | `7z a` (compression only) | 7z CLI for compression only                  |
| `BACKUP_COMPRESSION=zip` + `BACKUP_ENCRYPTION=none` | `zip` (compression only) | zip CLI for compression only                 |
| `BACKUP_COMPRESSION=gzip` + `BACKUP_ENCRYPTION=aes` | `gzip` + `openssl`     | Separate compression and encryption steps     |
| `BACKUP_COMPRESSION=zstd` + `BACKUP_ENCRYPTION=gpg` | `zstd` + `gpg`         | Separate compression and encryption steps     |

**Note**: The zip CLI uses traditional password-based encryption (not AES-256). For stronger encryption, use 7z or separate AES encryption.

### S3 Driver Settings

| Variable                  | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| S3_KEY                    | S3 access key                                                       |
| S3_SECRET                 | S3 secret key                                                       |
| S3_REGION                 | S3 region                                                           |
| S3_BUCKET                 | S3 bucket name                                                      |
| S3_ENDPOINT               | Custom endpoint (for non-AWS providers)                             |
| S3_USE_PATH_STYLE         | `true` if using path-style URLs (e.g., DigitalOcean Spaces)       |

### Backblaze B2 Settings

| Variable                  | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| B2_KEY                    | Backblaze B2 application key ID                                     |
| B2_SECRET                 | Backblaze B2 application key                                        |
| B2_BUCKET                 | Backblaze B2 bucket name                                            |
| B2_REGION                 | Backblaze B2 region (optional)                                      |

### FTP Driver Settings

| Variable                  | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| FTP_HOST                  | FTP server hostname                                                 |
| FTP_USER                  | FTP username                                                        |
| FTP_PASS                  | FTP password                                                        |
| FTP_ROOT                  | Base directory on FTP server (optional)                             |
| FTP_SSL                   | `true` or `false` for FTPS (optional)                               |
| FTP_PASSIVE               | `true` (passive) or `false` (active) (recommended: `true`)          |

### Local Storage Settings

| Variable                  | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| LOCAL_PATH                | Local file system path for backups                                  |

### Rotation Settings

| Variable                  | Description                 | Default  |
|---------------------------|-----------------------------|----------|
| ROTATION_ENABLED          | Enable automatic rotation   | `true`   |
| ROTATION_KEEP_LATEST      | Number of recent backups to keep | `7` |
| ROTATION_KEEP_DAILY       | Number of daily backups to keep | `7` |
| ROTATION_KEEP_WEEKLY      | Number of weekly backups to keep | `4` |
| ROTATION_KEEP_MONTHLY     | Number of monthly backups to keep | `12` |
| ROTATION_KEEP_YEARLY      | Number of yearly backups to keep | `3` |
| ROTATION_PATTERN          | Pattern to match backup files for rotation | `*.xbk.*` |

### Performance Limits

| Variable                  | Description                                                       | Default |
|---------------------------|-------------------------------------------------------------------|----------|
| ALLOWED_START_TIME        | Earliest allowed backup time (`HH:MM`), empty to disable           | `01:00`  |
| ALLOWED_END_TIME          | Latest allowed backup time (`HH:MM`), empty to disable            | `05:00`  |
| MAX_CPU_LOAD              | Max 1-min CPU load average, 0 to disable                           | `2.5`    |
| MIN_DISK_FREE_PERCENT     | Minimum free disk % in temp dir                                    | `15`     |
| MEMORY_LIMIT              | PHP memory limit (e.g., `256M`)                                     | `256M`   |
| TIME_LIMIT                | Max script time in seconds, 0 for unlimited                        | `3600`   |

### Notification Settings

| Variable                  | Description                                                    | Default |
|---------------------------|----------------------------------------------------------------|----------|
| EMAIL_SMTP_HOST           | SMTP server host                                              | -       |
| EMAIL_SMTP_PORT           | SMTP server port                                              | `587`   |
| EMAIL_SMTP_USER           | SMTP username                                                 | -       |
| EMAIL_SMTP_PASS           | SMTP password                                                 | -       |
| EMAIL_SMTP_ENCRYPTION     | SMTP encryption method (`tls`, `ssl`, or empty for none)    | `tls`   |
| ADMIN_EMAIL               | Email address to receive notifications                        | -       |
| TELEGRAM_BOT_TOKEN        | Telegram bot token                                            | -       |
| TELEGRAM_CHAT_ID          | Telegram chat ID                                              | -       |
| DISCORD_WEBHOOK           | Discord webhook URL                                           | -       |
| SLACK_WEBHOOK             | Slack webhook URL                                             | -       |
| TEAMS_WEBHOOK             | Microsoft Teams webhook URL                                   | -       |
| GOOGLE_CHAT_WEBHOOK       | Google Chat webhook URL                                       | -       |
| NOTIFY_INTERVAL_MINUTES   | Cool-down period between notifications (minutes)              | `180`   |

### Advanced Options

| Variable                  | Description                                                    | Default |
|---------------------------|----------------------------------------------------------------|---------|
| DRY_RUN                   | Simulate backup without writing/uploading files                | `false` |
| LOG_CHANNEL               | Log channel name (for Monolog)                                 | `app`   |
| LOG_PATH                  | Path to log file                                               | `storage/logs/app.log` |
| LOG_LEVEL                 | Log level (`info`, `debug`, `error`, etc.)                     | `info`  |
| LOCK_FILE                 | Path to lock file to prevent concurrent runs                   | `storage/.backup.lock` |

### Security Options

| Variable                  | Description                                                    | Default |
|---------------------------|----------------------------------------------------------------|---------|
| ENFORCE_READONLY          | Enforce read-only mode for BACKUP_DIRS                         | `true`  |
| SAFE_MODE                 | Enable extra safety checks (recommended)                       | `true`  |

### Developer/Debug Options

| Variable                  | Description                                                    | Default |
|---------------------------|----------------------------------------------------------------|---------|
| DEBUG                     | Enable debug mode                                              | `false` |
| VERBOSE                   | Enable verbose output                                          | `false` |

### Custom User Hooks

| Variable                  | Description                                                    | Default |
|---------------------------|----------------------------------------------------------------|---------|
| PRE_BACKUP_HOOK           | Path to script to run before backup                            | *Not set* |
| POST_BACKUP_HOOK          | Path to script to run after backup                             | *Not set* |

## Security & Data Integrity

- **Read-Only Guarantee for BACKUP_DIRS**: All files and directories specified in `BACKUP_DIRS` are treated as strictly read-only. The backup system will never modify, delete, move, or overwrite any original file in these directories. All backup, compression, and encryption operations are performed on temporary copies in a dedicated temp directory (`TMP_DIR`). This ensures absolute safety and integrity of your source data.
- **Automated Tests**: The project includes automated tests to verify that no write, delete, or move operations are ever performed directly in `BACKUP_DIRS`.
- **Best Practice**: Always set appropriate file system permissions to enforce read-only access for the backup process user on your backup source directories.

## Usage

### Run Backup

**Real backup:**

```bash
php run.php
```

**Dry-run (simulate):**

```bash
php run.php --dry-run
```

### Restore Backup

```bash
php download.php --user=<username> --version=<YYYY-MM-DD_HH-MM-SS> [--remote=<driver>] [--outdir=<path>]
```

### Automate with Cron

```cron
30 2 * * * /usr/bin/php /path/to/php-backup-remote/run.php > /dev/null 2>&1
```

## How It Works

1. **Initialization**: Load `.env` and bootstrap services.
2. **Locking**: Prevent concurrent runs with a lock file.
3. **Pre-flight Checks**: CPU, disk space, and time-window validation.
4. **Archive**: Create compressed archive per user directory.
5. **Encrypt**: Encrypt archive with AES or GPG.
6. **Upload**: Stream to configured remote storage.
7. **Cleanup**: Remove local temporary files and lock.
8. **Rotation**: List remote files and delete older backups beyond policy.
9. **Notification**: Send success or failure alerts.

## Testing

Run PHPUnit unit tests:

```bash
./libs/vendor/bin/phpunit --configuration=phpunit.xml
```

## Contributing

We welcome contributions from the community! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines. All contributors must follow the [CODEOWNERS](.github/CODEOWNERS) and use the provided [pull request template](.github/pull_request_template.md). Issues and feature requests should use the appropriate [issue templates](.github/ISSUE_TEMPLATE/).

- **Security Contact:** Please report security vulnerabilities via [security_vulnerability.md](.github/ISSUE_TEMPLATE/security_vulnerability.md) or email [license@xnetvn.net](mailto:license@xnetvn.net).
- **CI/CD:** All code is automatically tested, linted, scanned for secrets and vulnerabilities, and must pass all checks before merging.

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE](LICENSE) file for details.
