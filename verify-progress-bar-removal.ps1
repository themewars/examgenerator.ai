#!/usr/bin/env pwsh

Write-Host "🔍 Verifying Progress Bar Removal" -ForegroundColor Cyan
Write-Host "=================================" -ForegroundColor Cyan

# Navigate to project directory
$projectPath = "C:\Users\rai37\OneDrive\Desktop\QuizWhiz AI v1.2.0"
Set-Location $projectPath

# Check if directory exists
if (-not (Test-Path $projectPath)) {
    Write-Host "❌ Error: Project directory not found!" -ForegroundColor Red
    exit 1
}

Write-Host "📋 Checking current CreateQuizzes.php file:" -ForegroundColor Yellow
Write-Host ""

$createQuizzesPath = "QuizWhiz AI v1.2.0\dist\quiz-master\app\Filament\User\Resources\QuizzesResource\Pages\CreateQuizzes.php"

if (-not (Test-Path $createQuizzesPath)) {
    Write-Host "❌ Error: CreateQuizzes.php file not found at: $createQuizzesPath" -ForegroundColor Red
    exit 1
}

# Check if showProgressBar function exists (should NOT exist)
Write-Host "❌ Checking for showProgressBar function (should be removed):" -ForegroundColor Red
$showProgressResult = Select-String -Path $createQuizzesPath -Pattern "showProgressBar"
if ($showProgressResult) {
    $showProgressResult | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
} else {
    Write-Host "✅ showProgressBar function NOT found - Successfully removed!" -ForegroundColor Green
}

Write-Host ""

# Check if hideProgressBar function exists (should NOT exist)
Write-Host "❌ Checking for hideProgressBar function (should be removed):" -ForegroundColor Red
$hideProgressResult = Select-String -Path $createQuizzesPath -Pattern "hideProgressBar"
if ($hideProgressResult) {
    $hideProgressResult | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
} else {
    Write-Host "✅ hideProgressBar function NOT found - Successfully removed!" -ForegroundColor Green
}

Write-Host ""

# Check if live-progress-container creation exists (should NOT exist)
Write-Host "❌ Checking for live-progress-container creation (should be removed):" -ForegroundColor Red
$containerResult = Select-String -Path $createQuizzesPath -Pattern "live-progress-container"
if ($containerResult) {
    $containerResult | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
} else {
    Write-Host "✅ live-progress-container creation NOT found - Successfully removed!" -ForegroundColor Green
}

Write-Host ""

# Check if updateCreateButton function exists (should exist)
Write-Host "✅ Checking for updateCreateButton function (should exist):" -ForegroundColor Green
$updateButtonResult = Select-String -Path $createQuizzesPath -Pattern "updateCreateButton"
if ($updateButtonResult) {
    Write-Host "✅ updateCreateButton function found - Button progress enabled!" -ForegroundColor Green
    $updateButtonResult | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
} else {
    Write-Host "❌ updateCreateButton function NOT found!" -ForegroundColor Red
}

Write-Host ""

# Check if Creating Exam text exists (should exist)
Write-Host "✅ Checking for 'Creating Exam' text (should exist):" -ForegroundColor Green
$creatingExamResult = Select-String -Path $createQuizzesPath -Pattern "Creating Exam"
if ($creatingExamResult) {
    Write-Host "✅ Button progress text found!" -ForegroundColor Green
    $creatingExamResult | ForEach-Object { Write-Host "$($_.LineNumber): $($_.Line.Trim())" -ForegroundColor White }
} else {
    Write-Host "❌ 'Creating Exam' text NOT found!" -ForegroundColor Red
}

Write-Host ""
Write-Host "🎯 Summary:" -ForegroundColor Cyan
Write-Host "- If showProgressBar, hideProgressBar, and live-progress-container are NOT found = Progress bar successfully removed" -ForegroundColor White
Write-Host "- If updateCreateButton and 'Creating Exam' are found = Button progress successfully added" -ForegroundColor White
Write-Host ""
Write-Host "✅ Verification complete!" -ForegroundColor Green
