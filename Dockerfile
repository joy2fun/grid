# Use the serversideup/php image with FrankenPHP
FROM serversideup/php:8.3-frankenphp as base

# Set working directory
WORKDIR /var/www/html

# Switch to root to install dependencies and set permissions
USER root

# Install PHP extensions
RUN install-php-extensions intl

# Install system dependencies if any are needed beyond the base image
# RUN apt-get update && apt-get install -y ...

# Copy application files
COPY --chown=1000:1000 . .

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Switch back to the unprivileged user
USER www-data
