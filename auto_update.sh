#!/bin/sh
#
# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License Contact: license@xnetvn.net
# License Name: Apache License 2.0
# License URL: https://www.apache.org/licenses/LICENSE-2.0
#
# auto_update.sh - Safe automatic updater for php-backup-remote project
#
# Usage: sh auto_update.sh [--force] [--backup-dir=PATH] [--branch=BRANCH]
#
# This script safely updates the project with automatic backup and rollback capabilities.
# Features:
# - Automatic backup before update
# - Configuration preservation
# - Integrity verification
# - Rollback capability on failure
# - Progress indication
# - Comprehensive error handling
#

# =============================================================================
# Configuration
# =============================================================================
REPO_URL="https://github.com/xnetvn-com/php-backup-remote.git"
DEFAULT_BRANCH="main"
SCRIPT_NAME="$(basename "$0")"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# Default settings
BACKUP_DIR="${SCRIPT_DIR}/backups/auto_update_${TIMESTAMP}"
FORCE_UPDATE=false
BRANCH="$DEFAULT_BRANCH"
TMP_DIR=".auto_update_tmp_$$"
LOG_FILE="${SCRIPT_DIR}/auto_update.log"

# Protected files/directories (will be preserved during update)
PROTECTED_ITEMS="config/app.php config/constant.php storage/logs .env .env.* backups auto_update.log"

# =============================================================================
# Utility Functions
# =============================================================================

# Logging function
log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
}

# Print usage information
usage() {
    cat << EOF
Usage: $SCRIPT_NAME [OPTIONS]

Safe automatic updater for php-backup-remote project.

OPTIONS:
    --force              Force update without confirmation
    --backup-dir=PATH    Custom backup directory (default: ./backups/auto_update_TIMESTAMP)
    --branch=BRANCH      Git branch to update from (default: main)
    --help               Show this help message

EXAMPLES:
    $SCRIPT_NAME                           # Interactive update from main branch
    $SCRIPT_NAME --force                   # Force update without confirmation
    $SCRIPT_NAME --branch=develop          # Update from develop branch
    $SCRIPT_NAME --backup-dir=/tmp/backup  # Use custom backup directory

EOF
}

# Parse command line arguments
parse_args() {
    while [ $# -gt 0 ]; do
        case "$1" in
            --force)
                FORCE_UPDATE=true
                ;;
            --backup-dir=*)
                BACKUP_DIR="${1#*=}"
                ;;
            --branch=*)
                BRANCH="${1#*=}"
                ;;
            --help|-h)
                usage
                exit 0
                ;;
            *)
                log "ERROR" "Unknown option: $1"
                usage
                exit 1
                ;;
        esac
        shift
    done
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check prerequisites
check_prerequisites() {
    log "INFO" "Checking prerequisites..."
    
    # Check required commands
    local missing_commands=""
    for cmd in git rsync php; do
        if ! command_exists "$cmd"; then
            missing_commands="$missing_commands $cmd"
        fi
    done
    
    if [ -n "$missing_commands" ]; then
        log "ERROR" "Missing required commands:$missing_commands"
        log "ERROR" "Please install the missing commands and try again"
        exit 1
    fi
    
    # Check write permissions
    if [ ! -w "$SCRIPT_DIR" ]; then
        log "ERROR" "No write permission in current directory: $SCRIPT_DIR"
        exit 1
    fi
    
    # Check network connectivity
    log "INFO" "Testing network connectivity to GitHub..."
    if ! git ls-remote --heads "$REPO_URL" >/dev/null 2>&1; then
        log "ERROR" "Cannot connect to repository: $REPO_URL"
        log "ERROR" "Please check your internet connection and try again"
        exit 1
    fi
    
    log "INFO" "Prerequisites check passed"
}

# Create backup of current installation
create_backup() {
    log "INFO" "Creating backup in: $BACKUP_DIR"
    
    # Create backup directory
    if ! mkdir -p "$BACKUP_DIR"; then
        log "ERROR" "Failed to create backup directory: $BACKUP_DIR"
        exit 1
    fi
    
    # Copy current files to backup
    if ! rsync -a --exclude="$TMP_DIR" --exclude="backups" "$SCRIPT_DIR"/ "$BACKUP_DIR"/; then
        log "ERROR" "Failed to create backup"
        exit 1
    fi
    
    log "INFO" "Backup created successfully"
}

# Preserve user configurations
preserve_configs() {
    log "INFO" "Preserving user configurations..."
    
    local config_backup="$TMP_DIR/.preserved_configs"
    mkdir -p "$config_backup"
    
    for item in $PROTECTED_ITEMS; do
        local source_path="$SCRIPT_DIR/$item"
        if [ -e "$source_path" ]; then
            local dest_dir="$config_backup/$(dirname "$item")"
            mkdir -p "$dest_dir"
            cp -r "$source_path" "$config_backup/$item" 2>/dev/null || true
            log "INFO" "Preserved: $item"
        fi
    done
}

# Restore preserved configurations
restore_configs() {
    log "INFO" "Restoring user configurations..."
    
    local config_backup="$TMP_DIR/.preserved_configs"
    if [ -d "$config_backup" ]; then
        for item in $PROTECTED_ITEMS; do
            local source_path="$config_backup/$item"
            local dest_path="$SCRIPT_DIR/$item"
            if [ -e "$source_path" ]; then
                cp -r "$source_path" "$dest_path" 2>/dev/null || true
                log "INFO" "Restored: $item"
            fi
        done
    fi
}

# Verify download integrity
verify_integrity() {
    log "INFO" "Verifying download integrity..."
    
    # Check if essential files exist
    local essential_files="run.php app/Backup/BackupManager.php app/Utils/Helper.php"
    for file in $essential_files; do
        if [ ! -f "$TMP_DIR/$file" ]; then
            log "ERROR" "Essential file missing: $file"
            return 1
        fi
    done
    
    # Basic syntax check for PHP files
    if command_exists php; then
        log "INFO" "Performing PHP syntax check..."
        if ! find "$TMP_DIR" -name "*.php" -exec php -l {} \; >/dev/null 2>&1; then
            log "ERROR" "PHP syntax errors detected in downloaded files"
            return 1
        fi
    fi
    
    log "INFO" "Integrity verification passed"
    return 0
}

# Rollback to previous version
rollback() {
    log "WARN" "Rolling back to previous version..."
    
    if [ -d "$BACKUP_DIR" ]; then
        # Remove current files (except protected items)
        log "INFO" "Removing current installation..."
        for item in $(find "$SCRIPT_DIR" -maxdepth 1 -type f -name "*.php" -o -name "*.sh" | grep -v auto_update.sh); do
            rm -f "$item" 2>/dev/null || true
        done
        for item in app libs docs tests; do
            if [ -d "$SCRIPT_DIR/$item" ]; then
                rm -rf "$SCRIPT_DIR/$item" 2>/dev/null || true
            fi
        done
        
        # Restore from backup
        log "INFO" "Restoring from backup..."
        if rsync -a --exclude="backups" "$BACKUP_DIR"/ "$SCRIPT_DIR"/; then
            log "INFO" "Rollback completed successfully"
            return 0
        else
            log "ERROR" "Rollback failed - manual intervention required"
            return 1
        fi
    else
        log "ERROR" "No backup found for rollback"
        return 1
    fi
}

# Cleanup function
cleanup() {
    log "INFO" "Cleaning up temporary files..."
    if [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
}

# Signal handler for cleanup
trap cleanup EXIT INT TERM

# =============================================================================
# Main Update Process
# =============================================================================

# Parse command line arguments
parse_args "$@"

# Start update process
log "INFO" "Starting auto-update process for php-backup-remote"
log "INFO" "Repository: $REPO_URL"
log "INFO" "Branch: $BRANCH"
log "INFO" "Backup directory: $BACKUP_DIR"

# Step 1: Check prerequisites
check_prerequisites

# Step 2: Get user confirmation (unless forced)
if [ "$FORCE_UPDATE" = false ]; then
    echo ""
    echo "This will update your php-backup-remote installation from branch '$BRANCH'."
    echo "A backup will be created in: $BACKUP_DIR"
    echo ""
    printf "Do you want to continue? [y/N]: "
    read -r answer
    case "$answer" in
        [Yy]|[Yy][Ee][Ss])
            log "INFO" "User confirmed update"
            ;;
        *)
            log "INFO" "Update cancelled by user"
            exit 0
            ;;
    esac
fi

# Step 3: Create backup
create_backup

# Step 4: Download latest code
log "INFO" "Downloading latest code from branch '$BRANCH'..."
if ! git clone --depth=1 --branch="$BRANCH" "$REPO_URL" "$TMP_DIR"; then
    log "ERROR" "Failed to clone repository"
    exit 1
fi

# Step 5: Preserve user configurations
preserve_configs

# Step 6: Verify integrity
if ! verify_integrity; then
    log "ERROR" "Integrity verification failed"
    cleanup
    exit 1
fi

# Step 7: Update files
log "INFO" "Updating application files..."
if ! rsync -a --delete \
    --exclude='.git' \
    --exclude='config/app.php' \
    --exclude='config/constant.php' \
    --exclude='storage/logs' \
    --exclude='.env*' \
    --exclude='backups' \
    --exclude='auto_update.log' \
    "$TMP_DIR"/ "$SCRIPT_DIR"/; then
    log "ERROR" "Failed to update files"
    rollback
    exit 1
fi

# Step 8: Restore configurations
restore_configs

# Step 9: Set proper permissions
log "INFO" "Setting proper permissions..."
chmod +x "$SCRIPT_DIR"/*.sh 2>/dev/null || true
chmod +x "$SCRIPT_DIR"/run.php 2>/dev/null || true

# Step 10: Verify update
log "INFO" "Verifying update..."
if [ -f "$SCRIPT_DIR/run.php" ] && php -l "$SCRIPT_DIR/run.php" >/dev/null 2>&1; then
    log "SUCCESS" "Update completed successfully!"
    log "INFO" "Backup location: $BACKUP_DIR"
    echo ""
    echo "✅ Update completed successfully!"
    echo "📁 Backup saved to: $BACKUP_DIR"
    echo "🔍 Please review the changes and test your backup configuration"
    echo ""
else
    log "ERROR" "Update verification failed"
    rollback
    exit 1
fi
