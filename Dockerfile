FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /app
WORKDIR /app

RUN mkdir -p /app/uploads/photos /app/uploads/documents /app/uploads/maps \
    && chmod -R 755 /app/uploads

CMD php -S 0.0.0.0:${PORT:-8080} -t /app
