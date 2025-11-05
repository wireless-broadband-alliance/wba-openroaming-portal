# =========================
# PHP / Composer build stage
# =========================
FROM php:8.4-fpm-bullseye AS vendor
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

# Install minimal build deps for Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    git zip unzip curl gnupg tzdata wget \
 && rm -rf /var/lib/apt/lists/*


# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer \
 && composer self-update --2

# Copy Symfony app
COPY . .

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor tzdata xmlsec1 libxmlsec1-openssl \
    libpng-dev libjpeg-dev libfreetype6-dev libsqlite3-dev libicu-dev libzip-dev \
    libonig-dev libxml2-dev libgpgme-dev libgpg-error-dev libmemcached-dev \
    libldap2-dev build-essential pkg-config autoconf curl gnupg bash \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install intl zip bcmath mbstring pdo pdo_mysql pdo_sqlite soap gd dom exif opcache ldap \
 && pecl channel-update pecl.php.net \
 && pecl install gnupg-1.5.0 memcached-3.2.0 \
 && docker-php-ext-enable gnupg memcached \
 && rm -rf /var/lib/apt/lists/*

# Install PHP dependencies
COPY ./.env.sample /app/.env
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \
 && composer install --optimize-autoloader --no-interaction

# Warm Symfony cache
RUN php bin/console cache:warmup --env=prod

# =========================
# Frontend build stage
# =========================
FROM node:22-bullseye-slim AS frontend
WORKDIR /app

# Install Node dependencies & build assets
COPY package*.json yarn.lock* ./
COPY --from=vendor /app/vendor ./vendor
RUN npm ci --no-audit --progress=false
#RUN npm install --force

COPY . .
RUN npm run build

# =========================
# Final runtime image
# =========================
FROM php:8.4-fpm-bullseye AS runtime
ENV TZ=UTC
WORKDIR /var/www/openroaming

# Install runtime deps + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor tzdata xmlsec1 libxmlsec1-openssl \
    libpng-dev libjpeg-dev libfreetype6-dev libsqlite3-dev libicu-dev libzip-dev \
    libonig-dev libxml2-dev libgpgme-dev libgpg-error-dev libmemcached-dev \
    libldap2-dev build-essential pkg-config autoconf curl gnupg bash \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install intl zip bcmath mbstring pdo pdo_mysql pdo_sqlite soap gd dom exif opcache ldap \
 && pecl channel-update pecl.php.net \
 && pecl install gnupg-1.5.0 memcached-3.2.0 \
 && docker-php-ext-enable gnupg memcached \
 && rm -rf /var/lib/apt/lists/*

# Copy Symfony app from vendor stage
COPY --from=vendor /app /var/www/openroaming

# Copy built frontend assets
COPY --from=frontend /app/public/build /var/www/openroaming/public/build

# Copy configs
COPY service-config/supervisor/supervisord.conf /etc/supervisor/conf.d/
COPY service-config/nginx/nginx.conf /etc/nginx/nginx.conf
COPY service-config/nginx/mime.types /etc/nginx/mime.types
COPY service-config/nginx/fastcgi_params /etc/nginx/fastcgi_params
COPY service-config/nginx/sites /etc/nginx/conf.d/

# Prepare runtime environment
RUN mkdir -p /run/nginx /run/php /var/log/supervisor /var/www/openroaming/var \
 && chown -R www-data:www-data /var/www/openroaming

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
