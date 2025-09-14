#!/bin/bash

# QuizWhiz AI - Production Deployment Script
# Complete production deployment with all optimizations

echo "🚀 QuizWhiz AI - Production Deployment"
echo "======================================"

# Configuration
APP_NAME="QuizWhiz AI"
APP_VERSION="v1.2.0"
DEPLOY_PATH="/var/www/html/public_html"
BACKUP_PATH="/var/www/html/backups"
LOG_PATH="/var/log/quizwhiz"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root (use sudo)"
    exit 1
fi

# Create backup
print_status "Creating backup..."
mkdir -p $BACKUP_PATH
if [ -d "$DEPLOY_PATH" ]; then
    cp -r $DEPLOY_PATH $BACKUP_PATH/backup-$(date +%Y%m%d-%H%M%S)
    print_success "Backup created"
fi

# Extract application
print_status "Extracting application files..."
cd $DEPLOY_PATH
tar -xzf /tmp/quizwhiz-deployment.tar.gz

# Set permissions
print_status "Setting proper permissions..."
chown -R www-data:www-data $DEPLOY_PATH
chmod -R 755 $DEPLOY_PATH
chmod -R 777 $DEPLOY_PATH/storage
chmod -R 777 $DEPLOY_PATH/bootstrap/cache

# Install dependencies
print_status "Installing production dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Generate application key
print_status "Generating application key..."
php artisan key:generate --force

# Run database migrations
print_status "Running database migrations..."
php artisan migrate --force

# Seed database if needed
print_status "Seeding database..."
php artisan db:seed --force

# Clear all caches
print_status "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
print_status "Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Create storage link
print_status "Creating storage link..."
php artisan storage:link

# Set up queue worker
print_status "Setting up queue worker..."
php artisan queue:restart

# Configure supervisor for queue workers
print_status "Configuring supervisor for queue workers..."
cat > /etc/supervisor/conf.d/quizwhiz-worker.conf << 'EOF'
[program:quizwhiz-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/public_html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/quizwhiz/worker.log
stopwaitsecs=3600
EOF

# Create log directory
mkdir -p $LOG_PATH
chown -R www-data:www-data $LOG_PATH

# Restart supervisor
systemctl restart supervisor

# Set up log rotation
print_status "Setting up log rotation..."
cat > /etc/logrotate.d/quizwhiz << 'EOF'
/var/log/quizwhiz/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload supervisor
    endscript
}
EOF

# Set up database backup cron job
print_status "Setting up database backup..."
cat > /usr/local/bin/backup-quizwhiz-db.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/www/html/backups/database"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

mysqldump -u quizwhiz_user -p'your_secure_password' quizwhiz_ai > $BACKUP_DIR/quizwhiz_ai_$DATE.sql
gzip $BACKUP_DIR/quizwhiz_ai_$DATE.sql

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
EOF

chmod +x /usr/local/bin/backup-quizwhiz-db.sh

# Add to crontab
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-quizwhiz-db.sh") | crontab -

# Set up monitoring
print_status "Setting up monitoring..."
cat > /usr/local/bin/quizwhiz-health-check.sh << 'EOF'
#!/bin/bash
# Health check script for QuizWhiz AI

LOG_FILE="/var/log/quizwhiz/health-check.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

# Check if application is responding
if curl -f -s http://localhost/ > /dev/null; then
    echo "[$DATE] Application is healthy" >> $LOG_FILE
else
    echo "[$DATE] Application is not responding" >> $LOG_FILE
    # Restart services
    systemctl restart nginx
    systemctl restart php8.2-fpm
fi

# Check disk space
DISK_USAGE=$(df /var/www/html | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "[$DATE] WARNING: Disk usage is ${DISK_USAGE}%" >> $LOG_FILE
fi

# Check memory usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
if [ $MEMORY_USAGE -gt 90 ]; then
    echo "[$DATE] WARNING: Memory usage is ${MEMORY_USAGE}%" >> $LOG_FILE
fi
EOF

chmod +x /usr/local/bin/quizwhiz-health-check.sh

# Add health check to crontab
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/quizwhiz-health-check.sh") | crontab -

# Restart services
print_status "Restarting services..."
systemctl restart nginx
systemctl restart php8.2-fpm
systemctl restart supervisor

# Final optimizations
print_status "Applying final optimizations..."
echo "opcache.enable=1" >> /etc/php/8.2/fpm/conf.d/99-opcache.ini
echo "opcache.memory_consumption=256" >> /etc/php/8.2/fpm/conf.d/99-opcache.ini
echo "opcache.max_accelerated_files=20000" >> /etc/php/8.2/fpm/conf.d/99-opcache.ini
echo "opcache.validate_timestamps=0" >> /etc/php/8.2/fpm/conf.d/99-opcache.ini

systemctl restart php8.2-fpm

print_success "🎉 Production deployment completed successfully!"
print_status "Application is now live and optimized for production"
print_warning "Important reminders:"
print_warning "1. Update your AI API keys in the admin panel"
print_warning "2. Configure your domain DNS settings"
print_warning "3. Set up SSL certificate with Let's Encrypt"
print_warning "4. Monitor logs at: $LOG_PATH"
print_warning "5. Database backups are stored at: $BACKUP_PATH/database"
