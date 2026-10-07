# Pesu on Render (free web service): PHP 8.3 + Apache, with the CSS/JS built by Node.
# The database (Postgres) and files (S3-compatible buckets) live outside the container, because
# the container's disk is reset on every deploy and restart. See README → "Deploying for free".

# ---- 1. Build the front-end (Vite + Tailwind) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --audit false --fund false
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# ---- 2. The PHP app ----
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev unzip \
 && docker-php-ext-install pdo_pgsql opcache \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini $PHP_INI_DIR/conf.d/pesu.ini
COPY docker/apache.conf /etc/apache2/conf-enabled/pesu.conf

# Serve public/ and let its .htaccess route requests to Laravel.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
 && rm -f public/hot
COPY --from=assets /app/public/build ./public/build

RUN sed -i 's/\r$//' docker/start.sh && chmod +x docker/start.sh \
 && chown -R www-data:www-data storage bootstrap/cache

CMD ["docker/start.sh"]
