# Déploiement VPS — avepozo.operatogo.net

## 1. DNS
Créer l'enregistrement A : `avepozo` → IP du VPS (zone `operatogo.net`).

## 2. Push (depuis Windows/XAMPP)
```bat
git remote set-url origin https://github.com/lesbetisesdesofiya-pixel/minou.git
git branch -M main
git push -u origin main
```
> `set-url` (et non `add`) car `origin` existe déjà.

## 3. VPS — 1er déploiement
```bash
git clone https://github.com/lesbetisesdesofiya-pixel/minou.git /opt/opera && cd /opt/opera
cp .env.docker.example .env
nano .env   # APP_KEY (généré auto), DB_PASSWORD, JWT_SECRET (64 hex), MoneyFusion
docker compose up -d --build
docker compose exec app php artisan db:seed --force   # comptes admin + livreur
# Données du menu (exportées du local) :
PW=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2) && docker compose exec -T db mysql -uroot -p"$PW" opera_resto < database/menu-seed.sql
```

## 4. VPS — reverse-proxy + HTTPS (nginx hôte)
```bash
sudo cp deploy/nginx-avepozo.operatogo.net.conf /etc/nginx/sites-available/avepozo.operatogo.net
sudo ln -s /etc/nginx/sites-available/avepozo.operatogo.net /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d avepozo.operatogo.net
```

## 5. Mises à jour suivantes
```bash
cd /opt/opera && git pull && docker compose up -d --build
```

## URLs prod
- Site client : https://avepozo.operatogo.net/ (menu, checkout, suivi)
- API : https://avepozo.operatogo.net/api
- Admin : app Expo "Opéra Admin" (dossier myopera/) — plus de pages admin web.
  Lancer : `cd myopera && npx expo start` (dev) ou build EAS (`npx eas-cli@latest build -p android`).
- Comptes seed : `admin@opera.com` / `password` (admin), `admin2@opera.com` / `password` (livreur)

## 6. App Android "Opéra Admin" (dossier Opera/)
- Code : `Opera/` (Compose + WebView + FCM). `google-services.json` déjà inclus.
- Ouvrir `Opera/` dans Android Studio → laisser Gradle synchroniser → Run sur tablette/téléphone cuisine.
- 1er lancement : écran Setup → email + password admin → token stocké chiffré (plus jamais demandé).
- L'app surveille les commandes (FCM push + polling secours 20 s), sonne + affiche l'écran Alerte (design),
  Accepter/Refuser appelle l'API puis ouvre le détail dans la WebView
  (`/app/#/admin/commandes/:id?token=...`, sans login).
- Recette : passer une commande test sur le site → alarme en < 30 s → Accepter → détail affiché.
