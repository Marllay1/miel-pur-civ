# Sécurité — mesures, tests et risques résiduels

Dernière campagne de tests : 2026-10-14 (itération 2 : paiements intégrés, SMTP, bilinguisme, Apache/XAMPP et Nginx).

## Résultats

| Suite | Commande | Résultat |
|---|---|---|
| Fonctionnelle + sécurité (SQLite ; PHP 8.3 et PHP 8.2) | `php tests/run.php` | 30 scénarios, 832 assertions, 0 échec |
| Fonctionnelle + sécurité (MariaDB 11.4 et MariaDB 10.4 de XAMPP) | `php tests/run.php --mysql` | 30 scénarios, 832 assertions, 0 échec |
| Boîte noire HTTP — Apache 2.4 (XAMPP) | `php tests/security-http.php http://miel.localhost` | 66 contrôles, 0 échec |
| Boîte noire HTTP — Nginx 1.30 | `php tests/security-http.php http://miel-nginx.localhost:8088` | 66 contrôles, 0 échec |
| Boîte noire HTTP — serveur intégré PHP | `php tests/security-http.php` | 66 contrôles, 0 échec |
| SMTP (TLS vérifié, identifiants masqués, injection d'en-tête) | `php tests/smtp-check.php --mailpit` | 15 contrôles, 0 échec |

## Couverture

| Domaine | Scénarios | Contrôles principaux |
|---|---|---|
| Injection SQL | 20 | 9 charges (UNION, OR 1=1, SLEEP, DROP, empilées) sur tous les filtres publics et d'administration, connexion, suivi, panier, promo ; tables intactes, aucune temporisation |
| XSS | 16, 21 | réfléchi (recherche, retour, suivi), stocké (avis, contact, devis, commande) affiché côté client et admin ; contenus éditables (liens `javascript:` et `//domaine` refusés) ; JSON-LD protégé contre `</script>` |
| CSRF | 11, 22, HTTP | jeton absent, erroné, issu d'une autre session ; déconnexion forcée impossible ; jeton renouvelé à la connexion |
| Sessions | 22, HTTP | fixation refusée (mode strict, régénération à la connexion), ancien identifiant inutilisable, cookies HttpOnly/SameSite/Secure, HSTS, invalidation des autres sessions après changement ou réinitialisation du mot de passe, compte désactivé coupé, session forgée rejetée, inactivité personnel 2 h |
| Authentification | 10, 25, HTTP | hachage, verrouillage 5 échecs/15 min par compte+IP, 20 par IP (pulvérisation), messages identiques compte existant/inexistant, réinitialisation à usage unique et neutre |
| Contrôle d'accès | 11, 12, 18, 23 | matrice des rôles (GET et POST), auto-promotion et dernier admin protégés, commande d'autrui (404), adresses d'autrui, mass assignment (rôle, e-mail, statut, état de paiement, user_id) |
| Téléversements | 13, 24 | SVG, GIF polyglotte, HTML déguisé, taille, erreurs, PHP caché dans un JPEG supprimé par ré-encodage WebP, nom aléatoire, suppression hors `/uploads` refusée, `.htaccess` |
| Logique métier | 04, 06, 07, 17, 26 | prix/total falsifiés, quantités invalides, clé d'idempotence forgée, double commande, changement de prix en cours, zone désactivée, promo plafonnée, paiement en ligne d'une commande à la livraison, stock jamais négatif (application + contrainte CHECK) |
| Paiement / webhooks | 08, 09, 26, 28 | signature HMAC (Wave, paiement simulé), horodatage, corps modifié, rejeu, montant, contenu > 64 Ko, secret trop court, paiement tardif ; notifications non signées (MTN, Orange) ou non concordantes (CinetPay) jamais crues : état vérifié auprès du prestataire ; vérification avant toute expiration ; aucun secret affiché en administration |
| Abus | 25 | limites : suivi (10/15 min), contact, avis, inscription, réinitialisation (3 e-mails/h), webhook (120/min) |
| Divulgation | 27, HTTP | aucune trace technique en 500 (journalisée côté serveur), pas de `X-Powered-By`, aucun message PHP, `.env`/journaux/sources/`.git` inaccessibles (17 chemins de traversée testés), méthodes TRACE/PUT/DELETE/PATCH refusées, en-têtes Host et X-Forwarded-Host ignorés pour les liens envoyés par e-mail, en-tête Server sans numéro de version |
| En-têtes | 15, HTTP | CSP stricte sans script inline, `frame-ancestors 'none'`, DENY, nosniff, Referrer-Policy, Permissions-Policy, no-store + noindex sur les zones privées |

## Défauts trouvés et corrigés pendant l'itération 2 (Apache, Nginx, paiements)

1. **Formulaire expiré transformé en « 500 Internal Server Error » sous Apache** : le code 419 (non standard) était remplacé par 500 par Apache → code standard 403 avec une page dédiée « Formulaire expiré ».
2. **Méthode TRACE acceptée par Apache** (200) → `TraceEnable Off` (documenté dans INSTALLATION.md et DEPLOIEMENT.md).
3. **Versions d'Apache, PHP et OpenSSL divulguées** par XAMPP (en-tête `Server` et pages d'erreur) → `ServerTokens Prod`, `ServerSignature Off` ; Nginx : `server_tokens off`. Nouveau contrôle automatique.
4. **Annulation possible d'une commande payée** : si la notification d'un prestataire se perdait, la tâche d'expiration annulait la commande sans vérifier le paiement → l'état est désormais demandé au prestataire avant toute annulation (et, s'il est injoignable, l'annulation attend 24 h puis est signalée « à contrôler »). La tâche planifiée vérifie aussi tous les paiements en cours.
5. **Liste de contrôle de mise en production trop optimiste** : les mentions légales vides n'étaient plus détectées → contrôle des réglages obligatoires (raison sociale, RCCM, directeur de la publication, hébergeur, e-mail de notification) et de la configuration SMTP.
6. Parcours : une zone payable uniquement en ligne était proposée alors qu'aucun paiement en ligne n'était actif (client bloqué à l'étape Paiement) → zone masquée tant qu'aucun moyen de paiement n'y est utilisable, et commande refusée côté serveur.

## Défauts trouvés et corrigés pendant la première campagne

1. **Version de PHP exposée** (`X-Powered-By: PHP/8.3.33`) → en-tête supprimé, `expose_php` désactivé.
2. **Sessions non invalidées après changement / réinitialisation du mot de passe** → empreinte HMAC du mot de passe en session, contrôlée à chaque requête.
3. **Jeton CSRF conservé à la connexion** → renouvelé.
4. **Liens `//domaine` acceptés dans les contenus éditables** → refusés (seuls `/chemin` et `https://` sont autorisés).
5. **Quantité non numérique convertie en 1** lors de l'ajout au panier → quantité strictement entière exigée.
6. **Erreur fatale affichée par le routeur du serveur de développement** sur un octet nul (chemin et pile d'appels visibles) → requête rejetée en 400 ; `display_errors` forcé à 0 hors mode débogage, quelle que soit la configuration de PHP.
7. Lors de la campagne précédente : POST anonyme sur une route d'administration → 403 ; liens produits masqués sans permission catalogue.

## Risques résiduels et recommandations

- **Énumération par l'inscription** : le formulaire d'inscription indique qu'un e-mail est déjà utilisé (choix d'ergonomie, limité à 5 inscriptions par heure et par IP).
- **Pas de double authentification** pour l'administration : recommandée pour les comptes administrateurs (TOTP) dans une version ultérieure.
- **Limitation par IP** : derrière un proxy/CDN, renseigner `TRUSTED_PROXY`, sinon toutes les requêtes partagent la même IP.
- **Pas de test de charge** ni d'audit par un tiers ; recommandé avant l'ouverture, de même qu'un scanner (OWASP ZAP) sur l'environnement de préproduction.
- Configuration serveur de production (TLS, `php.ini`, droits des fichiers, pare-feu) à vérifier lors du déploiement (voir DEPLOIEMENT.md).
- Aucune dépendance tierce (pas de Composer ni de npm à l'exécution) : la surface est limitée au code du projet et à PHP/MariaDB, à maintenir à jour.
- **Paiements en ligne non éprouvés en conditions réelles** : les quatre intégrations n'ont été testées que contre un simulateur local. Avant ouverture, une transaction réelle de faible montant par moyen de paiement est indispensable (voir INTEGRATIONS.md). Les notifications MTN et Orange ne sont pas signées : la sécurité repose sur la vérification serveur à serveur, déjà systématique.
- **Secrets des prestataires et SMTP** : uniquement dans `.env` (droits 600, jamais versionné) ; ils ne sont jamais affichés (page Intégrations : seulement « renseigné ✔ / ✘ ») ni journalisés.
- **XAMPP** est un environnement de développement (MySQL accessible sur le réseau avec un `root` sans mot de passe par défaut) : ne jamais l'utiliser pour héberger le site en production.
