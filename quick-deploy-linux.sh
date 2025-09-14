#!/bin/bash

# 🚀 QuizWhiz AI v1.2.0 - Quick Linux Deployment Script
# For servers that already have PHP, MySQL, and web server installed

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

# Configuration
PROJECT_NAME="quizwhiz-ai"
WEB_ROOT="/var/www/html"
PROJECT_PATH="$WEB_ROOT/$PROJECT_NAME"
DOMAIN="examgenerator.ai"

echo -e "${BLUE}🚀 QuizWhiz AI - Quick Deployment${NC}"
echo -e "${BLUE}=================================${NC}"

# Check if project directory exists
if [ ! -d "$PROJECT_PATH" ]; then
    echo -e "${RED}❌ Project directory not found: $PROJECT_PATH${NC}"
    echo -e "${YELLOW}Please upload your project files first${NC}"
    exit 1
fi

cd $PROJECT_PATH

# Set permissions
echo -e "${BLUE}Setting permissions...${NC}"
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 775 storage bootstrap/cache
chmod 600 .env

# Install dependencies
echo -e "${BLUE}Installing dependencies...${NC}"
composer install --optimize-autoloader --no-dev

# Generate key if not exists
if ! grep -q "APP_KEY=" .env || grep -q "APP_KEY=$" .env; then
    php artisan key:generate --force
fi

# Run migrations
echo -e "${BLUE}Running migrations...${NC}"
php artisan migrate --force

# Create storage link
php artisan storage:link

# Cache configurations
echo -e "${BLUE}Caching configurations...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize
php artisan optimize

echo -e "${GREEN}✅ Quick deployment completed!${NC}"
echo -e "${BLUE}Your site should be accessible at: http://$DOMAIN${NC}"
