#!/usr/bin/env pwsh

Write-Host "🔍 Checking All Progress Bar Sources" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan

# Navigate to project directory
$projectPath = "C:\Users\rai37\OneDrive\Desktop\QuizWhiz AI v1.2.0"
Set-Location $projectPath

# Check if directory exists
if (-not (Test-Path $projectPath)) {
    Write-Host "❌ Error: Project directory not found!" -ForegroundColor Red
    exit 1
}

Write-Host "📋 Checking for any remaining progress bar code:" -ForegroundColor Yellow
Write-Host ""

# Check all PHP files for progress bar related code
Write-Host "🔍 Searching all PHP files for progress bar code:" -ForegroundColor Green
Get-ChildItem -Recurse -Filter "*.php" | Select-String -Pattern "live-progress-container|showProgressBar|hideProgressBar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique

Write-Host ""

# Check JavaScript files
Write-Host "🔍 Searching JavaScript files for progress bar code:" -ForegroundColor Green
Get-ChildItem -Recurse -Filter "*.js" | Select-String -Pattern "live-progress-container|showProgressBar|hideProgressBar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique

Write-Host ""

# Check specific files that might have progress bars
Write-Host "🔍 Checking specific files:" -ForegroundColor Green
Write-Host ""

Write-Host "📄 EditQuizzes.php:" -ForegroundColor Yellow
$editQuizzesPath = "QuizWhiz AI v1.2.0\dist\quiz-master\app\Filament\User\Resources\QuizzesResource\Pages\EditQuizzes.php"
if (Test-Path $editQuizzesPath) {
    $result = Select-String -Path $editQuizzesPath -Pattern "progress|bar"
    if ($result) {
        $result | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
    } else {
        Write-Host "No progress bar code found" -ForegroundColor Green
    }
} else {
    Write-Host "File not found: $editQuizzesPath" -ForegroundColor Red
}

Write-Host ""

Write-Host "📄 progress-monitor.js:" -ForegroundColor Yellow
$progressMonitorPath = "QuizWhiz AI v1.2.0\dist\quiz-master\public\js\progress-monitor.js"
if (Test-Path $progressMonitorPath) {
    $result = Select-String -Path $progressMonitorPath -Pattern "progress|bar"
    if ($result) {
        $result | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
    } else {
        Write-Host "No progress bar code found" -ForegroundColor Green
    }
} else {
    Write-Host "File not found: $progressMonitorPath" -ForegroundColor Red
}

Write-Host ""

Write-Host "📄 Any other progress bar in views:" -ForegroundColor Yellow
Get-ChildItem -Recurse -Filter "*.blade.php" | Select-String -Pattern "progress.*bar|live-progress" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique

Write-Host ""
Write-Host "✅ Check complete!" -ForegroundColor Green
