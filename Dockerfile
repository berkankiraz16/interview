FROM php:8.1-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install \
        curl \
        dom \
        simplexml \
        xml \
        xmlwriter \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress

COPY index.php ./
COPY src ./src
COPY tests ./tests
COPY phpstan.neon ./
COPY .php-cs-fixer.dist.php ./

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "index.php"]