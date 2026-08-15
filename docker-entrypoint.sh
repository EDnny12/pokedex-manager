#!/bin/sh
set -e

# Support Render dynamic PORT
if [ -n "$PORT" ]; then
    sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
fi

# Ensure storage link exists
php artisan storage:link --force || true

# Run migrations and cache in production if database is ready
if [ "$APP_ENV" = "production" ]; then
    php artisan migrate --force || echo "Migrate pending or database connecting..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

exec "$@"
