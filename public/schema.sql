-- ============================================================
-- Maison Des Bains — MySQL schema
-- Import once:  mysql maison_des_bains < schema.sql
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------- products (the item; sizes live in product_variants) ----------
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`  VARCHAR(64)  NOT NULL,
  `brand`       VARCHAR(120) NOT NULL,
  `name`        VARCHAR(160) NOT NULL,
  `line`        VARCHAR(120) NOT NULL DEFAULT '',
  `category`    VARCHAR(32)  NOT NULL DEFAULT 'Soap',
  `notes`       VARCHAR(255) NOT NULL DEFAULT '',
  `blurb`       TEXT,
  `badge`       VARCHAR(24)  DEFAULT NULL,
  `status`      TINYINT(1)   NOT NULL DEFAULT 1,
  `date_created` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- product_variants (a purchasable size of a product) ----------
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product`     INT UNSIGNED NOT NULL,
  `identifier`  VARCHAR(80)  NOT NULL,           -- unique slug, e.g. bal-dafrique-shower-gel-50ml
  `size`        VARCHAR(40)  NOT NULL,           -- display label, e.g. '50 ml'
  `price_cents` INT UNSIGNED NOT NULL DEFAULT 0, -- EUR, in cents
  `price_sek`   INT UNSIGNED NOT NULL DEFAULT 0, -- SEK, in whole kronor
  `sku`         VARCHAR(40)  NOT NULL DEFAULT '',
  `stock`       INT          NOT NULL DEFAULT 100,
  `sold_out`    TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = unavailable / coming soon
  `position`    INT          NOT NULL DEFAULT 0,
  `status`      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`),
  KEY `product` (`product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- users ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name`    VARCHAR(80)  NOT NULL DEFAULT '',
  `last_name`     VARCHAR(80)  NOT NULL DEFAULT '',
  `phone`         VARCHAR(40)  NOT NULL DEFAULT '',
  `status`        TINYINT(1)   NOT NULL DEFAULT 1,
  `date_created`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- sessions ----------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user`         INT UNSIGNED NOT NULL,
  `token`        VARCHAR(96)  NOT NULL,
  `date_created` INT UNSIGNED NOT NULL DEFAULT 0,
  `status`       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- carts ----------
CREATE TABLE IF NOT EXISTS `carts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user`         INT UNSIGNED DEFAULT NULL,
  `token`        VARCHAR(96)  NOT NULL,
  `date_created` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- cart_items (reference a variant) ----------
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart`     INT UNSIGNED NOT NULL,
  `variant`  INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cart_variant` (`cart`, `variant`),
  KEY `cart` (`cart`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- orders ----------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(20)  NOT NULL,
  `user`            INT UNSIGNED DEFAULT NULL,
  `email`           VARCHAR(190) NOT NULL,
  `first_name`      VARCHAR(80)  NOT NULL DEFAULT '',
  `last_name`       VARCHAR(80)  NOT NULL DEFAULT '',
  `address_line1`   VARCHAR(190) NOT NULL DEFAULT '',
  `address_line2`   VARCHAR(190) NOT NULL DEFAULT '',
  `city`            VARCHAR(120) NOT NULL DEFAULT '',
  `postcode`        VARCHAR(40)  NOT NULL DEFAULT '',
  `country`         VARCHAR(80)  NOT NULL DEFAULT '',
  `subtotal_cents`  INT UNSIGNED NOT NULL DEFAULT 0,
  `shipping_cents`  INT UNSIGNED NOT NULL DEFAULT 0,
  `gift_wrap_cents` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_cents`     INT UNSIGNED NOT NULL DEFAULT 0,
  `gift_wrap`       TINYINT(1)   NOT NULL DEFAULT 0,
  `currency`        VARCHAR(3)   NOT NULL DEFAULT 'EUR',
  `status`          VARCHAR(20)  NOT NULL DEFAULT 'pending', -- pending | paid | cancelled
  `stripe_session`  VARCHAR(255) DEFAULT NULL,
  `date_created`    INT UNSIGNED NOT NULL DEFAULT 0,
  `date_paid`       INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `user` (`user`),
  KEY `stripe_session` (`stripe_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- order_items (immutable snapshot incl. size) ----------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order`            INT UNSIGNED NOT NULL,
  `variant`          INT UNSIGNED DEFAULT NULL,
  `brand`            VARCHAR(120) NOT NULL,
  `name`             VARCHAR(160) NOT NULL,
  `size`             VARCHAR(40)  NOT NULL DEFAULT '',
  `sku`              VARCHAR(40)  NOT NULL DEFAULT '',
  `unit_price_cents` INT UNSIGNED NOT NULL,
  `quantity`         INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Seed catalogue
-- ============================================================
INSERT INTO `products` (`identifier`, `brand`, `name`, `line`, `category`, `notes`, `blurb`, `badge`, `date_created`)
VALUES
  ('santal-33-shower-gel',    'Le Labo', 'Santal 33 Shower Gel',     'Shower Gel', 'Wash', 'Sandalwood · Cardamom · Iris · Leather', 'The cult Santal 33, drawn into a lathering shower gel. Smoky sandalwood and iris, left on warm skin.', NULL, UNIX_TIMESTAMP()),
  ('santal-33-body-lotion',   'Le Labo', 'Santal 33 Body Lotion',    'Body Lotion', 'Body', 'Sandalwood · Violet · Cardamom · Amber', 'A weightless lotion carrying Santal 33''s smoky sandalwood and violet. Sinks in, lingers for hours.', NULL, UNIX_TIMESTAMP()),
  ('bal-dafrique-shower-gel', 'Byredo',  'Bal d''Afrique Shower Gel', 'Shower Gel', 'Wash', 'Bergamot · Neroli · Marigold · Vetiver', 'Byredo''s Bal d''Afrique as a shower gel — African marigold, neroli and warm vetiver.', NULL, UNIX_TIMESTAMP()),
  ('bal-dafrique-body-lotion','Byredo',  'Bal d''Afrique Body Lotion', 'Body Lotion', 'Body', 'Bergamot · Violet · Vetiver · Musk', 'A supple body lotion of bergamot, violet and vetiver. The 1920s Paris–Africa reverie, worn on skin.', NULL, UNIX_TIMESTAMP()),
  ('bal-dafrique-soap',       'Byredo',  'Bal d''Afrique Soap',       'Soap', 'Soap', 'Bergamot · Neroli · Black Amber', 'A milled soap of Bal d''Afrique — bergamot and black amber, kept by the basin.', NULL, UNIX_TIMESTAMP()),
  ('bal-dafrique-hand-wash',  'Byredo',  'Bal d''Afrique Hand Wash',  'Hand Wash', 'Wash', 'Bergamot · Neroli · Vetiver · Amber', 'A generous hand wash of Bal d''Afrique. Neroli and vetiver, left on the hands like a signature.', NULL, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `brand`=VALUES(`brand`), `name`=VALUES(`name`), `line`=VALUES(`line`), `category`=VALUES(`category`),
  `notes`=VALUES(`notes`), `blurb`=VALUES(`blurb`), `badge`=VALUES(`badge`);

-- Variants (sizes). 450 ml editions for the gels & lotions are marked
-- sold_out = 1 ("coming soon") until priced and stocked.
INSERT INTO `product_variants` (`product`, `identifier`, `size`, `price_cents`, `price_sek`, `sku`, `stock`, `sold_out`, `position`)
SELECT p.id, v.identifier, v.size, v.price_cents, v.price_sek, v.sku, v.stock, v.sold_out, v.position
FROM (
  SELECT 'santal-33-shower-gel'      AS pslug, 'santal-33-shower-gel-90ml'      AS identifier, '90 ml'  AS size, 2000 AS price_cents, 229 AS price_sek, 'MDB·LL—901'  AS sku, 100 AS stock, 0 AS sold_out, 1 AS position UNION ALL
  SELECT 'santal-33-shower-gel',            'santal-33-shower-gel-450ml',           '450 ml',    0,   0,   'MDB·LL—901L', 0,   1, 2 UNION ALL
  SELECT 'santal-33-body-lotion',           'santal-33-body-lotion-90ml',           '90 ml',     2000, 229, 'MDB·LL—902',  100, 0, 1 UNION ALL
  SELECT 'santal-33-body-lotion',           'santal-33-body-lotion-450ml',          '450 ml',    0,   0,   'MDB·LL—902L', 0,   1, 2 UNION ALL
  SELECT 'bal-dafrique-shower-gel',         'bal-dafrique-shower-gel-50ml',         '50 ml',     1500, 169, 'MDB·BY—501',  100, 0, 1 UNION ALL
  SELECT 'bal-dafrique-shower-gel',         'bal-dafrique-shower-gel-450ml',        '450 ml',    0,   0,   'MDB·BY—501L', 0,   1, 2 UNION ALL
  SELECT 'bal-dafrique-body-lotion',        'bal-dafrique-body-lotion-50ml',        '50 ml',     1500, 169, 'MDB·BY—502',  100, 0, 1 UNION ALL
  SELECT 'bal-dafrique-body-lotion',        'bal-dafrique-body-lotion-450ml',       '450 ml',    0,   0,   'MDB·BY—502L', 0,   1, 2 UNION ALL
  SELECT 'bal-dafrique-soap',               'bal-dafrique-soap-30g',                '30 g',      800,  89,  'MDB·BY—030',  100, 0, 1 UNION ALL
  SELECT 'bal-dafrique-hand-wash',          'bal-dafrique-hand-wash-450ml',         '450 ml',    3900, 449, 'MDB·BY—450',  100, 0, 1
) AS v
JOIN `products` p ON p.identifier = v.pslug
ON DUPLICATE KEY UPDATE
  `size`=VALUES(`size`), `price_cents`=VALUES(`price_cents`), `price_sek`=VALUES(`price_sek`),
  `sku`=VALUES(`sku`), `stock`=VALUES(`stock`), `sold_out`=VALUES(`sold_out`), `position`=VALUES(`position`);
