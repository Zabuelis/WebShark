#!/usr/bin/env bash

apt install nginx -y
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"
apt install php-fpm npm php8.4.xml php8.4-sqlite3 -y
cp -r ~/webshark/src/WebShark /var/www/WebShark

cp ~/webshark/src/scripts/nginx_conf/WenShark /ect/nxing/sites-available/
ln -s /etc/nginx/sites-available/WebShark /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default

cd /var/www/WebShark
composer install
php artisan key:generate
npm install
npm run build

chown -r www-data:www-data /var/www/WebShark
chmod -r 755 /var/www/WebShark

systemctl restart php8.4-fpm
systemctl reload nginx