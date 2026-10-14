# Miel Pur de Côte d'Ivoire — boutique en ligne

Boutique en ligne officielle de **MIEL PUR DE CÔTE D'IVOIRE** (apiculture, Katiola), réalisée par **HELIX TECHNOLOGIES**.

PHP 8.2+ (8.3 recommandé) sans framework ni Composer · MariaDB/MySQL en production · SQLite pour les tests · CSS et JavaScript écrits à la main, sans script inline.

## Fonctionnalités

- **Site public bilingue** français / anglais (`/en/…`, balises hreflang, e-mails dans la langue de la commande), **thèmes clair et sombre** (préférence du système ou choix mémorisé, sans JavaScript requis), interface inspirée des applications mobiles (barre d'onglets, cellules groupées, contrastes vérifiés).
- **Pages** : accueil, boutique (recherche, filtres catégorie/format/prix/stock, tri, pagination), fiches produit avec variantes, avis modérés, notre histoire, contact, commandes professionnelles (devis), FAQ, suivi de commande, pages légales, newsletter avec consentement.
- **Panier persistant** (cookie opaque + base), codes promo, fusion à la connexion.
- **Commande en 4 étapes sans compte** : coordonnées → livraison par zones → paiement → confirmation. Montants recalculés par le serveur, protection contre les doubles commandes, contrôle du total attendu.
- **Paiement** : à la livraison ; **Wave**, **Orange Money**, **MTN Mobile Money** et **carte bancaire (CinetPay)** intégrés (pages de paiement hébergées par les prestataires, notifications vérifiées) — testés contre des simulateurs, à valider avec les clés marchandes avant ouverture (voir [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md)).
- **E-mails** : client SMTP intégré (STARTTLS/TLS, authentification), messages texte + HTML.
- **Espace client** : commandes, adresses, profil, mot de passe, réinitialisation par e-mail.
- **Administration** (en français) : tableau de bord, produits/variantes/images avec **traduction anglaise**, catégories, stock (mouvements tracés), codes promo, commandes (filtres, transitions, notes, livraison, export CSV), devis, clients, messages, newsletter, contenus et pages (FR/EN), FAQ, avis, réglages, zones de livraison, intégrations (état réel + liste de contrôle avant production), équipe et rôles (permissions vérifiées côté serveur), journal d'audit.
- **Sécurité** : requêtes préparées, échappement systématique, CSRF, CSP stricte, mots de passe hachés, limitation des tentatives, téléversements ré-encodés, export CSV protégé, aucun secret dans le code, aucune donnée de carte stockée.
- **SEO et accessibilité** : sitemap bilingue, robots.txt, Open Graph, JSON-LD (Organization, Product, FAQPage, BreadcrumbList), navigation au clavier, lien d'évitement, erreurs de formulaire annoncées.

## Démarrage rapide (développement)

```bash
php bin/install.php --catalog
```

```bash
php bin/create-admin.php --email=vous@exemple.ci --first=Prénom --last=Nom
```

```bash
php -S localhost:8000 -t public public/router.php
```

Puis ouvrir http://localhost:8000 (administration : http://localhost:8000/admin). Windows/XAMPP : voir [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Tests

```bash
php tests/run.php
```

```bash
php tests/run.php --mysql
```

31 scénarios (dont 8 de sécurité, 1 sur les paiements intégrés et 1 sur la séparation client/administration), 1 023 assertions — réussis sous PHP 8.3 et 8.2, SQLite, MariaDB 11.4 et 10.4 ; 66 contrôles HTTP en boîte noire réussis contre Apache, Nginx et le serveur intégré (`php tests/security-http.php <url>`) ; 15 contrôles SMTP (`php tests/smtp-check.php --mailpit`). Voir [docs/TESTS.md](docs/TESTS.md) et [docs/SECURITE.md](docs/SECURITE.md).

## Documentation

| Document | Contenu |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installation locale Linux et Windows/XAMPP (procédure exécutée) |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | Mise en production : Apache et Nginx (configurations testées), HTTPS, cron, premier administrateur |
| [docs/ADMINISTRATION.md](docs/ADMINISTRATION.md) | Guide d'utilisation de l'administration |
| [docs/SAUVEGARDE.md](docs/SAUVEGARDE.md) | Sauvegarde et restauration |
| [docs/PARAMETRES.md](docs/PARAMETRES.md) | Paramètres à renseigner avant la mise en ligne (SMTP, mentions légales…) |
| [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md) | Paiements et services : état réel, ce qui est testé et ce qui reste à valider |
| [docs/TESTS.md](docs/TESTS.md) | Suite de tests et résultats |
| [docs/IDENTITE-VISUELLE.md](docs/IDENTITE-VISUELLE.md) | Logo et favicons (bin/brand.php), photographies, crédits et licences |
| [docs/SECURITE.md](docs/SECURITE.md) | Mesures, tests de sécurité, défauts corrigés, risques résiduels |

## Arborescence

```
app/           Core (noyau), Services (métier, Payment/ : prestataires), Controllers (+ Admin), Views, lang (fr, en), config, routes.php
bin/           install, create-admin, expire-orders, backup, restore, check-translations
database/      migrations SQL portables, données de référence et catalogue initial (FR/EN)
public/        racine web : index.php, router.php, assets (css, js, img), uploads
storage/       logs, mail (pilote log), sessions, backups — non accessible depuis le web
tests/         suite de tests sans dépendance, simulateur des prestataires de paiement
```

## Contenu

Le catalogue initial (`--catalog`) reprend la gamme et les prix confirmés par la boutique ; les illustrations sont à remplacer par de vraies photos. Aucun avis client n'est inventé. Les informations non fournies (mentions légales, horaires, e-mail…) ne sont pas affichées tant qu'elles ne sont pas renseignées dans l'administration.
