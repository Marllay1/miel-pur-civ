# Sauvegarde et restauration

## Sauvegarde intégrée (MySQL ou SQLite)

```bash
php bin/backup.php
```

Produit dans `storage/backups/` :
- `backup-AAAAMMJJ-HHMMSS.sql.gz` : toutes les données (format SQL compressé) ;
- `uploads-AAAAMMJJ-HHMMSS.zip` : les images téléversées (extension PHP `zip` requise).

Les `BACKUP_KEEP` archives les plus récentes sont conservées (14 par défaut). **Copier régulièrement ce dossier hors du serveur** (autre machine, stockage externe) : une sauvegarde restée sur le même serveur ne protège pas d'une panne de disque ou d'un piratage. Planification : voir DEPLOIEMENT.md (cron quotidien).

## Restauration

1. Schéma en place (nouvelle installation : `php bin/install.php` sans `--demo`).
2. Restaurer les données (**remplace toutes les données actuelles**) :

```bash
php bin/restore.php storage/backups/backup-AAAAMMJJ-HHMMSS.sql.gz --force
```

3. Images : décompresser `uploads-….zip` dans `public/uploads/`.

Vérifié pendant le développement : sauvegarde de la base MariaDB `miel_pur`, restauration dans `miel_test`, comptages identiques (utilisateurs, variantes, stock, journal).

## Alternative : phpMyAdmin / mysqldump

- phpMyAdmin : sélectionner la base → *Exporter* → méthode rapide, format SQL. Restauration : *Importer* le fichier sur une base vide.
- Ligne de commande :

```bash
mysqldump --single-transaction --default-character-set=utf8mb4 -u miel -p miel_pur > miel_pur.sql
```

Penser aussi à sauvegarder le fichier `.env` (il contient `APP_KEY` et les accès) dans un endroit sûr, séparé des sauvegardes de données.
