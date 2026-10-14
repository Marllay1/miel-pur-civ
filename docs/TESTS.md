# Tests

```bash
php tests/run.php
```

```bash
php tests/run.php --mysql
```

Option `--filter=09` pour un seul scénario. Les tests passent par le vrai noyau HTTP (`App::handle` : routage, middlewares, CSRF, contrôleurs, vues) avec un client simulé (cookies + session), sur une base remise à zéro pour chaque scénario.

## Résultat de la dernière exécution (2026-10-14)

| Environnement | Suite | Résultat |
|---|---|---|
| PHP 8.3.33, SQLite en mémoire | `php tests/run.php` | **31 scénarios, 1 026 assertions, 0 échec** |
| PHP 8.3.33, MariaDB 11.4.8 | `php tests/run.php --mysql` | **31 scénarios, 1 026 assertions, 0 échec** |
| XAMPP 8.2.12 : PHP 8.2.12, SQLite | `php tests/run.php` | **31 scénarios, 1 026 assertions, 0 échec** |
| XAMPP 8.2.12 : PHP 8.2.12, MariaDB 10.4.32 | `php tests/run.php --mysql` | **31 scénarios, 1 026 assertions, 0 échec** |
| Apache 2.4.58 (XAMPP, hôte virtuel `miel.localhost`) | `php tests/security-http.php http://miel.localhost` | **66 contrôles, 0 échec** |
| Nginx 1.30.5 + PHP 8.2 FastCGI (bloc server de DEPLOIEMENT.md, servi en HTTP local ; hôte inconnu → 444) | `php tests/security-http.php http://miel-nginx.localhost:8088` | **66 contrôles, 0 échec** |
| Serveur intégré PHP 8.3 | `php tests/security-http.php` | **66 contrôles, 0 échec** |
| SMTP contre Mailpit 1.31.5 (clair + STARTTLS/authentification) | `php tests/smtp-check.php --mailpit` | **15 contrôles, 0 échec** |
| Traductions | `php bin/check-translations.php` | 554 clés présentes en français et en anglais |

Vérifications manuelles dans un navigateur réel (Chromium) : sous Apache/XAMPP, commande complète (panier → coordonnées → livraison → paiement à la livraison → confirmation) avec e-mail de confirmation reçu en SMTP (Mailpit), zones de livraison sur la version anglaise du tunnel, administration (connexion, Intégrations, fiche produit avec traduction) ; rendu 375 px et bureau, thèmes clair et sombre.

Les scénarios 20 à 27 et la suite HTTP sont des tests de sécurité dédiés : voir [SECURITE.md](SECURITE.md).

| # | Scénario | Points vérifiés |
|---|---|---|
| 01 | Catalogue | liste, filtres catégorie/format/prix/stock, tri, recherche (caractères LIKE échappés), pagination non indexée, produit masqué exclu |
| 02 | Fiche produit | prix par variante, rupture signalée, JSON-LD sans note inventée, Open Graph, canonique, 404 |
| 03 | Panier | ajout (formulaire et AJAX), modification, retrait, persistance par cookie, code promo (minimum), fusion à la connexion |
| 04 | Refus de stock | quantité > stock, rupture, quantité nulle, baisse de stock après ajout |
| 05 | Commande valide | statut, totaux, décrément de stock, instantané, mouvement, historique, panier vidé, e-mail, jeton haché, retrait sans adresse |
| 06 | Prix falsifié | total attendu falsifié refusé (409, aucune commande, stock intact), champs de prix injectés ignorés, moyen de paiement non autorisé refusé |
| 07 | Double commande | seconde soumission → même commande, stock décrémenté une fois, contrainte UNIQUE |
| 08 | Paiement | en attente, échec, nouvelle tentative, réussite (via la page SIMULÉE et le traitement signé), expiration, désactivation |
| 09 | Webhook | signature invalide/absente, horodatage expiré, corps modifié, montant incohérent (audit), rejeu, déjà traité, prestataire non intégré, paiement inconnu, paiement tardif sur commande annulée |
| 10 | Compte | inscription, doublon, connexion, verrouillage après 5 échecs, réinitialisation (réponse neutre, jeton haché, usage unique) |
| 11 | Protection admin | anonyme, client, CSRF absent/erroné (403 « formulaire expiré »), inactivité 2 h, connexion journalisée |
| 12 | Permissions | matrice par rôle (GET et POST), menu filtré, auto-rétrogradation impossible |
| 13 | CRUD produit | création, slug, variante, SKU unique, prix, suppression logique avec commandes intactes, audit, image piégée refusée, ré-encodage WebP |
| 14 | Statuts | transitions interdites, chaîne complète, encaissement COD, remboursement, annulation (stock + promo, sans double restitution), note interne invisible |
| 15 | Rendu | viewport, lang, lien d'évitement, aucun script/style/gestionnaire inline, un h1, alt, CSP, nosniff, DENY, sitemap, robots |
| 16 | Formulaires | erreurs accessibles, saisie conservée, champ piège, délai minimal, horodatage forgé, XSS échappé, consentement newsletter, redirection externe neutralisée |
| 17 | Stock | deux clients pour les dernières unités, jamais de stock négatif (applicatif et contrainte CHECK), verrou optimiste admin, invariant mouvements = stock |
| 18 | Confidentialité | commande inaccessible sans jeton/à un autre client, suivi sans adresse, no-store/noindex, clients réservés, CSV anti-formule, aucune colonne bancaire |
| 19 | Transverse | 405, 404, redirection ouverte, échappement, mise en forme sûre |
| 20–27 | Sécurité | injection SQL, XSS, sessions/CSRF, contrôle d'accès, téléversements, force brute, logique métier, divulgation — détail dans SECURITE.md |
| 28 | Paiements intégrés | Wave, Orange Money, MTN MoMo, carte (CinetPay) contre simulateurs : redirection, notification signée ou vérifiée, rejeu, montant, échec, prestataire injoignable, vérification avant expiration (pas d'annulation à l'aveugle), page Intégrations sans secret |
| 29 | Bilinguisme et thème | /en (contenus, e-mails, validation, sitemap hreflang), administration en français, thème clair/sombre sans JavaScript, valeurs de cookie invalides ignorées |
| 31 | Séparation des espaces, logo, photos | anonyme, client, administrateur, gestionnaire catalogue : liens « Administration » visibles uniquement pour l'équipe, 403 pour un client (URL directe, JSON, POST, /en/admin), redirections après connexion/déconnexion selon le rôle, absence de boucle, retour d'administration limité à /admin, cache privé ; favicons présents, aucun logo inventé ; chaque image affichée existe, illustrations signalées, crédits complets |
| 30 | Traductions en administration | saisie EN produit, format, FAQ, zone, page, accueil ; champ non prévu ignoré, texte échappé, champ vide = français, audit, suppression en cascade |

## Limites

- Le **rendu visuel** mobile/bureau est vérifié structurellement par les tests (scénario 15) et **manuellement** dans un navigateur réel (375 px et bureau) ; pas de test de régression visuelle automatisé.
- Le JavaScript (étapes de commande, AJAX) a été vérifié manuellement dans le navigateur ; il n'a pas de tests automatisés propres. Le site fonctionne sans JavaScript (les tests HTTP le prouvent pour les parcours).
- Pas de test de charge ni de concurrence réelle multi-processus : la concurrence est couverte par les mises à jour conditionnelles (`stock >= ?`, verrous optimistes, contraintes UNIQUE), testées séquentiellement.
- **Paiements en ligne** : testés uniquement contre le simulateur local `tests/mock-providers/server.php` (127.0.0.1:8099), jamais contre les bacs à sable ni la production de Wave, Orange, MTN ou CinetPay (clés marchandes nécessaires). Voir INTEGRATIONS.md.
- **E-mails** : l'envoi SMTP est testé contre un serveur Mailpit local (en clair, puis STARTTLS avec authentification et certificat vérifié). L'envoi vers une vraie boîte de réception reste à faire avec les identifiants SMTP de la boutique : `php tests/smtp-check.php --to=…`.
- `tests/security-http.php` crée un compte jetable et remet à zéro les limitations de fréquence de la base configurée : réservé au développement (il refuse de s'exécuter si `APP_ENV=production`) et à lancer avec `MAIL_DRIVER=log`.
