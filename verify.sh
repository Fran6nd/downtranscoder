#!/bin/bash

# Verification script for DownTranscoder Nextcloud app

echo "========================================="
echo "DownTranscoder Verification Script"
echo "========================================="
echo ""

# Check directory structure
echo "✓ Checking directory structure..."
required_dirs=(
    "appinfo"
    "lib/AppInfo"
    "lib/Controller"
    "lib/Service"
    "lib/Command"
    "lib/BackgroundJob"
    "lib/Settings"
    "templates/settings"
    "js"
    "css"
)

for dir in "${required_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "  ✅ $dir"
    else
        echo "  ❌ $dir - MISSING!"
    fi
done

echo ""
echo "✓ Checking required files..."
required_files=(
    "appinfo/info.xml"
    "appinfo/routes.php"
    "lib/AppInfo/Application.php"
    "lib/Controller/ApiController.php"
    "lib/Service/MediaScannerService.php"
    "lib/Service/TranscodingQueueService.php"
    "lib/Service/TranscodingService.php"
    "lib/Command/ScanCommand.php"
    "lib/Command/TranscodeCommand.php"
    "lib/BackgroundJob/TranscodeJob.php"
    "lib/Settings/Admin.php"
    "lib/Settings/AdminSection.php"
    "templates/settings/admin.php"
    "js/admin.js"
    "css/admin.css"
    "composer.json"
    "README.md"
    "INSTALL.md"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file - MISSING!"
    fi
done

echo ""
echo "✓ Checking PHP syntax..."
for file in $(find lib -name "*.php"); do
    php -l "$file" > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file - SYNTAX ERROR!"
    fi
done

echo ""
echo "✓ Checking FFmpeg availability..."
if command -v ffmpeg &> /dev/null; then
    echo "  ✅ FFmpeg is installed: $(ffmpeg -version | head -n1)"
else
    echo "  ⚠️  FFmpeg not found - install it for the app to work!"
fi

echo ""
echo "========================================="
echo "Verification Complete!"
echo "========================================="
echo ""
echo "Next steps:"
echo "1. Copy this directory to your Nextcloud apps/ folder as 'downtranscoder'"
echo "2. Run: sudo chown -R www-data:www-data /path/to/nextcloud/apps/downtranscoder"
echo "3. Run: sudo -u www-data php occ app:enable downtranscoder"
echo "4. Configure in: Settings → Administration → DownTranscoder"
echo ""
