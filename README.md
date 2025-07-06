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
composer install --no-dev --optimize-autoloader
```

### Quick Start

1. Clone the repository and enter directory:

   ```bash
   git clone https://github.com/xnetvn-com/php-backup-remote.git
   cd php-backup-remote
   ```

2. Install PHP dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader
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
# Example .env configuration
BACKUP_PASSWORD=your-super-secret-encryption-password
BACKUP_DIRS=/backup
BACKUP_COMPRESSION=none
BACKUP_ENCRYPTION=aes
ROTATION_ENABLED=true
ROTATION_KEEP_LATEST=7
S3_KEY=your-s3-access-key
S3_SECRET=your-s3-secret-key
S3_REGION=ap-southeast-1
S3_BUCKET=your-s3-bucket-name
B2_KEY=your-b2-application-key-id
B2_SECRET=your-b2-application-key
B2_BUCKET=your-b2-bucket-name
FTP_HOST=your-ftp-host
FTP_USER=your-ftp-username
FTP_PASS=your-ftp-password
EMAIL_SMTP_HOST=smtp.example.com
EMAIL_SMTP_USER=your-email@example.com
EMAIL_SMTP_PASS=your-email-password
ADMIN_EMAIL=admin-to-notify@example.com
TELEGRAM_BOT_TOKEN=your-telegram-bot-token
TELEGRAM_CHAT_ID=your-telegram-chat-id
DISCORD_WEBHOOK=your-discord-webhook-url
SLACK_WEBHOOK=your-slack-webhook-url
TEAMS_WEBHOOK=your-teams-webhook-url
GOOGLE_CHAT_WEBHOOK=your-google-chat-webhook-url
NOTIFY_INTERVAL_MINUTES=180
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
| BACKUP_COMPRESSION        | Compression method (`none`, `gzip`, `zstd`)                         | `none`              |
| BACKUP_ENCRYPTION         | Encryption method (`none`, `aes`, `gpg`)                           | `aes`               |
| REMOTE_DRIVER             | Override to use a single storage driver: `s3`, `b2`, `ftp` or `local` | *Not set*         |

### S3 Driver Settings

| Variable                  | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| S3_KEY                    | S3 access key                                                       |
| S3_SECRET                 | S3 secret key                                                       |
| S3_REGION                 | S3 region                                                           |
| S3_BUCKET                 | S3 bucket name                                                      |
| S3_ENDPOINT               | Custom endpoint (for non-AWS providers)                             |

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

### Rotation Settings

| Variable                  | Description                 | Default  |
|---------------------------|-----------------------------|----------|
| ROTATION_ENABLED          | Enable automatic rotation   | `true`   |
| ROTATION_KEEP_LATEST      | Number of recent backups to keep | `7` |

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
| EMAIL_SMTP_USER           | SMTP username                                                 | -       |
| EMAIL_SMTP_PASS           | SMTP password                                                 | -       |
| ADMIN_EMAIL               | Email address to receive notifications                        | -       |
| TELEGRAM_BOT_TOKEN        | Telegram bot token                                            | -       |
| TELEGRAM_CHAT_ID          | Telegram chat ID                                              | -       |
| NOTIFY_INTERVAL_MINUTES   | Cool-down period between notifications (minutes)              | `180`   |

#### Email Channel

Configure SMTP settings:

```ini
EMAIL_SMTP_HOST=smtp.example.com
EMAIL_SMTP_PORT=587
EMAIL_SMTP_USER=your-email@example.com
EMAIL_SMTP_PASS=your-smtp-password
EMAIL_SMTP_ENCRYPTION=tls
ADMIN_EMAIL=admin@example.com
```

#### Telegram Channel

```ini
TELEGRAM_BOT_TOKEN=your-telegram-bot-token
TELEGRAM_CHAT_ID=your-telegram-chat-id
```

#### Other Webhook Options

```ini
DISCORD_WEBHOOK=your-discord-webhook-url
SLACK_WEBHOOK=your-slack-webhook-url
TEAMS_WEBHOOK=your-teams-webhook-url
GOOGLE_CHAT_WEBHOOK=your-google-chat-webhook-url
NOTIFY_INTERVAL_MINUTES=180
```

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
