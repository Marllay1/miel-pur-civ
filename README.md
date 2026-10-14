# Miel Pur de Côte d'Ivoire — boutique en ligne

Boutique en ligne officielle de **MIEL PUR DE CÔTE D'IVOIRE** (apiculture, Katiola), réalisée par **HELIX TECHNOLOGIES**.

PHP 8.3 sans framework ni Composer · MariaDB/MySQL en production · SQLite pour les tests · CSS et JavaScript écrits à la main, sans script inline.

## Fonctionnalités

- **Site public** : accueil, boutique (recherche, filtres catégorie/format/prix/stock, tri, pagination), fiches produit avec variantes, avis modérés, à propos, contact, commandes professionnelles (devis), FAQ, suivi de commande, 5 pages légales (modèles provisoires signalés), newsletter avec consentement.
- **Panier persistant** (cookie opaque + base), codes promo, fusion à la connexion.
- **Commande en 4 étapes sans compte** : coordonnées → livraison par zones → paiement → confirmation. Montants recalculés par le serveur, protection contre les doubles commandes, contrôle du total attendu.
- **Paiement** : à la livraison (opérationnel) ; paiement **SIMULÉ** clairement étiqueté pour les tests ; architecture prête pour Orange Money, MTN MoMo, Wave et carte (**non intégrés, jamais proposés**).
- **Espace client** : commandes, adresses, profil, mot de passe, réinitialisation par e-mail.
- **Administration** : tableau de bord (chiffres réels), produits/variantes/images, catégories, stock (mouvements tracés), codes promo, commandes (filtres, transitions, notes, livraison, export CSV), devis, clients, messages, newsletter, contenus, pages légales, FAQ, avis, réglages, zones de livraison, intégrations (liste de contrôle avant production), équipe et rôles, journal d'audit.
- **Sécurité** : requêtes préparées, échappement systématique, CSRF, CSP stricte, mots de passe hachés, limitation des tentatives, permissions vérifiées côté serveur, téléversements ré-encodés, export CSV protégé.
- **SEO et accessibilité** : sitemap, robots.txt, Open Graph, JSON-LD (Organization, Product, FAQPage, BreadcrumbList), navigation au clavier, lien d'évitement, erreurs de formulaire annoncées.

## Démarrage rapide (développement)

```bash
php bin/install.php --demo
```

```bash
php bin/create-admin.php --email=vous@exemple.ci --first=Prénom --last=Nom
```

```bash
php -S localhost:8000 -t public public/router.php
```

Puis ouvrir http://localhost:8000 (administration : http://localhost:8000/admin).

## Tests

```bash
php tests/run.php
```

```bash
php tests/run.php --mysql
```

27 scénarios (dont 8 de sécurité), 723 assertions, plus 61 contrôles HTTP en boîte noire (`php tests/security-http.php`, serveur lancé). Voir [docs/TESTS.md](docs/TESTS.md) et [docs/SECURITE.md](docs/SECURITE.md).

## Documentation

| Document | Contenu |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installation locale Linux et Windows/XAMPP (phpMyAdmin) |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | Mise en production (hébergement, HTTPS, cron) et premier administrateur |
| [docs/ADMINISTRATION.md](docs/ADMINISTRATION.md) | Guide d'utilisation de l'administration |
| [docs/SAUVEGARDE.md](docs/SAUVEGARDE.md) | Sauvegarde et restauration |
| [docs/PARAMETRES.md](docs/PARAMETRES.md) | Paramètres à renseigner avant la mise en ligne |
| [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md) | État honnête des intégrations (opérationnel / à configurer) |
| [docs/TESTS.md](docs/TESTS.md) | Suite de tests et résultats |

## Arborescence

```
app/           Core (noyau), Services (métier), Controllers (+ Admin), Views, config, routes.php
bin/           install, create-admin, expire-orders, backup, restore
database/      migrations SQL portables, données de référence et de démonstration
public/        racine web : index.php, router.php, assets (css, js, fonts, img), uploads
storage/       logs, mail (pilote log), sessions, backups — non accessible depuis le web
tests/         suite de tests sans dépendance
```

## Données de démonstration

Les produits, prix, stocks et illustrations chargés par `--demo` sont **fictifs** et étiquetés « Démo ». Aucun avis client n'est créé. Les textes marqués `[À CONFIRMER]` / `[À COMPLÉTER]` sont affichés en surbrillance tant qu'ils n'ont pas été remplacés par des informations vérifiées.
