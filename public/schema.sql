-- ============================================================
-- Maison Des Bains — MySQL schema
-- Import once:  mysql -u root maison_des_bains < schema.sql
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------- products ----------
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`  VARCHAR(64)  NOT NULL,
  `brand`       VARCHAR(120) NOT NULL,
  `name`        VARCHAR(160) NOT NULL,
  `line`        VARCHAR(120) NOT NULL DEFAULT '',
  `category`    VARCHAR(32)  NOT NULL DEFAULT 'Soap',
  `price_cents` INT UNSIGNED NOT NULL,
  `sku`         VARCHAR(40)  NOT NULL DEFAULT '',
  `notes`       VARCHAR(255) NOT NULL DEFAULT '',
  `blurb`       TEXT,
  `badge`       VARCHAR(24)  DEFAULT NULL,
  `sold_out`    TINYINT(1)   NOT NULL DEFAULT 0,
  `stock`       INT          NOT NULL DEFAULT 100,
  `status`      TINYINT(1)   NOT NULL DEFAULT 1,
  `date_created` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`)
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

-- ---------- cart_items ----------
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart`     INT UNSIGNED NOT NULL,
  `product`  INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cart_product` (`cart`, `product`),
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
  `status`          VARCHAR(20)  NOT NULL DEFAULT 'pending', -- pending | paid | cancelled
  `stripe_session`  VARCHAR(255) DEFAULT NULL,
  `date_created`    INT UNSIGNED NOT NULL DEFAULT 0,
  `date_paid`       INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `user` (`user`),
  KEY `stripe_session` (`stripe_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- order_items ----------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order`            INT UNSIGNED NOT NULL,
  `product`          INT UNSIGNED DEFAULT NULL,
  `brand`            VARCHAR(120) NOT NULL,
  `name`             VARCHAR(160) NOT NULL,
  `sku`              VARCHAR(40)  NOT NULL DEFAULT '',
  `unit_price_cents` INT UNSIGNED NOT NULL,
  `quantity`         INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Seed catalogue
-- ============================================================
INSERT INTO `products`
  (`identifier`, `brand`, `name`, `line`, `category`, `price_cents`, `sku`, `notes`, `blurb`, `badge`, `sold_out`, `date_created`)
VALUES
  ('santal-33',   'Le Labo',  'Santal 33',     'Bar Soap',      'Soap', 3800, 'MDB·04—217', 'Sandalwood · Cardamom · Leather', 'A cult sandalwood, pressed into a triple-milled bar. Warm, smoky, quietly addictive.', NULL,      0, UNIX_TIMESTAMP()),
  ('rose-noir',   'Byredo',   'Rose Noir',     'Hand Wash',     'Body', 4500, 'MDB·04—331', 'Black Rose · Freesia · Musk',     'A darkened rose for the basin — sharp at first, then soft as dusk.',                    NULL,      0, UNIX_TIMESTAMP()),
  ('bain-moussant','Diptyque','Bain Moussant', 'Bubble Bath',   'Bath', 5200, 'MDB·05—118', 'Fig Leaf · Cedar · Green Sap',    'A foaming fig bath drawn from a Mediterranean garden after rain.',                      NULL,      1, UNIX_TIMESTAMP()),
  ('blanc-de-peau','Dior',    'Blanc de Peau', 'Cleansing Bar', 'Soap', 4000, 'MDB·04—402', 'White Iris · Rice · Cotton',      'A powder-soft white bar. Skin left matte, clean, unscented at the finish.',             'New',     0, UNIX_TIMESTAMP()),
  ('mojave-ghost','Byredo',   'Mojave Ghost',  'Body Lotion',   'Body', 5800, 'MDB·06—077', 'Sandalwood · Violet · Amber',     'A desert flower that blooms against all odds — powdery, resinous, resolute.',           NULL,      0, UNIX_TIMESTAMP()),
  ('the-noir-29', 'Le Labo',  'Thé Noir 29',   'Bath Salts',    'Bath', 6200, 'MDB·05—244', 'Black Tea · Fig · Bay Leaves',    'Coarse grey salts steeped in black tea. For the long, slow soak.',                      NULL,      0, UNIX_TIMESTAMP()),
  ('baies-candle','Diptyque', 'Baies Candle',  'Home',          'Home', 6800, 'MDB·07—012', 'Blackcurrant · Bulgarian Rose',   'The house classic. Berries and rose, for the room the bath opens onto.',                'Limited', 0, UNIX_TIMESTAMP()),
  ('gris-poudre', 'Dior',     'Gris Poudré',   'Body Oil',      'Body', 7200, 'MDB·06—190', 'Grey Iris · Musk · Vanilla',      'A weightless grey oil that disappears into damp skin.',                                 NULL,      0, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `brand`=VALUES(`brand`), `name`=VALUES(`name`), `line`=VALUES(`line`), `category`=VALUES(`category`),
  `price_cents`=VALUES(`price_cents`), `sku`=VALUES(`sku`), `notes`=VALUES(`notes`), `blurb`=VALUES(`blurb`),
  `badge`=VALUES(`badge`), `sold_out`=VALUES(`sold_out`);
