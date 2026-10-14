# Installation locale

Prérequis communs : **PHP 8.3** (8.2 minimum) avec les extensions `pdo_mysql`, `pdo_sqlite`, `mbstring`, `gd` (avec WebP), `openssl`, `fileinfo`, `zip` (sauvegarde des images) ; **MariaDB 10.6+ ou MySQL 8+** (ou SQLite pour un essai rapide). Aucun Composer, aucun Node.js requis.

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
php bin/install.php --demo
```

```bash
php bin/create-admin.php --email=vous@exemple.ci --first=Prénom --last=Nom
```

```bash
php -S localhost:8000 -t public public/router.php
```

Essai sans MySQL : `php bin/install.php --sqlite --demo` (base `storage/database.sqlite`).

## 2. Windows avec XAMPP et phpMyAdmin

> Ces étapes suivent le fonctionnement standard de XAMPP ; elles n'ont **pas** été exécutées sur une installation XAMPP pendant le développement (machine de développement : PHP 8.3 + MariaDB 11.4 autonomes sous Windows 11). Merci de signaler tout écart.

1. Installer XAMPP avec PHP 8.2 ou 8.3. Démarrer **Apache** et **MySQL** depuis le panneau XAMPP.
2. Copier le dossier `miel-pur-ci` dans `C:\xampp\htdocs\miel-pur-ci`.
3. Vérifier `C:\xampp\php\php.ini` : les lignes `extension=gd`, `extension=mbstring`, `extension=pdo_mysql`, `extension=pdo_sqlite`, `extension=zip`, `extension=fileinfo` ne doivent pas commencer par `;`. Redémarrer Apache.
4. **phpMyAdmin** (http://localhost/phpmyadmin) :
   - onglet *Bases de données* → nom `miel_pur`, interclassement `utf8mb4_unicode_ci` → *Créer* ;
   - onglet *Comptes d'utilisateurs* → *Ajouter un compte* : nom `miel`, hôte `localhost`, mot de passe fort ; dans *Base de données pour ce compte*, ne rien cocher puis, après création, *Modifier les privilèges* → *Base de données* `miel_pur` → *Tout cocher* → *Exécuter*.
5. Ouvrir l'invite de commandes dans le dossier du projet :

```bash
cd C:\xampp\htdocs\miel-pur-ci
```

```bash
C:\xampp\php\php.exe bin\install.php --demo
```

   Le premier lancement crée `.env`. L'ouvrir avec un éditeur de texte, renseigner `DB_PASSWORD` (et `DB_USERNAME=miel`), puis relancer la commande précédente.

```bash
C:\xampp\php\php.exe bin\create-admin.php --email=vous@exemple.ci --first=Prénom --last=Nom
```

6. Accès au site — deux options :
   - **Simple** : `C:\xampp\php\php.exe -S localhost:8000 -t public public\router.php` puis http://localhost:8000 ;
   - **Via Apache** (hôte virtuel, recommandé pour tester les `.htaccess`) : ajouter dans `C:\xampp\apache\conf\extra\httpd-vhosts.conf` :

```
<VirtualHost *:80>
    ServerName miel.localhost
    DocumentRoot "C:/xampp/htdocs/miel-pur-ci/public"
    <Directory "C:/xampp/htdocs/miel-pur-ci/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

     redémarrer Apache, puis ouvrir http://miel.localhost. Dans `.env`, mettre `APP_URL=http://miel.localhost`.

**Important** : le site doit être servi depuis le dossier `public/`. Ne pas utiliser http://localhost/miel-pur-ci/ directement (les chemins `/assets/...` supposent que `public/` est la racine).

## 3. Vérifier l'installation

```bash
php tests/run.php
```

Tous les scénarios doivent afficher `OK`. Pour tester sur MySQL : créer une base vide `miel_test` accessible au même utilisateur, puis `php tests/run.php --mysql` (la base de test est **entièrement vidée** à chaque scénario).

## 4. E-mails en développement

Par défaut `MAIL_DRIVER=log` : les e-mails (confirmation de commande, réinitialisation) sont écrits dans `storage/mail/*.eml` et **ne sont pas envoyés**.
