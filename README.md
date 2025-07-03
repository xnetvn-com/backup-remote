# HestiaCP Remote Backup Tool

A robust PHP script designed to automate the backup of Hestia Control Panel user data to various remote storage providers. It's built with modern PHP practices, focusing on reliability, efficiency, and ease of use, especially on servers with limited resources.

## Features

- **Multiple Storage Backends**: Seamlessly upload backups to any S3-compatible object storage (like AWS S3, Backblaze B2, DigitalOcean Spaces) or traditional FTP servers, thanks to [Flysystem](https://flysystem.thephpleague.com/).
- **Strong Encryption**: Secure your backups with AES-256-CBC encryption before they leave your server.
- **Compression**: Backups are compressed into `.tar.gz` format to save storage space.
- **Smart Backup Rotation**: Automatically manages backup history by keeping a configurable number of daily, weekly, and monthly backups, pruning old archives to free up space.
- **Resource-Aware**: Includes pre-flight checks for CPU load and available disk space to prevent backups from overwhelming your server.
- **Detailed Logging**: Comprehensive logging using [Monolog](https://github.com/Seldaek/monolog) provides clear insight into every step of the process. Logs are stored in `storage/logs/app.log`.
- **Multi-Channel Notifications**: Get notified about backup success or failure through various channels like Email and Telegram.
- **Flexible Configuration**: All settings are managed through a simple `.env` file.
- **Dry-Run Mode**: Safely test your configuration and the backup logic without performing any actual file operations or uploads.
- **Professional Codebase**: Written in modern PHP 8.2+, follows PSR-12 coding standards, and is fully object-oriented.

## Requirements

- PHP 8.2 or higher
- Composer
- The following PHP extensions:
  - `ctype`
  - `mbstring`
  - `openssl`
  - `ftp` (if using the FTP driver)

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/xnetvn-com/hestia-remote-backup.git
    cd hestia-remote-backup
    ```

2.  **Install dependencies:**
    Make sure you have Composer installed.
    ```bash
    composer install --no-dev --optimize-autoloader
    ```

3.  **Create your environment file:**
    Copy the example environment file and edit it with your settings.
    ```bash
    cp .env.example .env
    ```

4.  **Configure the application:**
    Open the `.env` file and fill in your specific details. See the **Configuration** section below for a detailed explanation of all variables.

## Configuration

All configuration is done in the `.env` file.

#### General Settings
| Variable | Description | Default |
| --- | --- | --- |
| `APP_NAME` | The name of your application. | "Hestia Backup" |
| `APP_ENV` | The application environment. | `production` |
| `APP_DEBUG` | Enable/disable debug mode. **Should be `false` in production.** | `false` |
| `LOG_LEVEL` | The minimum log level to record. | `info` |
| `ENCRYPTION_PASSWORD` | **Crucial!** The password used to encrypt your backup files. **Choose a strong, unique password and save it securely.** | `YourSecretPassword` |

---

#### Remote Storage
Set up the primary remote storage driver.

| Variable | Description |
| --- | --- |
| `REMOTE_DRIVER` | The storage driver to use. Supported: `s3`, `ftp`. |
| `REMOTE_PATH` | The base directory on the remote storage where backups will be stored. |

**S3 Driver Settings (`REMOTE_DRIVER=s3`)**
| Variable | Description |
| --- | --- |
| `AWS_ACCESS_KEY_ID` | Your S3-compatible access key. |
| `AWS_SECRET_ACCESS_KEY` | Your S3-compatible secret key. |
| `AWS_DEFAULT_REGION` | The region of your S3 bucket. |
| `AWS_BUCKET` | The name of your S3 bucket. |
| `AWS_ENDPOINT` | The endpoint URL. **Required for non-AWS S3-compatible services** (e.g., Backblaze, DigitalOcean). |

**FTP Driver Settings (`REMOTE_DRIVER=ftp`)**
| Variable | Description |
| --- | --- |
| `FTP_HOST` | FTP server hostname. |
| `FTP_USERNAME` | FTP username. |
| `FTP_PASSWORD` | FTP password. |
| `FTP_PORT` | FTP port. |
| `FTP_ROOT` | The root path to use on the FTP server. |
| `FTP_SSL` | Whether to use a secure FTP connection (FTPS). |

---

#### Backup Rotation
| Variable | Description | Default |
| --- | --- | --- |
| `ROTATION_ENABLED` | Set to `true` to enable automatic backup rotation. | `true` |
| `ROTATION_DAILY` | Number of daily backups to keep. | `7` |
| `ROTATION_WEEKLY` | Number of weekly backups to keep. | `4` |
| `ROTATION_MONTHLY` | Number of monthly backups to keep. | `6` |

---

#### Performance
| Variable | Description | Default |
| --- | --- | --- |
| `ALLOWED_START_TIME` | The start of the allowed backup window (e.g., '01:00'). Leave empty to disable. | `01:00` |
| `ALLOWED_END_TIME` | The end of the allowed backup window (e.g., '05:00'). Leave empty to disable. | `05:00` |
| `MAX_CPU_LOAD` | Aborts if the 1-min CPU load average exceeds this value. `0` to disable. | `2.5` |
| `MIN_DISK_FREE_PERCENT` | Aborts if free disk space in the temp directory is below this percentage. | `15` |
| `MEMORY_LIMIT` | PHP memory limit for the script (e.g., '256M'). | `256M` |
| `TIME_LIMIT` | Maximum script execution time in seconds. `0` for no limit. | `3600` |

---

#### Notifications
| Variable | Description | Default |
| --- | --- | --- |
| `NOTIFICATION_ENABLED` | Set to `true` to enable notifications. | `true` |
| `NOTIFICATION_CHANNELS` | Comma-separated list of channels to use. Supported: `email`, `telegram`. | `email` |
| `ALERT_THROTTLE_MINUTES` | Avoids spamming alerts for the same issue by setting a cool-down period. | `60` |

**Email Channel Settings**
| Variable | Description |
| --- | --- |
| `MAIL_MAILER` | `smtp` is recommended. |
| `MAIL_HOST` | Your SMTP server host. |
| `MAIL_PORT` | Your SMTP server port. |
| `MAIL_USERNAME` | Your SMTP username. |
| `MAIL_PASSWORD` | Your SMTP password. |
| `MAIL_ENCRYPTION` | `tls` or `ssl`. |
| `MAIL_FROM_ADDRESS` | The "From" email address. |
| `MAIL_FROM_NAME` | The "From" name. |
| `MAIL_TO_ADDRESS` | The recipient's email address. |

**Telegram Channel Settings**
| Variable | Description |
| --- | --- |
| `TELEGRAM_BOT_TOKEN` | Your Telegram Bot's API token. |
| `TELEGRAM_CHAT_ID` | The target chat/channel ID. |

## Usage

### Running Manually

You can execute the script directly from the command line.

-   **Perform a real backup:**
    ```bash
    php /path/to/your/project/run.php
    ```

-   **Perform a dry run (recommended for testing):**
    A dry run will simulate the entire process—finding users, creating archives, and planning rotations—without actually creating, encrypting, uploading, or deleting any files. All actions will be logged.
    ```bash
    php /path/to/your/project/run.php --dry-run
    ```

### Automating with a Cron Job

To run the backup automatically every night, set up a cron job.

1.  Open the crontab editor:
    ```bash
    crontab -e
    ```

2.  Add the following line to run the backup script every day at 2:30 AM. Adjust the time and path as needed.
    ```cron
    30 2 * * * /usr/bin/php /path/to/your/project/run.php > /dev/null 2>&1
    ```
    This command redirects all output to `/dev/null` to prevent cron from sending unnecessary emails. The script's own logging will capture all important information in `storage/logs/app.log`.

## How It Works

1.  **Initialization**: Loads configuration from `.env` and sets up the logger.
2.  **Pre-flight Checks**: `SystemChecker` verifies that CPU load and disk space are within the configured limits and that the script is running within the allowed time window.
3.  **Find Users**: `LocalFinder` scans the `/home/` directory to find all active HestiaCP user accounts.
4.  **Process Each User**: For each user found:
    a. **Archive**: `ArchiveHandler` creates a `.tar.gz` archive of the user's home directory.
    b. **Encrypt**: The archive is then encrypted using OpenSSL with AES-256-CBC.
    c. **Upload**: The encrypted archive is streamed to the configured remote storage using `StorageFactory` and Flysystem.
    d. **Cleanup**: The local temporary archive is deleted.
5.  **Rotate Backups**: `RotationManager` connects to the remote storage, lists all existing backups for the project, and deletes old archives according to the daily, weekly, and monthly retention policy.
6.  **Notification**: `NotificationManager` sends a summary of the operation (success or failure) via the configured channels.

## License

This project is open-source software licensed under the [MIT license](LICENSE).