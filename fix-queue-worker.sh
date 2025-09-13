#!/bin/bash

echo "🔧 Fixing Quiz Generation Queue Issue"
echo "======================================"

# Navigate to project directory
cd /var/www/html/public_html

echo "📋 Current Queue Configuration:"
php artisan config:show queue

echo ""
echo "🔍 Checking if queue worker is running:"
ps aux | grep "queue:work" | grep -v grep

echo ""
echo "📊 Checking jobs table:"
php artisan tinker --execute="echo 'Jobs in queue: ' . \App\Models\Job::count();"

echo ""
echo "🚀 Starting Queue Worker (this will run in background):"
nohup php artisan queue:work --daemon --timeout=300 --tries=3 > /dev/null 2>&1 &

echo "✅ Queue worker started!"

echo ""
echo "🔍 Verifying queue worker is running:"
sleep 2
ps aux | grep "queue:work" | grep -v grep

echo ""
echo "📋 Recent quiz generation logs:"
tail -20 storage/logs/laravel.log | grep -i "generatequizjob\|quiz.*processing"

echo ""
echo "🎯 To monitor queue worker logs in real-time, run:"
echo "tail -f storage/logs/laravel.log | grep -i queue"

echo ""
echo "✅ Fix completed! Quiz generation should now work properly."
