# QuizWhiz AI - Live Server Deployment Script (PowerShell)
# This script deploys the application to a live server

param(
    [Parameter(Mandatory=$true)]
    [string]$ServerUser,
    
    [Parameter(Mandatory=$true)]
    [string]$ServerHost,
    
    [string]$ServerPath = "/var/www/html/public_html",
    [string]$BackupPath = "/var/www/html/backups"
)

Write-Host "🚀 QuizWhiz AI - Live Server Deployment" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Function to print colored output
function Write-Status {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Blue
}

function Write-Success {
    param([string]$Message)
    Write-Host "[SUCCESS] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

Write-Status "Starting deployment to $ServerUser@$ServerHost"

# Step 1: Create deployment package
Write-Status "Creating deployment package..."
if (Test-Path "dist") {
    Remove-Item -Path "dist" -Recurse -Force
}

New-Item -ItemType Directory -Path "dist" -Force | Out-Null
Copy-Item -Path "QuizWhiz AI v1.2.0\dist\quiz-master\*" -Destination "dist\" -Recurse -Force
Copy-Item -Path ".env" -Destination "dist\" -Force
Copy-Item -Path "composer.json" -Destination "dist\" -Force
Copy-Item -Path "composer.lock" -Destination "dist\" -Force

# Step 2: Create deployment archive
Write-Status "Creating deployment archive..."
Compress-Archive -Path "dist\*" -DestinationPath "quizwhiz-deployment.zip" -Force
Write-Success "Deployment package created: quizwhiz-deployment.zip"

# Step 3: Upload to server
Write-Status "Uploading files to server..."
scp quizwhiz-deployment.zip "$ServerUser@$ServerHost:/tmp/"

# Step 4: Deploy on server
Write-Status "Deploying on server..."
$deployScript = @"
echo "📦 Starting server deployment..."

# Create backup
if [ -d "$ServerPath" ]; then
    echo "📋 Creating backup..."
    mkdir -p $BackupPath
    cp -r $ServerPath $BackupPath/backup-\$(date +%Y%m%d-%H%M%S)
fi

# Extract new files
echo "📂 Extracting new files..."
cd $ServerPath
unzip -o /tmp/quizwhiz-deployment.zip

# Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data $ServerPath
chmod -R 755 $ServerPath
chmod -R 777 $ServerPath/storage
chmod -R 777 $ServerPath/bootstrap/cache

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate --force

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link

# Set up queue worker
echo "⚙️ Setting up queue worker..."
php artisan queue:restart

echo "✅ Deployment completed successfully!"
"@

ssh "$ServerUser@$ServerHost" $deployScript

# Step 5: Cleanup
Write-Status "Cleaning up..."
Remove-Item -Path "dist" -Recurse -Force
Remove-Item -Path "quizwhiz-deployment.zip" -Force

Write-Success "🎉 Deployment completed successfully!"
Write-Status "Your QuizWhiz AI application is now live at: http://$ServerHost"
Write-Warning "Don't forget to:"
Write-Warning "1. Update your domain DNS settings"
Write-Warning "2. Configure SSL certificate"
Write-Warning "3. Set up cron jobs for queue processing"
Write-Warning "4. Configure your AI API keys in the admin panel"
