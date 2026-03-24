FROM node:24-alpine

# Use Alpine Edge repositories to get the latest PHP 8.4
RUN apk add --no-cache --repository=http://dl-cdn.alpinelinux.org/alpine/edge/community \
    php84 \
    php84-phar \
    php84-mbstring \
    php84-openssl \
    php84-dom \
    php84-xml \
    php84-xmlwriter \
    php84-curl \
    php84-tokenizer \
    php84-session \
    php84-fileinfo

# Link the php84 binary to the standard 'php' command
RUN ln -sf /usr/bin/php84 /usr/bin/php

WORKDIR /var/www
