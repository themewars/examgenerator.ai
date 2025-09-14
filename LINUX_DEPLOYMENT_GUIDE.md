# 🚀 QuizWhiz AI v1.2.0 - Linux Server Deployment Guide

## 📋 **Prerequisites**

### **Server Requirements**
- **OS**: Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- **RAM**: Minimum 2GB (Recommended 4GB+)
- **Storage**: Minimum 20GB SSD
- **CPU**: 2+ cores
- **Network**: Static IP address

### **Software Requirements**
- **PHP**: 8.1 or higher
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Composer**: Latest version
- **Node.js**: 16+ (for asset compilation)

---

## 🚀 **Method 1: Automated Deployment (Recommended)**

### **Step 1: Upload Deployment Script**
```bash
# Upload the deployment script to your server
scp deploy-to-linux-server.sh root@your-server-ip:/root/
```

### **Step 2: Run Deployment Script**
```bash
# SSH into your server
ssh root@your-server-ip

# Make script executable
chmod +x deploy-to-linux-server.sh

# Run deployment (this will take 10-15 minutes)
./deploy-to-linux-server.sh
```

### **Step 3: Upload Project Files**
```bash
# From your local machine, upload project files
scp -r "QuizWhiz AI v1.2.0/dist/quiz-master/*" root@your-server-ip:/var/www/html/quizwhiz-ai/
```

### **Step 4: Configure Environment**
```bash
# SSH into server and edit environment file
ssh root@your-server-ip
cd /var/www/html/quizwhiz-ai
nano .env
```

**Update these values in .env:**
```env
APP_NAME="QuizWhiz AI"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=quizwhiz_ai
DB_USERNAME=quizwhiz_user
DB_PASSWORD=your_secure_password

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls

OPENAI_API_KEY=your-openai-api-key
```

---

## 🔧 **Method 2: Manual Deployment**

### **Step 1: Install Required Software**

#### **Ubuntu/Debian:**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.1
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-xml php8.1-gd php8.1-mbstring php8.1-curl php8.1-zip php8.1-intl php8.1-bcmath

# Install MySQL
sudo apt install -y mysql-server

# Install Nginx
sudo apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo bash -
sudo apt install -y nodejs
```

#### **CentOS/RHEL:**
```bash
# Install EPEL repository
sudo yum install -y epel-release

# Install PHP 8.1
sudo yum install -y php81 php81-php-cli php81-php-fpm php81-php-mysql php81-php-xml php81-php-gd php81-php-mbstring php81-php-curl php81-php-zip php81-php-intl php81-php-bcmath

# Install MySQL
sudo yum install -y mysql-server

# Install Nginx
sudo yum install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### **Step 2: Configure MySQL**
```bash
# Start MySQL service
sudo systemctl start mysql
sudo systemctl enable mysql

# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
mysql -u root -p
```

```sql
CREATE DATABASE quizwhiz_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'quizwhiz_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON quizwhiz_ai.* TO 'quizwhiz_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### **Step 3: Upload Project Files**
```bash
# Create project directory
sudo mkdir -p /var/www/html/quizwhiz-ai
sudo chown -R www-data:www-data /var/www/html/quizwhiz-ai

# Upload files (from your local machine)
scp -r "QuizWhiz AI v1.2.0/dist/quiz-master/*" root@your-server-ip:/var/www/html/quizwhiz-ai/
```

### **Step 4: Install Dependencies**
```bash
cd /var/www/html/quizwhiz-ai
composer install --optimize-autoloader --no-dev
```

### **Step 5: Configure Environment**
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit environment file
nano .env
```

### **Step 6: Run Migrations**
```bash
php artisan migrate --force
php artisan storage:link
```

### **Step 7: Configure Web Server**

#### **Nginx Configuration:**
```bash
sudo nano /etc/nginx/sites-available/quizwhiz-ai
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/html/quizwhiz-ai/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/quizwhiz-ai /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
sudo systemctl enable nginx
sudo systemctl enable php8.1-fpm
```

#### **Apache Configuration:**
```bash
sudo nano /etc/apache2/sites-available/quizwhiz-ai.conf
```

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/html/quizwhiz-ai/public

    <Directory /var/www/html/quizwhiz-ai/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/quizwhiz_error.log
    CustomLog ${APACHE_LOG_DIR}/quizwhiz_access.log combined
</VirtualHost>
```

```bash
# Enable site and modules
sudo a2ensite quizwhiz-ai
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### **Step 8: Set Permissions**
```bash
sudo chown -R www-data:www-data /var/www/html/quizwhiz-ai
sudo chmod -R 755 /var/www/html/quizwhiz-ai
sudo chmod -R 775 /var/www/html/quizwhiz-ai/storage
sudo chmod -R 775 /var/www/html/quizwhiz-ai/bootstrap/cache
sudo chmod 600 /var/www/html/quizwhiz-ai/.env
```

### **Step 9: Cache Configurations**
```bash
cd /var/www/html/quizwhiz-ai
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

### **Step 10: Set Up SSL (Let's Encrypt)**
```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get SSL certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### **Step 11: Set Up Cron Job**
```bash
# Edit crontab
sudo crontab -e

# Add this line
* * * * * cd /var/www/html/quizwhiz-ai && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔐 **Security Configuration**

### **Firewall Setup**
```bash
# Ubuntu/Debian
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# CentOS/RHEL
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### **File Permissions**
```bash
# Secure sensitive files
sudo chmod 600 /var/www/html/quizwhiz-ai/.env
sudo chmod 644 /var/www/html/quizwhiz-ai/public/.htaccess

# Secure directories
sudo chmod -R 755 /var/www/html/quizwhiz-ai
sudo chmod -R 775 /var/www/html/quizwhiz-ai/storage
sudo chmod -R 775 /var/www/html/quizwhiz-ai/bootstrap/cache
```

---

## 📊 **Performance Optimization**

### **PHP Configuration**
```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

**Update these values:**
```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
max_input_vars = 3000
```

### **MySQL Configuration**
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

**Add these optimizations:**
```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 64M
query_cache_limit = 2M
max_connections = 200
```

---

## 🔍 **Testing & Verification**

### **Test Commands**
```bash
# Test PHP
php -v

# Test MySQL connection
php artisan migrate:status

# Test web server
curl -I http://yourdomain.com

# Check logs
tail -f /var/www/html/quizwhiz-ai/storage/logs/laravel.log
tail -f /var/log/nginx/error.log
```

### **Functionality Tests**
- [ ] Homepage loads correctly
- [ ] User registration works
- [ ] User login works
- [ ] Quiz creation works
- [ ] Quiz taking works
- [ ] Admin panel accessible
- [ ] File uploads work
- [ ] Email notifications work

---

## 🆘 **Troubleshooting**

### **Common Issues:**

#### **500 Internal Server Error**
```bash
# Check file permissions
sudo chown -R www-data:www-data /var/www/html/quizwhiz-ai
sudo chmod -R 755 /var/www/html/quizwhiz-ai
sudo chmod -R 775 /var/www/html/quizwhiz-ai/storage
sudo chmod -R 775 /var/www/html/quizwhiz-ai/bootstrap/cache

# Check logs
tail -f /var/log/nginx/error.log
tail -f /var/www/html/quizwhiz-ai/storage/logs/laravel.log
```

#### **Database Connection Error**
```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -u quizwhiz_user -p quizwhiz_ai

# Check .env file
cat /var/www/html/quizwhiz-ai/.env | grep DB_
```

#### **Permission Denied**
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/html/quizwhiz-ai

# Fix permissions
sudo chmod -R 755 /var/www/html/quizwhiz-ai
sudo chmod -R 775 /var/www/html/quizwhiz-ai/storage
sudo chmod -R 775 /var/www/html/quizwhiz-ai/bootstrap/cache
```

---

## 📈 **Monitoring & Maintenance**

### **Backup Script**
```bash
sudo nano /usr/local/bin/backup-quizwhiz.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/quizwhiz"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u quizwhiz_user -p'your_password' quizwhiz_ai > $BACKUP_DIR/db_backup_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz /var/www/html/quizwhiz-ai

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

```bash
sudo chmod +x /usr/local/bin/backup-quizwhiz.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-quizwhiz.sh
```

### **Log Monitoring**
```bash
# Application logs
tail -f /var/www/html/quizwhiz-ai/storage/logs/laravel.log

# Web server logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# System logs
tail -f /var/log/syslog
```

---

## ✅ **Deployment Checklist**

### **Pre-Deployment**
- [ ] Server requirements met
- [ ] Domain DNS configured
- [ ] SSL certificate ready
- [ ] Database credentials prepared
- [ ] Environment variables ready

### **Deployment**
- [ ] Project files uploaded
- [ ] Dependencies installed
- [ ] Database configured
- [ ] Environment configured
- [ ] Migrations run
- [ ] Web server configured
- [ ] SSL certificate installed
- [ ] Permissions set

### **Post-Deployment**
- [ ] Site accessible
- [ ] All features working
- [ ] Performance optimized
- [ ] Monitoring set up
- [ ] Backups configured
- [ ] Security hardened

---

## 🎉 **Success!**

Your QuizWhiz AI should now be live at:
**https://yourdomain.com**

### **Available URLs:**
- **Main Site**: https://yourdomain.com
- **Admin Panel**: https://yourdomain.com/admin
- **User Dashboard**: https://yourdomain.com/user/dashboard

### **Support:**
- Check logs for any issues
- Monitor server resources
- Keep backups updated
- Update dependencies regularly

---

## 📞 **Need Help?**

If you encounter any issues:
1. Check the logs first
2. Verify file permissions
3. Test database connection
4. Check web server configuration
5. Review environment variables

**Happy Deploying! 🚀**
