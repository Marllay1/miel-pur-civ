# Tests

```bash
php tests/run.php
```

```bash
php tests/run.php --mysql
```

Option `--filter=09` pour un seul scénario. Les tests passent par le vrai noyau HTTP (`App::handle` : routage, middlewares, CSRF, contrôleurs, vues) avec un client simulé (cookies + session), sur une base remise à zéro pour chaque scénario.

## Résultat de la dernière exécution (2026-10-14)

- SQLite en mémoire : **27 scénarios réussis, 0 échec, 723 assertions** (8 s).
- MariaDB 11.4.8 (`miel_test`) : **27 scénarios réussis, 0 échec, 723 assertions** (28 s).
- Boîte noire HTTP contre le serveur réel (`php tests/security-http.php`) : **61 contrôles, 0 échec**.

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
| 11 | Protection admin | anonyme, client, CSRF absent/erroné (419), inactivité 2 h, connexion journalisée |
| 12 | Permissions | matrice par rôle (GET et POST), menu filtré, auto-rétrogradation impossible |
| 13 | CRUD produit | création, slug, variante, SKU unique, prix, suppression logique avec commandes intactes, audit, image piégée refusée, ré-encodage WebP |
| 14 | Statuts | transitions interdites, chaîne complète, encaissement COD, remboursement, annulation (stock + promo, sans double restitution), note interne invisible |
| 15 | Rendu | viewport, lang, lien d'évitement, aucun script/style/gestionnaire inline, un h1, alt, CSP, nosniff, DENY, sitemap, robots |
| 16 | Formulaires | erreurs accessibles, saisie conservée, champ piège, délai minimal, horodatage forgé, XSS échappé, consentement newsletter, redirection externe neutralisée |
| 17 | Stock | deux clients pour les dernières unités, jamais de stock négatif (applicatif et contrainte CHECK), verrou optimiste admin, invariant mouvements = stock |
| 18 | Confidentialité | commande inaccessible sans jeton/à un autre client, suivi sans adresse, no-store/noindex, clients réservés, CSV anti-formule, aucune colonne bancaire |
| 19 | Transverse | 405, 404, redirection ouverte, échappement, mise en forme sûre |

## Limites

- Le **rendu visuel** mobile/bureau est vérifié structurellement par les tests (scénario 15) et **manuellement** dans un navigateur réel (375 px et bureau) ; pas de test de régression visuelle automatisé.
- Le JavaScript (étapes de commande, AJAX) a été vérifié manuellement dans le navigateur ; il n'a pas de tests automatisés propres. Le site fonctionne sans JavaScript (les tests HTTP le prouvent pour les parcours).
- Pas de test de charge ni de concurrence réelle multi-processus : la concurrence est couverte par les mises à jour conditionnelles (`stock >= ?`, verrous optimistes, contraintes UNIQUE), testées séquentiellement.
