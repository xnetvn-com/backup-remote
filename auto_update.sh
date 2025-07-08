#!/bin/bash

#================================================================
# Copyright (c) 2025 xNetVN Inc.
# Website: https://xnetvn.com/
# License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
# Contact: license@xnetvn.net
#================================================================
# This script automatically fetches or updates the latest source code
# from a GitHub repository. It will automatically detect the default branch
# (main or master) and use it.
#
# REQUIREMENTS:
# - git: must be installed on your system.
#
# USAGE:
# 1. Save this file as auto_update.sh.
# 2. Grant execute permission: chmod +x auto_update.sh
# 3. Run the script: ./auto_update.sh
#================================================================

# --- Configuration ---
GITHUB_REPO_URL="https://github.com/xnetvn-com/php-backup-remote.git"
TARGET_DIR="." # Current directory

# --- Functions ---

# This function connects to the repository URL and finds the default branch
get_default_branch() {
    # $1 is the first argument passed to the function (GITHUB_REPO_URL)
    # git remote show: displays information about the remote repository
    # grep 'HEAD branch': filters the line containing the default branch info
    # awk '{print $3}': gets the third word (the branch name)
    # 2>/dev/null: suppresses error messages if any
    git remote show "$1" 2>/dev/null | grep 'HEAD branch' | awk '{print $3}'
}

# --- Main script ---

echo "Starting update process from repository: $GITHUB_REPO_URL"

# 1. Check if git is installed
if ! command -v git &> /dev/null; then
    echo "ERROR: Please install 'git' to run this script."
    exit 1
fi

# 2. Automatically detect the default branch
echo "Detecting default branch (main/master)..."
BRANCH=$(get_default_branch "$GITHUB_REPO_URL")

# Check if branch was found
if [ -z "$BRANCH" ]; then
    echo "ERROR: Could not detect the default branch. Please check the repository URL and network connection."
    exit 1
fi

echo "Default branch detected: '$BRANCH'"

# 3. Check whether to CLONE or PULL
if [ -d "$TARGET_DIR/.git" ]; then
    # --- UPDATE EXISTING REPOSITORY ---
    echo "Current directory is a Git repository. Updating..."
    cd "$TARGET_DIR" || exit

    echo "Fetching changes from remote..."
    git fetch origin

    echo "Overwriting local changes to match remote branch '$BRANCH'..."
    # The reset command uses the auto-detected BRANCH variable
    git reset --hard "origin/$BRANCH"

    if [ $? -ne 0 ]; then
        echo "ERROR: Could not reset to origin/${BRANCH}."
        exit 1
    fi
    
    # Pull the latest changes to ensure full sync
    git pull origin "$BRANCH"

    echo "Repository updated successfully."

else
    # --- CLONE NEW REPOSITORY ---
    echo "Current directory is not a Git repository. Cloning for the first time..."
    TEMP_CLONE_DIR=$(mktemp -d)

    echo "Cloning branch '$BRANCH'..."
    # The clone command uses the auto-detected BRANCH variable
    git clone --depth 1 --branch "$BRANCH" "$GITHUB_REPO_URL" "$TEMP_CLONE_DIR"

    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to clone the repository."
        rm -rf "$TEMP_CLONE_DIR"
        exit 1
    fi

    echo "Moving files from temporary directory to current directory..."
    rsync -av --remove-source-files "$TEMP_CLONE_DIR/" "$TARGET_DIR/"
    rm -rf "$TEMP_CLONE_DIR"

    echo "Done! Repository cloned successfully."
fi

exit 0