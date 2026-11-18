-- HerbaBloom schema SQL.
-- Creates the me_ tables and stock view, including customer accounts and offers.
-- It contains no product/order/customer rows, password hashes, OTP codes, or credentials.
-- After importing the real product catalog, run database/seeds/001_herbabloom_bundles.sql
-- followed by database/seeds/002_herbabloom_bundles.sql.
-- Import into a new/empty database; this script does not drop existing objects.
SET NAMES utf8mb4;

CREATE TABLE `me_cilesimet` (
  `id` int NOT NULL DEFAULT '1',
  `emri_dyqanit` varchar(100) NOT NULL DEFAULT 'HerbaBloom',
  `pronari` varchar(100) NOT NULL DEFAULT 'HerbaBloom',
  `personi_kontaktit` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefoni` varchar(30) DEFAULT NULL,
  `adresa` text,
  `otp_email` varchar(100) NOT NULL DEFAULT '',
  `tarifa_dorezimit` decimal(10,2) NOT NULL DEFAULT '2.50',
  `dergesa_falas_mbi` decimal(10,2) DEFAULT '20.00',
  `pragu_stokut` int NOT NULL DEFAULT '5',
  `paralajmerimi_1` int NOT NULL DEFAULT '30',
  `paralajmerimi_2` int NOT NULL DEFAULT '60',
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_cil_single` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_kategorite` (
  `id` int NOT NULL AUTO_INCREMENT,
  `emri` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `pershkrimi` text,
  `statusi` enum('Aktiv','Inaktiv') NOT NULL DEFAULT 'Aktiv',
  PRIMARY KEY (`id`),
  UNIQUE KEY `emri` (`emri`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_perdoruesit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `emri` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `fjalekalimi` varchar(255) NOT NULL,
  `rooli` enum('admin','farmacist','kurier') NOT NULL DEFAULT 'farmacist',
  `telefoni` varchar(30) DEFAULT NULL,
  `qyteti` varchar(50) DEFAULT 'Prizren',
  `statusi` enum('Aktiv','Bllokuar') NOT NULL DEFAULT 'Aktiv',
  `duhet_ndryshim_fjalekalimi` tinyint(1) NOT NULL DEFAULT '0',
  `hyrja_e_fundit` datetime DEFAULT NULL,
  `data_krijimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_user_rooli` (`rooli`,`statusi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_web_design` (
  `id` tinyint NOT NULL DEFAULT '1',
  `draft_json` json DEFAULT NULL,
  `published_json` json DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `published_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_web_design_single` CHECK (`id` = 1),
  CONSTRAINT `fk_web_design_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `me_perdoruesit` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_web_design_published_by` FOREIGN KEY (`published_by`) REFERENCES `me_perdoruesit` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
INSERT INTO `me_web_design` (`id`) VALUES (1);

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

CREATE TABLE `me_njoftimet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lloji` varchar(30) NOT NULL DEFAULT 'info',
  `titulli` varchar(120) NOT NULL,
  `mesazhi` text NOT NULL,
  `rooli_target` enum('admin','farmacist','kurier','te_gjithe') NOT NULL DEFAULT 'te_gjithe',
  `perdorues_id` int DEFAULT NULL,
  `ref_tipi` varchar(30) DEFAULT NULL,
  `ref_id` int DEFAULT NULL,
  `data_krijimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_njoft_target` (`rooli_target`,`perdorues_id`,`id`),
  KEY `idx_njoft_ref` (`ref_tipi`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_produktet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `emri` varchar(150) NOT NULL,
  `marka` varchar(100) DEFAULT NULL,
  `kategoria_id` int DEFAULT NULL,
  `pershkrimi` text,
  `cmimi` decimal(10,2) NOT NULL,
  `stoku_minimal` int NOT NULL DEFAULT '5',
  `imazhi` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT '1',
  `i_dukshem_ne_web` tinyint(1) NOT NULL DEFAULT '1',
  `data_krijimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_prod_kat` (`kategoria_id`),
  KEY `idx_prod_aktiv` (`aktiv`,`i_dukshem_ne_web`),
  FULLTEXT KEY `ft_prod` (`emri`,`pershkrimi`),
  CONSTRAINT `fk_prod_kat` FOREIGN KEY (`kategoria_id`) REFERENCES `me_kategorite` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_prod_cmimi` CHECK ((`cmimi` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_ofertat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(140) NOT NULL,
  `emri` varchar(150) NOT NULL,
  `pershkrimi` text,
  `imazhi` varchar(255) DEFAULT NULL,
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

CREATE TABLE `me_porosite_online` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kodi_gjurmimit` varchar(20) NOT NULL,
  `burimi` enum('web','manual') NOT NULL DEFAULT 'web',
  `klienti` varchar(100) NOT NULL,
  `klient_id` int DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefoni` varchar(30) NOT NULL,
  `qyteti` varchar(50) NOT NULL DEFAULT 'Prizren',
  `adresa` varchar(255) NOT NULL,
  `shenime` text,
  `nentotali` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tarifa_dorezimit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `totali` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pagesa` enum('Cash në dorëzim') NOT NULL DEFAULT 'Cash në dorëzim',
  `statusi` enum('Në pritje','Në përgatitje','Në rrugë','Dorëzuar','Anuluar') NOT NULL DEFAULT 'Në pritje',
  `kurier_id` int DEFAULT NULL,
  `anuluar_nga` enum('klienti','kurieri','stafi') DEFAULT NULL,
  `arsyeja_anullimit` varchar(255) DEFAULT NULL,
  `data_porosise` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_dorezimit` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kodi_gjurmimit` (`kodi_gjurmimit`),
  KEY `idx_por_statusi` (`statusi`,`data_porosise`),
  KEY `idx_por_kurier` (`kurier_id`,`statusi`),
  KEY `idx_por_dorezimi` (`data_dorezimit`),
  KEY `idx_por_telefoni` (`telefoni`),
  KEY `idx_por_klient` (`klient_id`),
  CONSTRAINT `fk_por_kurier` FOREIGN KEY (`kurier_id`) REFERENCES `me_perdoruesit` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_por_klient` FOREIGN KEY (`klient_id`) REFERENCES `me_klientet` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_stoku_batch` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produkt_id` int NOT NULL,
  `nr_batch` varchar(50) NOT NULL,
  `sasia` int NOT NULL DEFAULT '0',
  `data_skadence` date DEFAULT NULL,
  `data_krijimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch` (`produkt_id`,`nr_batch`),
  KEY `idx_batch_skadenca` (`data_skadence`),
  CONSTRAINT `fk_batch_prod` FOREIGN KEY (`produkt_id`) REFERENCES `me_produktet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_batch_sasia` CHECK ((`sasia` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_porosi_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `porosia_id` int NOT NULL,
  `produkt_id` int DEFAULT NULL,
  `emri_produktit` varchar(150) NOT NULL,
  `cmimi` decimal(10,2) NOT NULL,
  `sasia` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pi_porosia` (`porosia_id`),
  KEY `idx_pi_prod` (`produkt_id`),
  CONSTRAINT `fk_pi_porosia` FOREIGN KEY (`porosia_id`) REFERENCES `me_porosite_online` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_prod` FOREIGN KEY (`produkt_id`) REFERENCES `me_produktet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_pi_sasia` CHECK ((`sasia` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_porosi_statuset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `porosia_id` int NOT NULL,
  `statusi` varchar(30) NOT NULL,
  `nga_user_id` int DEFAULT NULL,
  `shenim` varchar(255) DEFAULT NULL,
  `koha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ps_porosia` (`porosia_id`,`koha`),
  CONSTRAINT `fk_ps_porosia` FOREIGN KEY (`porosia_id`) REFERENCES `me_porosite_online` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_stoku_levizjet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produkt_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `sasia` int NOT NULL,
  `lloji` enum('shitje','anullim','shtim','korrigjim') NOT NULL,
  `porosia_id` int DEFAULT NULL,
  `nga_user_id` int DEFAULT NULL,
  `koha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_sl_batch` (`batch_id`),
  KEY `idx_sl_porosia` (`porosia_id`),
  KEY `idx_sl_prod` (`produkt_id`,`koha`),
  CONSTRAINT `fk_sl_batch` FOREIGN KEY (`batch_id`) REFERENCES `me_stoku_batch` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sl_prod` FOREIGN KEY (`produkt_id`) REFERENCES `me_produktet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_njoftimet_lexuara` (
  `njoftim_id` int NOT NULL,
  `perdorues_id` int NOT NULL,
  `pare_at` datetime DEFAULT NULL,
  `mbyllur_at` datetime DEFAULT NULL,
  PRIMARY KEY (`njoftim_id`,`perdorues_id`),
  KEY `fk_nl_user` (`perdorues_id`),
  CONSTRAINT `fk_nl_njoft` FOREIGN KEY (`njoftim_id`) REFERENCES `me_njoftimet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nl_user` FOREIGN KEY (`perdorues_id`) REFERENCES `me_perdoruesit` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_otp_kodet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `perdorues_id` int NOT NULL,
  `kodi_hash` varchar(255) NOT NULL,
  `skadon_me` datetime NOT NULL,
  `tentativa` tinyint NOT NULL DEFAULT '0',
  `perdorur` tinyint(1) NOT NULL DEFAULT '0',
  `ip` varchar(45) DEFAULT NULL,
  `krijuar_me` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_user` (`perdorues_id`,`perdorur`,`skadon_me`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`perdorues_id`) REFERENCES `me_perdoruesit` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_login_tentativa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `sukses` tinyint(1) NOT NULL DEFAULT '0',
  `koha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lt_user` (`username`,`koha`),
  KEY `idx_lt_ip` (`ip`,`koha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_mesazhet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `emri` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subjekti` varchar(150) NOT NULL,
  `mesazhi` text NOT NULL,
  `i_trajtuar` tinyint(1) NOT NULL DEFAULT '0',
  `data_dergimit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mes_trajtuar` (`i_trajtuar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `me_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `perdorues_id` int DEFAULT NULL,
  `perdoruesi` varchar(50) NOT NULL,
  `veprimi` text NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `koha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_koha` (`koha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE VIEW `me_produktet_stoku` AS
SELECT
    `p`.`id` AS `id`,
    `p`.`sku` AS `sku`,
    `p`.`slug` AS `slug`,
    `p`.`emri` AS `emri`,
    `p`.`marka` AS `marka`,
    `p`.`kategoria_id` AS `kategoria_id`,
    `p`.`pershkrimi` AS `pershkrimi`,
    `p`.`cmimi` AS `cmimi`,
    `p`.`stoku_minimal` AS `stoku_minimal`,
    `p`.`imazhi` AS `imazhi`,
    `p`.`aktiv` AS `aktiv`,
    `p`.`i_dukshem_ne_web` AS `i_dukshem_ne_web`,
    `p`.`data_krijimit` AS `data_krijimit`,
    COALESCE(SUM(
        CASE
            WHEN `b`.`data_skadence` IS NULL OR `b`.`data_skadence` >= CURDATE()
            THEN `b`.`sasia`
        END
    ), 0) AS `stoku_aktual`,
    MIN(
        CASE
            WHEN `b`.`sasia` > 0 AND `b`.`data_skadence` >= CURDATE()
            THEN `b`.`data_skadence`
        END
    ) AS `skadenca_me_e_afert`,
    CASE
        WHEN COALESCE(SUM(
            CASE
                WHEN `b`.`data_skadence` IS NULL OR `b`.`data_skadence` >= CURDATE()
                THEN `b`.`sasia`
            END
        ), 0) = 0 THEN 'Pa stok'
        WHEN COALESCE(SUM(
            CASE
                WHEN `b`.`data_skadence` IS NULL OR `b`.`data_skadence` >= CURDATE()
                THEN `b`.`sasia`
            END
        ), 0) <= `p`.`stoku_minimal` THEN 'Stok i ulët'
        ELSE 'Në stok'
    END AS `statusi_stokut`
FROM `me_produktet` AS `p`
LEFT JOIN `me_stoku_batch` AS `b` ON `b`.`produkt_id` = `p`.`id`
GROUP BY `p`.`id`;
