-- Localisation de livraison transmise par le client à la commande (saisie manuelle et/ou position GPS facultative).
-- Les coordonnées sont celles validées par le client (après déplacement éventuel du marqueur) ; aucune position
-- successive n'est conservée. Le futur suivi des livraisons disposera de ses propres tables (voir docs/SUIVI-LIVRAISONS.md).

-- Instructions complémentaires pour trouver le domicile (distinctes de la note de commande).
ALTER TABLE orders ADD COLUMN delivery_instructions TEXT NULL;
-- manual : adresse saisie seulement ; gps : position GPS validée (l'adresse écrite reste conservée telle quelle).
ALTER TABLE orders ADD COLUMN location_method VARCHAR(10) NOT NULL DEFAULT 'manual';
ALTER TABLE orders ADD COLUMN delivery_lat DECIMAL(9,6) NULL;
ALTER TABLE orders ADD COLUMN delivery_lng DECIMAL(9,6) NULL;
-- Précision annoncée par l'appareil (mètres) ; position déplacée à la main par le client sur la carte.
ALTER TABLE orders ADD COLUMN delivery_accuracy_m INT NULL;
ALTER TABLE orders ADD COLUMN location_adjusted SMALLINT NOT NULL DEFAULT 0;
-- Adresse estimée par un service de géocodage, seulement si un tel service est configuré (sinon vide).
ALTER TABLE orders ADD COLUMN geocoded_address VARCHAR(255) NULL;
