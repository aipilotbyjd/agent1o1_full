# 🚀 Deployment Guide

**Complete guide for deploying LinkFlow to production**

---

## Overview

This guide provides a consolidated deployment reference for LinkFlow. For detailed deployment documentation, see the **[/docs/deployment/](/app/docs/deployment/)** folder which contains comprehensive step-by-step guides for multiple cloud providers.

**Target Audience:** DevOps engineers, system administrators, developers deploying to production

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [System Requirements](#system-requirements)
3. [Deployment Options](#deployment-options)
4. [Environment Configuration](#environment-configuration)
5. [Database Setup](#database-setup)
6. [Application Deployment](#application-deployment)
7. [Queue Workers](#queue-workers)
8. [Web Server Configuration](#web-server-configuration)
9. [SSL/HTTPS Setup](#sslhttps-setup)
10. [Post-Deployment Checklist](#post-deployment-checklist)
11. [CI/CD Pipeline](#cicd-pipeline)
12. [Monitoring & Health Checks](#monitoring--health-checks)
13. [Backup & Recovery](#backup--recovery)
14. [Scaling](#scaling)
15. [Troubleshooting](#troubleshooting)

---

## Quick Start

**For detailed step-by-step deployment guides, see:**
- **[Hetzner VPS Deployment](./deployment/03-hetzner-deployment.md)** - €3.79–€14/mo
- **[Azure VM Deployment](./deployment/04-azure-deployment.md)** - Free tier available
- **[AWS EC2 Deployment](./deployment/05-aws-deployment.md)** - Free tier available
- **[Docker Configuration](./deployment/02-docker-configuration.md)** - Container-based deployment

**Recommended Path:**
1. Read [Architecture Overview](./deployment/01-architecture.md)
2. Choose your provider (Hetzner/Azure/AWS)
3. Follow provider-specific guide
4. Apply [Security Hardening](./deployment/06-security-hardening.md)
5. Set up [Backups](./deployment/07-backup-strategy.md)
6. Configure [Monitoring](./deployment/08-monitoring.md)

---

## System Requirements

### Minimum Requirements (Small Workload)

**Server:**
- **CPU:** 2 vCPUs
- **RAM:** 4 GB
- **Storage:** 20 GB SSD
- **OS:** Ubuntu 22.04 LTS or later

**Software:**
- PHP 8.3+
- PostgreSQL 14+ with pgvector extension
- Redis 6+
- Nginx 1.18+ or Apache 2.4+
- Supervisor (for queue workers)
- Node.js 18+ (for frontend builds)

### Recommended Production (Medium Workload)

**Server:**
- **CPU:** 4 vCPUs
- **RAM:** 8 GB
- **Storage:** 50 GB SSD
- **OS:** Ubuntu 22.04 LTS

**Additional:**
- Dedicated Redis server (2GB RAM)
- Managed PostgreSQL (optional)
- Load balancer (for scaling)

### High-Volume Production

See **[Scaling Guide](./deployment/09-scaling.md)** for:
- Horizontal scaling strategies
- Database optimization
- Queue worker scaling
- Load balancing
- CDN integration

---

## Deployment Options

### Option 1: Docker Deployment (Recommended)

**Pros:**
- Consistent environment
- Easy scaling
- Quick setup
- Portable across providers

**See:** [Docker Configuration Guide](./deployment/02-docker-configuration.md)

**Quick Docker Setup:**
```bash
# Clone repository
git clone <your-repo>
cd linkflow

# Copy environment files
cp .env.example .env
# Edit .env with production values

# Build and start services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan db:seed --force

# Generate Passport keys
docker-compose exec app php artisan passport:install
```

---

### Option 2: Traditional VPS Deployment

**Pros:**
- Full control
- Lower cost
- No container overhead

**Provider Guides:**
- **[Hetzner VPS](./deployment/03-hetzner-deployment.md)** - Best value, €3.79/mo
- **[Azure VM](./deployment/04-azure-deployment.md)** - Free tier, enterprise features
- **[AWS EC2](./deployment/05-aws-deployment.md)** - Free tier, extensive services

**Basic VPS Setup:**
```bash
# 1. Update system
sudo apt update && sudo apt upgrade -y

# 2. Install PHP 8.3
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-pgsql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath

# 3. Install PostgreSQL 17 with pgvector
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update
sudo apt install -y postgresql-17 postgresql-17-pgvector

# 4. Install Redis
sudo apt install -y redis-server

# 5. Install Nginx
sudo apt install -y nginx

# 6. Install Supervisor
sudo apt install -y supervisor

# 7. Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## Environment Configuration

### Critical Environment Variables

**Create `/path/to/app/.env` from template:**

```env
# Application
APP_NAME="LinkFlow"
APP_ENV=production
APP_KEY=base64:YOUR_32_CHAR_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=linkflow_prod
DB_USERNAME=linkflow_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=REDIS_PASSWORD_HERE
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Passport OAuth2
PASSPORT_PERSONAL_ACCESS_CLIENT_ID=client-id-here
PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=client-secret-here

# File Storage
FILESYSTEM_DISK=s3  # or 'local' for local storage
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name

# Security
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=yourdomain.com
```

**Generate Application Key:**
```bash
php artisan key:generate
```

**⚠️ Security Notes:**
- Never commit `.env` to version control
- Use strong random passwords (32+ characters)
- Rotate secrets regularly
- Use different credentials per environment

---

## Database Setup

### PostgreSQL Installation & Configuration

**1. Create Database & User:**
```bash
sudo -u postgres psql
```

```sql
-- Create database
CREATE DATABASE linkflow_prod;

-- Create user
CREATE USER linkflow_user WITH ENCRYPTED PASSWORD 'YOUR_STRONG_PASSWORD';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE linkflow_prod TO linkflow_user;

-- Connect to database
\c linkflow_prod

-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Grant schema permissions
GRANT ALL ON SCHEMA public TO linkflow_user;

\q
```

**2. Configure PostgreSQL for Performance:**

Edit `/etc/postgresql/17/main/postgresql.conf`:

```ini
# Memory settings (for 8GB RAM server)
shared_buffers = 2GB
effective_cache_size = 6GB
maintenance_work_mem = 512MB
work_mem = 32MB

# Connection settings
max_connections = 100

# Write-ahead log
wal_buffers = 16MB
checkpoint_completion_target = 0.9

# Query planner
random_page_cost = 1.1  # For SSD
effective_io_concurrency = 200
```

Restart PostgreSQL:
```bash
sudo systemctl restart postgresql
```

**3. Run Migrations:**
```bash
cd /var/www/linkflow
php artisan migrate --force
php artisan db:seed --force  # Seed node definitions
```

**4. Verify Database:**
```bash
php artisan tinker
```
```php
// Check connection
DB::connection()->getPdo();

// Check tables
DB::select('SELECT tablename FROM pg_tables WHERE schemaname = \'public\'');

// Check pgvector
DB::select('SELECT * FROM pg_extension WHERE extname = \'vector\'');
```

---

## Application Deployment

### Method 1: Git Deployment

**Initial Setup:**
```bash
# Create application directory
sudo mkdir -p /var/www/linkflow
sudo chown -R www-data:www-data /var/www/linkflow

# Clone repository
cd /var/www
sudo -u www-data git clone <your-repo> linkflow
cd linkflow

# Install dependencies
sudo -u www-data composer install --optimize-autoloader --no-dev

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Run optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Generate Passport keys
php artisan passport:install
```

**Deployment Script (`deploy.sh`):**
```bash
#!/bin/bash
set -e

echo "🚀 Deploying LinkFlow..."

# Enter maintenance mode
php artisan down

# Pull latest code
git pull origin main

# Install/update dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
php artisan horizon:terminate

# Exit maintenance mode
php artisan up

echo "✅ Deployment complete!"
```

---

### Method 2: Docker Deployment

See **[Docker Configuration Guide](./deployment/02-docker-configuration.md)** for complete Docker setup.

**Production docker-compose.yml:**
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
    volumes:
      - ./storage:/var/www/html/storage
    depends_on:
      - postgres
      - redis
    networks:
      - linkflow

  postgres:
    image: pgvector/pgvector:pg17
    environment:
      POSTGRES_DB: linkflow_prod
      POSTGRES_USER: linkflow_user
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - linkflow

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks:
      - linkflow

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - app
    networks:
      - linkflow

  horizon:
    build:
      context: .
      dockerfile: Dockerfile
    command: php artisan horizon
    depends_on:
      - app
      - redis
    networks:
      - linkflow

volumes:
  postgres_data:
  redis_data:

networks:
  linkflow:
    driver: bridge
```

---

## Queue Workers

### Laravel Horizon Setup

LinkFlow uses Laravel Horizon for queue management.

**1. Install Horizon (already included in composer.json):**
```bash
composer require laravel/horizon
php artisan horizon:install
```

**2. Configure Horizon (`config/horizon.php`):**
```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'workflows', 'webhooks'],
            'balance' => 'auto',
            'maxProcesses' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 300,
        ],
    ],
],
```

**3. Set up Supervisor:**

Create `/etc/supervisor/conf.d/horizon.conf`:
```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/linkflow/artisan horizon
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/linkflow/storage/logs/horizon.log
stopwaitsecs=3600
```

**4. Start Supervisor:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

**5. Monitor Horizon:**
- Dashboard: `https://yourdomain.com/horizon`
- Logs: `storage/logs/horizon.log`

**Common Commands:**
```bash
# Start Horizon
php artisan horizon

# Terminate Horizon gracefully
php artisan horizon:terminate

# Pause queue processing
php artisan horizon:pause

# Continue queue processing
php artisan horizon:continue

# Check status
sudo supervisorctl status horizon
```

---

## Web Server Configuration

### Nginx Configuration (Recommended)

**Create `/etc/nginx/sites-available/linkflow`:**

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/linkflow/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Logging
    access_log /var/log/nginx/linkflow-access.log;
    error_log /var/log/nginx/linkflow-error.log;
    
    # Client upload size
    client_max_body_size 100M;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Increase timeouts for long-running workflows
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Enable site:**
```bash
sudo ln -s /etc/nginx/sites-available/linkflow /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

### Apache Configuration (Alternative)

**Create `/etc/apache2/sites-available/linkflow.conf`:**

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    
    DocumentRoot /var/www/linkflow/public
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
    
    <Directory /var/www/linkflow/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/linkflow-error.log
    CustomLog ${APACHE_LOG_DIR}/linkflow-access.log combined
</VirtualHost>
```

**Enable modules and site:**
```bash
sudo a2enmod rewrite ssl
sudo a2ensite linkflow
sudo systemctl reload apache2
```

---

## SSL/HTTPS Setup

### Let's Encrypt (Free SSL)

**1. Install Certbot:**
```bash
sudo apt install -y certbot python3-certbot-nginx
```

**2. Obtain Certificate:**
```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

**3. Auto-renewal:**
Certbot automatically sets up renewal. Test it:
```bash
sudo certbot renew --dry-run
```

**4. Renewal cron job (already set up by certbot):**
```bash
# Check cron
sudo systemctl status certbot.timer
```

---

## Post-Deployment Checklist

### Essential Checks

```bash
# 1. Application
□ Application is accessible via HTTPS
□ HTTP redirects to HTTPS
□ .env has correct production values
□ APP_DEBUG=false
□ APP_ENV=production
□ Strong APP_KEY generated

# 2. Database
□ PostgreSQL is running
□ pgvector extension enabled
□ All migrations executed
□ Database user has correct permissions
□ Backups configured

# 3. Queue Workers
□ Horizon is running
□ Supervisor configured for auto-restart
□ Jobs are processing (test with simple workflow)

# 4. Security
□ Firewall configured (UFW/iptables)
□ SSH key-only authentication
□ Root login disabled
□ SSL certificate valid
□ Security headers present
□ Rate limiting enabled

# 5. Performance
□ Opcache enabled
□ Redis connected
□ Config/route/view caches built
□ Gzip compression enabled

# 6. Monitoring
□ Log rotation configured
□ Uptime monitoring (UptimeRobot/Pingdom)
□ Error tracking (Sentry/Bugsnag)
□ Disk space alerts

# 7. Backups
□ Database backup script running
□ Offsite backup configured
□ Restore procedure tested
```

**Run automated checks:**
```bash
php artisan app:health-check
```

---

## CI/CD Pipeline

See **[CI/CD Guide](./deployment/10-cicd.md)** for complete GitHub Actions setup.

**Quick GitHub Actions Workflow:**

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Run tests
        run: php artisan test

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.HOST }}
          username: ${{ secrets.USERNAME }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /var/www/linkflow
            git pull origin main
            composer install --optimize-autoloader --no-dev
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan horizon:terminate
```

---

## Monitoring & Health Checks

See **[Monitoring Guide](./deployment/08-monitoring.md)** for detailed monitoring setup.

### Essential Monitoring

**1. Application Health Endpoint:**

Create route in `routes/api.php`:
```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::connection()->ping() ? 'connected' : 'disconnected',
        'queue' => \Horizon::stats() ? 'running' : 'stopped',
    ]);
});
```

**2. Uptime Monitoring:**
- UptimeRobot (free tier: 50 monitors)
- Pingdom
- Healthchecks.io

**3. Error Tracking:**

Install Sentry:
```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=your-dsn
```

**4. Log Monitoring:**
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Monitor Horizon
tail -f storage/logs/horizon.log

# Monitor Nginx errors
sudo tail -f /var/log/nginx/error.log
```

---

## Backup & Recovery

See **[Backup Strategy Guide](./deployment/07-backup-strategy.md)** for comprehensive backup setup.

### Database Backup Script

Create `/root/backup-db.sh`:

```bash
#!/bin/bash
set -e

BACKUP_DIR="/var/backups/linkflow"
DATE=$(date +%Y%m%d_%H%M%S)
FILENAME="linkflow_${DATE}.sql.gz"

mkdir -p $BACKUP_DIR

# Backup database
pg_dump -U linkflow_user -h localhost linkflow_prod | gzip > "${BACKUP_DIR}/${FILENAME}"

# Delete backups older than 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "Backup completed: ${FILENAME}"
```

**Schedule with cron:**
```bash
sudo crontab -e
```

Add:
```cron
# Daily database backup at 2 AM
0 2 * * * /root/backup-db.sh >> /var/log/backup.log 2>&1
```

### Restore from Backup

```bash
# Decompress and restore
gunzip < /var/backups/linkflow/linkflow_20240101_020000.sql.gz | \
    psql -U linkflow_user -h localhost linkflow_prod
```

---

## Scaling

See **[Scaling Guide](./deployment/09-scaling.md)** for detailed scaling strategies.

### Horizontal Scaling

**1. Multiple Web Servers:**
- Use load balancer (Nginx, HAProxy, AWS ALB)
- Shared session storage (Redis)
- Shared file storage (S3, NFS)

**2. Dedicated Queue Workers:**
```yaml
# docker-compose.scale.yml
services:
  worker:
    image: linkflow:latest
    command: php artisan horizon
    deploy:
      replicas: 3
```

**3. Database Read Replicas:**
```php
// config/database.php
'pgsql' => [
    'read' => [
        'host' => ['replica1.example.com', 'replica2.example.com'],
    ],
    'write' => [
        'host' => ['primary.example.com'],
    ],
],
```

### Vertical Scaling

**Upgrade server resources:**
- 8GB → 16GB RAM
- 4 vCPU → 8 vCPU
- Tune PostgreSQL config for new resources

---

## Troubleshooting

### Application Not Accessible

```bash
# Check Nginx
sudo systemctl status nginx
sudo nginx -t

# Check PHP-FPM
sudo systemctl status php8.3-fpm

# Check logs
sudo tail -f /var/log/nginx/error.log
tail -f storage/logs/laravel.log
```

### Queue Jobs Not Processing

```bash
# Check Horizon
sudo supervisorctl status horizon
php artisan horizon:status

# Check Redis
redis-cli ping

# Restart Horizon
sudo supervisorctl restart horizon
```

### Database Connection Issues

```bash
# Check PostgreSQL
sudo systemctl status postgresql

# Test connection
psql -U linkflow_user -h localhost linkflow_prod

# Check credentials in .env
cat .env | grep DB_
```

### High Memory Usage

```bash
# Check processes
top
htop

# Check Horizon memory
php artisan horizon:status

# Restart Horizon to free memory
php artisan horizon:terminate
```

### Slow Performance

```bash
# Check opcache
php -i | grep opcache

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Check database queries
# Enable query log in .env
DB_LOG_QUERIES=true
```

---

## Additional Resources

**Detailed Deployment Guides:**
- [Architecture Overview](./deployment/01-architecture.md)
- [Docker Configuration](./deployment/02-docker-configuration.md)
- [Hetzner Deployment](./deployment/03-hetzner-deployment.md)
- [Azure Deployment](./deployment/04-azure-deployment.md)
- [AWS Deployment](./deployment/05-aws-deployment.md)
- [Security Hardening](./deployment/06-security-hardening.md)
- [Backup Strategy](./deployment/07-backup-strategy.md)
- [Monitoring Setup](./deployment/08-monitoring.md)
- [Scaling Guide](./deployment/09-scaling.md)
- [CI/CD Pipeline](./deployment/10-cicd.md)

**Other Guides:**
- [Security Guide](../guides/security.md)
- [Troubleshooting Guide](../guides/troubleshooting.md)
- [Developer Handbook](../core/04-developer-handbook.md)

---

**Production deployment requires careful planning and testing. Always test deployments in a staging environment first!** 🚀

*Last Updated: December 2024*
