FROM dunglas/frankenphp:1-php8.2-bookworm

RUN install-php-extensions pdo_pgsql opcache

WORKDIR /app

COPY composer.json composer.lock* /app/
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs

COPY . /app

RUN ln -sf /usr/local/bin/frankenphp /app/frankenphp

ENV PORT=80
EXPOSE 80

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
