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
| APP_NAME                  | Application name                                                    | "Hestia Backup"    |
| APP_ENV                   | Environment (`production` or `development`)                         | `production`        |
| APP_DEBUG                 | Enable debug mode (`true`/`false`)                                  | `false`             |
| LOG_LEVEL                 | Minimum log level (`debug`, `info`, `warning`, `error`)             | `info`              |
| ENCRYPTION_PASSWORD       | Password for AES or GPG encryption (choose a strong secret)         | *REQUIRED*          |
| BACKUP_DIRS               | Comma-separated absolute paths to backup user directories           | `/backup`           |
| REMOTE_DRIVER             | Storage driver: `s3` or `ftp`                                       | `s3`                |
| REMOTE_PATH               | Base path or folder in remote storage                               | `backups/`          |

### S3 Driver Settings

| Variable                  | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| AWS_ACCESS_KEY_ID         | S3 access key                                                       |
| AWS_SECRET_ACCESS_KEY     | S3 secret key                                                       |
| AWS_DEFAULT_REGION        | S3 region                                                           |
| AWS_BUCKET                | S3 bucket name                                                      |
| AWS_ENDPOINT              | Custom endpoint (for non-AWS providers)                             |

### FTP Driver Settings

| Variable                  | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| FTP_HOST                  | FTP server hostname                                                 |
| FTP_USERNAME              | FTP username                                                        |
| FTP_PASSWORD              | FTP password                                                        |
| FTP_PORT                  | FTP port (default `21`)                                              |
| FTP_ROOT                  | Base directory on FTP server                                        |
| FTP_SSL                   | `true` or `false` for FTPS                                          |
| FTP_PASSIVE               | `true` (passive) or `false` (active)                                 |

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
| NOTIFICATION_ENABLED      | Enable notifications                                          | `true`  |
| NOTIFICATION_CHANNELS     | Comma-separated: `email`, `telegram`                          | `email` |
| ALERT_THROTTLE_MINUTES    | Cool-down period between alerts                               | `60`    |

#### Email Channel

Configure SMTP settings:

```ini
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
MAIL_TO_ADDRESS=
```

#### Telegram Channel

```ini
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

## Usage

### Run Backup

- **Real backup:**
  ```bash
  php run.php
  ```
- **Dry-run (simulate):**
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