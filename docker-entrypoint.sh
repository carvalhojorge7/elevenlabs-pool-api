#!/bin/sh
set -e

# Criar banco de dados SQLite caso não exista
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Garantir permissões de escrita
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Rodar migrations do Laravel
php artisan migrate --force --no-interaction

exec "$@"
