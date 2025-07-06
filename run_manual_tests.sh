#!/bin/bash

/**
 * Copyright (c) 2025 xNetVN Inc.
 * Website: https://xnetvn.com/
 * License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * Contact: license@xnetvn.net
 *
 * Manual Test Runner - Run the standalone test classes
 */

echo "🔧 HestiaCP Remote Backup Tool - Manual Test Runner"
echo "=================================================="

echo ""
echo "📝 Running XBK Filename Processing Tests..."
php tests/manual/XbkFilenameTest.php
echo ""

echo "🔄 Running XBK Integration Tests..."
php tests/manual/XbkIntegrationTest.php
echo ""

echo "✅ Manual tests completed!"
echo "💡 Tip: These tests demonstrate the XBK filename processing and backup workflows."
