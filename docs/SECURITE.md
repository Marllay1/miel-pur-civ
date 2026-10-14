# Sécurité — mesures, tests et risques résiduels

Dernière campagne de tests : 2026-10-14.

## Résultats

| Suite | Commande | Résultat |
|---|---|---|
| Fonctionnelle + sécurité (SQLite) | `php tests/run.php` | 27 scénarios, 723 assertions, 0 échec |
| Fonctionnelle + sécurité (MariaDB 11.4) | `php tests/run.php --mysql` | 27 scénarios, 723 assertions, 0 échec |
| Boîte noire HTTP (serveur réel) | `php tests/security-http.php` | 61 contrôles, 0 échec |

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
| Paiement / webhooks | 08, 09, 26 | signature HMAC, horodatage, corps modifié, rejeu, montant, prestataire non intégré, contenu > 64 Ko, secret trop court, paiement tardif |
| Abus | 25 | limites : suivi (10/15 min), contact, avis, inscription, réinitialisation (3 e-mails/h), webhook (120/min) |
| Divulgation | 27, HTTP | aucune trace technique en 500 (journalisée côté serveur), pas de `X-Powered-By`, aucun message PHP, `.env`/journaux/sources/`.git` inaccessibles (17 chemins de traversée testés), méthodes TRACE/PUT/DELETE/PATCH refusées, en-tête Host ignoré pour les liens envoyés par e-mail |
| En-têtes | 15, HTTP | CSP stricte sans script inline, `frame-ancestors 'none'`, DENY, nosniff, Referrer-Policy, Permissions-Policy, no-store + noindex sur les zones privées |

## Défauts trouvés et corrigés pendant cette campagne

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
