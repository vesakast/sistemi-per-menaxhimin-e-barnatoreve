-- Upgrade an existing HerbaBloom database to customer accounts, favorites, consent, and configured bundles.
-- Run once against an existing installation. New installations should import database/schema.sql instead.
-- Apply database/seeds/001_herbabloom_bundles.sql after this migration and the real product catalog.

CREATE TABLE `me_klientet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `emri` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `fjalekalimi_hash` varchar(255) NOT NULL,
  `statusi` enum('Aktiv','Bllokuar') NOT NULL DEFAULT 'Aktiv',
  `data_krijimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_perditesimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_klient_email` (`email`),
  KEY `idx_klient_status` (`statusi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_klient_favorites` (
  `klient_id` int NOT NULL,
  `produkt_id` int NOT NULL,
  `data_krijimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`klient_id`,`produkt_id`),
  KEY `idx_favorite_product` (`produkt_id`),
  CONSTRAINT `fk_favorite_klient` FOREIGN KEY (`klient_id`) REFERENCES `me_klientet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorite_product` FOREIGN KEY (`produkt_id`) REFERENCES `me_produktet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_klient_preferencat` (
  `klient_id` int NOT NULL,
  `njoftime_produkte_te_reja` tinyint(1) NOT NULL DEFAULT '0',
  `njoftime_zbritje` tinyint(1) NOT NULL DEFAULT '0',
  `njoftime_oferta` tinyint(1) NOT NULL DEFAULT '0',
  `pelqim_dhen_me` datetime DEFAULT NULL,
  `data_perditesimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`klient_id`),
  CONSTRAINT `fk_preferenca_klient` FOREIGN KEY (`klient_id`) REFERENCES `me_klientet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_klient_login_tentativa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `sukses` tinyint(1) NOT NULL DEFAULT '0',
  `koha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_klient_login_email` (`email`,`koha`),
  KEY `idx_klient_login_ip` (`ip`,`koha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `me_porosite_online`
  ADD COLUMN `klient_id` int DEFAULT NULL AFTER `klienti`,
  ADD KEY `idx_por_klient` (`klient_id`),
  ADD CONSTRAINT `fk_por_klient` FOREIGN KEY (`klient_id`) REFERENCES `me_klientet` (`id`) ON DELETE SET NULL;

CREATE TABLE `me_ofertat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(140) NOT NULL,
  `emri` varchar(150) NOT NULL,
  `pershkrimi` text,
  `cmimi` decimal(10,2) NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT '0',
  `fillon_me` datetime DEFAULT NULL,
  `mbaron_me` datetime DEFAULT NULL,
  `data_krijimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oferta_slug` (`slug`),
  KEY `idx_oferta_active_dates` (`aktiv`,`fillon_me`,`mbaron_me`),
  CONSTRAINT `chk_oferta_cmimi` CHECK (`cmimi` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_oferta_items` (
  `oferta_id` int NOT NULL,
  `produkt_id` int NOT NULL,
  `sasia` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`oferta_id`,`produkt_id`),
  KEY `idx_oferta_item_product` (`produkt_id`),
  CONSTRAINT `fk_oferta_item_offer` FOREIGN KEY (`oferta_id`) REFERENCES `me_ofertat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oferta_item_product` FOREIGN KEY (`produkt_id`) REFERENCES `me_produktet` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_oferta_item_quantity` CHECK (`sasia` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
