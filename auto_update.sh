#!/bin/sh
#
# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License Contact: license@xnetvn.net
# License Name: Apache License 2.0
# License URL: https://www.apache.org/licenses/LICENSE-2.0
#
# auto_update.sh - Script to automatically update the current directory with the latest code from https://github.com/xnetvn-com/php-backup-remote
#
# Usage: sh auto_update.sh
#
# This script will fetch the latest code from the official repository and update the current directory.
#

REPO_URL="https://github.com/xnetvn-com/php-backup-remote.git"
TMP_DIR=".auto_update_tmp_$$"

set -e

# Step 1: Clone the latest code to a temporary directory
echo "[INFO] Cloning latest code from $REPO_URL ..."
git clone --depth=1 "$REPO_URL" "$TMP_DIR"

# Step 2: Copy all files (except .git) to current directory
echo "[INFO] Updating current directory ..."
rsync -a --delete --exclude='.git' "$TMP_DIR"/ ./

# Step 3: Clean up temporary directory
rm -rf "$TMP_DIR"

echo "[SUCCESS] Update completed. Please review any changes before use."
