# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mbstring xml pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Create default .env file
RUN echo "APP_NAME=LibraTrack\n\
APP_ENV=production\n\
APP_DEBUG=false\n\
APP_KEY=\n\
APP_URL=http://localhost\n\
DB_CONNECTION=mysql\n\
DB_HOST=localhost\n\
DB_PORT=3306\n\
DB_DATABASE=libratrack\n\
DB_USERNAME=root\n\
DB_PASSWORD=\n" > .env

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Create storage directories
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views && \
    chown -R www-data:www-data storage bootstrap/cache

# Generate app key
RUN php artisan key:generate --force

# Create storage symlink
RUN php artisan storage:link || true

# Configure Apache to use Laravel's public folder
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Expose port
EXPOSE 8080

# Start Apache
CMD ["apache2-foreground"]
