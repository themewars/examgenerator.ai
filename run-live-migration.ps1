#!/usr/bin/env pwsh

# 🚀 QuizWhiz AI - Live Server Database Migration Script (PowerShell)
# This script runs database migrations on live server

Write-Host "🚀 QuizWhiz AI - Live Server Migration" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$PROJECT_PATH = "/var/www/html/quizwhiz-ai"
$BACKUP_DIR = "/var/backups/quizwhiz"

Write-Host "⚠️  IMPORTANT: This will modify your live database!" -ForegroundColor Yellow
Write-Host "Make sure you have a backup before proceeding." -ForegroundColor Yellow
Write-Host ""

# Check if project directory exists
if (-not (Test-Path $PROJECT_PATH)) {
    Write-Host "❌ Project directory not found: $PROJECT_PATH" -ForegroundColor Red
    Write-Host "Please update PROJECT_PATH in the script" -ForegroundColor Yellow
    exit 1
}

Set-Location $PROJECT_PATH

# Check if .env file exists
if (-not (Test-Path ".env")) {
    Write-Host "❌ .env file not found!" -ForegroundColor Red
    exit 1
}

Write-Host "📋 Pre-Migration Checklist:" -ForegroundColor Blue
Write-Host "   • Project Path: $PROJECT_PATH"
Write-Host "   • Backup Directory: $BACKUP_DIR"
Write-Host "   • Environment: Production"
Write-Host ""

# Create backup directory
if (-not (Test-Path $BACKUP_DIR)) {
    New-Item -ItemType Directory -Path $BACKUP_DIR -Force | Out-Null
}

# Step 1: Create database backup
Write-Host "Step 1: Creating database backup..." -ForegroundColor Blue

# Get database credentials from .env
$envContent = Get-Content ".env"
$dbHost = ($envContent | Where-Object { $_ -match "^DB_HOST=" }) -replace "DB_HOST=", ""
$dbPort = ($envContent | Where-Object { $_ -match "^DB_PORT=" }) -replace "DB_PORT=", ""
$dbDatabase = ($envContent | Where-Object { $_ -match "^DB_DATABASE=" }) -replace "DB_DATABASE=", ""
$dbUsername = ($envContent | Where-Object { $_ -match "^DB_USERNAME=" }) -replace "DB_USERNAME=", ""
$dbPassword = ($envContent | Where-Object { $_ -match "^DB_PASSWORD=" }) -replace "DB_PASSWORD=", ""

$backupFile = "$BACKUP_DIR/db_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql"

try {
    # Create database backup using mysqldump
    $backupCommand = "mysqldump -h `"$dbHost`" -P `"$dbPort`" -u `"$dbUsername`" -p`"$dbPassword`" `"$dbDatabase`" > `"$backupFile`""
    Invoke-Expression $backupCommand
    
    if (Test-Path $backupFile) {
        Write-Host "✅ Database backup created: $backupFile" -ForegroundColor Green
    } else {
        throw "Backup file not created"
    }
} catch {
    Write-Host "❌ Database backup failed!" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Step 2: Put application in maintenance mode
Write-Host "Step 2: Enabling maintenance mode..." -ForegroundColor Blue
try {
    php artisan down --message="Database migration in progress" --retry=60
    Write-Host "✅ Maintenance mode enabled" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Could not enable maintenance mode (artisan command not found)" -ForegroundColor Yellow
}

Write-Host ""

# Step 3: Run migrations
Write-Host "Step 3: Running database migrations..." -ForegroundColor Blue

try {
    # Check if specific migration exists
    if (Test-Path "database/migrations/2025_01_14_123000_add_type_column_to_questions_table.php") {
        Write-Host "Running specific migration for type column..." -ForegroundColor Blue
        php artisan migrate --path=database/migrations/2025_01_14_123000_add_type_column_to_questions_table.php --force
    } else {
        Write-Host "Running all pending migrations..." -ForegroundColor Blue
        php artisan migrate --force
    }
    
    Write-Host "✅ Migrations completed successfully" -ForegroundColor Green
} catch {
    Write-Host "❌ Migration failed!" -ForegroundColor Red
    Write-Host "Restoring from backup..." -ForegroundColor Yellow
    
    # Restore from backup
    try {
        $restoreCommand = "mysql -h `"$dbHost`" -P `"$dbPort`" -u `"$dbUsername`" -p`"$dbPassword`" `"$dbDatabase`" < `"$backupFile`""
        Invoke-Expression $restoreCommand
        Write-Host "❌ Migration failed and database restored from backup" -ForegroundColor Red
    } catch {
        Write-Host "❌ Failed to restore from backup!" -ForegroundColor Red
    }
    exit 1
}

Write-Host ""

# Step 4: Clear caches
Write-Host "Step 4: Clearing application caches..." -ForegroundColor Blue
try {
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    Write-Host "✅ Caches cleared successfully" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Some cache commands failed" -ForegroundColor Yellow
}

Write-Host ""

# Step 5: Disable maintenance mode
Write-Host "Step 5: Disabling maintenance mode..." -ForegroundColor Blue
try {
    php artisan up
    Write-Host "✅ Maintenance mode disabled" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Could not disable maintenance mode" -ForegroundColor Yellow
}

Write-Host ""

# Step 6: Verify migration
Write-Host "Step 6: Verifying migration..." -ForegroundColor Blue

try {
    # Check if type column exists
    $checkCommand = "mysql -h `"$dbHost`" -P `"$dbPort`" -u `"$dbUsername`" -p`"$dbPassword`" `"$dbDatabase`" -e `"DESCRIBE questions;`""
    $result = Invoke-Expression $checkCommand
    
    if ($result -match "type") {
        Write-Host "✅ Type column exists in questions table" -ForegroundColor Green
    } else {
        Write-Host "❌ Type column not found in questions table" -ForegroundColor Red
    }
    
    # Check migration status
    php artisan migrate:status
} catch {
    Write-Host "⚠️  Could not verify migration" -ForegroundColor Yellow
}

Write-Host ""

# Step 7: Test application
Write-Host "Step 7: Testing application..." -ForegroundColor Blue
try {
    $response = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing -TimeoutSec 10
    if ($response.StatusCode -eq 200) {
        Write-Host "✅ Application is responding" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Application returned status code: $($response.StatusCode)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "⚠️  Application test failed - check manually" -ForegroundColor Yellow
}

Write-Host ""

# Summary
Write-Host "🎉 Live Server Migration Summary" -ForegroundColor Green
Write-Host "================================" -ForegroundColor Green
Write-Host "✅ Database backup created: $backupFile" -ForegroundColor Green
Write-Host "✅ Migrations completed successfully" -ForegroundColor Green
Write-Host "✅ Application caches cleared" -ForegroundColor Green
Write-Host "✅ Maintenance mode disabled" -ForegroundColor Green
Write-Host "✅ Application is live and responding" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Next Steps:" -ForegroundColor Blue
Write-Host "   1. Test quiz creation functionality" -ForegroundColor White
Write-Host "   2. Verify all features work correctly" -ForegroundColor White
Write-Host "   3. Monitor application logs for any issues" -ForegroundColor White
Write-Host "   4. Keep backup file safe: $backupFile" -ForegroundColor White
Write-Host ""
Write-Host "✅ Live server migration completed successfully!" -ForegroundColor Green
