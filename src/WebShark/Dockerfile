FROM php:8.4-fpm

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

# Install and enable Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Install Python dependencies
COPY ./scapy/requirements.txt ./scapy_reqs.txt
RUN pip3 install --no-cache-dir -r ./scapy_reqs.txt --break-system-packages

# Increase upload limits (10mb)
RUN echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/uploads.ini