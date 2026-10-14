-- Itération 5 : produits entièrement administrables, photothèque de l'entreprise, messagerie de contact,
-- suivi des encaissements (acompte, solde, remboursements), paiement sur facture pour les professionnels autorisés.

-- ---------- Produits ----------
-- status : draft (brouillon) | published (publié) | disabled (désactivé). is_active reste l'indicateur public (= publié).
ALTER TABLE products ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'published';
UPDATE products SET status = 'disabled' WHERE is_active = 0;
-- Référence commerciale du produit (distincte de l'identifiant technique) ; vide tant que l'entreprise ne l'a pas fournie.
ALTER TABLE products ADD COLUMN reference VARCHAR(64) NULL;
CREATE UNIQUE INDEX uq_products_reference ON products (reference);
ALTER TABLE products ADD COLUMN extra_info TEXT NULL;
-- Point à vérifier signalé dans l'administration (photo manquante, correspondance à confirmer…).
ALTER TABLE products ADD COLUMN review_note VARCHAR(500) NULL;
ALTER TABLE product_variants ADD COLUMN volume_ml INT NULL;
-- Provenance déclarée de chaque photo (ressources de l'entreprise : page Facebook, photo fournie…).
ALTER TABLE product_images ADD COLUMN source_note VARCHAR(255) NULL;
UPDATE product_images SET source_note = 'Page Facebook de l''entreprise' WHERE path LIKE '/assets/img/photos/fb-%';

-- Seules les photos de l'entreprise sont utilisées : les photos d'illustration (banques d'images libres) sont retirées.
-- Le coffret n'a pas de photo vérifiable (la photo du stand montre des paniers cadeaux, pas le coffret décrit).
DELETE FROM product_images WHERE path LIKE '/assets/img/photos/wm-%';
DELETE FROM product_images WHERE path = '/assets/img/photos/fb-stand-coffrets.webp';
UPDATE products SET review_note = 'Photo à fournir : aucune photo de ce produit n''a été trouvée sur la page Facebook de l''entreprise.'
WHERE slug IN ('miel-cremeux', 'miel-vrac-professionnels', 'pain-de-cire-d-abeille', 'coffret-decouverte');
UPDATE products SET review_note = 'Conditionnement à vérifier : les photos Facebook montrent des bouteilles, les formats indiquent « pot en verre » et « pot plastique ».'
WHERE slug = 'miel-liquide-toutes-fleurs';
DELETE FROM home_slides WHERE image_path LIKE '/assets/img/photos/wm-%';
ALTER TABLE home_slides ADD COLUMN source_note VARCHAR(255) NULL;
UPDATE home_slides SET source_note = 'Page Facebook de l''entreprise' WHERE image_path LIKE '/assets/img/photos/fb-%';
UPDATE settings SET value = '' WHERE name = 'home_hero_image' AND value LIKE '/assets/img/photos/wm-%';

-- ---------- Photothèque de l'entreprise (galeries de l'accueil et de la page À propos, photo de l'histoire) ----------
CREATE TABLE media (
  id {PK},
  path VARCHAR(255) NOT NULL,
  width INT NULL,
  height INT NULL,
  alt VARCHAR(200) NOT NULL,
  alt_en VARCHAR(200) NULL,
  source_note VARCHAR(255) NOT NULL,
  in_home_gallery SMALLINT NOT NULL DEFAULT 0,
  in_about_gallery SMALLINT NOT NULL DEFAULT 0,
  is_story SMALLINT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

INSERT INTO media (path, width, height, alt, alt_en, source_note, in_home_gallery, in_about_gallery, is_story, sort_order, created_at, updated_at) VALUES
('/assets/img/photos/fb-miel-en-rayon-vente.webp', 960, 521, 'Barquettes de miel en rayon empilées à côté d''un panneau « Miel de l''apiculteur — En vente ici »', 'Stacked tubs of comb honey next to a "Beekeeper''s honey — On sale here" sign', 'Page Facebook de l''entreprise', 1, 1, 1, 0, '2026-10-14 00:00:00', '2026-10-14 00:00:00'),
('/assets/img/photos/fb-bouteilles-trio.webp', 640, 640, 'Trois bouteilles de Miel Pur de Côte d''Ivoire, étiquettes avant et arrière', 'Three bottles of Miel Pur de Côte d''Ivoire honey, front and back labels', 'Page Facebook de l''entreprise', 0, 1, 0, 1, '2026-10-14 00:00:00', '2026-10-14 00:00:00'),
('/assets/img/photos/fb-bouteille-fond-jaune.webp', 393, 590, 'Bouteille de Miel Pur de Côte d''Ivoire tenue en main sur fond jaune', 'Hand holding a bottle of Miel Pur de Côte d''Ivoire honey against a yellow background', 'Page Facebook de l''entreprise', 1, 0, 0, 2, '2026-10-14 00:00:00', '2026-10-14 00:00:00'),
('/assets/img/photos/fb-bouteilles-duo.webp', 266, 590, 'Deux bouteilles de miel ambré Miel Pur de Côte d''Ivoire', 'Two bottles of amber Miel Pur de Côte d''Ivoire honey', 'Page Facebook de l''entreprise', 1, 0, 0, 3, '2026-10-14 00:00:00', '2026-10-14 00:00:00'),
('/assets/img/photos/fb-stand-coffrets.webp', 590, 266, 'Stand de vente : bouteilles de miel alignées et paniers cadeaux emballés', 'Sales stand: rows of honey bottles and wrapped gift baskets', 'Page Facebook de l''entreprise', 0, 1, 0, 4, '2026-10-14 00:00:00', '2026-10-14 00:00:00');

-- ---------- Messagerie de contact ----------
-- status : new | read | in_progress | resolved | archived ; reason : product | order | delivery | payment | other
ALTER TABLE contact_messages ADD COLUMN reason VARCHAR(20) NOT NULL DEFAULT 'other';
ALTER TABLE contact_messages ADD COLUMN user_id {ID} NULL;
ALTER TABLE contact_messages ADD COLUMN updated_at DATETIME NULL;
CREATE INDEX idx_contact_status ON contact_messages (status, created_at);
CREATE INDEX idx_contact_user ON contact_messages (user_id);

CREATE TABLE message_replies (
  id {PK},
  message_id {ID} NOT NULL,
  user_id {ID} NULL,
  body TEXT NOT NULL,
  sent_by_email SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_reply_message FOREIGN KEY (message_id) REFERENCES contact_messages (id) ON DELETE CASCADE,
  CONSTRAINT fk_reply_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

-- ---------- Paiements : conditions, encaissements, remboursements ----------
-- payment_terms : full (paiement intégral) | deposit (acompte + solde) | cod (à la livraison) | invoice (sur facture)
ALTER TABLE orders ADD COLUMN payment_terms VARCHAR(12) NOT NULL DEFAULT 'full';
UPDATE orders SET payment_terms = 'cod' WHERE payment_method = 'cod';
ALTER TABLE orders ADD COLUMN customer_type VARCHAR(12) NOT NULL DEFAULT 'individual';
ALTER TABLE orders ADD COLUMN is_wholesale SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN deposit_amount INT NULL;
ALTER TABLE orders ADD COLUMN amount_paid INT NOT NULL DEFAULT 0;
UPDATE orders SET amount_paid = total WHERE payment_status IN ('paid', 'refunded');
ALTER TABLE orders ADD COLUMN amount_refunded INT NOT NULL DEFAULT 0;
UPDATE orders SET amount_refunded = total WHERE payment_status = 'refunded';
ALTER TABLE orders ADD COLUMN balance_due_at DATETIME NULL;
ALTER TABLE orders ADD COLUMN payment_declared_at DATETIME NULL;
ALTER TABLE orders ADD COLUMN payment_declaration VARCHAR(255) NULL;
UPDATE orders SET payment_status = 'cod' WHERE payment_method = 'cod' AND payment_status = 'unpaid';

-- Journal des encaissements et remboursements (rapprochement commandes / paiements / remboursements).
-- type : payment | refund ; status : completed | pending (remboursement à effectuer) | cancelled
CREATE TABLE order_transactions (
  id {PK},
  order_id {ID} NOT NULL,
  type VARCHAR(10) NOT NULL,
  method VARCHAR(30) NOT NULL,
  amount INT NOT NULL CHECK (amount > 0),
  status VARCHAR(12) NOT NULL DEFAULT 'completed',
  reference VARCHAR(120) NULL,
  reason VARCHAR(255) NULL,
  payment_id {ID} NULL,
  user_id {ID} NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_tx_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_tx_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
  CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

CREATE INDEX idx_tx_order ON order_transactions (order_id, created_at);
CREATE UNIQUE INDEX uq_tx_payment ON order_transactions (payment_id);

-- Paiements déjà enregistrés avant cette version : repris dans le journal.
INSERT INTO order_transactions (order_id, type, method, amount, status, reference, reason, payment_id, user_id, created_at, updated_at)
SELECT o.id, 'payment', o.payment_method, o.total, 'completed', NULL, 'Reprise de l''historique', NULL, NULL, COALESCE(o.paid_at, o.updated_at), COALESCE(o.paid_at, o.updated_at)
FROM orders o WHERE o.amount_paid > 0;

-- Clients professionnels autorisés au paiement sur facture (autorisation accordée par un administrateur).
CREATE TABLE pro_accounts (
  id {PK},
  user_id {ID} NOT NULL UNIQUE,
  company VARCHAR(160) NOT NULL,
  is_active SMALLINT NOT NULL DEFAULT 1,
  invoice_allowed SMALLINT NOT NULL DEFAULT 0,
  credit_limit INT NULL,
  payment_days INT NOT NULL DEFAULT 30,
  conditions TEXT NULL,
  approved_by {ID} NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_pro_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pro_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

-- Montants minimum et maximum de commande par moyen de paiement (vide = sans limite).
ALTER TABLE payment_methods ADD COLUMN min_amount INT NULL;
ALTER TABLE payment_methods ADD COLUMN max_amount INT NULL;

INSERT INTO payment_methods (code, kind, name, name_en, description, description_en, logo_paths, show_logo, is_enabled, sort_order,
  merchant_number, receiver_number, beneficiary, instructions, instructions_en, created_at, updated_at) VALUES
('invoice', 'invoice', 'Paiement sur facture', 'Pay on invoice', NULL, NULL, NULL, 0, 0, 7, NULL, NULL, NULL, NULL, NULL, '2026-10-14 00:00:00', '2026-10-14 00:00:00');
