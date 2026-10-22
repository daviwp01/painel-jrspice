# Versão travada por causa das falhas críticas de RCE de 21/10/2026
# (CVE-2026-109618 / CVE-2026-109619), corrigidas a partir do 8.4.27.
# Atualizar para o próximo patch assim que sair.
FROM php:8.4.27-fpm-alpine

# Instalar dependências do sistema
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
  libpng-dev \
  linux-headers

# Instalar extensões do PHP (Incluindo OPCACHE para performance)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
  gd \
  pdo \
  pdo_mysql \
  mbstring \
  zip \
  exif \
  pcntl \
  bcmath \
  intl \
  opcache

# Instalar Composer v2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar arquivos de configuração (Garanta que o local.ini tenha as linhas do opcache)
COPY local.ini /usr/local/etc/php/conf.d/local.ini
COPY zz-docker.conf /usr/local/etc/php/zz-docker.conf

# Configurar usuário para evitar erro de permissão (UID 1000 que você já usa)
RUN addgroup -g 1000 www && adduser -D -u 1000 -G www www

# Definir diretório de trabalho
WORKDIR /var/www/html

# Ajustar permissões iniciais
RUN chown -R www:www /var/www/html || true

# Alternar para o usuário www para segurança
USER www

EXPOSE 9000
CMD ["php-fpm"]