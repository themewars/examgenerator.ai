#!/bin/bash

echo "🔍 Verifying Progress Bar Removal"
echo "================================="

# Navigate to project directory
cd "C:\Users\rai37\OneDrive\Desktop\QuizWhiz AI v1.2.0"

# Check if directory exists
if [ ! -d "." ]; then
    echo "❌ Error: Project directory not found!"
    exit 1
fi

echo "📋 Checking current CreateQuizzes.php file:"
echo ""

# Check if showProgressBar function exists (should NOT exist)
echo "❌ Checking for showProgressBar function (should be removed):"
grep -n "showProgressBar" "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php" || echo "✅ showProgressBar function NOT found - Successfully removed!"

echo ""

# Check if hideProgressBar function exists (should NOT exist)
echo "❌ Checking for hideProgressBar function (should be removed):"
grep -n "hideProgressBar" "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php" || echo "✅ hideProgressBar function NOT found - Successfully removed!"

echo ""

# Check if live-progress-container creation exists (should NOT exist)
echo "❌ Checking for live-progress-container creation (should be removed):"
grep -n "live-progress-container" "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php" || echo "✅ live-progress-container creation NOT found - Successfully removed!"

echo ""

# Check if updateCreateButton function exists (should exist)
echo "✅ Checking for updateCreateButton function (should exist):"
grep -n "updateCreateButton" "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php" && echo "✅ updateCreateButton function found - Button progress enabled!"

echo ""

# Check if Creating Exam text exists (should exist)
echo "✅ Checking for 'Creating Exam' text (should exist):"
grep -n "Creating Exam" "QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php" && echo "✅ Button progress text found!"

echo ""
echo "🎯 Summary:"
echo "- If showProgressBar, hideProgressBar, and live-progress-container are NOT found = Progress bar successfully removed"
echo "- If updateCreateButton and 'Creating Exam' are found = Button progress successfully added"
echo ""
echo "✅ Verification complete!"
