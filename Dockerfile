FROM node:20-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY tailwind.config.js postcss.config.js ./
COPY public/ ./public/
COPY templates/ ./templates/
COPY src/ ./src/
COPY admin/ ./admin/
COPY *.php ./

RUN npm run build:css

FROM php:8.2-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        unzip \
        rsync \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-ita \
        qpdf \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_mysql \
        mbstring \
        zip \
        opcache \
    && a2enmod rewrite

RUN { \
        echo 'SetEnvIf X-Forwarded-Proto "https" HTTPS=on'; \
    } > /etc/apache2/conf-available/forwarded-proto.conf \
    && a2enconf forwarded-proto

RUN sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN { \
        echo 'file_uploads = On'; \
        echo 'upload_max_filesize = 50M'; \
        echo 'post_max_size = 128M'; \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 300'; \
    } > /usr/local/etc/php/conf.d/portaledipendenti.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html-src

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY . /var/www/html-src/
COPY --from=assets /app/public/assets/css/output.css /var/www/html-src/public/assets/css/output.css

RUN rm -rf /var/www/html-src/node_modules \
    && chown -R www-data:www-data /var/www/html-src

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
