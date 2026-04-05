# ============================================
# STAGE 1: Build dos assets (Node.js)
# ============================================
FROM node:22-alpine AS node-builder

WORKDIR /app

# Copiar apenas package files primeiro (cache de camadas)
COPY package.json package-lock.json ./
RUN npm ci --production=false

# Copiar codigo e compilar
COPY . .
RUN npm run build

# ============================================
# STAGE 2: Aplicacao PHP
# ============================================
FROM php:8.5-fpm-alpine

# Instalar extensoes PHP necessarias
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-dev \
        libzip-dev \
        icu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        opcache \
        pcntl

# Configurar OPcache para producao
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM static pool para performance
RUN sed -i 's/pm = dynamic/pm = static/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.max_children = 5/pm.max_children = 20/' /usr/local/etc/php-fpm.d/www.conf

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalar dependencias PHP (sem dev)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# Copiar codigo da aplicacao
COPY . .

# Copiar assets compilados do stage 1
COPY --from=node-builder /app/public/build public/build

# Otimizacoes Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Configurar Nginx e Supervisor
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Permissoes
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
