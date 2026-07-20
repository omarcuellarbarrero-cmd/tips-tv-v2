FROM php:8.2-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Configurar PHP-FPM
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen = 127.0.0.1:9000" >> /usr/local/etc/php-fpm.d/www.conf

# Configurar Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Configurar Supervisor
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copiar archivos de la app
COPY . /usr/share/nginx/html

# CREAR CARPETA DATA CON PERMISOS CORRECTOS (IMPORTANTE)

# Al final del Dockerfile
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/data
