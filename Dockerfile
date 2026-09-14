# Build frontend assets (Tailwind/Vite) separately so the PHP layer doesn't need Node.
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# Runtime image: PHP built-in server, good enough for a single-container personal project.
FROM php:8.2-cli-alpine

RUN apk add --no-cache \
        libzip \
        icu-libs \
        icu-data-full \
    && apk add --no-cache --virtual .build-deps \
        oniguruma-dev \
        libzip-dev \
        icu-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        bcmath \
        zip \
        intl \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
