#!/bin/sh
set -e

if [ -f "composer.json" ]; then
    if [ ! -d "vendor" ]; then
        echo "Installing Composer dependencies..."
        composer install --no-interaction --no-progress --optimize-autoloader
    elif ! php -r "require 'vendor/autoload.php'; new Illuminate\Foundation\Application;" 2>/dev/null; then
        echo "Regenerating autoloader..."
        composer dump-autoload --optimize --quiet
    fi
fi

exec "$@"
