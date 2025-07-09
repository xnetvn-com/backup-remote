# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Code coverage reporting capability (pending Xdebug installation)
- Performance optimization recommendations
- Security audit integration plans

### Changed
- Enhanced test suite with additional edge cases
- Improved documentation structure

### Fixed
- RotationManagerTest compatibility issue with StorageAttributes objects
- Test suite now achieving 100% pass rate

## [2.1.0] - 2025-01-09

### Added
- **Comprehensive Test Suite**: 21+ test files covering unit, integration, E2E, security, and performance testing
- **Enhanced Security**: AES-256-CBC encryption, GPG support, secure file handling with path validation
- **Performance Optimization**: Resource monitoring (CPU, disk), streaming for large files, memory-efficient operations  
- **Multi-Storage Support**: AWS S3, Backblaze B2, FTP/FTPS, local filesystem with parallel operations
- **Advanced Archive Handling**: XBK file format support with compression and encryption layering
- **Backup Rotation**: Configurable retention policies with intelligent file grouping
- **Notification System**: Multi-channel notifications (email, Telegram, Discord) with alert throttling
- **System Checks**: Pre-flight validation for CPU load, disk space, time windows
- **Auto-Update Mechanism**: Built-in update script with backup and rollback capabilities
- **Remote File Optimization**: Pre-check existence before processing to avoid unnecessary work

### Changed
- **Architecture**: Modern PHP 8.2+ with PSR-4 autoloading and PSR-12 coding standards
- **Dependencies**: Upgraded to Flysystem 3.x, Monolog 3.x, and modern library versions
- **Configuration**: Enhanced config structure with performance and security settings
- **CLI Interface**: Improved command-line options with `--dry-run` and `--force` modes
- **Logging**: Structured logging with detailed operation tracking and debug information
- **Error Handling**: Comprehensive exception handling with proper error recovery

### Fixed
- **Storage Compatibility**: Fixed RotationManager compatibility with Flysystem StorageAttributes objects
- **File Path Validation**: Enhanced security with proper tmp directory validation
- **Memory Management**: Optimized memory usage for large file operations
- **Upload Reliability**: Improved upload verification and retry mechanisms

### Security
- **Encryption**: Strong AES-256-CBC encryption with optional GPG support
- **Path Traversal Protection**: Comprehensive validation to prevent directory traversal attacks
- **Credential Management**: Secure handling of passwords and API keys
- **File Permissions**: Proper file permission management (0700 for temp directories)
- **Input Validation**: Rigorous validation of all user inputs and configuration

### Performance
- **Streaming Operations**: Large file support without memory exhaustion
- **Parallel Processing**: Multi-remote upload capabilities
- **Compression**: Multiple algorithms (gzip, zstd, bzip2, xz, 7zip) with configurable levels
- **Resource Monitoring**: Intelligent resource usage monitoring and limits
- **Efficient Rotation**: Optimized backup rotation with minimal remote API calls

## [2.0.0] - 2024-12-01

### Added
- **Initial Release**: HestiaCP Remote Backup Tool
- **Core Backup Engine**: Full backup functionality for HestiaCP users
- **Storage Backends**: Initial support for S3 and FTP
- **Basic Encryption**: AES encryption support
- **Configuration System**: Environment-based configuration
- **Logging**: Basic logging with Monolog

### Infrastructure
- **PSR Compliance**: PSR-4 autoloading, PSR-3 logging interfaces
- **Composer**: Dependency management with Composer
- **GitHub Actions**: Basic CI/CD pipeline
- **Docker**: Containerization support
- **Documentation**: Comprehensive README and contributing guidelines

## Version Support

| Version | Status | PHP Requirement | Support Until |
|---------|--------|----------------|---------------|
| 2.1.x   | **Current** | PHP 8.2+ | 2025-12-31 |
| 2.0.x   | Maintenance | PHP 8.2+ | 2025-06-01 |
| 1.x.x   | EOL | PHP 7.4+ | 2024-12-31 |

## Migration Guide

For detailed migration instructions between versions, see [UPDATE_GUIDE.md](UPDATE_GUIDE.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines and development setup instructions.
