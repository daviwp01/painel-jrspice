FROM php:8.4-fpm-alpine

RUN apk update && apk add --no-cache \
  bash \
  git \
  openssh-client \
  libzip-dev \
  oniguruma-dev \
  icu-dev \
  zlib-dev \
  curl \
  nodejs \
  npm \
  make \
  g++ \
  autoconf \
  build-base \
  freetype-dev \
  libjpeg-turbo-dev \
  libpng-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd \
  && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath intl || true

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY local.ini /usr/local/etc/php/conf.d/local.ini
COPY zz-docker.conf /usr/local/etc/php/zz-docker.conf

RUN addgroup -g 1000 www && adduser -D -u 1000 -G www www
WORKDIR /var/www/html

RUN chown -R www:www /var/www/html || true

EXPOSE 9000
CMD ["php-fpm"]
