FROM phpdockerio/php:7.4-fpm
COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV DEBIAN_FRONTEND noninteractive

VOLUME ["/app"]
WORKDIR /app

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
        unzip bash-completion \
        git \
        php7.4 \
        php7.4-cli \
        php7.4-curl \
        php7.4-json \
        php7.4-zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN composer self-update
