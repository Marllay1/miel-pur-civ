-- Schéma initial — Miel Pur de Côte d'Ivoire
-- Portable MariaDB/MySQL et SQLite (jetons traduits par App\Core\Migrator).
-- Montants : entiers en FCFA. Dates : UTC, format Y-m-d H:i:s.

CREATE TABLE roles (
  id {PK},
  name VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(100) NOT NULL
) {ENGINE};

CREATE TABLE permissions (
  id {PK},
  name VARCHAR(80) NOT NULL UNIQUE,
  label VARCHAR(150) NOT NULL
) {ENGINE};

CREATE TABLE role_permissions (
  role_id {ID} NOT NULL,
  permission_id {ID} NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) {ENGINE};

CREATE TABLE users (
  id {PK},
  role_id {ID} NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active SMALLINT NOT NULL DEFAULT 1,
  marketing_opt_in SMALLINT NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) {ENGINE};

CREATE TABLE password_resets (
  id {PK},
  user_id {ID} NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) {ENGINE};

CREATE TABLE addresses (
  id {PK},
  user_id {ID} NOT NULL,
  label VARCHAR(60) NOT NULL,
  recipient_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  city VARCHAR(100) NOT NULL,
  district VARCHAR(150) NULL,
  address_line VARCHAR(255) NOT NULL,
  landmark VARCHAR(255) NULL,
  is_default SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) {ENGINE};

CREATE TABLE categories (
  id {PK},
  parent_id {ID} NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active SMALLINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) {ENGINE};

CREATE TABLE products (
  id {PK},
  category_id {ID} NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  short_description VARCHAR(300) NULL,
  description TEXT NULL,
  origin TEXT NULL,
  usage_tips TEXT NULL,
  storage_tips TEXT NULL,
  is_active SMALLINT NOT NULL DEFAULT 1,
  is_featured SMALLINT NOT NULL DEFAULT 0,
  is_demo SMALLINT NOT NULL DEFAULT 0,
  meta_title VARCHAR(160) NULL,
  meta_description VARCHAR(300) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) {ENGINE};

CREATE INDEX idx_products_listing ON products (is_active, deleted_at, category_id);

CREATE TABLE product_variants (
  id {PK},
  product_id {ID} NOT NULL,
  label VARCHAR(100) NOT NULL,
  format VARCHAR(50) NOT NULL,
  weight_grams INT NULL,
  sku VARCHAR(64) NOT NULL UNIQUE,
  price INT NOT NULL CHECK (price >= 0),
  compare_at_price INT NULL,
  stock INT NOT NULL DEFAULT 0 CHECK (stock >= 0),
  is_active SMALLINT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_var_prod FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) {ENGINE};

CREATE INDEX idx_variants_product ON product_variants (product_id, is_active, deleted_at);

CREATE TABLE product_images (
  id {PK},
  product_id {ID} NOT NULL,
  path VARCHAR(255) NOT NULL,
  alt VARCHAR(200) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_demo SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_img_prod FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) {ENGINE};

CREATE TABLE stock_movements (
  id {PK},
  variant_id {ID} NOT NULL,
  quantity INT NOT NULL,
  reason VARCHAR(40) NOT NULL,
  reference VARCHAR(60) NULL,
  user_id {ID} NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_sm_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

CREATE TABLE carts (
  id {PK},
  token CHAR(48) NULL UNIQUE,
  user_id {ID} NULL,
  promo_code VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) {ENGINE};

CREATE INDEX idx_carts_user ON carts (user_id);

CREATE TABLE cart_items (
  id {PK},
  cart_id {ID} NOT NULL,
  variant_id {ID} NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  created_at DATETIME NOT NULL,
  UNIQUE (cart_id, variant_id),
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) {ENGINE};

CREATE TABLE shipping_zones (
  id {PK},
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  fee INT NOT NULL DEFAULT 0 CHECK (fee >= 0),
  free_threshold INT NULL,
  delay_text VARCHAR(120) NULL,
  allows_cod SMALLINT NOT NULL DEFAULT 1,
  is_pickup SMALLINT NOT NULL DEFAULT 0,
  is_active SMALLINT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

CREATE TABLE promo_codes (
  id {PK},
  code VARCHAR(40) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  type VARCHAR(20) NOT NULL CHECK (type IN ('percent', 'fixed', 'free_shipping')),
  value INT NOT NULL DEFAULT 0 CHECK (value >= 0),
  min_subtotal INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  max_uses INT NULL,
  used_count INT NOT NULL DEFAULT 0 CHECK (used_count >= 0),
  is_active SMALLINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

CREATE TABLE orders (
  id {PK},
  number VARCHAR(40) NOT NULL UNIQUE,
  idempotency_key CHAR(32) NOT NULL UNIQUE,
  access_token_hash CHAR(64) NOT NULL,
  user_id {ID} NULL,
  status VARCHAR(30) NOT NULL,
  payment_method VARCHAR(30) NOT NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  customer_first_name VARCHAR(100) NOT NULL,
  customer_last_name VARCHAR(100) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  shipping_zone_id {ID} NULL,
  shipping_zone_name VARCHAR(120) NOT NULL,
  is_pickup SMALLINT NOT NULL DEFAULT 0,
  shipping_city VARCHAR(100) NULL,
  shipping_district VARCHAR(150) NULL,
  shipping_address VARCHAR(255) NULL,
  shipping_landmark VARCHAR(255) NULL,
  customer_note TEXT NULL,
  subtotal INT NOT NULL,
  discount INT NOT NULL DEFAULT 0,
  shipping_fee INT NOT NULL DEFAULT 0,
  total INT NOT NULL CHECK (total >= 0),
  promo_code VARCHAR(40) NULL,
  promo_code_id {ID} NULL,
  stock_restored SMALLINT NOT NULL DEFAULT 0,
  promo_released SMALLINT NOT NULL DEFAULT 0,
  admin_note TEXT NULL,
  expires_at DATETIME NULL,
  paid_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_zone FOREIGN KEY (shipping_zone_id) REFERENCES shipping_zones (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_promo FOREIGN KEY (promo_code_id) REFERENCES promo_codes (id) ON DELETE SET NULL
) {ENGINE};

CREATE INDEX idx_orders_status ON orders (status, created_at);
CREATE INDEX idx_orders_user ON orders (user_id);
CREATE INDEX idx_orders_email ON orders (customer_email);

CREATE TABLE order_items (
  id {PK},
  order_id {ID} NOT NULL,
  product_id {ID} NULL,
  variant_id {ID} NULL,
  product_name VARCHAR(160) NOT NULL,
  variant_label VARCHAR(100) NOT NULL,
  sku VARCHAR(64) NOT NULL,
  unit_price INT NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  line_total INT NOT NULL,
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE SET NULL
) {ENGINE};

CREATE TABLE order_status_history (
  id {PK},
  order_id {ID} NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  note TEXT NULL,
  user_id {ID} NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

CREATE TABLE payments (
  id {PK},
  order_id {ID} NOT NULL,
  provider VARCHAR(30) NOT NULL,
  reference VARCHAR(40) NOT NULL UNIQUE,
  amount INT NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'XOF',
  status VARCHAR(20) NOT NULL,
  provider_reference VARCHAR(120) NULL,
  failure_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) {ENGINE};

CREATE TABLE payment_events (
  id {PK},
  provider VARCHAR(30) NOT NULL,
  event_id VARCHAR(100) NOT NULL,
  payment_id {ID} NULL,
  type VARCHAR(40) NOT NULL,
  result VARCHAR(40) NULL,
  payload TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE (provider, event_id),
  CONSTRAINT fk_pe_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) {ENGINE};

CREATE TABLE shipments (
  id {PK},
  order_id {ID} NOT NULL UNIQUE,
  carrier VARCHAR(120) NULL,
  tracking_number VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_ship_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) {ENGINE};

CREATE TABLE quote_requests (
  id {PK},
  company VARCHAR(160) NOT NULL,
  contact_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  activity VARCHAR(80) NOT NULL,
  products_interest VARCHAR(255) NULL,
  estimated_volume VARCHAR(120) NULL,
  frequency VARCHAR(60) NULL,
  city VARCHAR(100) NULL,
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

CREATE TABLE contact_messages (
  id {PK},
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL
) {ENGINE};

CREATE TABLE reviews (
  id {PK},
  product_id {ID} NOT NULL,
  user_id {ID} NULL,
  author_name VARCHAR(100) NOT NULL,
  rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  verified_purchase SMALLINT NOT NULL DEFAULT 0,
  ip VARCHAR(45) NULL,
  moderated_by {ID} NULL,
  moderated_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_rev_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_rev_mod FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

CREATE TABLE settings (
  name VARCHAR(100) NOT NULL PRIMARY KEY,
  value TEXT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

CREATE TABLE pages (
  id {PK},
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  is_legal SMALLINT NOT NULL DEFAULT 0,
  is_validated SMALLINT NOT NULL DEFAULT 0,
  meta_description VARCHAR(300) NULL,
  updated_by {ID} NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_pages_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

CREATE TABLE faqs (
  id {PK},
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Général',
  sort_order INT NOT NULL DEFAULT 0,
  is_active SMALLINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) {ENGINE};

CREATE TABLE newsletter_subscribers (
  id {PK},
  email VARCHAR(190) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL DEFAULT 'subscribed',
  unsubscribe_token CHAR(32) NOT NULL,
  consent_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL
) {ENGINE};

CREATE TABLE audit_log (
  id {PK},
  user_id {ID} NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(50) NULL,
  entity_id VARCHAR(50) NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) {ENGINE};

CREATE INDEX idx_audit_created ON audit_log (created_at);

CREATE TABLE rate_limits (
  bucket VARCHAR(40) NOT NULL PRIMARY KEY,
  hits INT NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL
) {ENGINE};
