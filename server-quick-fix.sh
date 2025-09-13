#!/bin/bash

# Quick Fix for Server Syntax Error
# Run this on your server

echo "🔧 Quick Fix for Server Syntax Error"

# Navigate to project directory
cd /var/www/html/public_html

FILE_PATH="QuizWhiz AI v1.2.0/dist/quiz-master/app/Filament/User/Resources/QuizzesResource/Pages/CreateQuizzes.php"

# Backup
cp "$FILE_PATH" "$FILE_PATH.backup"

# Remove the entire JavaScript section temporarily
echo "🔧 Temporarily removing JavaScript section..."
sed -i '/\/\/ Add progress bar for processing quizzes/,/^\s*\);/d' "$FILE_PATH"

# Add a simple version back
echo "🔧 Adding simplified JavaScript section..."
cat >> "$FILE_PATH" << 'EOF'
    public function mount(): void
    {
        parent::mount();
        
        // Simple progress bar script
        $this->js('
            console.log("Create page loaded");
        ');
    }
}
EOF

echo "✅ Fix applied! Try running: php artisan view:clear"
