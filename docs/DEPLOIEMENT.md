# Mise en production

## 1. Hébergement

- PHP 8.2+ (8.3 recommandé) avec les extensions listées dans INSTALLATION.md (dont `curl` pour les paiements en ligne) ; MariaDB 10.4+ ou MySQL 8+.
- Hébergement mutualisé possible si l'on peut **choisir le dossier racine** (DocumentRoot) et lancer des tâches planifiées (cron).
- **Le DocumentRoot doit pointer sur `public/`**. Les dossiers `app/`, `bin/`, `database/`, `storage/`, `tests/` et le fichier `.env` ne doivent jamais être accessibles depuis le web. Un `.htaccess` de secours à la racine du projet bloque ces chemins si la configuration est imparfaite, mais il ne remplace pas un DocumentRoot correct.

Les deux configurations ci-dessous ont été **testées** (Apache 2.4.58 via XAMPP, Nginx 1.30.5 avec PHP en FastCGI) : 66 contrôles de sécurité HTTP réussis sur chacune (`tests/security-http.php`, voir docs/TESTS.md).

### Apache

```
TraceEnable Off
ServerTokens Prod
ServerSignature Off

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

`TraceEnable`, `ServerTokens` et `ServerSignature` se placent au niveau du serveur (pas dans un `.htaccess`) ; vérifier qu'aucun fichier inclus plus loin ne les redéfinit (sous XAMPP : `httpd-default.conf`). Modules requis : `mod_rewrite`, `mod_headers`.

### Nginx (PHP-FPM)

```
server_tokens off;

# Hôte inconnu (en-tête Host forgé) : connexion fermée sans réponse.
server {
    listen 443 ssl default_server;
    server_name _;
    ssl_reject_handshake on;
}

server {
    listen 443 ssl;
    server_name www.exemple.ci;
    root /var/www/miel-pur-ci/public;
    index index.php;
    client_max_body_size 8m;
    # certificats Let's Encrypt (certbot)…
    location ~ /\. { deny all; }
    location /uploads/ { location ~ \.php$ { deny all; } }
    location / { try_files $uri /index.php?$query_string; }
    location = /index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
    location ~ \.php$ { return 404; }
}
```

Seul `public/index.php` est exécuté ; tout autre fichier `.php` renvoie 404, et aucun script n'est exécuté dans `uploads/`. `client_max_body_size` doit rester supérieur à la taille maximale des images (5 Mo).

## 2. HTTPS

Obligatoire (et indispensable aux notifications de paiement). Certificat Let's Encrypt (certbot) ou celui de l'hébergeur. Puis dans `.env` : `APP_URL=https://www.exemple.ci`. Les cookies passent alors en `Secure` et l'en-tête HSTS est envoyé automatiquement. Derrière un proxy/CDN, renseigner `TRUSTED_PROXY`.

## 3. Installation sur le serveur

```bash
php bin/install.php --catalog
```

(`--catalog` charge la gamme initiale ; l'omettre pour démarrer avec un catalogue vide). Compléter `.env` : `APP_ENV=production`, `APP_DEBUG=0`, accès base, e-mails (`MAIL_DRIVER=smtp` et `SMTP_*`, voir PARAMETRES.md), `DEMO_PAYMENT_ENABLED=0`, clés des prestataires de paiement (voir INTEGRATIONS.md). Droits : `.env` en 600 ; `storage/` et `public/uploads/` inscriptibles par l'utilisateur PHP.

Vérifier l'envoi d'e-mails :

```bash
php tests/smtp-check.php --to=votre@adresse.ci
```

## 4. Premier administrateur

```bash
php bin/create-admin.php --email=direction@exemple.ci --first=Prénom --last=Nom
```

Le mot de passe généré s'affiche **une seule fois** : le noter, se connecter sur `/admin/connexion`, puis le transmettre de façon sûre. Créer ensuite les comptes des autres administrateurs depuis *Équipe* (un seul rôle d'équipe : Administrateur). Mot de passe perdu : `php bin/create-admin.php --email=... --reset-password`.

## 5. Tâches planifiées (cron)

```
*/10 * * * * php /var/www/miel-pur-ci/bin/expire-orders.php >> /var/www/miel-pur-ci/storage/logs/cron.log 2>&1
30 2 * * *   php /var/www/miel-pur-ci/bin/backup.php >> /var/www/miel-pur-ci/storage/logs/cron.log 2>&1
```

`expire-orders` annule les commandes payables en ligne restées impayées après le délai (réglage *Délai de paiement*), restitue stock et codes promo, vérifie une dernière fois l'état des paiements en attente auprès des prestataires et purge les données techniques expirées.

## 6. Avant d'ouvrir le site

Suivre la liste de contrôle de **/admin/integrations** (tout doit être « oui ») et le document [PARAMETRES.md](PARAMETRES.md). En particulier : faire valider les pages légales, renseigner raison sociale, RCCM, directeur de la publication, hébergeur et e-mail de notification des commandes, vérifier les frais et délais des zones de livraison, tester chaque moyen de paiement en ligne avec une petite transaction réelle avant de l'ouvrir.

## 7. Mises à jour

Sauvegarder (`bin/backup.php`), déployer les fichiers, puis `php bin/install.php` (applique les nouvelles migrations sans toucher aux données).
