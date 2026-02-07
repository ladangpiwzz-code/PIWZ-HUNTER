# ============================================
# PIWZ HUNTER - DARK CONTROL PANEL
# AGENZ EDITION - ENHANCED SECURITY & FEATURES
# ============================================

# Multi-stage build for security
FROM node:18-alpine AS node_builder
WORKDIR /build
# Install dependencies for potential frontend build
RUN npm install -g uglify-js clean-css-cli

FROM php:8.2-apache AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    wget \
    nano \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    sockets \
    && docker-php-ext-enable opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies if composer.json exists
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Remove sensitive files
RUN rm -f .env .env.example docker-compose.yml \
    && find . -name "*.md" -type f -delete \
    && find . -name "*.txt" ! -name "logs.txt" ! -name "README.md" -type f -delete

# ============================================
# PRODUCTION STAGE
# ============================================
FROM php:8.2-apache

LABEL maintainer="PIWZ HUNTER Team"
LABEL version="2.0"
LABEL description="Dark Cyber Control Panel"

# Install production dependencies only
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    gd \
    zip \
    sockets \
    && a2enmod rewrite headers

# Security: Create non-root user
RUN useradd -m -u 1000 -s /bin/bash hunter \
    && usermod -a -G www-data hunter

# Copy built application from builder stage
COPY --from=builder --chown=hunter:www-data /var/www/html /var/www/html

# Copy optimized PHP configuration
COPY --from=builder /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Custom PHP settings for security
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/performance.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/performance.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/performance.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini

# Apache security configuration
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf \
    && echo "ServerSignature Off" >> /etc/apache2/conf-available/security.conf \
    && echo "TraceEnable Off" >> /etc/apache2/conf-available/security.conf

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/uploads \
    /var/www/html/backups \
    /var/www/html/sessions \
    && chown -R hunter:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 777 /var/www/html/logs.txt \
    && chmod 777 /var/www/html/devices.json \
    && chmod 777 /var/www/html/uploads \
    && chmod 777 /var/www/html/sessions \
    && chmod 600 /var/www/html/api.php

# Switch to non-root user
USER hunter

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Expose port (Koyeb uses PORT environment variable)
EXPOSE 8080

# Start command (Koyeb compatible)
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
