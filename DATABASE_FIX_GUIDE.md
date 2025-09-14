# 🔧 QuizWhiz AI - Database Fix Guide

## 🚨 **Problem**: Quiz Creation Error

**Error Message:**
```
Quiz Created with Issues
Quiz created but question generation failed: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'type' in 'INSERT INTO' (Connection: mysql, SQL: insert into `questions` (`title`, `type`, `quiz_id`, `updated_at`, `created_at`) values (What is the capital of France?, 0, 106, 2025-09-14 12:32:53, 2025-09-14 12:32:53))
```

## 🔍 **Root Cause**

The `questions` table is missing the `type` column that the application is trying to insert data into.

## ✅ **Solutions**

### **Method 1: Automated Fix (Recommended)**

#### **For Linux/Mac:**
```bash
chmod +x fix-database-issues.sh
./fix-database-issues.sh
```

#### **For Windows:**
```powershell
.\fix-database-issues.ps1
```

### **Method 2: Manual SQL Fix**

1. **Access your database** (phpMyAdmin, MySQL Workbench, or command line)
2. **Run this SQL command:**

```sql
-- Add the missing 'type' column to questions table
ALTER TABLE `questions` ADD COLUMN `type` INT DEFAULT 0 AFTER `title`;

-- Update existing questions to have default type
UPDATE `questions` SET `type` = 0 WHERE `type` IS NULL;
```

### **Method 3: Laravel Migration**

If you have Laravel environment set up:

```bash
# Run the migration
php artisan migrate

# Or run specific migration
php artisan migrate --path=database/migrations/2025_01_14_123000_add_type_column_to_questions_table.php
```

## 🔧 **Additional Fixes**

### **Fix Quiz Table Columns**

The quiz table might also be missing some columns. Run these SQL commands:

```sql
-- Add generation status columns to quizzes table
ALTER TABLE `quizzes` ADD COLUMN `generation_status` VARCHAR(50) DEFAULT 'pending' AFTER `is_show_home`;
ALTER TABLE `quizzes` ADD COLUMN `generation_progress_total` INT DEFAULT 0 AFTER `generation_status`;
ALTER TABLE `quizzes` ADD COLUMN `generation_progress_done` INT DEFAULT 0 AFTER `generation_progress_total`;
ALTER TABLE `quizzes` ADD COLUMN `generation_error` TEXT NULL AFTER `generation_progress_done`;
```

### **Clean Up Corrupted Data**

```sql
-- Delete questions without quiz_id
DELETE FROM `questions` WHERE `quiz_id` IS NULL;

-- Update any NULL type values
UPDATE `questions` SET `type` = 0 WHERE `type` IS NULL;

-- Clean up orphaned answers
DELETE FROM `answers` WHERE `question_id` NOT IN (SELECT `id` FROM `questions`);
```

## 📋 **Verification Steps**

After applying the fixes:

1. **Check table structure:**
   ```sql
   DESCRIBE questions;
   DESCRIBE quizzes;
   ```

2. **Test quiz creation** in your application

3. **Verify questions are generated** properly

## 🎯 **Expected Results**

After the fix:
- ✅ Quiz creation should work without errors
- ✅ Questions should be generated properly
- ✅ All question types should work correctly
- ✅ No more "Column not found" errors

## 🆘 **Troubleshooting**

### **If you still get errors:**

1. **Check database connection** in `.env` file
2. **Verify table permissions** for your database user
3. **Check if migrations ran** properly
4. **Clear application cache:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

### **Common Issues:**

1. **Permission Denied**: Make sure your database user has ALTER privileges
2. **Table Locked**: Wait for any running queries to complete
3. **Syntax Error**: Check SQL syntax carefully

## 📞 **Support**

If you continue to have issues:

1. **Check application logs**: `storage/logs/laravel.log`
2. **Check database logs** for any errors
3. **Verify all required columns** exist in both tables
4. **Test with a simple quiz** first

## 🎉 **Success!**

Once fixed, your QuizWhiz AI should work perfectly for creating quizzes and generating questions!

---

**Note**: Always backup your database before making structural changes.
