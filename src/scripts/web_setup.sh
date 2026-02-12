#!/usr/bin/env bash
set -e
cont=""

read -p "Do you want to install dependencies for laravel? (y/n)" cont
if [ "$cont" == "y" ] ; then
        apt install nginx -y
        /bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"
        apt install php-fpm npm php8.4-xml php8.4-sqlite3 php-mbstring -y
fi

read -p "Do you want to setup nginx configuration for laravel? (y/n)" cont
if [ "$cont" == "y" ] ; then
        cp ./nginx_conf/WebShark /etc/nginx/sites-available/
        ln -s /etc/nginx/sites-available/WebShark /etc/nginx/sites-enabled/
        rm /etc/nginx/sites-enabled/default
fi

echo "Moving project files to www location and installing project dependencies"
cp -r ../WebShark /var/www/WebShark
cd /var/www/WebShark
cp ../WebShark/.env.example /var/www/WebShark/.env
~/.config/herd-lite/bin/composer install
php artisan key:generate
npm install
npm run build

touch /var/www/WebShark/database/database.sqlite
chown -R www-data:www-data /var/www/WebShark
chmod -R 755 /var/www/WebShark

systemctl restart php8.4-fpm
systemctl reload nginx
