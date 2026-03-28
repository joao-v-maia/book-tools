#!/bin/sh
set -e

# Install/update composer dependencies when the source is mounted as a volume
if [ -f composer.json ]; then
    composer install --prefer-dist --no-interaction --no-progress
fi

exec docker-php-entrypoint "$@"
