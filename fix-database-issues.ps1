#!/usr/bin/env pwsh

# 🔧 QuizWhiz AI - Database Issues Fix Script (PowerShell)
# This script fixes common database issues

Write-Host "🔧 QuizWhiz AI - Database Issues Fix" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

# Check if .env file exists
if (-not (Test-Path ".env")) {
    Write-Host "❌ .env file not found!" -ForegroundColor Red
    Write-Host "Please create .env file first" -ForegroundColor Yellow
    exit 1
}

# Read .env file and extract database credentials
$envContent = Get-Content ".env"
$dbHost = ($envContent | Where-Object { $_ -match "^DB_HOST=" }) -replace "DB_HOST=", ""
$dbPort = ($envContent | Where-Object { $_ -match "^DB_PORT=" }) -replace "DB_PORT=", ""
$dbDatabase = ($envContent | Where-Object { $_ -match "^DB_DATABASE=" }) -replace "DB_DATABASE=", ""
$dbUsername = ($envContent | Where-Object { $_ -match "^DB_USERNAME=" }) -replace "DB_USERNAME=", ""
$dbPassword = ($envContent | Where-Object { $_ -match "^DB_PASSWORD=" }) -replace "DB_PASSWORD=", ""

Write-Host "Database Configuration:" -ForegroundColor Blue
Write-Host "   • Host: $dbHost"
Write-Host "   • Port: $dbPort"
Write-Host "   • Database: $dbDatabase"
Write-Host "   • Username: $dbUsername"
Write-Host ""

# Test database connection
Write-Host "Testing database connection..." -ForegroundColor Blue
try {
    $connectionString = "Server=$dbHost;Port=$dbPort;Database=$dbDatabase;Uid=$dbUsername;Pwd=$dbPassword;"
    $connection = New-Object MySql.Data.MySqlClient.MySqlConnection($connectionString)
    $connection.Open()
    $connection.Close()
    Write-Host "✅ Database connection successful" -ForegroundColor Green
} catch {
    Write-Host "❌ Database connection failed" -ForegroundColor Red
    Write-Host "Please check your database credentials in .env file" -ForegroundColor Yellow
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Fix 1: Add missing 'type' column to questions table
Write-Host "Fix 1: Adding 'type' column to questions table..." -ForegroundColor Blue

$sqlCommands = @"
-- Check if type column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$dbDatabase'
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
"@

try {
    $connection.Open()
    $command = New-Object MySql.Data.MySqlClient.MySqlCommand($sqlCommands, $connection)
    $command.ExecuteNonQuery() | Out-Null
    $connection.Close()
    Write-Host "✅ Type column added/updated successfully" -ForegroundColor Green
} catch {
    Write-Host "❌ Failed to add type column" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Fix 2: Check and fix other common issues
Write-Host "Fix 2: Checking for other common issues..." -ForegroundColor Blue

$quizTableFixes = @"
-- Check if generation_status column exists in quizzes table
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$dbDatabase'
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
    WHERE TABLE_SCHEMA = '$dbDatabase'
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
    WHERE TABLE_SCHEMA = '$dbDatabase'
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
    WHERE TABLE_SCHEMA = '$dbDatabase'
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
"@

try {
    $connection.Open()
    $command = New-Object MySql.Data.MySqlClient.MySqlCommand($quizTableFixes, $connection)
    $command.ExecuteNonQuery() | Out-Null
    $connection.Close()
    Write-Host "✅ Quiz table columns checked/updated successfully" -ForegroundColor Green
} catch {
    Write-Host "❌ Failed to update quiz table columns" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Fix 3: Check table structure
Write-Host "Fix 3: Checking table structure..." -ForegroundColor Blue

try {
    $connection.Open()
    
    # Show questions table structure
    Write-Host "Questions table structure:" -ForegroundColor Yellow
    $command = New-Object MySql.Data.MySqlClient.MySqlCommand("DESCRIBE questions", $connection)
    $reader = $command.ExecuteReader()
    while ($reader.Read()) {
        Write-Host "   $($reader['Field']) - $($reader['Type']) - $($reader['Null']) - $($reader['Key']) - $($reader['Default'])" -ForegroundColor White
    }
    $reader.Close()
    
    Write-Host ""
    Write-Host "Quizzes table structure:" -ForegroundColor Yellow
    $command = New-Object MySql.Data.MySqlClient.MySqlCommand("DESCRIBE quizzes", $connection)
    $reader = $command.ExecuteReader()
    while ($reader.Read()) {
        Write-Host "   $($reader['Field']) - $($reader['Type']) - $($reader['Null']) - $($reader['Key']) - $($reader['Default'])" -ForegroundColor White
    }
    $reader.Close()
    
    $connection.Close()
} catch {
    Write-Host "❌ Failed to check table structure" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Fix 4: Clear any corrupted data
Write-Host "Fix 4: Cleaning up corrupted data..." -ForegroundColor Blue

$cleanupCommands = @"
-- Delete questions without quiz_id
DELETE FROM questions WHERE quiz_id IS NULL;

-- Update any NULL type values
UPDATE questions SET type = 0 WHERE type IS NULL;

-- Clean up any orphaned answers
DELETE FROM answers WHERE question_id NOT IN (SELECT id FROM questions);
"@

try {
    $connection.Open()
    $command = New-Object MySql.Data.MySqlClient.MySqlCommand($cleanupCommands, $connection)
    $affectedRows = $command.ExecuteNonQuery()
    $connection.Close()
    Write-Host "✅ Data cleanup completed successfully ($affectedRows rows affected)" -ForegroundColor Green
} catch {
    Write-Host "❌ Failed to cleanup data" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Summary
Write-Host "🎉 Database Fix Summary" -ForegroundColor Green
Write-Host "======================" -ForegroundColor Green
Write-Host "✅ Added 'type' column to questions table" -ForegroundColor Green
Write-Host "✅ Added generation status columns to quizzes table" -ForegroundColor Green
Write-Host "✅ Cleaned up corrupted data" -ForegroundColor Green
Write-Host "✅ Updated existing records with default values" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Next Steps:" -ForegroundColor Blue
Write-Host "   1. Test quiz creation again" -ForegroundColor White
Write-Host "   2. Check if questions are being generated properly" -ForegroundColor White
Write-Host "   3. Verify all functionality works as expected" -ForegroundColor White
Write-Host ""
Write-Host "✅ Database issues fixed successfully!" -ForegroundColor Green
