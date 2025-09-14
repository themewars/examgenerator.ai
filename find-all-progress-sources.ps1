#!/usr/bin/env pwsh

Write-Host "🔍 Finding All Progress Bar Sources" -ForegroundColor Cyan
Write-Host "===================================" -ForegroundColor Cyan

# Navigate to project directory
$projectPath = "C:\Users\rai37\OneDrive\Desktop\QuizWhiz AI v1.2.0"
Set-Location $projectPath

# Check if directory exists
if (-not (Test-Path $projectPath)) {
    Write-Host "❌ Error: Project directory not found!" -ForegroundColor Red
    exit 1
}

Write-Host "📋 Checking all possible progress bar sources:" -ForegroundColor Yellow
Write-Host ""

# Check all PHP files for any progress bar related code
Write-Host "🔍 Searching all PHP files for progress bar code:" -ForegroundColor Green
Get-ChildItem -Recurse -Filter "*.php" | Select-String -Pattern "live-progress-container|Generating Exam Questions|progress.*bar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique

Write-Host ""

# Check all JavaScript files
Write-Host "🔍 Searching all JavaScript files for progress bar code:" -ForegroundColor Green
Get-ChildItem -Recurse -Filter "*.js" | Select-String -Pattern "live-progress-container|Generating Exam Questions|progress.*bar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique

Write-Host ""

# Check all Blade template files
Write-Host "🔍 Searching all Blade template files for progress bar code:" -ForegroundColor Green
Get-ChildItem -Recurse -Filter "*.blade.php" | Select-String -Pattern "live-progress-container|Generating Exam Questions|progress.*bar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique

Write-Host ""

# Check specific directories
Write-Host "🔍 Checking specific directories:" -ForegroundColor Green
Write-Host ""

Write-Host "📄 Filament User Resources:" -ForegroundColor Yellow
$filamentPath = "QuizWhiz AI v1.2.0\dist\quiz-master\app\Filament\User\Resources\"
if (Test-Path $filamentPath) {
    Get-ChildItem -Path $filamentPath -Recurse -Filter "*.php" | Select-String -Pattern "progress|bar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique
} else {
    Write-Host "Directory not found: $filamentPath" -ForegroundColor Red
}

Write-Host ""

Write-Host "📄 Public JS files:" -ForegroundColor Yellow
$jsPath = "QuizWhiz AI v1.2.0\dist\quiz-master\public\js\"
if (Test-Path $jsPath) {
    Get-ChildItem -Path $jsPath -Recurse -Filter "*.js" | Select-String -Pattern "progress|bar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique
} else {
    Write-Host "Directory not found: $jsPath" -ForegroundColor Red
}

Write-Host ""

Write-Host "📄 Resources Views:" -ForegroundColor Yellow
$viewsPath = "QuizWhiz AI v1.2.0\dist\quiz-master\resources\views\"
if (Test-Path $viewsPath) {
    Get-ChildItem -Path $viewsPath -Recurse -Filter "*.blade.php" | Select-String -Pattern "progress|bar" | Select-Object -ExpandProperty Filename | Sort-Object | Get-Unique
} else {
    Write-Host "Directory not found: $viewsPath" -ForegroundColor Red
}

Write-Host ""
Write-Host "✅ Check complete!" -ForegroundColor Green