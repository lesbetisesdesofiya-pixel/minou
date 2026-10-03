# ─── Étape 1 : build des assets Vite (menu/checkout/track : Tailwind + JS) ───
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-apache

# Extensions PHP requises par Laravel 12 + MySQL
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache : rewrite (routes Laravel) + DocumentRoot sur /public + nom du sous-domaine
RUN a2enmod rewrite
COPY .docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Dépendances PHP (sans les paquets dev)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Code applicatif (le build React public/app/ est déjà commité)
COPY . .
# Assets Vite construits à l'étape Node (public/build ignoré par git)
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
