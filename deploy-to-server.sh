#!/bin/bash

# QuizWhiz AI - Live Server Deployment Script
# This script deploys the application to a live server

echo "🚀 QuizWhiz AI - Live Server Deployment"
echo "========================================"

# Configuration
SERVER_USER="your_username"
SERVER_HOST="your_server_ip"
SERVER_PATH="/var/www/html/public_html"
BACKUP_PATH="/var/www/html/backups"
APP_NAME="QuizWhiz AI v1.2.0"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if required parameters are provided
if [ "$1" = "" ] || [ "$2" = "" ]; then
    print_error "Usage: ./deploy-to-server.sh <server_user> <server_ip>"
    print_error "Example: ./deploy-to-server.sh root 192.168.1.100"
    exit 1
fi

SERVER_USER=$1
SERVER_HOST=$2

print_status "Starting deployment to $SERVER_USER@$SERVER_HOST"

# Step 1: Create deployment package
print_status "Creating deployment package..."
if [ -d "dist" ]; then
    rm -rf dist
fi

mkdir -p dist
cp -r "QuizWhiz AI v1.2.0/dist/quiz-master/"* dist/
cp .env dist/
cp composer.json dist/
cp composer.lock dist/

# Step 2: Create deployment archive
print_status "Creating deployment archive..."
tar -czf quizwhiz-deployment.tar.gz -C dist .
print_success "Deployment package created: quizwhiz-deployment.tar.gz"

# Step 3: Upload to server
print_status "Uploading files to server..."
scp quizwhiz-deployment.tar.gz $SERVER_USER@$SERVER_HOST:/tmp/

# Step 4: Deploy on server
print_status "Deploying on server..."
ssh $SERVER_USER@$SERVER_HOST << EOF
    echo "📦 Starting server deployment..."
    
    # Create backup
    if [ -d "$SERVER_PATH" ]; then
        echo "📋 Creating backup..."
        mkdir -p $BACKUP_PATH
        cp -r $SERVER_PATH $BACKUP_PATH/backup-\$(date +%Y%m%d-%H%M%S)
    fi
    
    # Extract new files
    echo "📂 Extracting new files..."
    cd $SERVER_PATH
    tar -xzf /tmp/quizwhiz-deployment.tar.gz
    
    # Set permissions
    echo "🔐 Setting permissions..."
    chown -R www-data:www-data $SERVER_PATH
    chmod -R 755 $SERVER_PATH
    chmod -R 777 $SERVER_PATH/storage
    chmod -R 777 $SERVER_PATH/bootstrap/cache
    
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
    
    # Set up queue worker (if needed)
    echo "⚙️ Setting up queue worker..."
    php artisan queue:restart
    
    echo "✅ Deployment completed successfully!"
EOF

# Step 5: Cleanup
print_status "Cleaning up..."
rm -rf dist
rm quizwhiz-deployment.tar.gz

print_success "🎉 Deployment completed successfully!"
print_status "Your QuizWhiz AI application is now live at: http://$SERVER_HOST"
print_warning "Don't forget to:"
print_warning "1. Update your domain DNS settings"
print_warning "2. Configure SSL certificate"
print_warning "3. Set up cron jobs for queue processing"
print_warning "4. Configure your AI API keys in the admin panel"
