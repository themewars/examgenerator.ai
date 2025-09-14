#!/bin/bash

echo "🔍 Finding All Progress Bar Sources"
echo "==================================="

# Navigate to project directory
cd "C:\Users\rai37\OneDrive\Desktop\QuizWhiz AI v1.2.0"

# Check if directory exists
if [ ! -d "." ]; then
    echo "❌ Error: Project directory not found!"
    exit 1
fi

echo "📋 Checking all possible progress bar sources:"
echo ""

# Check all PHP files for any progress bar related code
echo "🔍 Searching all PHP files for progress bar code:"
find . -name "*.php" -exec grep -l "live-progress-container\|Generating Exam Questions\|progress.*bar" {} \;

echo ""

# Check all JavaScript files
echo "🔍 Searching all JavaScript files for progress bar code:"
find . -name "*.js" -exec grep -l "live-progress-container\|Generating Exam Questions\|progress.*bar" {} \;

echo ""

# Check all Blade template files
echo "🔍 Searching all Blade template files for progress bar code:"
find . -name "*.blade.php" -exec grep -l "live-progress-container\|Generating Exam Questions\|progress.*bar" {} \;

echo ""

# Check specific directories
echo "🔍 Checking specific directories:"
echo ""

echo "📄 Filament User Resources:"
find "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/" -name "*.php" -exec grep -l "progress\|bar" {} \;

echo ""
echo "📄 Public JS files:"
find "QuizWhiz AI v1.2.0/dist/quiz-master/public/js/" -name "*.js" -exec grep -l "progress\|bar" {} \;

echo ""
echo "📄 Resources Views:"
find "QuizWhiz AI v1.2.0/dist/quiz-master/resources/views/" -name "*.blade.php" -exec grep -l "progress\|bar" {} \;

echo ""
echo "✅ Check complete!"
