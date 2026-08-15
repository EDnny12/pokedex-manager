#!/bin/sh
set -e

# Support Render dynamic PORT
if [ -n "$PORT" ]; then
    sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
fi

# Ensure all storage subdirectories exist
mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/app/public/profile-photos \
         /var/www/html/storage/app/private/assistant \
         /var/www/html/bootstrap/cache

# Ensure storage link exists
php artisan storage:link --force || true

# Run migrations and cache in production if database is ready
if [ "$APP_ENV" = "production" ]; then
    php artisan migrate --force || echo "Migrate pending or database connecting..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Ensure www-data ownership and write permissions across all storage files
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
