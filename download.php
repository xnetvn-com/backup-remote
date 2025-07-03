#!/usr/bin/env php
<?php
/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 */

declare(strict_types=1);

require_once __DIR__ . '/libs/vendor/autoload.php';

use App\Utils\Logger;
use App\Utils\Helper;
use App\Storage\StorageFactory;

$options = getopt('', ['user:', 'version:', 'remote::', 'outdir::']);
$username = $options['user'] ?? null;
$version = $options['version'] ?? null;
$remote = $options['remote'] ?? null;
$outdir = $options['outdir'] ?? null;

$env = getenv('APP_ENV') ?: 'development';
$envFile = ".env.$env";
if (file_exists(__DIR__ . "/$envFile")) {
    Dotenv\Dotenv::createImmutable(__DIR__, $envFile)->safeLoad();
} else {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$config = require __DIR__ . '/config/app.php';
$logger = Logger::getLogger();
$tmpDir = Helper::getTmpDir();
$downloadDir = rtrim($tmpDir, '/') . '/download';
if (!is_dir($downloadDir)) {
    if (!mkdir($downloadDir, 0770, true) && !is_dir($downloadDir)) {
        $logger->error("Không thể tạo thư mục download: $downloadDir");
        echo "[LỖI] Không thể tạo thư mục download: $downloadDir\n";
        exit(11);
    }
}
$outdir = $outdir ?: $downloadDir;

function cli_select($title, $options, $default = 0) {
    echo "\n$title\n";
    foreach ($options as $i => $opt) {
        echo "  [" . ($i+1) . "] $opt\n";
    }
    echo "Chọn số (1-" . count($options) . ") [mặc định: " . ($default+1) . "]: ";
    $input = trim(fgets(STDIN));
    if ($input === '' && isset($options[$default])) return $options[$default];
    $idx = (int)$input - 1;
    if ($idx >= 0 && $idx < count($options)) return $options[$idx];
    echo "Lựa chọn không hợp lệ.\n";
    return cli_select($title, $options, $default);
}

try {
    $remotes = Helper::detectAllRemotes();
    $targetRemote = null;
    if ($remote) {
        foreach ($remotes as $r) {
            if ($r['driver'] === $remote) {
                $targetRemote = $r;
                break;
            }
        }
    } else {
        $targetRemote = $remotes[0] ?? null;
    }
    if (!$targetRemote) {
        $logger->error("No valid remote storage found.");
        exit(2);
    }
    $storage = StorageFactory::create($targetRemote['driver'], $targetRemote);
    if (!$storage) {
        $logger->error("Failed to create storage adapter for remote: " . $targetRemote['driver']);
        exit(3);
    }
    // Quét danh sách file backup hợp lệ
    $listing = $storage->listContents('', true);
    $validExt = '(xenc|zst|gz|bz2|xz|zip|7z|tar|gpg)';
    $pattern = "/^([a-zA-Z0-9_.-]+)\\.(\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2})\\..*\\.$validExt$/";
    $backups = [];
    foreach ($listing as $item) {
        if ($item->isFile() && preg_match($pattern, $item->path(), $m)) {
            $user = $m[1];
            $ver = $m[2];
            $backups[$user][$ver][] = $item->path();
        }
    }
    // Nếu thiếu user hoặc version thì cho chọn
    if (!$username || !isset($backups[$username])) {
        $users = array_keys($backups);
        if (empty($users)) {
            $logger->error("No backup files found on remote.");
            exit(10);
        }
        $username = cli_select('Chọn user cần restore:', $users);
    }
    if (!$version || !isset($backups[$username][$version])) {
        $versions = array_keys($backups[$username]);
        rsort($versions); // mới nhất đầu tiên
        $version = cli_select('Chọn phiên bản backup:', $versions, 0);
    }
    // Chọn file backup phù hợp (ưu tiên file mới nhất, đúng pattern)
    $files = $backups[$username][$version];
    // Nếu có nhiều file (nén/mã hóa khác nhau), cho chọn
    if (count($files) > 1) {
        $file = cli_select('Chọn file backup (theo định dạng nén/mã hóa):', $files, 0);
    } else {
        $file = $files[0];
    }
    $logger->info("Đang tải file: $file");
    $localFile = rtrim($outdir, '/') . '/' . basename($file);
    $skipDownload = false;
    if (file_exists($localFile)) {
        echo "\n[CẢNH BÁO] File $localFile đã tồn tại. Bạn có muốn ghi đè? (y/N): ";
        $input = strtolower(trim(fgets(STDIN)));
        if ($input !== 'y' && $input !== 'yes') {
            $logger->warning("Người dùng chọn không ghi đè file đã tồn tại: $localFile. Sẽ sử dụng file hiện có để giải mã/giải nén.");
            echo "[BỎ QUA] Bỏ qua bước tải về, tiếp tục xử lý file hiện có: $localFile\n";
            $skipDownload = true;
        } else {
            $logger->info("Ghi đè file đã tồn tại: $localFile");
        }
    }
    if (!$skipDownload) {
        $stream = $storage->readStream($file);
        if (!$stream) {
            $logger->error("Failed to download file from remote: $file");
            exit(5);
        }
        $out = fopen($localFile, 'wb');
        if (!$out) {
            fclose($stream);
            $logger->error("Failed to open local file for writing: $localFile");
            exit(6);
        }
        while (!feof($stream)) {
            fwrite($out, fread($stream, 8192));
        }
        fclose($stream);
        fclose($out);
        $logger->info("Downloaded file: $localFile");
    }
    // Giải mã và giải nén tự động
    $password = Helper::env('BACKUP_PASSWORD');
    $decrypted = $localFile;
    $decryptOk = true;
    if (str_ends_with($localFile, '.xenc')) {
        $decrypted = preg_replace('/\.xenc$/', '', $localFile);
        $decryptOk = Helper::decryptFile($localFile, $decrypted, $password);
        if ($decryptOk) {
            $logger->info("Decrypted: $decrypted");
        } else {
            $logger->error("Giải mã AES thất bại. Kiểm tra mật khẩu hoặc file nguồn.");
            echo "[LỖI] Giải mã AES thất bại. File đầu ra có thể không hợp lệ!\n";
        }
    } elseif (str_ends_with($localFile, '.gpg')) {
        $decrypted = preg_replace('/\.gpg$/', '', $localFile);
        $decryptOk = Helper::gpgDecryptFile($localFile, $decrypted, $password);
        if ($decryptOk) {
            $logger->info("GPG decrypted: $decrypted");
        } else {
            $logger->error("Giải mã GPG thất bại. Kiểm tra mật khẩu hoặc file nguồn.");
            echo "[LỖI] Giải mã GPG thất bại. File đầu ra có thể không hợp lệ!\n";
        }
    } elseif (str_ends_with($localFile, '.zst')) {
        $decrypted = preg_replace('/\.zst$/', '', $localFile);
        $decryptOk = Helper::zstdDecryptFile($localFile, $decrypted, $password);
        if ($decryptOk) {
            $logger->info("Zstd decrypted: $decrypted");
        } else {
            $logger->error("Giải mã Zstd thất bại. Kiểm tra mật khẩu hoặc file nguồn.");
            echo "[LỖI] Giải mã Zstd thất bại. File đầu ra có thể không hợp lệ!\n";
        }
    }
    // Giải nén nếu cần (chưa implement extraction thực tế)
    $final = $decrypted;
    $extractionNeeded = preg_match('/\.(tar\.gz|tar\.zst|tar\.bz2|tar\.xz|tar|zip|7z)$/', $decrypted);
    if ($extractionNeeded) {
        // TODO: implement extraction logic if needed
        $logger->warning("Chưa thực thi giải nén tự động cho định dạng archive. File trả về vẫn là archive.");
        echo "[CẢNH BÁO] File backup là archive (tar/zip/7z...). Bạn cần tự giải nén thủ công nếu cần.\n";
        $logger->info("Backup archive ready: $decrypted");
    }
    $logger->info("Backup file for user=$username, version=$version is ready at $final");
    clearstatcache();
    if (is_file($final)) {
        $size = filesize($final);
        $sizeStr = Helper::formatSize($size);
        if ($size === 0) {
            echo "\n[WARNING] File đầu ra có kích thước 0 B. Có thể quá trình giải mã/giải nén đã thất bại hoặc file nguồn rỗng.\n";
            $logger->warning("File đầu ra $final có kích thước 0 B. Kiểm tra lại quá trình restore.");
        }
        echo "\n[OK] Đã hoàn tất. Đường dẫn file: $final\n";
        echo "Kích thước: $sizeStr\n";
    } else {
        echo "\n[WARNING] Không tìm thấy file đầu ra: $final\n";
        $logger->error("Không tìm thấy file đầu ra: $final");
    }
    exit(0);
} catch (Throwable $e) {
    $logger->error('Download error: ' . $e->getMessage(), ['exception' => $e]);
    exit(7);
}
