-- Bilinguisme des contenus et données des prestataires de paiement.

CREATE TABLE translations (
  id {PK},
  entity_type VARCHAR(30) NOT NULL,
  entity_id {ID} NOT NULL,
  locale VARCHAR(5) NOT NULL,
  field VARCHAR(40) NOT NULL,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE (entity_type, entity_id, locale, field)
) {ENGINE};

ALTER TABLE orders ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'fr';

ALTER TABLE payments ADD COLUMN payer_phone VARCHAR(30) NULL;

ALTER TABLE payments ADD COLUMN provider_token VARCHAR(190) NULL;

ALTER TABLE payments ADD COLUMN checkout_url VARCHAR(500) NULL;

ALTER TABLE payments ADD COLUMN last_checked_at DATETIME NULL;
