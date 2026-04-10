#!/bin/sh
set -e

cd /var/www/html

# Install dependencies if vendor doesn't exist
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# Cache config/routes/views
if [ -f "artisan" ]; then
    echo "Running migrations..."
    php artisan migrate --force 2>/dev/null || true

    echo "Clearing and rebuilding caches..."
    php artisan config:cache 2>/dev/null || true
    php artisan route:cache 2>/dev/null || true
    php artisan view:cache 2>/dev/null || true
fi

exec "$@"
