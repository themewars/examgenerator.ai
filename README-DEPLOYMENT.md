# QuizWhiz AI - Live Server Deployment Guide

## 🚀 Quick Start

### 1. Server Setup
```bash
# Run on your server
chmod +x server-setup.sh
./server-setup.sh
```

### 2. Deploy Application
```bash
# From your local machine
chmod +x deploy-to-server.sh
./deploy-to-server.sh root your_server_ip
```

### 3. Production Deployment
```bash
# On your server
chmod +x production-deploy.sh
./production-deploy.sh
```

## 📋 Prerequisites

### Server Requirements
- Ubuntu 20.04+ or CentOS 8+
- Root access
- Domain name pointing to server
- Minimum 2GB RAM, 20GB storage

### Local Requirements
- SSH access to server
- SCP installed
- PowerShell (for Windows) or Bash (for Linux/Mac)

## 🛠️ Deployment Scripts

### 1. `server-setup.sh`
Complete server environment setup including:
- Nginx web server
- PHP 8.2 with all extensions
- MySQL database
- Composer
- Node.js
- SSL configuration
- Firewall setup

### 2. `deploy-to-server.sh`
Deploys application to live server:
- Creates deployment package
- Uploads to server
- Extracts files
- Sets permissions
- Installs dependencies
- Runs migrations
- Optimizes for production

### 3. `production-deploy.sh`
Complete production deployment with:
- Database backups
- Queue workers
- Log rotation
- Health monitoring
- Performance optimization
- Security hardening

### 4. `quick-deploy.sh`
Fast deployment for testing:
- Minimal setup
- Quick configuration
- Development-ready

## 🔧 Configuration

### Environment Variables
Update `.env` file with your settings:
```env
APP_NAME="QuizWhiz AI"
APP_ENV=production
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=quizwhiz_ai
DB_USERNAME=quizwhiz_user
DB_PASSWORD=your_secure_password

OPENAI_API_KEY=your_openai_api_key
GEMINI_API_KEY=your_gemini_api_key
```

### Domain Configuration
1. Update DNS settings to point to your server
2. Run SSL setup:
```bash
certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

## 📊 Monitoring

### Health Checks
- Application response monitoring
- Disk space monitoring
- Memory usage monitoring
- Automatic service restart

### Logs
- Application logs: `/var/log/quizwhiz/`
- Nginx logs: `/var/log/nginx/`
- PHP logs: `/var/log/php8.2-fpm.log`

### Backups
- Database backups: Daily at 2 AM
- Application backups: Before each deployment
- Retention: 7 days for database, 30 days for application

## 🔐 Security

### Firewall
- Only HTTP (80), HTTPS (443), and SSH (22) ports open
- Fail2ban protection
- Regular security updates

### SSL/TLS
- Let's Encrypt certificates
- Automatic renewal
- HTTP to HTTPS redirect

### File Permissions
- Proper ownership (www-data:www-data)
- Secure directory permissions
- Protected sensitive files

## 🚨 Troubleshooting

### Common Issues

1. **Permission Errors**
```bash
chown -R www-data:www-data /var/www/html/public_html
chmod -R 755 /var/www/html/public_html
```

2. **Database Connection Issues**
```bash
mysql -u root -p
GRANT ALL PRIVILEGES ON quizwhiz_ai.* TO 'quizwhiz_user'@'localhost';
FLUSH PRIVILEGES;
```

3. **Queue Workers Not Running**
```bash
systemctl restart supervisor
supervisorctl status
```

4. **Cache Issues**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Log Analysis
```bash
# Check application logs
tail -f /var/log/quizwhiz/worker.log

# Check Nginx logs
tail -f /var/log/nginx/error.log

# Check PHP logs
tail -f /var/log/php8.2-fpm.log
```

## 📞 Support

For deployment issues:
1. Check logs first
2. Verify all services are running
3. Test database connectivity
4. Check file permissions
5. Review firewall settings

## 🎯 Post-Deployment Checklist

- [ ] Domain DNS updated
- [ ] SSL certificate installed
- [ ] AI API keys configured
- [ ] Database seeded
- [ ] Queue workers running
- [ ] Monitoring active
- [ ] Backups scheduled
- [ ] Health checks passing
- [ ] Performance optimized
- [ ] Security hardened
