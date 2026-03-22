FROM dunglas/frankenphp:latest AS base

RUN install-php-extensions \
    pdo_mysql \
    redis \
    pcntl \
    intl \
    zip \
    bcmath \
    opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# -----------------------------------------------------------
# Development stage
# -----------------------------------------------------------
FROM base AS development

RUN install-php-extensions xdebug

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

COPY . .

RUN composer install --no-interaction --no-progress

EXPOSE 8000 443 443/udp 5173

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]

# -----------------------------------------------------------
# Production stage
# -----------------------------------------------------------
FROM base AS production

ENV APP_ENV=production
ENV APP_DEBUG=false

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN npm run build \
    && rm -rf node_modules

RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 8000 443 443/udp

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]
