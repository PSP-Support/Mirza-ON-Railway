FROM php:8.4-apache

ENV APACHE_DOCUMENT_ROOT=/app \
    COMPOSER_ALLOW_SUPERUSER=1 \
    TZ=Asia/Tehran \
    RAILWAY_PROXY_MODE=true

RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip curl libcurl4-openssl-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
      libfreetype6-dev libicu-dev libxml2-dev libssh2-1-dev libonig-dev cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli curl mbstring zip gd intl bcmath soap opcache \
    && pecl install ssh2-1.4.1 \
    && docker-php-ext-enable ssh2 \
    && a2enmod rewrite headers expires \
    && for module in mpm_event mpm_worker mpm_prefork; do a2dismod "$module" >/dev/null 2>&1 || true; done \
    && rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork \
    && test "$(find /etc/apache2/mods-enabled -maxdepth 1 -name 'mpm_*.load' | wc -l)" -eq 1 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . /app
RUN sed -ri 's!/var/www/html!/app!g' /etc/apache2/sites-available/000-default.conf \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p /app/storage/cache \
    && chown -R www-data:www-data /app/storage \
    && chmod -R 775 /app/storage

COPY railway/start.sh /usr/local/bin/mirza-start
RUN chmod +x /usr/local/bin/mirza-start \
    && printf 'ServerName localhost\n<Directory /app>\n    Options FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

EXPOSE 8080
CMD ["/usr/local/bin/mirza-start"]
