FROM php:8.3-cli-alpine

# Instalar dependências de sistema e extensões necessárias
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libzip-dev \
    sqlite-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_sqlite opcache pcntl bcmath \
    && rm -rf /var/cache/apk/*

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurar diretório da aplicação
WORKDIR /var/www/html

# Copiar arquivos de dependências primeiro para aproveitar cache de camadas
COPY composer.json composer.lock ./

# Instalar dependências PHP
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copiar todo o código-fonte da aplicação
COPY . .

# Garantir permissões nos diretórios de storage e bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Gerar autoload otimizado final
RUN composer dump-autoload --optimize

# Script de entrada para migrações e inicialização
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
