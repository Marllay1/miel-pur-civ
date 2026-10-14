# Mise en production

## 1. Hébergement

- PHP 8.2+ (8.3 recommandé) avec les extensions listées dans INSTALLATION.md ; MariaDB 10.6+ ou MySQL 8+.
- Hébergement mutualisé possible si l'on peut **choisir le dossier racine** (DocumentRoot) et lancer des tâches planifiées (cron).
- **Le DocumentRoot doit pointer sur `public/`**. Les dossiers `app/`, `bin/`, `database/`, `storage/`, `tests/` et le fichier `.env` ne doivent jamais être accessibles depuis le web. Un `.htaccess` de secours à la racine du projet bloque ces chemins si la configuration est imparfaite, mais il ne remplace pas un DocumentRoot correct.

Exemple Apache :

```
<VirtualHost *:443>
    ServerName www.exemple.ci
    DocumentRoot /var/www/miel-pur-ci/public
    <Directory /var/www/miel-pur-ci/public>
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    # certificats Let's Encrypt (certbot)…
</VirtualHost>
```

Exemple Nginx (PHP-FPM) :

```
server {
    server_name www.exemple.ci;
    root /var/www/miel-pur-ci/public;
    index index.php;
    location ~ /\. { deny all; }
    location /uploads/ { location ~ \.php$ { deny all; } }
    location / { try_files $uri /index.php?$query_string; }
    location = /index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
    location ~ \.php$ { return 404; }
}
```

## 2. HTTPS

Obligatoire. Certificat Let's Encrypt (certbot) ou celui de l'hébergeur. Puis dans `.env` : `APP_URL=https://www.exemple.ci`. Les cookies passent alors en `Secure` et l'en-tête HSTS est envoyé automatiquement. Derrière un proxy/CDN, renseigner `TRUSTED_PROXY`.

## 3. Installation sur le serveur

```bash
php bin/install.php
```

(sans `--demo` en production). Compléter `.env` : `APP_ENV=production`, `APP_DEBUG=0`, accès base, `MAIL_DRIVER=mail` si un serveur de messagerie est configuré, `DEMO_PAYMENT_ENABLED=0`. Droits : `.env` en 600 ; `storage/` et `public/uploads/` inscriptibles par l'utilisateur PHP.

## 4. Premier administrateur

```bash
php bin/create-admin.php --email=direction@exemple.ci --first=Prénom --last=Nom
```

Le mot de passe généré s'affiche **une seule fois** : le noter, se connecter sur `/admin/connexion`, puis le transmettre de façon sûre. Créer ensuite les comptes de l'équipe depuis *Équipe et rôles* (gestionnaire catalogue, gestionnaire commandes). Mot de passe perdu : `php bin/create-admin.php --email=... --reset-password`.

## 5. Tâches planifiées (cron)

```
*/10 * * * * php /var/www/miel-pur-ci/bin/expire-orders.php >> /var/www/miel-pur-ci/storage/logs/cron.log 2>&1
30 2 * * *   php /var/www/miel-pur-ci/bin/backup.php >> /var/www/miel-pur-ci/storage/logs/cron.log 2>&1
```

`expire-orders` annule les commandes payables en ligne restées impayées après le délai (réglage *Délai de paiement*), restitue stock et codes promo, et purge les données techniques expirées. Tant que seul le paiement à la livraison est actif, il sert surtout au nettoyage.

## 6. Avant d'ouvrir le site

Suivre la liste de contrôle de **/admin/integrations** (tout doit être « oui ») et le document [PARAMETRES.md](PARAMETRES.md). En particulier : retirer les produits de démonstration, désactiver le paiement simulé et le bandeau de démonstration, faire valider les pages légales, renseigner coordonnées et zones de livraison réelles.

## 7. Mises à jour

Sauvegarder (`bin/backup.php`), déployer les fichiers, puis `php bin/install.php` (applique les nouvelles migrations sans toucher aux données).
