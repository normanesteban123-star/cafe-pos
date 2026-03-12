FROM dunglas/frankenphp:php8.4.18-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

ENV PORT=8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT}"]
