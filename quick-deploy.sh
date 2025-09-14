#!/bin/bash

# QuizWhiz AI - Quick Deployment Script
# Fast deployment for development/testing servers

echo "⚡ QuizWhiz AI - Quick Deployment"
echo "================================"

# Configuration
DEPLOY_PATH="/var/www/html/public_html"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }

# Extract and deploy
print_status "Quick deployment in progress..."
cd $DEPLOY_PATH
tar -xzf /tmp/quizwhiz-deployment.tar.gz

# Set permissions
chown -R www-data:www-data $DEPLOY_PATH
chmod -R 755 $DEPLOY_PATH
chmod -R 777 $DEPLOY_PATH/storage
chmod -R 777 $DEPLOY_PATH/bootstrap/cache

# Install dependencies
composer install --no-dev --optimize-autoloader

# Generate key and migrate
php artisan key:generate --force
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link

# Restart services
systemctl restart nginx
systemctl restart php8.2-fpm

print_success "🚀 Quick deployment completed!"
print_status "Application is ready at: http://$(curl -s ifconfig.me)"
