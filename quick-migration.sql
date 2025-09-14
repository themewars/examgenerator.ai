-- 🚀 QuizWhiz AI - Quick Database Migration
-- Run this SQL script directly in your database to fix the "type" column issue

-- Step 1: Add missing 'type' column to questions table
ALTER TABLE `questions` ADD COLUMN `type` INT DEFAULT 0 AFTER `title`;

-- Step 2: Update existing questions to have default type (0 = Multiple Choice)
UPDATE `questions` SET `type` = 0 WHERE `type` IS NULL;

-- Step 3: Add generation status columns to quizzes table (if missing)
ALTER TABLE `quizzes` ADD COLUMN `generation_status` VARCHAR(50) DEFAULT 'pending' AFTER `is_show_home`;

ALTER TABLE `quizzes` ADD COLUMN `generation_progress_total` INT DEFAULT 0 AFTER `generation_status`;

ALTER TABLE `quizzes` ADD COLUMN `generation_progress_done` INT DEFAULT 0 AFTER `generation_progress_total`;

ALTER TABLE `quizzes` ADD COLUMN `generation_error` TEXT NULL AFTER `generation_progress_done`;

-- Step 4: Clean up any corrupted data
DELETE FROM `questions` WHERE `quiz_id` IS NULL;

DELETE FROM `answers` WHERE `question_id` NOT IN (SELECT `id` FROM `questions`);

-- Step 5: Verify the changes
-- Check questions table structure
DESCRIBE `questions`;

-- Check quizzes table structure  
DESCRIBE `quizzes`;

-- Check if type column exists
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'questions' 
AND COLUMN_NAME = 'type';

-- Show sample data
SELECT id, title, type, quiz_id FROM `questions` LIMIT 5;
