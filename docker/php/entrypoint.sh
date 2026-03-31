#!/bin/sh
set -e

# Install/update composer dependencies when the source is mounted as a volume
if [ -f composer.json ]; then
    composer install --prefer-dist --no-interaction --no-progress
fi

# Ensure writable runtime directories exist with correct ownership
mkdir -p var/cache var/log var/storage/temporary
chown -R www-data:www-data var

exec docker-php-entrypoint "$@"
