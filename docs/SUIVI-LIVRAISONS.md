# Proposition : suivi GPS des livraisons et confirmation par QR code (version future)

Document de proposition. **Rien de ce qui suit n'est développé** : seule la localisation à la commande l'est (voir [LOCALISATION.md](LOCALISATION.md)).

## 1. Ce qui existe déjà et sur quoi s'appuyer

- Commandes avec statuts (`preparing` → `shipped` → `delivered`), historique horodaté (`order_status_history`), table `shipments` (transporteur, n° de suivi, dates d'expédition et de livraison), encaissement à la livraison confirmé par l'équipe (journal `order_transactions`).
- Position de livraison validée par le client (`orders.delivery_lat/lng`), instructions et repère : destination de la mission.
- Rôles et permissions, audit, jetons secrets d'accès aux commandes (hachés), limitation de fréquence : réutilisables pour l'authentification des livreurs et la vérification des QR codes.

## 2. Modèle de données proposé

| Table | Rôle | Remarques |
|---|---|---|
| `couriers` | livreur (lié à un compte `users` avec un rôle « Livreur » aux droits limités), téléphone, actif | rôle distinct de l'administrateur |
| `delivery_assignments` | affectation d'une commande à un livreur : statut (assignée, en route, livrée, échec, annulée), dates de début et de fin de mission | une affectation active par commande |
| `delivery_events` | événements horodatés : départ, arrivée, tentative échouée (motif), livraison confirmée, incident | historique de livraison, distinct de `order_status_history` |
| `courier_positions` | positions du livreur **pendant une mission active uniquement** (affectation, horodatage, lat/lng, précision) | stockage séparé des commandes, **purgé automatiquement** (ex. 30 jours après la fin de mission, durée à valider) ; jamais de positions dans `orders` |
| `delivery_confirmations` | jeton de confirmation (haché), date de création, d'expiration, d'utilisation, livreur ayant validé | usage unique |

## 3. Composants et flux

**Interface livreur** (connexion sécurisée, rôle « Livreur »)
1. Liste des commandes qui lui sont affectées ; détail : adresse, repère, instructions, téléphone du client, montant à encaisser.
2. « Démarrer la mission » : statut « en route », envoi de la position à intervalle régulier (ex. 15 à 30 s) **seulement pendant la mission**, tant que l'appareil le permet.
3. Navigation vers l'adresse : lien vers l'application de cartographie du téléphone (coordonnées du client).
4. À l'arrivée : scan du QR code présenté par le client → vérification serveur → commande « Livrée », encaissement à confirmer s'il s'agit d'un paiement à la livraison.
5. Fin de mission : arrêt de l'envoi de position.

**Interface client** (page de suivi de commande existante)
- Statut et progression ; carte avec la position du livreur **seulement quand la mission est active** ; plus aucune position après livraison ou annulation.
- QR code de confirmation affiché sur la page de la commande (accès par compte ou lien secret), présenté au livreur à la remise.

**Confirmation par QR code**
- Jeton aléatoire d'au moins 128 bits, propre à la commande, stocké haché, valable une seule fois, avec expiration.
- Le QR code ne contient pas une URL publique de confirmation : il n'est accepté que **scanné depuis l'interface d'un livreur authentifié, affecté à cette commande**, alors que la commande est « expédiée » ou « en route ».
- Le serveur vérifie le jeton, l'affectation et le statut, puis enregistre la date et le livreur, passe la commande à « Livrée » ; une seconde validation est refusée (verrou sur la commande).
- Le client ne peut pas confirmer lui-même la livraison depuis son navigateur.
- **Procédure de secours** (client sans téléphone, QR illisible) : code court à 6 chiffres lu oralement, à usage unique, limité en tentatives, ou validation manuelle par un administrateur avec motif obligatoire, tracée dans l'audit.

## 4. Choix technologique : site web, PWA ou application

| Critère | Site web responsive | PWA (site installable) | Application mobile (multiplateforme ou native) |
|---|---|---|---|
| Coût initial | le plus faible (base existante) | faible : manifeste et service worker sur la base existante | élevé : nouveau projet, deux plateformes |
| Coûts récurrents | hébergement | hébergement | comptes développeur (Apple : 99 USD par an ; Google Play : 25 USD une fois), publication des mises à jour, maintenance des versions de systèmes |
| Maintenance | un seul code | un seul code | code mobile séparé et API à maintenir |
| Position pendant l'utilisation (écran allumé, page ouverte) | oui (API de géolocalisation, HTTPS) | oui | oui |
| **Position en arrière-plan** (écran éteint, autre application ouverte) | **non** : les navigateurs suspendent la géolocalisation quand la page n'est pas visible | **non sur iOS** ; sur Android, pas de géolocalisation fiable en arrière-plan non plus | **oui**, avec les autorisations du système (Android : service au premier plan avec notification visible ; iOS : autorisation « Toujours » ou mode arrière-plan, justifiés lors de la validation de l'App Store) |
| Scan de QR code | possible par la caméra et une bibliothèque JavaScript (l'API native de lecture de codes-barres des navigateurs n'est pas disponible partout, notamment sur Safari) | idem | intégré, fiable |
| Hors ligne | non | partiel (pages et données mises en cache ; synchronisation en arrière-plan limitée sur iOS) | oui (file d'attente locale des événements) |
| Expérience | correcte | proche d'une application (icône, plein écran) | la meilleure |

**Recommandation** : interface **client** en web (existante, aucune installation demandée). Interface **livreur** :
- si l'entreprise accepte que le livreur garde l'écran allumé, page ouverte, pendant la mission (support de téléphone en moto, par exemple), une **PWA** suffit et coûte peu ;
- si le suivi doit continuer écran éteint ou application en arrière-plan, il faut une **application mobile** (Android d'abord, la plupart des livreurs l'utilisant ; à confirmer). Certaines bibliothèques de géolocalisation en arrière-plan pour les frameworks multiplateformes sont payantes en production : à vérifier au moment du choix.

À tester sur les téléphones réellement utilisés par les livreurs avant de décider (économie de batterie des constructeurs qui coupent les applications en arrière-plan, réseau mobile instable).

## 5. Contraintes, coûts récurrents et risques

- **Vie privée** : position du livreur = donnée personnelle (salarié ou prestataire) ; informer, limiter à la mission, purger, déclarer auprès de l'ARTCI si nécessaire.
- **Batterie et données mobiles** du livreur : intervalle d'envoi raisonnable, envoi groupé hors réseau.
- **Réseau instable** : file d'attente locale et synchronisation ; le statut « Livrée » n'est appliqué qu'après la vérification par le serveur.
- **Fonds de carte** côté client : trafic plus élevé que la seule commande → prévoir un fournisseur de tuiles dédié (abonnement) plutôt que les serveurs OpenStreetMap.
- **Fraude** : QR code photographié ou transmis → usage unique, affectation au livreur, statut compatible, expiration ; position du livreur au moment du scan enregistrée à titre indicatif.
- **Coûts** : fournisseur de tuiles au-delà de l'offre gratuite, comptes développeur et maintenance si application, éventuelle licence de géolocalisation en arrière-plan, envoi de SMS si le code de secours est envoyé par SMS.

## 6. Découpage proposé

1. **Affectation et statuts** : rôle « Livreur », tables `couriers`, `delivery_assignments`, `delivery_events` ; affectation dans l'administration ; liste des missions du livreur (web) ; statuts « en route », « échec de livraison » ; historique visible du client.
2. **Confirmation par QR code** : jeton à usage unique, affichage client, scan par le livreur authentifié, passage à « Livrée », procédure de secours, tests de sécurité (rejeu, mauvais livreur, mauvais statut, double validation).
3. **Suivi de position pendant la mission** : envoi de position par le livreur (page ouverte), carte côté client pendant la mission, purge automatique.
4. **Selon le besoin** : PWA installable (hors ligne partiel), puis application Android si le suivi en arrière-plan est indispensable.

Chaque étape est utilisable seule et testée avant la suivante.
