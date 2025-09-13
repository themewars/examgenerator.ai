#!/bin/bash

echo "🚨 SERVER RECOVERY SCRIPT"
echo "========================="

# Navigate to project directory
cd /var/www/html/public_html

echo "📋 Checking server status:"
echo ""

# 1. Check Laravel logs for errors
echo "🔍 Checking Laravel logs:"
tail -20 storage/logs/laravel.log | grep -i error

echo ""

# 2. Check PHP syntax
echo "🔍 Checking PHP syntax:"
php -l "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php"
php -l "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/EditQuizzes.php"

echo ""

# 3. Clear all caches
echo "🧹 Clearing all caches:"
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan cache:clear

echo ""

# 4. Check if server is running
echo "🔍 Checking server status:"
ps aux | grep "php-fpm\|nginx\|apache" | head -5

echo ""

# 5. Restart services if needed
echo "🔄 Restarting services:"
systemctl restart php-fpm
systemctl restart nginx

echo ""
echo "✅ Recovery steps completed!"
echo "🎯 Check if the website is working now"
