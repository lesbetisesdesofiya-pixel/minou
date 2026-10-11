# Déploiement VPS — 3 sites : avepozo / avedji / legbassito (.operatogo.net)

## 0. Méthode rapide : UNE SEULE commande (tout sauf DNS, voir §1)
```bash
sudo mkdir -p /opt && sudo chown $USER:$USER /opt && [ -d /opt/opera/.git ] || git clone https://github.com/lesbetisesdesofiya-pixel/minou.git /opt/opera && bash /opt/opera/deploy/deploy-all.sh
```
Le script (idempotent, relançable) : pull/clone les 3 dossiers, crée les `.env`
(secrets générés sauf existants), copie `serviceAccountKey.json`, seed + menu,
ajoute les 3 vhosts Caddy + reload. Affiche la clé API à reporter dans les apps.

## 1. DNS (zone operatogo.net)
Pour CHAQUE sous-domaine (avepozo, avedji, legbassito) :
```
A     <site>  →  158.69.209.237
AAAA  <site>  →  2607:5300:205:200::a97f
```

## 2. Push (depuis Windows/XAMPP)
```bat
git remote set-url origin https://github.com/lesbetisesdesofiya-pixel/minou.git
git branch -M main
git push -u origin main
```
> `set-url` (et non `add`) car `origin` existe déjà.

## 3. VPS — 1 dossier par restaurant (même code, bases isolées)
```bash
# AVEDJI (port 8081)
git clone https://github.com/lesbetisesdesofiya-pixel/minou.git /opt/avedji && cd /opt/avedji
cp .env.docker.example .env && nano .env
#   SITE=avedji, APP_PORT=8081, APP_URL=https://avedji.operatogo.net
#   DB_DATABASE=opera_resto, DB_PASSWORD=<solide unique>, JWT_SECRET, API_KEY, MoneyFusion
#   + copier serviceAccountKey.json (scp, jamais git)
docker compose up -d --build
docker compose exec app php artisan db:seed --force
PW=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2) && docker compose exec -T db mysql -uroot -p"$PW" opera_resto < database/menu-seed.sql

# LEGBASSITO (port 8082) : pareil dans /opt/legbassito avec SITE=legbassito, APP_PORT=8082

# AVEPOZO existant (/opt/opera) : git pull + passer à SITE=avepozo / APP_PORT=8080 dans .env,
# renommer le dossier en /opt/avepozo (optionnel), puis :
#   docker compose down && docker compose up -d --build   # down obligatoire (noms de containers changent)
```

## 4. VPS — reverse-proxy Caddy (HTTPS auto, 3 sites)
```bash
# Ajouter les 3 blocs de deploy/caddy-operatogo.conf dans /home/ubuntu/classinote/Caddyfile
docker exec classinote-caddy-1 caddy reload --config /etc/caddy/Caddyfile
# (nginx n'est PAS utilisé sur ce VPS ; deploy/nginx-*.conf est obsolète)
```

## 5. Mises à jour suivantes (dans CHAQUE dossier /opt/<site>)
```bash
cd /opt/avepozo && git pull && docker compose up -d --build
```

## URLs prod (x3 : avepozo, avedji, legbassito)
- Site client : https://&lt;site&gt;.operatogo.net/ (menu, checkout, suivi)
- API : https://&lt;site&gt;.operatogo.net/api
- Admin : app Expo "Opéra Admin" (choix du site au 1er lancement).
  Lancer : `cd myopera && npx expo start` (dev) ou build EAS (`npx eas-cli@latest build -p android`).
- Comptes seed (par site) : `admin@opera.com` / `password` (admin), `admin2@opera.com` / `password` (livreur)

## 6. App Android "Opéra Admin" (dossier Opera/)
- Code : `Opera/` (Compose + WebView + FCM). `google-services.json` déjà inclus.
- Ouvrir `Opera/` dans Android Studio → laisser Gradle synchroniser → Run sur tablette/téléphone cuisine.
- 1er lancement : écran Setup → email + password admin → token stocké chiffré (plus jamais demandé).
- L'app surveille les commandes (FCM push + polling secours 20 s), sonne + affiche l'écran Alerte (design),
  Accepter/Refuser appelle l'API puis ouvre le détail dans la WebView
  (`/app/#/admin/commandes/:id?token=...`, sans login).
- Recette : passer une commande test sur le site → alarme en < 30 s → Accepter → détail affiché.
