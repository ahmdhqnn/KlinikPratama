#!/bin/sh
set -e

php artisan optimize:clear
php artisan migrate --force
php artisan storage:link --quiet || true
php artisan optimize

exec php-fpm
