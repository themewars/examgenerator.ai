# 🚀 QuizWhiz AI - Live Server Migration Guide

## 📋 **Overview**

This guide helps you run database migrations on your live QuizWhiz AI server to fix the "Column not found: type" error.

## ⚠️ **IMPORTANT WARNINGS**

- **ALWAYS BACKUP** your database before running migrations
- **Test migrations** on a staging environment first if possible
- **Schedule maintenance window** for production deployments
- **Monitor application** after migration completion

---

## 🚀 **Method 1: Automated Migration (Recommended)**

### **For Linux/Mac Servers:**

1. **Upload migration script to server:**
   ```bash
   scp run-live-migration.sh root@your-server-ip:/root/
   ```

2. **SSH into server:**
   ```bash
   ssh root@your-server-ip
   ```

3. **Make script executable and run:**
   ```bash
   chmod +x run-live-migration.sh
   ./run-live-migration.sh
   ```

### **For Windows Servers:**

1. **Upload PowerShell script to server**
2. **Run as Administrator:**
   ```powershell
   .\run-live-migration.ps1
   ```

---

## 🔧 **Method 2: Manual Migration**

### **Step 1: Create Database Backup**

```bash
# Create backup directory
mkdir -p /var/backups/quizwhiz

# Create database backup
mysqldump -u username -p database_name > /var/backups/quizwhiz/db_backup_$(date +%Y%m%d_%H%M%S).sql
```

### **Step 2: Enable Maintenance Mode**

```bash
cd /var/www/html/quizwhiz-ai
php artisan down --message="Database migration in progress" --retry=60
```

### **Step 3: Run Migration**

```bash
# Run specific migration
php artisan migrate --path=database/migrations/2025_01_14_123000_add_type_column_to_questions_table.php --force

# Or run all pending migrations
php artisan migrate --force
```

### **Step 4: Clear Caches**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### **Step 5: Disable Maintenance Mode**

```bash
php artisan up
```

### **Step 6: Verify Migration**

```bash
# Check if type column exists
mysql -u username -p database_name -e "DESCRIBE questions;"

# Check migration status
php artisan migrate:status
```

---

## 🗄️ **Method 3: Direct SQL Migration**

If Laravel artisan commands are not available:

### **Step 1: Create Backup**
```sql
-- Create backup
mysqldump -u username -p database_name > backup.sql
```

### **Step 2: Run SQL Commands**
```sql
-- Add type column to questions table
ALTER TABLE `questions` ADD COLUMN `type` INT DEFAULT 0 AFTER `title`;

-- Update existing questions
UPDATE `questions` SET `type` = 0 WHERE `type` IS NULL;

-- Add generation status columns to quizzes table (if missing)
ALTER TABLE `quizzes` ADD COLUMN `generation_status` VARCHAR(50) DEFAULT 'pending' AFTER `is_show_home`;
ALTER TABLE `quizzes` ADD COLUMN `generation_progress_total` INT DEFAULT 0 AFTER `generation_status`;
ALTER TABLE `quizzes` ADD COLUMN `generation_progress_done` INT DEFAULT 0 AFTER `generation_progress_total`;
ALTER TABLE `quizzes` ADD COLUMN `generation_error` TEXT NULL AFTER `generation_progress_done`;
```

### **Step 3: Verify Changes**
```sql
-- Check questions table structure
DESCRIBE questions;

-- Check quizzes table structure
DESCRIBE quizzes;
```

---

## 📊 **Pre-Migration Checklist**

- [ ] **Database backup created**
- [ ] **Application files uploaded**
- [ ] **Environment variables configured**
- [ ] **Maintenance window scheduled**
- [ ] **Team notified of downtime**
- [ ] **Rollback plan prepared**

---

## 🔍 **Post-Migration Verification**

### **1. Check Database Structure**
```sql
-- Verify type column exists
DESCRIBE questions;

-- Check for any missing columns
SHOW COLUMNS FROM questions;
SHOW COLUMNS FROM quizzes;
```

### **2. Test Application Functionality**
- [ ] **Homepage loads correctly**
- [ ] **User login works**
- [ ] **Quiz creation works**
- [ ] **Question generation works**
- [ ] **Admin panel accessible**
- [ ] **All features functional**

### **3. Monitor Application Logs**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check web server logs
tail -f /var/log/nginx/error.log
# or
tail -f /var/log/apache2/error.log
```

---

## 🆘 **Troubleshooting**

### **Common Issues:**

#### **1. Migration Fails**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check database connection
php artisan migrate:status

# Restore from backup if needed
mysql -u username -p database_name < backup.sql
```

#### **2. Column Already Exists Error**
```sql
-- Check if column exists
SHOW COLUMNS FROM questions LIKE 'type';

-- If exists, skip the ALTER TABLE command
```

#### **3. Permission Denied**
```bash
# Fix file permissions
chown -R www-data:www-data /var/www/html/quizwhiz-ai
chmod -R 755 /var/www/html/quizwhiz-ai
chmod -R 775 /var/www/html/quizwhiz-ai/storage
chmod -R 775 /var/www/html/quizwhiz-ai/bootstrap/cache
```

#### **4. Database Connection Error**
- Check `.env` file database credentials
- Verify database server is running
- Check firewall settings
- Test database connection manually

---

## 🔄 **Rollback Procedure**

If migration fails and you need to rollback:

### **1. Restore Database Backup**
```bash
mysql -u username -p database_name < /var/backups/quizwhiz/db_backup_YYYYMMDD_HHMMSS.sql
```

### **2. Clear Application Caches**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### **3. Disable Maintenance Mode**
```bash
php artisan up
```

### **4. Test Application**
- Verify application works as before
- Check all functionality
- Monitor logs for errors

---

## 📈 **Performance Considerations**

### **During Migration:**
- **Maintenance mode** prevents user access
- **Backup creation** may take time for large databases
- **Migration execution** should be fast for this specific change

### **After Migration:**
- **Clear caches** for optimal performance
- **Monitor database performance**
- **Check application response times**

---

## 📞 **Support**

If you encounter issues:

1. **Check application logs** first
2. **Verify database structure** manually
3. **Test with simple quiz creation**
4. **Contact support** with specific error messages

---

## ✅ **Success Criteria**

Migration is successful when:
- ✅ **Type column exists** in questions table
- ✅ **Quiz creation works** without errors
- ✅ **Question generation completes** successfully
- ✅ **All application features** work normally
- ✅ **No error logs** in application

---

## 🎉 **Completion**

Once migration is complete:
- **Monitor application** for 24-48 hours
- **Keep backup files** for at least 30 days
- **Update documentation** with any changes
- **Notify team** of successful completion

**Your QuizWhiz AI should now work perfectly on the live server! 🚀**
