#!/bin/bash

echo "🔍 Disabling progress-monitor.js"
echo "================================="

# Navigate to project directory
cd /var/www/html/public_html

echo "📋 Checking if progress-monitor.js is being loaded:"
echo ""

# Check all view files for progress-monitor.js references
echo "🔍 Searching for progress-monitor.js references:"
find . -name "*.blade.php" -exec grep -l "progress-monitor.js" {} \;

echo ""

# Check specific files
echo "📄 Checking specific view files:"
echo ""

echo "📄 app.blade.php:"
grep -n "progress-monitor" "QuizWhiz AI v1.2.0/dist/quiz-master/resources/views/layouts/app.blade.php" || echo "No progress-monitor found"

echo ""
echo "📄 Any other layout files:"
find . -name "*.blade.php" -exec grep -l "progress-monitor" {} \;

echo ""
echo "🎯 If progress-monitor.js is found, we need to remove it from view files"
echo "✅ Check complete!"
