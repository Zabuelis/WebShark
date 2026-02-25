#!/usr/bin/env bash
set -e
password=""

#apt update -y
#apt install postgresql -y

sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/" ../WebShark/.env
sed -i "s/^DB_PORT=.*/DB_PORT=5432/" ../WebShark/.env

read -p "Please insert a new password for the DB user postgres " password
sudo -u postgres psql -c "alter user postgres with password "\'$password\'";"
read -p "Please insert a new password for the DB user webshark " password
sudo -u postgres psql -c "create user webshark with password "\'$password\'";"
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=webshark/" ../WebShark/.env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD="$password"/" ../WebShark/.env
echo "Creating a new database for project user: websharkdb"
sudo -u postgres psql -c "create database websharkdb with owner = 'webshark';"
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=websharkdb/" ../WebShark/.env
