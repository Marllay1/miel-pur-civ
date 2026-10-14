# Guide d'administration

Accès : `/admin/connexion`. Session fermée après **2 heures d'inactivité**. Chaque action sensible est inscrite au **journal d'audit**.

## Rôles

| Rôle | Accès |
|---|---|
| Administrateur | Tout |
| Gestionnaire catalogue | Produits, catégories, stock, codes promo, tableau de bord |
| Gestionnaire commandes | Commandes, devis, clients, tableau de bord |

Les contenus (accueil, pages, FAQ, avis, messages, newsletter), les réglages et l'équipe sont réservés à l'administrateur. Les droits sont vérifiés par le serveur sur chaque page et chaque action, pas seulement dans le menu.

## Traiter une commande

1. *Commandes* → onglet **En attente de confirmation** (paiement à la livraison).
2. Ouvrir la commande, **appeler le client** au numéro affiché pour confirmer adresse et créneau.
3. *Changer le statut* → **Confirmer / mettre en préparation**. Le client reçoit un e-mail.
4. Renseigner *Livreur / N° de suivi* si utile, puis **Marquer comme expédiée**, enfin **Marquer comme livrée** (pour un paiement à la livraison, la commande passe automatiquement en « payée »).
5. Annulation : **Annuler la commande** restitue automatiquement le stock et l'utilisation du code promo. Remboursement : possible uniquement si la commande a été payée.

Les transitions impossibles (par exemple « livrée » sans passer par « expédiée ») sont refusées. Les **notes internes** ne sont jamais visibles du client ; le *commentaire* de changement de statut l'est. L'export CSV reprend les filtres affichés.

## Catalogue

- **Produit** : nom, résumé, description (mise en forme simple : ligne vide = paragraphe, `## ` titre, `- ` liste, `**gras**`), origine, utilisations, conservation, SEO. Case *Produit de démonstration* = badge « Démo ».
- **Variantes** : chaque format (250 g, 500 g, 1 kg, seau…) a sa référence (SKU), son prix en FCFA entiers et son stock. Un produit sans variante active n'apparaît pas sur le site.
- **Images** : JPEG, PNG ou WebP, 5 Mo maximum, ré-encodées en WebP ; le *texte alternatif* (description de l'image) est obligatoire.
- **Suppression** : le produit est archivé ; les commandes passées conservent nom et prix d'origine.
- **Stock** : page *Stock* → nouvelle quantité + motif (inventaire, réception, casse). Toutes les variations sont historisées. Si une vente a lieu pendant la saisie, l'ajustement est refusé pour éviter d'écraser la vente.
- **Codes promo** : pourcentage, montant fixe ou livraison offerte ; panier minimum, dates, nombre maximal d'utilisations.

## Contenus

- **Pages et légal** : modifier le texte des pages. Cocher *Texte validé juridiquement* uniquement après relecture par un juriste ; jusque-là, un avertissement « document provisoire » est affiché.
- **Avis** : publier ou refuser. Ne jamais publier d'avis inventé ni modifier le texte d'un client.
- **Accueil** : titres et textes ; conserver les marqueurs `[À COMPLÉTER]` tant que l'information n'est pas vérifiée (ils sont surlignés sur le site).

## Réglages

Coordonnées, mentions légales, réseaux sociaux, WhatsApp, paiement à la livraison, paiement simulé, montant minimum, délai de paiement, seuil de stock faible, e-mail de notification des commandes, mesure d'audience. **Zones de livraison** : frais, seuil de gratuité, délai, paiement à la livraison autorisé, retrait.

## Intégrations

La page *Intégrations* indique honnêtement ce qui fonctionne et ce qui reste à configurer, et présente la **liste de contrôle avant mise en production**.
