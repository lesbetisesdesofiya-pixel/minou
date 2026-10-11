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

# .env monté depuis l'hôte ; génère la clé seulement si absente.
# NOTE : la vraie variable d'environnement (env_file compose) prime sur le
# fichier .env, et `key:generate` refuse de tourner quand APP_KEY existe déjà
# dans l'environnement (même vide) => filet de sécurité ci-dessous.
if [ ! -f .env ]; then
  cp .env.docker.example .env
fi
if ! grep -qE '^APP_KEY=.+' .env 2>/dev/null; then
  php artisan key:generate --force --no-interaction || true
fi
if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
  echo "APP_KEY absente : clé éphémère générée pour ce démarrage (pense à la figer dans .env)"
fi
php artisan storage:link || true
php artisan migrate --force --no-interaction
php artisan optimize || true

chown -R www-data:www-data storage bootstrap/cache || true

exec apache2-foreground
