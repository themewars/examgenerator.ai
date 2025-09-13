#!/bin/bash

echo "🔍 Checking All Progress Bar Sources"
echo "===================================="

# Navigate to project directory
cd /var/www/html/public_html

echo "📋 Checking for any remaining progress bar code:"
echo ""

# Check all PHP files for progress bar related code
echo "🔍 Searching all PHP files for progress bar code:"
find . -name "*.php" -exec grep -l "live-progress-container\|showProgressBar\|hideProgressBar" {} \;

echo ""

# Check JavaScript files
echo "🔍 Searching JavaScript files for progress bar code:"
find . -name "*.js" -exec grep -l "live-progress-container\|showProgressBar\|hideProgressBar" {} \;

echo ""

# Check specific files that might have progress bars
echo "🔍 Checking specific files:"
echo ""

echo "📄 EditQuizzes.php:"
grep -n "progress\|bar" "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/EditQuizzes.php" || echo "No progress bar code found"

echo ""
echo "📄 progress-monitor.js:"
grep -n "progress\|bar" "QuizWhiz AI v1.2.0/dist/quiz-master/public/js/progress-monitor.js" || echo "No progress bar code found"

echo ""
echo "📄 Any other progress bar in views:"
find . -name "*.blade.php" -exec grep -l "progress.*bar\|live-progress" {} \;

echo ""
echo "✅ Check complete!"
