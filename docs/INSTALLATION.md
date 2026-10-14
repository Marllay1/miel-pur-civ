# Installation locale

Prérequis communs : **PHP 8.3** (8.2 minimum) avec les extensions `pdo_mysql`, `pdo_sqlite`, `mbstring`, `gd` (avec WebP), `openssl`, `fileinfo`, `curl` (paiements en ligne), `zip` (sauvegarde des images) ; **MariaDB 10.4+ ou MySQL 8+** (ou SQLite pour un essai rapide). Aucun Composer, aucun Node.js requis.

## 1. Linux (Debian/Ubuntu)

```bash
sudo apt-get install -y php-cli php-sqlite3 php-mbstring php-xml php-curl php-gd php-mysql php-zip sqlite3 mariadb-server
```

Créer la base et un utilisateur dédié (remplacer le mot de passe) :

```bash
sudo mariadb -e "CREATE DATABASE miel_pur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'miel'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT'; GRANT ALL ON miel_pur.* TO 'miel'@'localhost';"
```

Installer (le premier lancement crée `.env` avec des secrets générés ; renseigner ensuite `DB_PASSWORD` dans `.env` puis relancer) :

```bash
php bin/install.php --catalog
```

```bash
php bin/create-admin.php --email=vous@exemple.ci --first=Prénom --last=Nom
```

```bash
php -S localhost:8000 -t public public/router.php
```

Essai sans MySQL : `php bin/install.php --sqlite --catalog` (base `storage/database.sqlite`).

## 2. Windows avec XAMPP et phpMyAdmin

> **Procédure exécutée le 14/10/2026** sur Windows 11 avec **XAMPP 8.2.12** (PHP 8.2.12, Apache 2.4.58, MariaDB 10.4.32, phpMyAdmin 5.2.1) : installation, catalogue, administrateur, hôte virtuel Apache, commande complète dans un navigateur, e-mail de confirmation envoyé en SMTP, 66 contrôles de sécurité HTTP réussis contre Apache. Voir docs/TESTS.md.

1. Installer XAMPP (PHP 8.2 ou plus). Démarrer **Apache** et **MySQL** depuis le panneau XAMPP.
2. Copier le dossier `miel-pur-ci` dans `C:\xampp\htdocs\miel-pur-ci` (sans `.env` s'il en existe un : chaque installation a ses propres secrets).
3. Dans `C:\xampp\php\php.ini`, retirer le `;` devant `extension=gd` et `extension=zip` (désactivées par défaut dans XAMPP 8.2.12 ; `curl`, `mbstring`, `pdo_mysql`, `pdo_sqlite`, `fileinfo` et `openssl` sont déjà disponibles). Garder une copie du fichier avant modification. Vérifier avec `C:\xampp\php\php.exe -m` que les huit extensions de la liste des prérequis apparaissent, puis redémarrer Apache.
4. **phpMyAdmin** (http://localhost/phpmyadmin) :
   - onglet *Bases de données* → nom `miel_pur`, interclassement `utf8mb4_unicode_ci` → *Créer* ;
   - onglet *Comptes d'utilisateurs* → *Ajouter un compte* : nom `miel`, hôte `localhost`, mot de passe fort ; après création, *Modifier les privilèges* → *Base de données* `miel_pur` → *Tout cocher* → *Exécuter*.

   Équivalent en ligne de commande (le compte `root` de XAMPP n'a pas de mot de passe par défaut) :

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE USER 'miel'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT'; GRANT ALL PRIVILEGES ON miel_pur.* TO 'miel'@'localhost';"
```

5. Ouvrir l'invite de commandes dans le dossier du projet :

```bash
cd C:\xampp\htdocs\miel-pur-ci
```

```bash
C:\xampp\php\php.exe bin\install.php --catalog
```

   Le premier lancement crée `.env` puis s'arrête sur « Access denied for user 'miel' » : c'est normal. Ouvrir `.env` avec un éditeur de texte, renseigner `DB_PASSWORD` et `APP_URL=http://miel.localhost`, puis relancer la même commande (elle peut être relancée sans risque : rien n'est dupliqué).

```bash
C:\xampp\php\php.exe bin\create-admin.php --email=vous@exemple.ci --first=Prénom --last=Nom
```

   Le mot de passe généré s'affiche **une seule fois**.

6. **Hôte virtuel Apache** : ajouter à la fin de `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (garder une copie avant modification) :

```
# Refuse la méthode TRACE.
TraceEnable Off

# Premier hôte = hôte par défaut : conserve http://localhost (tableau de bord XAMPP, phpMyAdmin).
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"
</VirtualHost>

<VirtualHost *:80>
    ServerName miel.localhost
    DocumentRoot "C:/xampp/htdocs/miel-pur-ci/public"
    <Directory "C:/xampp/htdocs/miel-pur-ci/public">
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "logs/miel-error.log"
    CustomLog "logs/miel-access.log" common
</VirtualHost>
```

   Sans le premier bloc `localhost`, phpMyAdmin et le tableau de bord XAMPP ne sont plus accessibles (Apache envoie alors toute requête vers le premier hôte déclaré).

   Recommandé : dans `C:\xampp\apache\conf\extra\httpd-default.conf`, remplacer `ServerTokens Full` par `ServerTokens Prod` et `ServerSignature On` par `ServerSignature Off` (les versions d'Apache, PHP et OpenSSL ne sont plus divulguées). Ces deux lignes doivent être modifiées **dans ce fichier** : il est lu après `httpd-vhosts.conf` et l'emporterait.

   Vérifier la configuration avec `C:\xampp\apache\bin\httpd.exe -t` (doit afficher `Syntax OK`), redémarrer Apache, puis ouvrir **http://miel.localhost** (Chrome, Edge et Firefox reconnaissent les adresses en `.localhost` sans autre réglage).

   Variante sans Apache : `C:\xampp\php\php.exe -S localhost:8000 -t public public\router.php` puis http://localhost:8000 (mettre alors `APP_URL=http://localhost:8000`).

**Important** : le site doit être servi depuis le dossier `public/`. Ne pas utiliser http://localhost/miel-pur-ci/ directement (les chemins `/assets/...` supposent que `public/` est la racine).

7. **Vérifier la sécurité de la configuration Apache** (avec `MAIL_DRIVER=log` dans `.env`) :

```bash
C:\xampp\php\php.exe tests\security-http.php http://miel.localhost
```

   Résultat attendu : `66 contrôle(s) réussi(s), 0 échec(s)`. Le script crée un compte client jetable et remet à zéro les limitations de fréquence : ne jamais le lancer sur le site en production (il refuse si `APP_ENV=production`).

> Sécurité de XAMPP : XAMPP est un outil de développement. Son MySQL écoute sur toutes les interfaces avec un `root` sans mot de passe : ne pas l'exposer sur un réseau et ne jamais l'utiliser pour le site en production.

## 3. Vérifier l'installation

```bash
php tests/run.php
```

Tous les scénarios doivent afficher `OK`. Pour tester sur MySQL : créer une base vide `miel_test` accessible au même utilisateur, puis `php tests/run.php --mysql` (la base de test est **entièrement vidée** à chaque scénario).

## 4. E-mails en développement

Par défaut `MAIL_DRIVER=log` : les e-mails (confirmation de commande, réinitialisation) sont écrits dans `storage/mail/*.eml` et **ne sont pas envoyés**.

Pour voir les e-mails comme un client les recevrait, utiliser un serveur SMTP de test local comme **Mailpit** (https://mailpit.axllent.org) : le lancer, puis dans `.env` :

```
MAIL_DRIVER=smtp
SMTP_HOST=127.0.0.1
SMTP_PORT=1025
SMTP_ENCRYPTION=none
```

Les messages s'affichent sur http://localhost:8025. Pour l'envoi réel, voir docs/PARAMETRES.md (section E-mails) puis :

```bash
php tests/smtp-check.php --to=votre@adresse.ci
```
