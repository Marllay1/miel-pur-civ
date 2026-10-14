-- Configuration du site administrable : moyens de paiement, carrousel de l'accueil, devises d'affichage.

-- Moyens de paiement (remplacent les réglages cod_enabled, wave_enabled, orange_money_enabled, mtn_momo_enabled, card_enabled).
--  kind : cod (paiement à la livraison), gateway (prestataire intégré : wave, orange_money, mtn_momo, card),
--         manual (paiement manuel : le client règle hors du site selon les instructions, l'équipe confirme la réception).
CREATE TABLE payment_methods (
  id {PK},
  code VARCHAR(40) NOT NULL UNIQUE,
  kind VARCHAR(20) NOT NULL,
  name VARCHAR(80) NOT NULL,
  name_en VARCHAR(80) NULL,
  description VARCHAR(255) NULL,
  description_en VARCHAR(255) NULL,
  logo_paths TEXT NULL,
  show_logo INT NOT NULL DEFAULT 1,
  is_enabled INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  merchant_number VARCHAR(60) NULL,
  receiver_number VARCHAR(60) NULL,
  beneficiary VARCHAR(120) NULL,
  instructions TEXT NULL,
  instructions_en TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

INSERT INTO payment_methods (code, kind, name, name_en, description, description_en, logo_paths, show_logo, is_enabled, sort_order, created_at, updated_at) VALUES
('cod', 'cod', 'Paiement à la livraison', 'Cash on delivery', NULL, NULL, NULL, 1,
  CASE WHEN COALESCE((SELECT value FROM settings WHERE name = 'cod_enabled'), '1') = '1' THEN 1 ELSE 0 END, 0, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
INSERT INTO payment_methods (code, kind, name, name_en, description, description_en, logo_paths, show_logo, is_enabled, sort_order, created_at, updated_at) VALUES
('wave', 'gateway', 'Wave', 'Wave', NULL, NULL, '/assets/img/payments/wave.png', 1,
  CASE WHEN COALESCE((SELECT value FROM settings WHERE name = 'wave_enabled'), '1') = '1' THEN 1 ELSE 0 END, 1, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
INSERT INTO payment_methods (code, kind, name, name_en, description, description_en, logo_paths, show_logo, is_enabled, sort_order, created_at, updated_at) VALUES
('orange_money', 'gateway', 'Orange Money', 'Orange Money', NULL, NULL, '/assets/img/payments/orange-money.svg', 1,
  CASE WHEN COALESCE((SELECT value FROM settings WHERE name = 'orange_money_enabled'), '1') = '1' THEN 1 ELSE 0 END, 2, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
INSERT INTO payment_methods (code, kind, name, name_en, description, description_en, logo_paths, show_logo, is_enabled, sort_order, created_at, updated_at) VALUES
('mtn_momo', 'gateway', 'MTN Mobile Money', 'MTN Mobile Money', NULL, NULL, '/assets/img/payments/mtn-momo.png', 1,
  CASE WHEN COALESCE((SELECT value FROM settings WHERE name = 'mtn_momo_enabled'), '1') = '1' THEN 1 ELSE 0 END, 3, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
INSERT INTO payment_methods (code, kind, name, name_en, description, description_en, logo_paths, show_logo, is_enabled, sort_order, created_at, updated_at) VALUES
('card', 'gateway', 'Carte bancaire', 'Bank card', NULL, NULL, '/assets/img/payments/visa.svg
/assets/img/payments/mastercard.svg', 1,
  CASE WHEN COALESCE((SELECT value FROM settings WHERE name = 'card_enabled'), '1') = '1' THEN 1 ELSE 0 END, 4, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
INSERT INTO payment_methods (code, kind, name, name_en, description, description_en, logo_paths, show_logo, is_enabled, sort_order, created_at, updated_at) VALUES
('moov_money', 'manual', 'Moov Money', 'Moov Money', NULL, NULL, NULL, 1, 0, 5, '2026-10-14 00:00:00', '2026-10-14 00:00:00');

DELETE FROM settings WHERE name IN ('cod_enabled', 'wave_enabled', 'orange_money_enabled', 'mtn_momo_enabled', 'card_enabled');

-- Carrousel de l'accueil (sous l'en-tête). Sans diapositive active, l'accueil affiche son visuel d'ouverture.
CREATE TABLE home_slides (
  id {PK},
  image_path VARCHAR(255) NOT NULL,
  image_mobile_path VARCHAR(255) NULL,
  image_alt VARCHAR(200) NULL,
  image_alt_en VARCHAR(200) NULL,
  title VARCHAR(120) NOT NULL,
  title_en VARCHAR(120) NULL,
  body VARCHAR(300) NULL,
  body_en VARCHAR(300) NULL,
  button_label VARCHAR(60) NULL,
  button_label_en VARCHAR(60) NULL,
  link_url VARCHAR(255) NULL,
  is_active INT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

-- Diapositives initiales : uniquement des contenus déjà vérifiés (aucune promotion ni prix inventés).
INSERT INTO home_slides (image_path, image_alt, image_alt_en, title, title_en, body, body_en, button_label, button_label_en, link_url, is_active, sort_order, created_at, updated_at) VALUES
('/assets/img/photos/wm-pot-miel-rayon.webp', 'Pot de miel ambré avec un morceau de rayon et une cuillère (photo d''illustration)', 'Jar of amber honey with a piece of honeycomb and a spoon (illustrative photo)',
 'Le miel pur de Katiola', 'Pure honey from Katiola',
 'Miel 100 % bio, récolté, conditionné et vendu par nos soins.', '100% organic honey, harvested, packed and sold by us.',
 'Découvrir la boutique', 'Visit the shop', '/boutique', 1, 0, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
INSERT INTO home_slides (image_path, image_alt, image_alt_en, title, title_en, body, body_en, button_label, button_label_en, link_url, is_active, sort_order, created_at, updated_at) VALUES
('/assets/img/photos/fb-miel-en-rayon-vente.webp', 'Barquettes de miel en rayon et panneau « Miel de l''apiculteur — En vente ici »', 'Tubs of comb honey and a "Beekeeper''s honey — On sale here" sign',
 'Du rucher à votre table', 'From the hive to your table',
 'Notre mission : promouvoir les vertus du miel entièrement produit en Côte d''Ivoire.', 'Our mission: to promote the benefits of honey produced entirely in Côte d''Ivoire.',
 'Notre histoire', 'Our story', '/a-propos', 1, 1, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
INSERT INTO home_slides (image_path, image_alt, image_alt_en, title, title_en, body, body_en, button_label, button_label_en, link_url, is_active, sort_order, created_at, updated_at) VALUES
('/assets/img/photos/fb-stand-coffrets.webp', 'Stand de vente : bouteilles de miel et paniers cadeaux', 'Sales stand: bottles of honey and gift baskets',
 'Professionnels et volumes', 'Businesses and volumes',
 'Hôtels, restaurants, pâtisseries, épiceries fines : demandez un devis.', 'Hotels, restaurants, bakeries, delicatessens: request a quote.',
 'Demander un devis', 'Request a quote', '/commandes-professionnelles', 1, 2, '2026-10-14 00:00:00', '2026-10-14 00:00:00');

-- Devises d'affichage : conversions indicatives à côté des prix (le paiement se fait toujours en francs CFA, XOF).
-- xof_per_unit : nombre de FCFA pour 1 unité de la devise. Euro : parité fixe officielle 1 € = 655,957 FCFA.
CREATE TABLE currencies (
  id {PK},
  code VARCHAR(3) NOT NULL UNIQUE,
  name VARCHAR(40) NOT NULL,
  symbol VARCHAR(8) NOT NULL,
  xof_per_unit DECIMAL(14,4) NULL,
  decimals INT NOT NULL DEFAULT 2,
  is_enabled INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL
) {ENGINE};

INSERT INTO currencies (code, name, symbol, xof_per_unit, decimals, is_enabled, sort_order, updated_at) VALUES
('EUR', 'Euro', '€', 655.9570, 2, 0, 0, '2026-10-14 00:00:00');
INSERT INTO currencies (code, name, symbol, xof_per_unit, decimals, is_enabled, sort_order, updated_at) VALUES
('USD', 'Dollar américain', '$', NULL, 2, 0, 1, '2026-10-14 00:00:00');
