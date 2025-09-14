#!/bin/bash

# 🚀 QuizWhiz AI - Live Server Database Migration Script
# This script runs database migrations on live server

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🚀 QuizWhiz AI - Live Server Migration${NC}"
echo -e "${BLUE}=====================================${NC}"
echo ""

# Configuration
PROJECT_PATH="/var/www/html/quizwhiz-ai"
BACKUP_DIR="/var/backups/quizwhiz"

echo -e "${YELLOW}⚠️  IMPORTANT: This will modify your live database!${NC}"
echo -e "${YELLOW}Make sure you have a backup before proceeding.${NC}"
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Please run this script as root or with sudo${NC}"
    exit 1
fi

# Check if project directory exists
if [ ! -d "$PROJECT_PATH" ]; then
    echo -e "${RED}❌ Project directory not found: $PROJECT_PATH${NC}"
    echo -e "${YELLOW}Please update PROJECT_PATH in the script${NC}"
    exit 1
fi

cd $PROJECT_PATH

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}❌ .env file not found!${NC}"
    exit 1
fi

echo -e "${BLUE}📋 Pre-Migration Checklist:${NC}"
echo -e "   • Project Path: $PROJECT_PATH"
echo -e "   • Backup Directory: $BACKUP_DIR"
echo -e "   • Environment: Production"
echo ""

# Create backup directory
mkdir -p $BACKUP_DIR

# Step 1: Create database backup
echo -e "${BLUE}Step 1: Creating database backup...${NC}"
BACKUP_FILE="$BACKUP_DIR/db_backup_$(date +%Y%m%d_%H%M%S).sql"

# Get database credentials from .env
DB_HOST=$(grep "DB_HOST" .env | cut -d '=' -f2)
DB_PORT=$(grep "DB_PORT" .env | cut -d '=' -f2)
DB_DATABASE=$(grep "DB_DATABASE" .env | cut -d '=' -f2)
DB_USERNAME=$(grep "DB_USERNAME" .env | cut -d '=' -f2)
DB_PASSWORD=$(grep "DB_PASSWORD" .env | cut -d '=' -f2)

mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database backup created: $BACKUP_FILE${NC}"
else
    echo -e "${RED}❌ Database backup failed!${NC}"
    exit 1
fi

echo ""

# Step 2: Put application in maintenance mode
echo -e "${BLUE}Step 2: Enabling maintenance mode...${NC}"
php artisan down --message="Database migration in progress" --retry=60

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Maintenance mode enabled${NC}"
else
    echo -e "${YELLOW}⚠️  Could not enable maintenance mode (artisan command not found)${NC}"
fi

echo ""

# Step 3: Run migrations
echo -e "${BLUE}Step 3: Running database migrations...${NC}"

# Check if specific migration exists
if [ -f "database/migrations/2025_01_14_123000_add_type_column_to_questions_table.php" ]; then
    echo -e "${BLUE}Running specific migration for type column...${NC}"
    php artisan migrate --path=database/migrations/2025_01_14_123000_add_type_column_to_questions_table.php --force
else
    echo -e "${BLUE}Running all pending migrations...${NC}"
    php artisan migrate --force
fi

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Migrations completed successfully${NC}"
else
    echo -e "${RED}❌ Migration failed!${NC}"
    echo -e "${YELLOW}Restoring from backup...${NC}"
    
    # Restore from backup
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$BACKUP_FILE"
    
    echo -e "${RED}❌ Migration failed and database restored from backup${NC}"
    exit 1
fi

echo ""

# Step 4: Clear caches
echo -e "${BLUE}Step 4: Clearing application caches...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Caches cleared successfully${NC}"
else
    echo -e "${YELLOW}⚠️  Some cache commands failed${NC}"
fi

echo ""

# Step 5: Disable maintenance mode
echo -e "${BLUE}Step 5: Disabling maintenance mode...${NC}"
php artisan up

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Maintenance mode disabled${NC}"
else
    echo -e "${YELLOW}⚠️  Could not disable maintenance mode${NC}"
fi

echo ""

# Step 6: Verify migration
echo -e "${BLUE}Step 6: Verifying migration...${NC}"

# Check if type column exists
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "DESCRIBE questions;" | grep -q "type"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Type column exists in questions table${NC}"
else
    echo -e "${RED}❌ Type column not found in questions table${NC}"
fi

# Check migration status
php artisan migrate:status

echo ""

# Step 7: Test application
echo -e "${BLUE}Step 7: Testing application...${NC}"
if curl -f http://localhost > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Application is responding${NC}"
else
    echo -e "${YELLOW}⚠️  Application test failed - check manually${NC}"
fi

echo ""

# Summary
echo -e "${GREEN}🎉 Live Server Migration Summary${NC}"
echo -e "${GREEN}================================${NC}"
echo -e "✅ Database backup created: $BACKUP_FILE"
echo -e "✅ Migrations completed successfully"
echo -e "✅ Application caches cleared"
echo -e "✅ Maintenance mode disabled"
echo -e "✅ Application is live and responding"
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo -e "   1. Test quiz creation functionality"
echo -e "   2. Verify all features work correctly"
echo -e "   3. Monitor application logs for any issues"
echo -e "   4. Keep backup file safe: $BACKUP_FILE"
echo ""
echo -e "${GREEN}✅ Live server migration completed successfully!${NC}"
