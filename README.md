# HestiaCP Remote Backup Tool

A robust PHP command-line utility to automate the backup of Hestia Control Panel user data to various remote storage providers. It supports encryption, compression, retention policies, and multi-channel notifications, optimized for servers with limited resources.

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

## Installation

```bash
git clone https://github.com/xnetvn-com/php-backup-remote.git
cd php-backup-remote
composer install --no-dev --optimize-autoloader
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

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines. Report issues using [bug report template](.github/ISSUE_TEMPLATE/bug_report.md) and request features via [feature request template](.github/ISSUE_TEMPLATE/feature_request.md).

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE](LICENSE) file for details.
