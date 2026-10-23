# PHP with Composer: the harness installs this package and every omnimeet/*
# gateway at start.
FROM php:8.4-cli-alpine
RUN apk add --no-cache git unzip libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" zip \
    && apk del $PHPIZE_DEPS
# Session upload progress off for the demo's web server (php -S): php.net's
# mitigation for CVE-2026-109619.
RUN echo 'session.upload_progress.enabled = 0' > /usr/local/etc/php/conf.d/zz-harness.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_CACHE_DIR=/harness/.cache
WORKDIR /harness
ENTRYPOINT ["/omnimeet/core/docker/entrypoint.sh"]
CMD ["gateways"]
