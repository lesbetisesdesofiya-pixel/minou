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
# Rebuild du React si frontend/ modifié :
#   (en local) cd frontend && npm run build, commit de public/app, push, puis git pull + rebuild sur le VPS
```

## URLs prod
- App React : https://avepozo.operatogo.net/app/#/admin/login
- API : https://avepozo.operatogo.net/api
- Comptes seed : `admin@opera.com` / `password` (admin), `admin2@opera.com` / `password` (livreur)
