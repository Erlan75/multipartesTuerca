# ==============================================================================
# DOCKERFILE PRODUCCIÓN — MULTIPARTES EL TUERCA (LARAVEL 12 + POSTGRESQL NEON)
# Optimizado para Render.com
# ==============================================================================

FROM php:8.3-apache

# Actualizar repositorios e instalar librerías del sistema necesarias para extensiones PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    git \
    curl \
    ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        bcmath \
        xml \
        ctype \
        curl \
        zip \
        gd \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer desde la imagen oficial
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Habilitar mod_rewrite en Apache para el enrutamiento de Laravel
RUN a2enmod rewrite headers

# Configurar VirtualHost apuntando a /var/www/html/public
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de dependencias para aprovechar la caché de capas de Docker
COPY composer.json composer.lock ./

# Instalar dependencias sin scripts de dev
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copiar el resto del código de la aplicación
COPY . .

# Copiar script de arranque y dar permisos
COPY docker/start.sh /usr/local/bin/start.sh
RUN sed -i 's/\r$//' /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Configurar permisos en storage y bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Puerto de escucha predeterminado (Render inyectará la variable $PORT)
ENV PORT=80
EXPOSE 80

# Script de arranque
CMD ["/usr/local/bin/start.sh"]
