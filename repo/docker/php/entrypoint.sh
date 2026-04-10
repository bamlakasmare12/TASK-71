#!/bin/sh
set -e

if [ -f "composer.json" ]; then
    if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
        # Lock via shared bind mount so only one container installs
        LOCK="/var/www/html/.composer_installing"
        if mkdir "$LOCK" 2>/dev/null; then
            echo "Installing Composer dependencies..."
            composer install --no-interaction --no-progress --optimize-autoloader
            rmdir "$LOCK" 2>/dev/null || true
        else
            echo "Waiting for another container to finish installing..."
            WAIT=0
            while [ ! -f "vendor/autoload.php" ] && [ $WAIT -lt 300 ]; do
                sleep 3
                WAIT=$((WAIT + 3))
            done
            echo "Dependencies ready."
        fi
    elif ! php -r "require 'vendor/autoload.php'; new Illuminate\Foundation\Application;" 2>/dev/null; then
        echo "Regenerating autoloader..."
        composer dump-autoload --optimize --quiet
    fi
fi

exec "$@"
