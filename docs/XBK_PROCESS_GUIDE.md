# Quy Trình Xử Lý File Backup với Marker .xbk

## Tổng Quan

Hệ thống backup đã được cải tiến để sử dụng marker `.xbk` (xNetVN Backup) nhằm nhận diện và xử lý các file đã được nén và mã hóa. Điều này đảm bảo quá trình khôi phục được thực hiện chính xác theo đúng thứ tự và phương pháp đã sử dụng khi tạo backup.

## Quy Trình Tạo Backup (Upload/Run)

### Cấu Trúc Tên File

```text
{original_filename}.xbk[.{compression_ext}][.{encryption_ext}]
```

### Ví Dụ Các Định Dạng

| Phương Pháp | File Gốc | File Sau Xử Lý | Mô Tả |
|-------------|----------|-----------------|--------|
| **7z + None** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.7z` | Nén bằng 7z, không mã hóa |
| **Gzip + GPG** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.gz.gpg` | Nén bằng gzip, mã hóa bằng GPG |
| **Zstd + AES** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.zst.aes` | Nén bằng zstd, mã hóa bằng AES |
| **None + AES** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.aes` | Không nén, chỉ mã hóa AES |
| **Zip + None** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.zip` | Nén bằng zip, không mã hóa |
| **Bzip2 + GPG** | `xtest.2025-06-02_06-02-16.tar` | `xtest.2025-06-02_06-02-16.tar.xbk.bz2.gpg` | Nén bằng bzip2, mã hóa bằng GPG |

### Thứ Tự Xử Lý (Backup)

1. **Tạo Archive TAR** (nếu cần)
2. **Nén** (compression) - nếu được kích hoạt
3. **Mã Hóa** (encryption) - nếu được kích hoạt
4. **Upload** lên remote storage

## Quy Trình Khôi Phục (Download)

### Nhận Diện File

Hệ thống sử dụng pattern sau để nhận diện file backup:

```regex
/^([a-zA-Z0-9_.-]+)\.(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.tar(?:\.xbk)?(?:\.(gz|bz2|xz|zst|zip|7z))?(?:\.(aes|gpg))?$/
```

### Thứ Tự Xử Lý (Khôi Phục)

1. **Download** file từ remote storage
2. **Phân Tích** tên file để xác định phương pháp xử lý
3. **Giải Mã** (decryption) - nếu có encryption
4. **Giải Nén** (decompression) - nếu có compression  
5. **Khôi Phục** tên file gốc (loại bỏ marker `.xbk`)

### Ví Dụ Quy Trình Khôi Phục

#### Ví Dụ 1: File `xtest.2025-06-02_06-02-16.tar.xbk.gz.gpg`

```bash
# File ban đầu: xtest.2025-06-02_06-02-16.tar.xbk.gz.gpg
# Phân tích: compression=gz, encryption=gpg

# Bước 1: Giải mã GPG
gpg --decrypt file.tar.xbk.gz.gpg > file.tar.xbk.gz

# Bước 2: Giải nén Gzip  
gunzip file.tar.xbk.gz → file.tar.xbk

# Bước 3: Khôi phục tên gốc
mv file.tar.xbk → file.tar
```

#### Ví Dụ 2: File `xtest.2025-06-02_06-02-16.tar.xbk.7z`

```bash
# File ban đầu: xtest.2025-06-02_06-02-16.tar.xbk.7z
# Phân tích: compression=7z, encryption=none

# Bước 1: Giải nén 7z
7z x file.tar.xbk.7z → file.tar.xbk

# Bước 2: Khôi phục tên gốc
mv file.tar.xbk → file.tar
```

#### Ví Dụ 3: File `xtest.2025-06-02_06-02-16.tar.xbk.zst.aes`

```bash
# File ban đầu: xtest.2025-06-02_06-02-16.tar.xbk.zst.aes
# Phân tích: compression=zst, encryption=aes

# Bước 1: Giải mã AES
openssl_decrypt(file.tar.xbk.zst.aes) → file.tar.xbk.zst

# Bước 2: Giải nén Zstd
zstd -d file.tar.xbk.zst → file.tar.xbk

# Bước 3: Khôi phục tên gốc
mv file.tar.xbk → file.tar
```

## Các Phương Pháp Nén Được Hỗ Trợ

| Phương Pháp | Extension | Lệnh Nén | Lệnh Giải Nén |
|-------------|-----------|----------|---------------|
| **Gzip** | `.gz` | `gzip` | `gunzip` |
| **Bzip2** | `.bz2` | `bzip2` | `bunzip2` |
| **XZ** | `.xz` | `xz` | `unxz` |
| **Zstd** | `.zst` | `zstd` | `zstd -d` |
| **Zip** | `.zip` | `zip` | `unzip` |
| **7-Zip** | `.7z` | `7z a` | `7z x` |

## Các Phương Pháp Mã Hóa Được Hỗ Trợ

| Phương Pháp | Extension | Mô Tả |
|-------------|-----------|--------|
| **AES** | `.aes` | AES-256-CBC với OpenSSL |
| **GPG** | `.gpg` | GNU Privacy Guard |

## Cấu Hình Environment

```bash
# Nén
BACKUP_COMPRESSION=gzip      # none, gzip, bzip2, xz, zstd, zip, 7z
BACKUP_COMPRESSION_LEVEL=6   # Mức nén (1-9 cho hầu hết, 1-19 cho zstd)

# Mã hóa  
BACKUP_ENCRYPTION=aes        # none, aes, gpg
ENCRYPTION_PASSWORD=your_password
```

## Các Hàm Helper Mới

### `Helper::createXbkFilename()`

Tạo tên file với marker `.xbk` dựa trên phương pháp nén và mã hóa.

```php
$filename = Helper::createXbkFilename(
    'user.2025-01-01_12-00-00.tar',  // File gốc
    'gzip',                          // Phương pháp nén
    'aes'                           // Phương pháp mã hóa
);
// Kết quả: user.2025-01-01_12-00-00.tar.xbk.gz.aes
```

### `Helper::parseXbkFilename()`

Phân tích tên file để xác định phương pháp xử lý.

```php
$info = Helper::parseXbkFilename('user.2025-01-01_12-00-00.tar.xbk.gz.aes');
// Kết quả:
// [
//     'original' => 'user.2025-01-01_12-00-00.tar',
//     'compression' => 'gz',
//     'encryption' => 'aes', 
//     'hasXbk' => true
// ]
```

### `Helper::getOriginalFilename()`

Lấy tên file gốc từ file đã xử lý.

```php
$original = Helper::getOriginalFilename('user.2025-01-01_12-00-00.tar.xbk.gz.aes');
// Kết quả: user.2025-01-01_12-00-00.tar
```

## Tương Thích Ngược

Hệ thống vẫn hỗ trợ các file backup cũ không có marker `.xbk` thông qua logic legacy trong `download.php`. Tuy nhiên, khuyến nghị sử dụng định dạng mới cho tất cả backup mới.

## Lưu Ý Quan Trọng

1. **Thứ tự xử lý**: Luôn nén trước, mã hóa sau khi backup. Và ngược lại khi khôi phục: giải mã trước, giải nén sau.

2. **Marker .xbk**: Được chèn giữa tên file gốc và phần mở rộng xử lý để dễ dàng nhận diện.

3. **Tương thích**: File không có `.xbk` vẫn được xử lý theo logic cũ để đảm bảo tương thích ngược.

4. **Bảo mật**: Mật khẩu mã hóa phải được lưu trữ an toàn trong biến môi trường `ENCRYPTION_PASSWORD`.

5. **Test**: Luôn test quy trình backup và restore trước khi triển khai production.
