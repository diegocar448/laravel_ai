#!/bin/sh
set -e

# Regenerar caches com as variaveis de ambiente do container
php /var/www/html/artisan config:cache > /dev/null 2>&1
php /var/www/html/artisan route:cache > /dev/null 2>&1
php /var/www/html/artisan view:cache > /dev/null 2>&1
php /var/www/html/artisan event:cache > /dev/null 2>&1

# Iniciar Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
