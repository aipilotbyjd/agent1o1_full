# Docker Configuration Files

All files below should be created in your project root exactly as shown.
These are production-ready configurations — not development placeholders.

---

## 1. `Dockerfile`

Multi-stage build. Stage 1 compiles frontend assets. Stage 2 builds the PHP app.

```dockerfile
FROM node:20-alpine AS frontend-builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources/ ./resources/
COPY vite.config.js ./
COPY public/ ./public/

RUN npm run build


FROM php:8.2-fpm-alpine AS production

RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-client \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        mbstring \
        intl \
        pcntl \
        opcache

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .

COPY --from=frontend-builder /app/public/build ./public/build

RUN composer run-script post-autoload-dump --no-interaction || true

RUN mkdir -p storage/framework/{cache,sessions,views} \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

---

## 2. `docker-compose.prod.yml`

Full production stack with health checks and volume persistence.

```yaml
services:
    app:
        build:
            context: .
            dockerfile: Dockerfile
        restart: unless-stopped
        ports:
            - "80:80"
        environment:
            APP_ENV: production
            APP_DEBUG: false
            APP_KEY: ${APP_KEY}
            APP_URL: ${APP_URL}
            DB_CONNECTION: pgsql
            DB_HOST: pgsql
            DB_PORT: 5432
            DB_DATABASE: ${DB_DATABASE}
            DB_USERNAME: ${DB_USERNAME}
            DB_PASSWORD: ${DB_PASSWORD}
            REDIS_HOST: redis
            REDIS_PORT: 6379
            REDIS_PASSWORD: ${REDIS_PASSWORD}
            QUEUE_CONNECTION: redis
            CACHE_STORE: redis
            SESSION_DRIVER: database
        volumes:
            - app-storage:/var/www/html/storage/app
        depends_on:
            pgsql:
                condition: service_healthy
            redis:
                condition: service_healthy

    pgsql:
        image: postgres:17-alpine
        restart: unless-stopped
        environment:
            POSTGRES_DB: ${DB_DATABASE}
            POSTGRES_USER: ${DB_USERNAME}
            POSTGRES_PASSWORD: ${DB_PASSWORD}
        volumes:
            - pgsql-data:/var/lib/postgresql/data
        healthcheck:
            test: ['CMD-SHELL', 'pg_isready -U ${DB_USERNAME}']
            interval: 5s
            timeout: 5s
            retries: 10

    redis:
        image: redis:7-alpine
        restart: unless-stopped
        command: redis-server --requirepass ${REDIS_PASSWORD}
        volumes:
            - redis-data:/data
        healthcheck:
            test: ['CMD', 'redis-cli', '-a', '${REDIS_PASSWORD}', 'ping']
            interval: 5s
            timeout: 5s
            retries: 10

volumes:
    pgsql-data:
    redis-data:
    app-storage:
```

---

## 3. `docker/nginx.conf`

Optimized Nginx config for Laravel with security headers.

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 64M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    charset utf-8;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Serve Vite-built assets with long cache
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Block hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 4. `docker/php.ini`

PHP settings tuned for production and workflow processing.

```ini
; OPcache — speeds up PHP significantly in production
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1

; File uploads
upload_max_filesize=64M
post_max_size=64M

; Execution limits — workflows can be long-running
max_execution_time=300
max_input_time=300
memory_limit=512M

; Error reporting — errors to log, never to browser
display_errors=Off
log_errors=On
error_log=/var/www/html/storage/logs/php-error.log
```

---

## 5. `docker/supervisord.conf`

Manages three processes inside the app container.

```ini
[supervisord]
nodaemon=true
logfile=/var/log/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
priority=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
priority=2
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:horizon]
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
stopwaitsecs=3600
priority=3
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

---

## 6. `docker/entrypoint.sh`

Runs startup tasks before serving traffic.

```bash
#!/bin/sh
set -e

echo "Waiting for database to be ready..."
until php artisan db:show --json > /dev/null 2>&1; do
    echo "Database not ready — retrying in 2s..."
    sleep 2
done
echo "Database is ready."

echo "Running migrations..."
php artisan migrate --force

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Linking storage..."
php artisan storage:link || true

echo "Starting services..."
exec "$@"
```

---

## 7. `.env.production.example`

Template — copy to `.env` and fill in values on the server.

```env
APP_NAME="Your SaaS Name"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERATE_WITH_php_artisan_key_generate
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

# Database
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=CHANGE_THIS_USE_A_LONG_RANDOM_STRING

# Redis
REDIS_PASSWORD=CHANGE_THIS_USE_ANOTHER_LONG_RANDOM_STRING

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="hello@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

# Backups
BACKUP_OFFSITE_ENABLED=false
BACKUP_OFFSITE_DEST=
BACKUP_KEEP_DAYS=7

# Optional: Stripe, AI keys, etc.
STRIPE_KEY=
STRIPE_SECRET=
OPENAI_API_KEY=
```

---

## 8. `deploy.sh`

One-command deployment script.

```bash
#!/bin/bash
set -e

echo "=== Deploying application ==="

if [ ! -f ".env" ]; then
    echo "ERROR: .env file not found."
    echo "Copy .env.production.example to .env and fill in your values first."
    exit 1
fi

echo "Pulling latest code..."
git pull origin main

echo "Building and starting containers..."
docker compose -f docker-compose.prod.yml up -d --build --remove-orphans

echo "Waiting for app to be healthy..."
sleep 8

echo "=== Deployment complete ==="
APP_URL=$(grep APP_URL .env | cut -d '=' -f2)
echo "Your app is live at: $APP_URL"
```

---

## 9. `backup.sh`

Daily backup script — local + optional offsite via rclone.

```bash
#!/bin/bash
set -e

BACKUP_DIR="/home/deploy/backups"
DATE=$(date +%Y-%m-%d_%H-%M-%S)
DB_CONTAINER=$(docker ps --filter "name=pgsql" --format "{{.Names}}" | head -1)
BACKUP_FILE="db_$DATE.sql.gz"

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$APP_DIR/.env"

OFFSITE_ENABLED=${BACKUP_OFFSITE_ENABLED:-false}
OFFSITE_DEST=${BACKUP_OFFSITE_DEST:-""}
KEEP_DAYS=${BACKUP_KEEP_DAYS:-7}

mkdir -p "$BACKUP_DIR"

echo "[$DATE] Starting backup..."
docker exec "$DB_CONTAINER" pg_dump -U "$DB_USERNAME" "$DB_DATABASE" \
    | gzip > "$BACKUP_DIR/$BACKUP_FILE"

SIZE=$(du -sh "$BACKUP_DIR/$BACKUP_FILE" | cut -f1)
echo "[$DATE] Backup saved: $BACKUP_FILE ($SIZE)"

if [ "$OFFSITE_ENABLED" = "true" ] && [ -n "$OFFSITE_DEST" ]; then
    if ! command -v rclone &> /dev/null; then
        curl -fsSL https://rclone.org/install.sh | bash
    fi
    echo "[$DATE] Uploading to offsite: $OFFSITE_DEST"
    rclone copy "$BACKUP_DIR/$BACKUP_FILE" "$OFFSITE_DEST"
    rclone delete --min-age "${KEEP_DAYS}d" "$OFFSITE_DEST"
    echo "[$DATE] Offsite upload complete."
fi

find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime "+$KEEP_DAYS" -delete
echo "[$DATE] Done. Local backups:"
ls -lh "$BACKUP_DIR"
```

---

## 10. `.dockerignore`

Keeps the Docker image lean by excluding unnecessary files.

```
.git
.gitignore
node_modules
vendor
.env
.env.*
!.env.production.example
storage/app/*
storage/logs/*
bootstrap/cache/*
public/build
tests/
docker-compose.yml
docs/
.agents/
.local/
*.md
```
