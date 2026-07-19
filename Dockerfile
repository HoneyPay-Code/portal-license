FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-dev wget $PHPIZE_DEPS \
    && docker-php-ext-install pdo_sqlite \
    && apk del $PHPIZE_DEPS

WORKDIR /app

COPY . /app

RUN mkdir -p /app/storage/rate /app/storage/releases \
    && chmod -R 770 /app/storage \
    && if [ ! -f /app/.env ]; then cp /app/.env.example /app/.env; fi

EXPOSE 8081

CMD ["php", "-S", "0.0.0.0:8081", "router.php"]
