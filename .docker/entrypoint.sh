#!/bin/sh
set -e

cd /var/www/html

# Attend MySQL (service "db" du compose)
echo "Attente de MySQL..."
for i in $(seq 1 30); do
  if php -r "try { new PDO('mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-secret}'); exit(0); } catch (Throwable \$e) { exit(1); }"; then
    echo "MySQL OK"
    break
  fi
  sleep 2
done

# .env monté depuis l'hôte ; génère la clé si absente
if [ ! -f .env ]; then
  cp .env.docker.example .env
fi

php artisan key:generate --force --no-interaction || true
php artisan storage:link || true
php artisan migrate --force --no-interaction
php artisan optimize || true

chown -R www-data:www-data storage bootstrap/cache || true

exec apache2-foreground
