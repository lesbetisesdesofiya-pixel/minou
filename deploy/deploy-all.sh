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

# ── Contenu Caddy : 1 bloc par site (espaces, pas de \t littéraux) ───────────
caddy_block() {
  printf '\n%s.operatogo.net {\n  encode zstd gzip\n  reverse_proxy %s-app:80\n  log {\n    output stdout\n  }\n}\n' "$1" "$1"
}

deploy_site() { # $1=site $2=port $3=dir
  local site="$1" port="$2" dir="$3"
  echo "===== SITE $site ($dir, port $port) ====="

  if [ -d "$dir/.git" ]; then
    git -C "$dir" pull --quiet || echo "[warn] git pull impossible dans $dir"
  else
    git clone --quiet "$REPO" "$dir"
  fi

  # .env : SITE/PORT/URL imposés (structure, pas des secrets) ;
  # secrets (DB_PASSWORD, JWT_SECRET, API_KEY...) : ajoutés seulement si absents.
  [ -f "$dir/.env" ] || cp "$dir/.env.docker.example" "$dir/.env"
  force_kv() {
    if grep -q "^$1=" "$dir/.env"; then
      sed -i "s|^$1=.*|$1=$2|" "$dir/.env"
    else
      echo "$1=$2" >> "$dir/.env"
    fi
  }
  force_kv SITE "$site"
  force_kv APP_PORT "$port"
  force_kv APP_URL "https://$site.operatogo.net"
  grep -q '^DB_DATABASE=' "$dir/.env" || echo "DB_DATABASE=opera_resto" >> "$dir/.env"
  # APP_KEY : remplit si absente OU vide (sinon 500 MissingAppKeyException).
  # On supprime d'abord les lignes vides pour ne jamais en avoir 2.
  sed -i '/^APP_KEY=$/d' "$dir/.env"
  if ! grep -qE '^APP_KEY=.+' "$dir/.env"; then
    echo "APP_KEY=base64:$(openssl rand -base64 32)" >> "$dir/.env"
    echo "[$site] APP_KEY générée dans .env"
  fi
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

  if (cd "$dir" && docker compose up -d --build --force-recreate); then
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
  else
    echo "[ERREUR $site] docker compose up a échoué — seed/menu ignorés. Corrige puis relance."
  fi
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
