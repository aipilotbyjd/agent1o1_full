FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Install Node dependencies and build assets
RUN npm install
RUN npm run build

# Set permissions for storage
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Generate application key
RUN php artisan key:generate

# Create storage link
RUN php artisan storage:link

# Copy environment file
RUN cp .env.example .env

# Run migrations (will fail if database not available, but included for when it is)
# Note: In production, database connection must be set as environment variables
# RUN php artisan migrate --force

# Configure Apache document root
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
