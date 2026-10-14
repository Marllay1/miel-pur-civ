-- Itération 6 : blocs de réassurance de l'accueil, textes d'interface modifiables, retrait des mentions « Paiements acceptés ».

-- Blocs de réassurance affichés sous le carrousel (Administration → Page d'accueil → Blocs de réassurance).
-- icon : clé d'une icône du site (liste fermée) ; link_url : page du site (/…) ou adresse https:// (facultatif).
CREATE TABLE home_highlights (
  id {PK},
  icon VARCHAR(30) NOT NULL DEFAULT 'leaf',
  title VARCHAR(80) NOT NULL,
  title_en VARCHAR(80) NULL,
  body VARCHAR(200) NULL,
  body_en VARCHAR(200) NULL,
  link_url VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active SMALLINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

-- Reprise des trois engagements affichés jusqu'ici. La livraison hors Côte d'Ivoire n'est pas promise : « nous consulter ».
INSERT INTO home_highlights (icon, title, title_en, body, body_en, link_url, sort_order, is_active, created_at, updated_at) VALUES
('leaf', 'Miel 100 % bio', '100% organic honey', 'Récolté dans nos ruchers de Katiola, sans aucun ajout.', 'Harvested from our apiaries in Katiola, with nothing added.', '/a-propos', 0, 1, '2026-10-14 00:00:00', '2026-10-14 00:00:00'),
('shield', 'Qualité certifiée', 'Certified quality', 'Conforme aux normes internationales, contrôlé par un laboratoire habilité.', 'Compliant with international standards, tested by an approved laboratory.', NULL, 1, 1, '2026-10-14 00:00:00', '2026-10-14 00:00:00'),
('truck', 'Livraison dans toute la Côte d''Ivoire', 'Delivery across Côte d''Ivoire', 'Abidjan, Bouaké, Katiola et autres villes. Autre destination : nous consulter.', 'Abidjan, Bouaké, Katiola and other cities. Other destinations: please ask us.', '/contact?motif=delivery', 2, 1, '2026-10-14 00:00:00', '2026-10-14 00:00:00');

-- Textes d'interface du site public modifiés par l'administration (les autres gardent leur valeur par défaut, app/lang/*.php).
CREATE TABLE text_overrides (
  id {PK},
  lang_key VARCHAR(120) NOT NULL,
  locale VARCHAR(5) NOT NULL,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE (lang_key, locale)
) {ENGINE};

-- Mentions « Paiements acceptés » retirées du pied de page et de la section « Commander, simplement »
-- (le paiement à la livraison reste proposé au moment de la commande ; réactivable dans Apparence / Page d'accueil).
DELETE FROM settings WHERE name IN ('footer_show_payments', 'home_show_payments');
INSERT INTO settings (name, value, updated_at) VALUES ('footer_show_payments', '0', '2026-10-14 00:00:00');
INSERT INTO settings (name, value, updated_at) VALUES ('home_show_payments', '0', '2026-10-14 00:00:00');
