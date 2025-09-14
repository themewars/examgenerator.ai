#!/usr/bin/env pwsh

Write-Host "🔍 Disabling progress-monitor.js" -ForegroundColor Cyan
Write-Host "=================================" -ForegroundColor Cyan

# Navigate to project directory
$projectPath = "C:\Users\rai37\OneDrive\Desktop\QuizWhiz AI v1.2.0"
Set-Location $projectPath

# Check if directory exists
if (-not (Test-Path $projectPath)) {
    Write-Host "❌ Error: Project directory not found!" -ForegroundColor Red
    exit 1
}

Write-Host "📋 Checking if progress-monitor.js is being loaded:" -ForegroundColor Yellow
Write-Host ""

# Check all view files for progress-monitor.js references
Write-Host "🔍 Searching for progress-monitor.js references:" -ForegroundColor Green
$bladeFiles = Get-ChildItem -Recurse -Filter "*.blade.php"
$progressMonitorRefs = $bladeFiles | Select-String -Pattern "progress-monitor.js"
if ($progressMonitorRefs) {
    $progressMonitorRefs | ForEach-Object { Write-Host "$($_.Filename): $($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
} else {
    Write-Host "No progress-monitor.js references found" -ForegroundColor Green
}

Write-Host ""

# Check specific files
Write-Host "📄 Checking specific view files:" -ForegroundColor Green
Write-Host ""

Write-Host "📄 app.blade.php:" -ForegroundColor Yellow
$appBladePath = "QuizWhiz AI v1.2.0\dist\quiz-master\resources\views\layouts\app.blade.php"
if (Test-Path $appBladePath) {
    $appBladeResult = Select-String -Path $appBladePath -Pattern "progress-monitor"
    if ($appBladeResult) {
        $appBladeResult | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
    } else {
        Write-Host "No progress-monitor found" -ForegroundColor Green
    }
} else {
    Write-Host "File not found: $appBladePath" -ForegroundColor Red
}

Write-Host ""

Write-Host "📄 Any other layout files:" -ForegroundColor Yellow
$layoutFiles = Get-ChildItem -Recurse -Filter "*.blade.php" | Where-Object { $_.Name -like "*layout*" }
$layoutProgressRefs = $layoutFiles | Select-String -Pattern "progress-monitor"
if ($layoutProgressRefs) {
    $layoutProgressRefs | ForEach-Object { Write-Host "$($_.Filename): $($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
} else {
    Write-Host "No progress-monitor references found in layout files" -ForegroundColor Green
}

Write-Host ""
Write-Host "🎯 If progress-monitor.js is found, we need to remove it from view files" -ForegroundColor Cyan
Write-Host "✅ Check complete!" -ForegroundColor Green
