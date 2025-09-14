-- Fix questions table by adding missing 'type' column
-- Run this SQL command in your database

ALTER TABLE `questions` ADD COLUMN `type` INT DEFAULT 0 AFTER `title`;

-- Update existing questions to have default type (0 = Multiple Choice)
UPDATE `questions` SET `type` = 0 WHERE `type` IS NULL;
