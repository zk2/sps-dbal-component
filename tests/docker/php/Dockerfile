FROM php:8.0-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        libmcrypt-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pdo_mysql \
        intl

ENV COMPOSER_ALLOW_SUPERUSER 1
RUN curl -sS https://getcomposer.org/installer | php -- \
      --install-dir=/usr/local/bin \
      --filename=composer

COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
