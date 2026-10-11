#!/bin/bash
# ─── Déploiement COMPLET Opera Resto : avepozo + avedji + legbassito ─────────
# Usage (UNE SEULE commande, depuis un VPS Ubuntu avec git + docker) :
#   sudo mkdir -p /opt && sudo chown $USER:$USER /opt && \
#   [ -d /opt/opera/.git ] || git clone https://github.com/lesbetisesdesofiya-pixel/minou.git /opt/opera && \
#   bash /opt/opera/deploy/deploy-all.sh
#
# Idempotent : relançable sans risque (ne touche jamais aux secrets existants).
# Fait : clone/pull x3, .env par site (secrets générés si absents), FCM copié,
#         seed + menu, Caddy 3 vhosts + reload.
# NE FAIT PAS (manuel) : DNS registrar, apps mobiles (Expo/APK), serviceAccountKey
#         initial (à poser une fois dans /opt/opera, voir §5).
set -u
REPO="https://github.com/lesbetisesdesofiya-pixel/minou.git"
KEYFILE="/opt/.opera_api_key"

need() { command -v "$1" >/dev/null 2>&1 || { echo "MANQUANT: $1 (installe-le puis relance)"; exit 1; }; }
need git; need docker; need openssl

# ── 0. Clé API partagée (même valeur apps + 3 sites) ─────────────────────────
if [ ! -f "$KEYFILE" ]; then
  openssl rand -hex 32 > "$KEYFILE"
  chmod 600 "$KEYFILE"
  echo "[api] nouvelle clé API générée dans $KEYFILE"
fi
API_KEY="$(cat "$KEYFILE")"

# ── Contenu Caddy : 1 bloc par site ──────────────────────────────────────────
caddy_block() {
  cat <<EOF

$1.operatogo.net {
\tencode zstd gzip
\treverse_proxy $1-app:80
\tlog {
\t\toutput stdout
\t}
}
EOF
}

deploy_site() { # $1=site $2=port $3=dir
  local site="$1" port="$2" dir="$3"
  echo "===== SITE $site ($dir, port $port) ====="

  if [ -d "$dir/.git" ]; then
    git -C "$dir" pull --quiet || echo "[warn] git pull impossible dans $dir"
  else
    git clone --quiet "$REPO" "$dir"
  fi

  # .env : complète SANS écraser (jamais de secret régénéré)
  [ -f "$dir/.env" ] || cp "$dir/.env.docker.example" "$dir/.env"
  set_kv() { grep -q "^$1=" "$dir/.env" || echo "$1=$2" >> "$dir/.env"; }
  set_kv SITE "$site"
  set_kv APP_PORT "$port"
  set_kv APP_URL "https://$site.operatogo.net"
  set_kv DB_DATABASE "opera_resto"
  [ -f "$dir/.env" ] && grep -q '^DB_PASSWORD=' "$dir/.env" || echo "DB_PASSWORD=$(openssl rand -hex 16)" >> "$dir/.env"
  grep -q '^JWT_SECRET=' "$dir/.env" || echo "JWT_SECRET=$(openssl rand -hex 32)" >> "$dir/.env"
  grep -q '^API_KEY=' "$dir/.env" || echo "API_KEY=$API_KEY" >> "$dir/.env"

  # serviceAccountKey.json (push FCM) : propagé depuis /opt/opera si présent
  if [ ! -f "$dir/serviceAccountKey.json" ] && [ -f /opt/opera/serviceAccountKey.json ]; then
    cp /opt/opera/serviceAccountKey.json "$dir/serviceAccountKey.json"
    echo "[$site] serviceAccountKey.json copié"
  fi
  if [ ! -f "$dir/serviceAccountKey.json" ]; then
    echo "[WARN $site] serviceAccountKey.json absent -> push FCM inactif (polling seul)."
    echo "       Pose-le une fois (scp depuis le PC) puis relance ce script."
  fi

  # Ancien nom de container (opera-app) -> down avant rebuild (conflit de port/nom)
  if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q '^opera-app$'; then
    (cd "$dir" && docker compose down) || true
  fi

  (cd "$dir" && docker compose up -d --build --force-recreate)

  # Seed + menu (réessais : la migration tourne au démarrage du container)
  for i in 1 2 3 4 5 6; do
    if (cd "$dir" && docker compose exec -T app php artisan db:seed --force >/dev/null 2>&1); then
      echo "[$site] seed OK"
      break
    fi
    echo "[$site] seed en attente ($i/6)..."
    sleep 10
  done
  PW=$(grep -E '^DB_PASSWORD=' "$dir/.env" | cut -d= -f2-)
  (cd "$dir" && docker compose exec -T db mysql -uroot -p"$PW" opera_resto < database/menu-seed.sql) \
    && echo "[$site] menu OK" || echo "[WARN $site] import menu à vérifier"
}

deploy_site avepozo 8080 /opt/opera
deploy_site avedji 8081 /opt/avedji
deploy_site legbassito 8082 /opt/legbassito

# ── Caddy : ajoute les blocs manquants puis reload ───────────────────────────
CADDYFILE="/home/ubuntu/classinote/Caddyfile"
if [ -f "$CADDYFILE" ]; then
  for site in avepozo avedji legbassito; do
    if grep -q "^$site\.operatogo\.net" "$CADDYFILE"; then
      echo "[caddy] $site déjà présent"
    else
      caddy_block "$site" >> "$CADDYFILE"
      echo "[caddy] bloc $site ajouté"
    fi
  done
  docker exec classinote-caddy-1 caddy reload --config /etc/caddy/Caddyfile \
    && echo "[caddy] reload OK" || echo "[WARN] reload Caddy à faire à la main"
else
  echo "[WARN] Caddyfile introuvable ($CADDYFILE) : ajoute les blocs de deploy/caddy-operatogo.conf à la main"
fi

echo ""
echo "════════ DÉPLOIEMENT TERMINÉ ════════"
echo "Sites : https://avepozo.operatogo.net https://avedji.operatogo.net https://legbassito.operatogo.net"
echo "Clé API (apps Expo/APK) : cat $KEYFILE"
echo "RESTE MANUEL :"
echo "  1. DNS registrar (A+AAAA par sous-domaine, voir DEPLOY.md §1)"
echo "  2. serviceAccountKey.json initial si absent (scp une fois vers /opt/opera/)"
echo "  3. Apps : Expo (EXPO_PUBLIC_API_KEY) + APK alerte (apiKey) avec la clé ci-dessus"
echo "  4. Test : commande sur chaque site -> alarme de la bonne cuisine uniquement"
