# =============================================================================
# pdf2htmlEX builder — compiles from source on Alpine
# Kept as a separate stage so build tools never end up in the PHP image.
# This stage is slow on the first build but fully cached afterwards.
# =============================================================================
FROM alpine:3.21 AS pdf2htmlex-builder

RUN apk add --no-cache bash git sudo openjpeg-dev glib-dev \
    && printf '#!/bin/sh\nexit 0\n' > /usr/local/bin/msgfmt \
    && chmod +x /usr/local/bin/msgfmt

# GCC looks up CPATH automatically, even when invoked via cmake/make.
# Needed because pdf2htmlEX's cmake doesn't propagate glib's include path
# to ffw.c which pulls in fontforge headers that include <gio/gio.h>.
ENV CPATH=/usr/include/glib-2.0:/usr/lib/glib-2.0/include

RUN git clone --depth 1 https://github.com/pdf2htmlEX/pdf2htmlEX.git /pdf2htmlEX

WORKDIR /pdf2htmlEX

RUN sed -i '/define nullptr/d' \
        pdf2htmlEX/src/ArgParser.h \
        pdf2htmlEX/src/util/const.h \
    && ./buildScripts/buildInstallLocallyAlpine

# =============================================================================
# PHP base — shared between dev and prod
# =============================================================================
FROM php:8.3-fpm-alpine AS php-base

RUN apk add --no-cache \
        acl \
        bash \
        cairo \
        fcgi \
        file \
        fontconfig \
        gettext \
        git \
        glib \
        libjpeg-turbo \
        libpng \
        libstdc++ \
        libxml2 \
        openjpeg \
        qpdf \
        ttf-freefont \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        libzip-dev \
        zlib-dev \
    && docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
        pdo_pgsql \
        zip \
    && runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
        | tr ',' '\n' \
        | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )" \
    && apk add --no-cache --virtual .phpexts-rundeps $runDeps \
    && apk del .build-deps

# Copy pdf2htmlEX binary and data files from the builder stage
COPY --from=pdf2htmlex-builder /usr/local/bin/pdf2htmlEX /usr/local/bin/pdf2htmlEX
COPY --from=pdf2htmlex-builder /usr/local/share/pdf2htmlEX /usr/local/share/pdf2htmlEX

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini    $PHP_INI_DIR/conf.d/app.ini
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

WORKDIR /var/www/html

# =============================================================================
# Dev stage — adds Xdebug, source is mounted as a volume at runtime
# =============================================================================
FROM php-base AS php-dev

ENV APP_ENV=dev APP_DEBUG=1

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

COPY docker/php/xdebug.ini  $PHP_INI_DIR/conf.d/xdebug.ini
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# =============================================================================
# Prod stage — fully self-contained image
# =============================================================================
FROM php-base AS php-prod

ENV APP_ENV=prod APP_DEBUG=0

# Dummy DATABASE_URL so Symfony commands succeed at build time
ENV DATABASE_URL="postgresql://app:app@localhost:5432/app?serverVersion=16&charset=utf8"

COPY --chown=www-data:www-data . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && php bin/console importmap:install \
    && php bin/console assets:install public \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && chown -R www-data:www-data var

USER www-data
CMD ["php-fpm"]

# =============================================================================
# Nginx base
# =============================================================================
FROM nginx:1.27-alpine AS nginx-base

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# =============================================================================
# Nginx dev — static files come from a volume mount at runtime
# =============================================================================
FROM nginx-base AS nginx-dev

# =============================================================================
# Nginx prod — static assets baked in from the prod PHP stage
# =============================================================================
FROM nginx-base AS nginx-prod

COPY --from=php-prod /var/www/html/public /var/www/html/public
