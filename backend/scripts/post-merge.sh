#!/bin/bash
set -e

# Install / update Composer dependencies.
# --ignore-platform-reqs: some packages declare PHP ^8.3 but run fine on 8.2;
# this environment has 8.2 so we bypass the version check.
composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# Run any pending migrations
php artisan migrate --force

# Clear compiled caches so the running app picks up new code immediately
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
