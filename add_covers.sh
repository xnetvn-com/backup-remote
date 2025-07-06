#!/bin/bash

# Script to add @covers annotations to test files
# This script helps reduce risky tests by adding proper code coverage annotations

echo "Adding @covers annotations to test files..."

# Array of test files and their corresponding classes
declare -A test_mapping=(
    ["ArchiveHandlerTest.php"]="App\\Backup\\ArchiveHandler"
    ["BackupManagerTest.php"]="App\\Backup\\BackupManager"  
    ["RotationManagerTest.php"]="App\\Backup\\RotationManager"
    ["StorageFactoryTest.php"]="App\\Storage\\StorageFactory"
    ["NotificationManagerTest.php"]="App\\Notification\\NotificationManager"
    ["SystemCheckerTest.php"]="App\\System\\SystemChecker"
    ["LoggerTest.php"]="App\\Utils\\Logger"
    ["HelperCompressionTest.php"]="App\\Utils\\Helper"
    ["HelperCompressionLevelTest.php"]="App\\Utils\\Helper"
    ["HelperEncryptFileTest.php"]="App\\Utils\\Helper"
    ["HelperEncryptFileEdgeTest.php"]="App\\Utils\\Helper"
    ["HelperEncrypt7zZipTest.php"]="App\\Utils\\Helper"
    ["HelperDetectAllRemotesTest.php"]="App\\Utils\\Helper"
    ["SecurityFixesTest.php"]="App\\Utils\\Helper"
    ["SecurityEnhancementTest.php"]="App\\Utils\\Helper"
    ["PerformanceOptimizationTest.php"]="App\\Utils\\Helper"
)

cd /home/udev/xdev/projects/githubcom/xnetvn-com/php-backup-remote/tests

for test_file in "${!test_mapping[@]}"; do
    if [[ -f "$test_file" ]]; then
        class_name="${test_mapping[$test_file]}"
        echo "Processing $test_file -> $class_name"
        
        # Add class-level @covers if not exists
        if ! grep -q "@covers $class_name" "$test_file"; then
            # Find the line with "class TestName extends TestCase"
            if grep -q "class.*Test.*extends TestCase" "$test_file"; then
                # Add @covers before class declaration
                sed -i "/class.*Test.*extends TestCase/i /**\n * @covers $class_name\n */" "$test_file"
                echo "  Added class-level @covers annotation"
            fi
        else
            echo "  Class-level @covers already exists"
        fi
        
    else
        echo "  File $test_file not found, skipping..."
    fi
done

echo "Completed adding @covers annotations!"
echo ""
echo "Running PHPUnit to check improvement..."
cd /home/udev/xdev/projects/githubcom/xnetvn-com/php-backup-remote
php libs/vendor/bin/phpunit --testdox 2>&1 | tail -5
