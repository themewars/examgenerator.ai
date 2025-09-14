#!/bin/bash

# 🔧 QuizWhiz AI - Database Issues Fix Script
# This script fixes common database issues

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🔧 QuizWhiz AI - Database Issues Fix${NC}"
echo -e "${BLUE}====================================${NC}"
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}❌ .env file not found!${NC}"
    echo -e "${YELLOW}Please create .env file first${NC}"
    exit 1
fi

# Get database credentials from .env
DB_HOST=$(grep "DB_HOST" .env | cut -d '=' -f2)
DB_PORT=$(grep "DB_PORT" .env | cut -d '=' -f2)
DB_DATABASE=$(grep "DB_DATABASE" .env | cut -d '=' -f2)
DB_USERNAME=$(grep "DB_USERNAME" .env | cut -d '=' -f2)
DB_PASSWORD=$(grep "DB_PASSWORD" .env | cut -d '=' -f2)

echo -e "${BLUE}Database Configuration:${NC}"
echo -e "   • Host: $DB_HOST"
echo -e "   • Port: $DB_PORT"
echo -e "   • Database: $DB_DATABASE"
echo -e "   • Username: $DB_USERNAME"
echo ""

# Test database connection
echo -e "${BLUE}Testing database connection...${NC}"
if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "USE $DB_DATABASE;" 2>/dev/null; then
    echo -e "${GREEN}✅ Database connection successful${NC}"
else
    echo -e "${RED}❌ Database connection failed${NC}"
    echo -e "${YELLOW}Please check your database credentials in .env file${NC}"
    exit 1
fi

echo ""

# Fix 1: Add missing 'type' column to questions table
echo -e "${BLUE}Fix 1: Adding 'type' column to questions table...${NC}"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << EOF
-- Check if type column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'questions'
    AND COLUMN_NAME = 'type'
);

-- Add type column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE questions ADD COLUMN type INT DEFAULT 0 AFTER title',
    'SELECT "Column type already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing questions to have default type
UPDATE questions SET type = 0 WHERE type IS NULL;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Type column added/updated successfully${NC}"
else
    echo -e "${RED}❌ Failed to add type column${NC}"
fi

echo ""

# Fix 2: Check and fix other common issues
echo -e "${BLUE}Fix 2: Checking for other common issues...${NC}"

# Check if quizzes table has all required columns
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << EOF
-- Check if generation_status column exists in quizzes table
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_status'
);

-- Add generation_status column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_status VARCHAR(50) DEFAULT "pending" AFTER is_show_home',
    'SELECT "Column generation_status already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if generation_progress_total column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_progress_total'
);

-- Add generation_progress_total column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_progress_total INT DEFAULT 0 AFTER generation_status',
    'SELECT "Column generation_progress_total already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if generation_progress_done column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_progress_done'
);

-- Add generation_progress_done column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_progress_done INT DEFAULT 0 AFTER generation_progress_total',
    'SELECT "Column generation_progress_done already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if generation_error column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$DB_DATABASE'
    AND TABLE_NAME = 'quizzes'
    AND COLUMN_NAME = 'generation_error'
);

-- Add generation_error column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quizzes ADD COLUMN generation_error TEXT NULL AFTER generation_progress_done',
    'SELECT "Column generation_error already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Quiz table columns checked/updated successfully${NC}"
else
    echo -e "${RED}❌ Failed to update quiz table columns${NC}"
fi

echo ""

# Fix 3: Check table structure
echo -e "${BLUE}Fix 3: Checking table structure...${NC}"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << EOF
-- Show questions table structure
DESCRIBE questions;

-- Show quizzes table structure
DESCRIBE quizzes;
EOF

echo ""

# Fix 4: Clear any corrupted data
echo -e "${BLUE}Fix 4: Cleaning up corrupted data...${NC}"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << EOF
-- Delete questions without quiz_id
DELETE FROM questions WHERE quiz_id IS NULL;

-- Update any NULL type values
UPDATE questions SET type = 0 WHERE type IS NULL;

-- Clean up any orphaned answers
DELETE FROM answers WHERE question_id NOT IN (SELECT id FROM questions);
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Data cleanup completed successfully${NC}"
else
    echo -e "${RED}❌ Failed to cleanup data${NC}"
fi

echo ""

# Summary
echo -e "${GREEN}🎉 Database Fix Summary${NC}"
echo -e "${GREEN}======================${NC}"
echo -e "✅ Added 'type' column to questions table"
echo -e "✅ Added generation status columns to quizzes table"
echo -e "✅ Cleaned up corrupted data"
echo -e "✅ Updated existing records with default values"
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo -e "   1. Test quiz creation again"
echo -e "   2. Check if questions are being generated properly"
echo -e "   3. Verify all functionality works as expected"
echo ""
echo -e "${GREEN}✅ Database issues fixed successfully!${NC}"
