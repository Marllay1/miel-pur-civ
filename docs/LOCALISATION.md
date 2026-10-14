# Localisation de livraison à la commande (version actuelle)

## Fonctionnement

À l'étape « Livraison » du formulaire de commande (zones avec livraison à domicile ; rien pour un retrait) :

- **Méthode A — saisie manuelle** (inchangée, complétée) : pays (rappel, zones desservies), ville, commune ou quartier, adresse, repère, et nouveau champ **« Instructions pour trouver votre domicile »**. Le téléphone reste demandé à la première étape.
- **Méthode B — position GPS, facultative** : bouton **« Utiliser ma position GPS »**, avec une phrase expliquant pourquoi. La position n'est demandée au navigateur **qu'au clic**, une seule fois (aucun suivi continu). Si elle est obtenue :
  - une carte s'affiche avec un marqueur **déplaçable** (glisser, ou toucher la carte) pour corriger la position ;
  - les coordonnées et la précision annoncée par l'appareil sont affichées ;
  - une adresse estimée s'affiche seulement si un service de géocodage est configuré (désactivé par défaut) ;
  - « Retirer la position » revient à la saisie manuelle.
- La position **ne remplace pas** l'adresse écrite : tous les champs restent disponibles. Seule l'adresse devient facultative quand une position valide est jointe ; ville et commune restent demandées.
- **Échecs** (message clair, la commande reste possible par saisie manuelle) : autorisation refusée, position indisponible, délai dépassé (15 s), navigateur incompatible, connexion non sécurisée, carte non chargée (les coordonnées restent enregistrées), position hors zone desservie, coordonnées invalides (refusées par le serveur avec un message).

## Données enregistrées (migration `008_delivery_location.sql`, table `orders`)

| Colonne | Contenu |
|---|---|
| `shipping_city`, `shipping_district`, `shipping_address`, `shipping_landmark` | adresse saisie (existantes ; l'adresse est vide, et non inventée, si le client ne l'a pas écrite) |
| `delivery_instructions` | instructions pour trouver le domicile |
| `location_method` | `manual` ou `gps` |
| `delivery_lat`, `delivery_lng` | coordonnées validées par le client (6 décimales, environ 10 cm) |
| `delivery_accuracy_m` | précision annoncée par l'appareil, en mètres |
| `location_adjusted` | 1 si le client a déplacé le marqueur |
| `geocoded_address` | adresse estimée par le service de géocodage du serveur (jamais une valeur envoyée par le navigateur) |

Validation par le serveur (`App\Services\DeliveryLocation::validate`) : nombres décimaux, latitude et longitude dans leurs bornes, position dans la zone desservie (Côte d'Ivoire avec une marge : latitude 4 à 11, longitude −9 à −2 ; à élargir si des zones hors du pays sont créées). Un seul point est conservé par commande ; aucune position successive.

## Administration

Fiche commande → *Livraison* : « Adresse saisie par le client » (avec instructions), puis, si une position existe, un bloc distinct **« Position GPS transmise par le client »** : coordonnées, précision, marqueur déplacé ou non, adresse estimée (indiquée comme telle), liens **Ouvrir sur OpenStreetMap** et **Ouvrir dans Google Maps**. Sans position : « saisie manuelle ». Le récapitulatif imprimable (livreur) reprend repère, instructions et coordonnées. Accès : équipe (permission commandes) et client propriétaire de la commande, comme le reste de la commande. Une position GPS n'est ni une preuve d'identité ni une preuve de réception.

## Carte et services externes : choix et conditions

| Besoin | Choix | Conditions et limites |
|---|---|---|
| Carte interactive | **Leaflet 1.9.4** (licence BSD-2), fichiers servis par le site (`public/assets/vendor/leaflet`), chargée uniquement sur la page de commande | gratuit, sans clé |
| Fonds de carte | **OpenStreetMap** (`tile.openstreetmap.org`) par défaut | gratuit, financé par des dons ; [politique d'utilisation des tuiles](https://operations.osmfoundation.org/policies/tiles/) : attribution obligatoire (affichée), usage modéré, aucune garantie de service. **Pour un trafic important, passer à un fournisseur dédié** (offres gratuites limitées puis payantes, par exemple MapTiler, Stadia Maps, Thunderforest) en changeant `MAP_TILE_URL` (et `MAP_TILE_ATTRIBUTION`) ; aucune clé ne doit être secrète côté navigateur (les clés de tuiles sont publiques par nature : restreindre leur domaine chez le fournisseur) |
| Adresse estimée (géocodage inverse) | **désactivée par défaut** ; option **Nominatim** (OpenStreetMap) | [politique Nominatim](https://operations.osmfoundation.org/policies/nominatim/) : 1 requête par seconde au maximum, application identifiée (adresse de contact `GEOCODER_CONTACT_EMAIL`, obligatoire pour l'activer), attribution, pas d'usage intensif. Les requêtes partent **du serveur** (jamais du navigateur), limitées à 1 par seconde pour tout le site et 20 par 10 minutes par visiteur, résultat mémorisé |

Les coordonnées ne sont transmises à un service externe que si le géocodage est activé (adresse estimée) ; les tuiles de carte, elles, sont chargées par le navigateur du client chez le fournisseur de tuiles (comme toute carte en ligne) : la phrase d'explication et la politique de confidentialité doivent le mentionner. Si la carte ne peut pas être chargée, les coordonnées restent enregistrées et la commande continue.

## Configuration

| Où | Paramètre | Défaut |
|---|---|---|
| Paramètres du site → Livraison et commandes | Proposer « Utiliser ma position GPS » | activé |
| id. | Afficher une carte (OpenStreetMap) | activé |
| id. | Adresse estimée (géocodage) : Aucun / Nominatim | Aucun |
| `.env` | `MAP_TILE_URL`, `MAP_TILE_ATTRIBUTION` | OpenStreetMap |
| `.env` | `GEOCODER_NOMINATIM_URL`, `GEOCODER_CONTACT_EMAIL` | contact vide (géocodage inactif) |

En-têtes de sécurité : `Permissions-Policy: geolocation=(self)` (auparavant `geolocation=()`, qui bloquait toute géolocalisation) ; la politique de sécurité du contenu autorise l'hôte des tuiles en image uniquement quand la carte est activée.

**Avant la mise en production** : la géolocalisation des navigateurs exige une connexion **HTTPS** (seuls `localhost` et les adresses de développement locales en sont dispensés) ; vérifier sur le domaine final, sur Android (Chrome) et iPhone (Safari). Mettre à jour la politique de confidentialité (position facultative, finalité : livraison, destinataires : équipe et livreur, fournisseur de tuiles de carte, durée de conservation alignée sur celle des commandes) en lien avec la loi n° 2013-450 et l'ARTCI.

## Tests

Scénario 37 : formulaire (deux méthodes, explication, en-têtes), saisie manuelle (adresse obligatoire sans position, instructions longues conservées intégralement), position GPS déplacée sans adresse (coordonnées, précision, marqueur déplacé, adresse non inventée, adresse estimée forgée par le navigateur ignorée), GPS avec adresse, coordonnées invalides et hors zone refusées sans créer de commande, retrait sur place, fonction et carte désactivées, géocodage (désactivé : 404 ; activé : appel serveur identifié avec un service simulé, refus hors zone, adresse estimée jointe sans remplacer l'adresse saisie), affichage en administration, sur la page client et le récapitulatif, droits d'accès. Vérification dans un navigateur (position simulée) : carte OpenStreetMap chargée, marqueur, coordonnées, adresse rendue facultative, aucune erreur de script.
