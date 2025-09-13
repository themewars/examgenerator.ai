#!/bin/bash

echo "🚨 COMPREHENSIVE PROGRESS BAR FIX"
echo "=================================="

# Navigate to project directory
cd /var/www/html/public_html

echo "📋 Disabling ALL progress bar sources:"
echo ""

# 1. Disable all progress-monitor.js files
echo "🔧 Disabling progress-monitor.js files:"
find . -name "progress-monitor.js" -exec echo "Disabling: {}" \; -exec sh -c 'echo "console.log(\"Progress monitor disabled\");" > "$1"' _ {} \;

# 2. Disable exam-creation-progress.blade.php
echo ""
echo "🔧 Disabling exam-creation-progress widget:"
find . -name "exam-creation-progress.blade.php" -exec echo "Disabling: {}" \; -exec sh -c 'echo "{{-- Progress widget disabled --}}" > "$1"' _ {} \;

# 3. Add CSS to hide any remaining progress bars
echo ""
echo "🔧 Adding CSS to hide progress bars:"
cat > hide-progress-bars.css << 'EOF'
#live-progress-container {
    display: none !important;
    visibility: hidden !important;
}

.progress-bar-container {
    display: none !important;
}

.exam-progress-widget {
    display: none !important;
}
EOF

# 4. Check if there are any other progress bar sources
echo ""
echo "🔍 Checking for remaining progress bar sources:"
grep -r "live-progress-container" . --include="*.php" --include="*.js" --include="*.blade.php" | head -10

echo ""
echo "🔍 Checking for 'Generating Exam Questions' text:"
grep -r "Generating Exam Questions" . --include="*.php" --include="*.js" --include="*.blade.php" | head -10

# 5. Clear all caches
echo ""
echo "🧹 Clearing all caches:"
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear

echo ""
echo "✅ Comprehensive fix applied!"
echo "🎯 All progress bar sources should now be disabled"
echo ""
echo "📱 Browser में Hard Refresh करें: Ctrl + F5"
