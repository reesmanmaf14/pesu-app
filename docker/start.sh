#!/bin/sh
# Container start-up (Render runs this on every deploy and every wake-up from sleep).
set -e

# Render tells the app which port to listen on.
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views

# Cache config, routes and views using this environment's variables.
php artisan optimize

# Bring the database schema up to date (does nothing when it already is).
php artisan migrate --force

# Built-in words, only into an empty database. Never DatabaseSeeder (it creates a test user).
php artisan pesu:vocabulary --if-empty

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
