FROM php:8.3-cli-alpine

ARG timezone=America/Sao_Paulo
ARG user=hyperf
ARG uid=1000

ENV TIMEZONE=${timezone} \
    APP_ENV=prod \
    SCAN_CACHEABLE=true

RUN apk add --no-cache \
    git \
    curl \
    unzip \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    autoconf \
    g++ \
    make \
    brotli-dev \
    libtool \
    re2c \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-install \
        bcmath \
        mbstring \
        zip \
        sockets \
        opcache \
        pcntl

RUN pecl install redis \
    && echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini \
    && pecl install swoole \
    && echo "extension=swoole.so" > /usr/local/etc/php/conf.d/swoole.ini

RUN ln -sf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
    && echo "${TIMEZONE}" > /etc/timezone

RUN { \
        echo "upload_max_filesize=128M"; \
        echo "post_max_size=128M"; \
        echo "memory_limit=1G"; \
        echo "date.timezone=${TIMEZONE}"; \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=8"; \
        echo "opcache.max_accelerated_files=4000"; \
        echo "opcache.revalidate_freq=2"; \
        echo "opcache.fast_shutdown=1"; \ 
    } | tee /usr/local/etc/php/conf.d/99-custom.ini

RUN addgroup -g ${uid} ${user} \
    && adduser -D -s /bin/sh -u ${uid} -G ${user} ${user}

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /opt/www
COPY . /opt/www

RUN chown -R ${user}:${user} /opt/www

RUN composer install --no-dev --optimize-autoloader --no-scripts
USER ${user}

EXPOSE 9501

CMD ["php", "/opt/www/bin/hyperf.php", "start"]
