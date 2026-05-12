# 1. Usamos la versión CLI de PHP 8.3
FROM php:8.4-cli

# 2. Instalamos dependencias del sistema y drivers de SQL Server
RUN apt-get update && apt-get install -y \
    unixodbc-dev gnupg curl apt-transport-https \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && apt-get install -y libzip-dev zip unzip \
    && docker-php-ext-install zip pcntl

# 3. Traemos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Establecemos el directorio de trabajo
WORKDIR /var/www

# 5. Copiamos el proyecto
COPY . .

# 6. INSTALAMOS DEPENDENCIAS (Sin el flag --no-dev para desarrollo local)
RUN composer install

# 7. Ajustamos permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 8. Exponemos el puerto y levantamos el servidor de desarrollo
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
