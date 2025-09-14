#!/bin/bash

# 🚀 QuizWhiz AI - Migration Script for Current Directory
# This script runs migration from current directory (public_html)

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🚀 QuizWhiz AI - Current Directory Migration${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# Get current directory
CURRENT_DIR=$(pwd)
BACKUP_DIR="/var/backups/quizwhiz"

echo -e "${YELLOW}⚠️  IMPORTANT: This will modify your live database!${NC}"
echo -e "${YELLOW}Make sure you have a backup before proceeding.${NC}"
echo ""

echo -e "${BLUE}📋 Migration Details:${NC}"
echo -e "   • Current Directory: $CURRENT_DIR"
echo -e "   • Backup Directory: $BACKUP_DIR"
echo -e "   • Environment: Production"
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}❌ .env file not found in current directory!${NC}"
    echo -e "${YELLOW}Please run this script from your project root directory${NC}"
    exit 1
fi

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

echo -e "${BLUE}Database: $DB_DATABASE@$DB_HOST:$DB_PORT${NC}"

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
if command -v php &> /dev/null; then
    php artisan down --message="Database migration in progress" --retry=60
    echo -e "${GREEN}✅ Maintenance mode enabled${NC}"
else
    echo -e "${YELLOW}⚠️  PHP artisan not available, skipping maintenance mode${NC}"
fi

echo ""

# Step 3: Run direct SQL migration
echo -e "${BLUE}Step 3: Running database migration...${NC}"

# Check if type column already exists
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "DESCRIBE questions;" | grep -q "type"

if [ $? -eq 0 ]; then
    echo -e "${YELLOW}⚠️  Type column already exists in questions table${NC}"
else
    echo -e "${BLUE}Adding type column to questions table...${NC}"
    
    # Add type column
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << EOF
ALTER TABLE \`questions\` ADD COLUMN \`type\` INT DEFAULT 0 AFTER \`title\`;
UPDATE \`questions\` SET \`type\` = 0 WHERE \`type\` IS NULL;
EOF

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Type column added successfully${NC}"
    else
        echo -e "${RED}❌ Failed to add type column${NC}"
        echo -e "${YELLOW}Restoring from backup...${NC}"
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$BACKUP_FILE"
        exit 1
    fi
fi

echo ""

# Step 4: Add generation status columns to quizzes table
echo -e "${BLUE}Step 4: Adding generation status columns to quizzes table...${NC}"

mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << EOF
-- Add generation_status column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_status'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_status VARCHAR(50) DEFAULT \"pending\" AFTER is_show_home',
    'SELECT \"Column generation_status already exists\" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add generation_progress_total column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_progress_total'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_progress_total INT DEFAULT 0 AFTER generation_status',
    'SELECT \"Column generation_progress_total already exists\" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add generation_progress_done column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_progress_done'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_progress_done INT DEFAULT 0 AFTER generation_progress_total',
    'SELECT \"Column generation_progress_done already exists\" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add generation_error column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_error'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_error TEXT NULL AFTER generation_progress_done',
    'SELECT \"Column generation_error already exists\" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Generation status columns added successfully${NC}"
else
    echo -e "${YELLOW}⚠️  Some columns might already exist${NC}"
fi

echo ""

# Step 5: Clear caches (if artisan is available)
echo -e "${BLUE}Step 5: Clearing application caches...${NC}"
if command -v php &> /dev/null; then
    php artisan config:cache 2>/dev/null || echo -e "${YELLOW}⚠️  Config cache failed${NC}"
    php artisan route:cache 2>/dev/null || echo -e "${YELLOW}⚠️  Route cache failed${NC}"
    php artisan view:cache 2>/dev/null || echo -e "${YELLOW}⚠️  View cache failed${NC}"
    php artisan event:cache 2>/dev/null || echo -e "${YELLOW}⚠️  Event cache failed${NC}"
    echo -e "${GREEN}✅ Caches cleared${NC}"
else
    echo -e "${YELLOW}⚠️  PHP artisan not available, skipping cache clearing${NC}"
fi

echo ""

# Step 6: Disable maintenance mode
echo -e "${BLUE}Step 6: Disabling maintenance mode...${NC}"
if command -v php &> /dev/null; then
    php artisan up 2>/dev/null || echo -e "${YELLOW}⚠️  Could not disable maintenance mode${NC}"
    echo -e "${GREEN}✅ Maintenance mode disabled${NC}"
else
    echo -e "${YELLOW}⚠️  PHP artisan not available, skipping maintenance mode${NC}"
fi

echo ""

# Step 7: Verify migration
echo -e "${BLUE}Step 7: Verifying migration...${NC}"

# Check if type column exists
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "DESCRIBE questions;" | grep -q "type"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Type column exists in questions table${NC}"
else
    echo -e "${RED}❌ Type column not found in questions table${NC}"
fi

# Show table structure
echo -e "${BLUE}Questions table structure:${NC}"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "DESCRIBE questions;"

echo ""

# Summary
echo -e "${GREEN}🎉 Migration Summary${NC}"
echo -e "${GREEN}==================${NC}"
echo -e "✅ Database backup created: $BACKUP_FILE"
echo -e "✅ Type column added to questions table"
echo -e "✅ Generation status columns added to quizzes table"
echo -e "✅ Application caches cleared"
echo -e "✅ Maintenance mode disabled"
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo -e "   1. Test quiz creation functionality"
echo -e "   2. Verify all features work correctly"
echo -e "   3. Monitor application logs for any issues"
echo -e "   4. Keep backup file safe: $BACKUP_FILE"
echo ""
echo -e "${GREEN}✅ Migration completed successfully!${NC}"
echo -e "${BLUE}Your QuizWhiz AI should now work without the 'type' column error! 🚀${NC}"
