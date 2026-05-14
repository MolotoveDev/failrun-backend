# syntax=docker/dockerfile:1.7
#
# Backend Symfony - PHP 8.3 + Apache
# Imagen lista para ECS Fargate. Las variables sensibles (APP_SECRET,
# DATABASE_URL, etc.) se inyectan en runtime desde AWS Secrets Manager.
#

# ---------- Stage 1: composer (solo para traer el binario) ----------
FROM composer:2 AS composer

# ---------- Stage 2: runtime ----------
FROM php:8.3-apache

# 1) Dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

# 2) Apache: módulo rewrite + vhost apuntando a /public
RUN a2enmod rewrite headers
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# 3) php.ini en modo producción (opcache activo, etc.)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 4) Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 5) Cacheamos vendor copiando solo composer.* primero
COPY composer.json composer.lock symfony.lock* ./
ENV APP_ENV=prod APP_DEBUG=0
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

# 6) Resto de la app
COPY . .

# 7) Autoload optimizado + scripts post-install
RUN composer dump-autoload --classmap-authoritative --no-dev

# 8) Permisos de var/ (cache + logs en runtime)
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

# 9) Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
