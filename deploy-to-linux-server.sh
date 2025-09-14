#!/bin/bash

# 🚀 QuizWhiz AI v1.2.0 - Linux Server Deployment Script
# This script will deploy your Laravel application to a Linux server

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration Variables (Update these for your server)
DOMAIN="examgenerator.ai"
SERVER_USER="root"
SERVER_IP="your-server-ip"
PROJECT_NAME="quizwhiz-ai"
WEB_ROOT="/var/www/html"
PROJECT_PATH="$WEB_ROOT/$PROJECT_NAME"
DB_NAME="quizwhiz_ai"
DB_USER="quizwhiz_user"
DB_PASSWORD="your_secure_password_here"

echo -e "${BLUE}🚀 QuizWhiz AI v1.2.0 - Linux Server Deployment${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run this script as root or with sudo"
    exit 1
fi

print_info "Starting deployment process..."

# Step 1: Update system packages
print_info "Updating system packages..."
apt update && apt upgrade -y
print_status "System packages updated"

# Step 2: Install required packages
print_info "Installing required packages..."
apt install -y software-properties-common curl wget git unzip

# Add PHP repository
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP 8.1 and extensions
apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-xml php8.1-gd php8.1-mbstring php8.1-curl php8.1-zip php8.1-intl php8.1-bcmath php8.1-json php8.1-tokenizer php8.1-fileinfo php8.1-openssl

# Install MySQL
apt install -y mysql-server mysql-client

# Install Nginx
apt install -y nginx

# Install Composer
print_info "Installing Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
print_status "Composer installed"

# Install Node.js and npm
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

print_status "All required packages installed"

# Step 3: Configure MySQL
print_info "Configuring MySQL..."
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
print_status "MySQL configured"

# Step 4: Create project directory
print_info "Creating project directory..."
mkdir -p $PROJECT_PATH
chown -R www-data:www-data $PROJECT_PATH
print_status "Project directory created"

# Step 5: Upload project files (Manual step)
print_warning "IMPORTANT: Please upload your project files to $PROJECT_PATH"
print_warning "You can use SCP, SFTP, or Git to upload files"
print_warning "Example SCP command:"
print_warning "scp -r /path/to/your/project/* $SERVER_USER@$SERVER_IP:$PROJECT_PATH/"
echo ""
read -p "Press Enter after uploading files to continue..."

# Step 6: Set proper permissions
print_info "Setting file permissions..."
cd $PROJECT_PATH
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 775 storage bootstrap/cache
chmod 600 .env
print_status "File permissions set"

# Step 7: Install Composer dependencies
print_info "Installing Composer dependencies..."
composer install --optimize-autoloader --no-dev
print_status "Composer dependencies installed"

# Step 8: Configure environment
print_info "Configuring environment..."
if [ ! -f .env ]; then
    cp .env.example .env
    print_warning "Please edit .env file with your configuration"
fi

# Generate application key
php artisan key:generate --force
print_status "Environment configured"

# Step 9: Run database migrations
print_info "Running database migrations..."
php artisan migrate --force
print_status "Database migrations completed"

# Step 10: Create storage link
print_info "Creating storage link..."
php artisan storage:link
print_status "Storage link created"

# Step 11: Cache configurations
print_info "Caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
print_status "Configurations cached"

# Step 12: Configure Nginx
print_info "Configuring Nginx..."
cat > /etc/nginx/sites-available/$PROJECT_NAME << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;
    root $PROJECT_PATH/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Enable site
ln -sf /etc/nginx/sites-available/$PROJECT_NAME /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test Nginx configuration
nginx -t
systemctl restart nginx
systemctl enable nginx
print_status "Nginx configured and started"

# Step 13: Configure PHP-FPM
print_info "Configuring PHP-FPM..."
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.1/fpm/php.ini
systemctl restart php8.1-fpm
systemctl enable php8.1-fpm
print_status "PHP-FPM configured"

# Step 14: Configure MySQL
print_info "Configuring MySQL..."
systemctl restart mysql
systemctl enable mysql
print_status "MySQL configured"

# Step 15: Set up SSL with Let's Encrypt (Optional)
print_info "Setting up SSL certificate..."
if command -v certbot &> /dev/null; then
    apt install -y certbot python3-certbot-nginx
    certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN
    print_status "SSL certificate installed"
else
    print_warning "Certbot not available. Please install SSL certificate manually."
fi

# Step 16: Set up cron job
print_info "Setting up cron job..."
(crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1") | crontab -
print_status "Cron job configured"

# Step 17: Configure firewall
print_info "Configuring firewall..."
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
print_status "Firewall configured"

# Step 18: Create backup script
print_info "Creating backup script..."
cat > /usr/local/bin/backup-quizwhiz.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/quizwhiz"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u quizwhiz_user -p$DB_PASSWORD quizwhiz_ai > $BACKUP_DIR/db_backup_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz /var/www/html/quizwhiz-ai

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /usr/local/bin/backup-quizwhiz.sh

# Add backup to crontab (daily at 2 AM)
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-quizwhiz.sh") | crontab -
print_status "Backup script created"

# Step 19: Final optimizations
print_info "Running final optimizations..."
composer dump-autoload --optimize
php artisan optimize
print_status "Optimizations completed"

# Step 20: Test deployment
print_info "Testing deployment..."
if curl -f http://localhost > /dev/null 2>&1; then
    print_status "Deployment test successful!"
else
    print_error "Deployment test failed. Please check logs."
fi

echo ""
echo -e "${GREEN}🎉 Deployment Completed Successfully!${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo -e "${BLUE}📋 Deployment Summary:${NC}"
echo -e "   • Domain: http://$DOMAIN"
echo -e "   • Project Path: $PROJECT_PATH"
echo -e "   • Database: $DB_NAME"
echo -e "   • Web Server: Nginx"
echo -e "   • PHP Version: 8.1"
echo -e "   • SSL: $(if [ -f /etc/letsencrypt/live/$DOMAIN/fullchain.pem ]; then echo 'Enabled'; else echo 'Not configured'; fi)"
echo ""
echo -e "${BLUE}🔧 Next Steps:${NC}"
echo -e "   1. Update DNS records to point to this server"
echo -e "   2. Configure your .env file with production values"
echo -e "   3. Test all functionality"
echo -e "   4. Set up monitoring and alerts"
echo ""
echo -e "${BLUE}📞 Support:${NC}"
echo -e "   • Check logs: tail -f $PROJECT_PATH/storage/logs/laravel.log"
echo -e "   • Nginx logs: tail -f /var/log/nginx/error.log"
echo -e "   • PHP logs: tail -f /var/log/php8.1-fpm.log"
echo ""
echo -e "${GREEN}✅ Your QuizWhiz AI is now live!${NC}"
