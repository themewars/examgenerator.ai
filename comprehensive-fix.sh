#!/bin/bash

# Comprehensive Fix for CreateQuizzes.php Syntax Error
# Run this on your server

echo "🔧 Comprehensive Fix for CreateQuizzes.php Syntax Error"
echo "======================================================"

# Navigate to project directory
cd /var/www/html/public_html  # Change this to your actual project path

FILE_PATH="QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php"

echo "📁 Working with file: $FILE_PATH"

# Backup the file
echo "📁 Creating backup..."
cp "$FILE_PATH" "$FILE_PATH.backup.$(date +%Y%m%d_%H%M%S)"

# Check current line 812
echo "🔍 Checking line 812:"
sed -n '812p' "$FILE_PATH"

# Check lines around 812
echo "🔍 Checking lines 810-815:"
sed -n '810,815p' "$FILE_PATH"

# Fix all potential issues
echo "🔧 Applying comprehensive fixes..."

# Fix 1: Replace all "Don't" with "Do not"
sed -i "s/Don't/Do not/g" "$FILE_PATH"

# Fix 2: Replace any unescaped single quotes in comments
sed -i "s/\/\/ Do not/\/\/ Do not/g" "$FILE_PATH"

# Fix 3: Check for any problematic characters
echo "🔍 Checking for problematic characters..."
grep -n "'" "$FILE_PATH" | head -5

# Verify the fixes
echo "✅ Verifying fixes..."
if grep -q "Do not" "$FILE_PATH"; then
    echo "✅ Fixes applied successfully!"
else
    echo "❌ Fixes failed!"
    exit 1
fi

# Clear caches
echo "🧹 Clearing caches..."
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear

echo "🎉 Comprehensive fix completed!"
echo "Try running: php artisan view:clear"
