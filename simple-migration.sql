-- 🚀 QuizWhiz AI - Simple Database Migration
-- Run this SQL script to fix the "type" column issue

-- Step 1: Add type column to questions table (if not exists)
ALTER TABLE `questions` ADD COLUMN `type` INT DEFAULT 0 AFTER `title`;

-- Step 2: Update existing questions to have default type
UPDATE `questions` SET `type` = 0 WHERE `type` IS NULL;

-- Step 3: Add generation status columns to quizzes table (if not exists)
ALTER TABLE `quizzes` ADD COLUMN `generation_status` VARCHAR(50) DEFAULT 'pending' AFTER `is_show_home`;

ALTER TABLE `quizzes` ADD COLUMN `generation_progress_total` INT DEFAULT 0 AFTER `generation_status`;

ALTER TABLE `quizzes` ADD COLUMN `generation_progress_done` INT DEFAULT 0 AFTER `generation_progress_total`;

ALTER TABLE `quizzes` ADD COLUMN `generation_error` TEXT NULL AFTER `generation_progress_done`;

-- Step 4: Clean up corrupted data
DELETE FROM `questions` WHERE `quiz_id` IS NULL;

DELETE FROM `answers` WHERE `question_id` NOT IN (SELECT `id` FROM `questions`);

-- Step 5: Verify the changes
SELECT 'Migration completed successfully!' as status;

-- Show questions table structure
DESCRIBE `questions`;

-- Show quizzes table structure
DESCRIBE `quizzes`;
