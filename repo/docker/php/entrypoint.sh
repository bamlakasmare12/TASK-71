#!/bin/sh
set -e

if [ -f "composer.json" ]; then
    # Install if vendor missing, or re-install if autoloader is broken
    if [ ! -f "vendor/autoload.php" ] || ! php -r "require 'vendor/autoload.php';" 2>/dev/null; then
        echo "Installing Composer dependencies..."
        rm -rf vendor
        composer install --no-interaction --no-progress --optimize-autoloader
    fi
fi

exec "$@"
