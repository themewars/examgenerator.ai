#!/bin/bash

# QuizWhiz AI - Server Setup Script
# This script sets up the server environment for QuizWhiz AI

echo "🛠️ QuizWhiz AI - Server Setup"
echo "============================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Update system
print_status "Updating system packages..."
apt update && apt upgrade -y

# Install required packages
print_status "Installing required packages..."
apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath php8.2-cli php8.2-common php8.2-opcache php8.2-readline php8.2-soap php8.2-sqlite3 php8.2-tidy php8.2-xmlrpc php8.2-xsl

# Install Composer
print_status "Installing Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Node.js and NPM
print_status "Installing Node.js and NPM..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Create application directory
print_status "Creating application directory..."
mkdir -p /var/www/html/public_html
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Configure Nginx
print_status "Configuring Nginx..."
cat > /etc/nginx/sites-available/quizwhiz << 'EOF'
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/html/public_html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

# Enable site
ln -sf /etc/nginx/sites-available/quizwhiz /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Configure PHP-FPM
print_status "Configuring PHP-FPM..."
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.2/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 100M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 100M/' /etc/php/8.2/fpm/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini

# Configure MySQL
print_status "Configuring MySQL..."
mysql_secure_installation

# Create database
print_status "Creating database..."
mysql -u root -p << 'EOF'
CREATE DATABASE quizwhiz_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'quizwhiz_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON quizwhiz_ai.* TO 'quizwhiz_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Set up SSL with Let's Encrypt
print_status "Setting up SSL with Let's Encrypt..."
apt install -y certbot python3-certbot-nginx

# Create cron job for queue processing
print_status "Setting up cron job for queue processing..."
(crontab -l 2>/dev/null; echo "* * * * * cd /var/www/html/public_html && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# Restart services
print_status "Restarting services..."
systemctl restart nginx
systemctl restart php8.2-fpm
systemctl restart mysql
systemctl enable nginx
systemctl enable php8.2-fpm
systemctl enable mysql

# Configure firewall
print_status "Configuring firewall..."
ufw allow 'Nginx Full'
ufw allow OpenSSH
ufw --force enable

print_success "🎉 Server setup completed successfully!"
print_warning "Next steps:"
print_warning "1. Update your domain DNS to point to this server"
print_warning "2. Run: certbot --nginx -d your-domain.com -d www.your-domain.com"
print_warning "3. Deploy your QuizWhiz AI application using deploy-to-server.sh"
print_warning "4. Configure your AI API keys in the admin panel"
print_warning "5. Set up database backup cron job"
