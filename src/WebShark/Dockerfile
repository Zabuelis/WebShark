FROM php:8.4-fpm-alpine

# Install system dependencies & Python
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    python3 \
    python3-pip \
    python3-venv \
    autoconf \
    gcc \
    make \
    g++

# Clean cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Install Supercronic
ARG SUPERCRONIC_VERSION=v0.2.29
RUN curl -fsSLO "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-amd64" \
    && chmod +x supercronic-linux-amd64 \
    && mv supercronic-linux-amd64 /usr/local/bin/supercronic

# Create a crontab file for Laravel
RUN echo "* * * * * php /var/www/artisan schedule:run" > /etc/crontab

# Install and enable Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files first
COPY ./composer.json ./composer.lock ./

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Install Python dependencies
COPY ./scapy/requirements.txt ./scapy_reqs.txt
RUN pip3 install --no-cache-dir -r ./scapy_reqs.txt --break-system-packages

# Copy the rest of the application
COPY . .

# Increase PHP memory limit
RUN echo "memory_limit=1G" > /usr/local/etc/php/conf.d/memory-limit.ini

# Increase upload limits
RUN echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/uploads.ini

# Increase script execution time
RUN echo "max_execution_time=600" > /usr/local/etc/php/conf.d/execution.ini \
    && echo "max_input_time=600" >> /usr/local/etc/php/conf.d/execution.ini
