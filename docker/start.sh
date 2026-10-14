#!/bin/bash
set -e

# Puerto provisto por Render (por defecto 80 o 10000)
PORT="${PORT:-80}"
echo "=========================================================="
echo " Iniciando Laravel en Apache (Puerto: ${PORT})"
echo "=========================================================="

# Configurar puerto dinámico en Apache
sed -i "s/Listen [0-9]\+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]\+>/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Asegurar directorios de storage y permisos de escritura
mkdir -p /var/www/html/storage/framework/{sessions,views,cache} /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Caching de Laravel para producción
echo "▶ Ejecutando php artisan config:cache..."
php artisan config:cache || true

echo "▶ Ejecutando php artisan route:cache..."
php artisan route:cache || true

echo "▶ Ejecutando php artisan view:cache..."
php artisan view:cache || true

# Despliegue automático de base de datos si la variable está activa
if [ "${AUTO_DEPLOY_SMARTDB}" = "true" ]; then
    echo "▶ Ejecutando despliegue automático de Smart-DB..."
    php artisan smartdb:deploy --force || true
fi

echo "✔ Apache listo para recibir tráfico."
exec apache2-foreground
