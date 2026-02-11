#!/usr/bin/env bash
set -e

apt install nginx -y
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"
apt install php-fpm npm php8.4-xml php8.4-sqlite3 php-mbstring -y

cp ./nginx_conf/WebShark /etc/nginx/sites-available/
ln -s /etc/nginx/sites-available/WebShark /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default

cp -r ../WebShark /var/www/WebShark
cd /var/www/WebShark
cp ../WebShark/.env.example /var/www/WebShark/.env
~/.config/herd-lite/bin/composer install
php artisan key:generate
npm install
npm run build

chown -R www-data:www-data /var/www/WebShark
chmod -R 755 /var/www/WebShark

systemctl restart php8.4-fpm
systemctl reload nginx
