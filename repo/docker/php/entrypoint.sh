#!/bin/sh
set -e

# Install composer dependencies if vendor is missing
if [ -f "composer.json" ] && [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --no-progress
fi

exec "$@"
