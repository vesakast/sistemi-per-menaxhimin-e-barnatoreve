-- HerbaBloom starter bundles.
-- Run after database/migrations/001_customers_and_offers.sql and after the
-- real product catalog has been imported. Prices are calculated from the
-- current catalog prices; this script does not create discounts.

INSERT INTO `me_ofertat` (`slug`,`emri`,`pershkrimi`,`cmimi`,`aktiv`)
SELECT 'set-caj-dhe-arome','Set çaj dhe aromë',
       'Përzgjedhje me çaj kamomili dhe vaj esencial lavande.',
       ROUND(SUM(p.`cmimi` * bundle.`sasia`),2),1
FROM (
  SELECT 'caj-kamomili' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'vaj-esencial-lavande',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug` AND p.`aktiv`=1 AND p.`i_dukshem_ne_web`=1
JOIN `me_produktet_stoku` AS stock ON stock.`id`=p.`id` AND stock.`stoku_aktual`>=bundle.`sasia`
HAVING COUNT(DISTINCT p.`id`)=2
ON DUPLICATE KEY UPDATE
  `emri`=VALUES(`emri`),`pershkrimi`=VALUES(`pershkrimi`),`cmimi`=VALUES(`cmimi`),`aktiv`=1;

INSERT INTO `me_oferta_items` (`oferta_id`,`produkt_id`,`sasia`)
SELECT offer.`id`,p.`id`,bundle.`sasia`
FROM (
  SELECT 'caj-kamomili' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'vaj-esencial-lavande',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug`
JOIN `me_ofertat` AS offer ON offer.`slug`='set-caj-dhe-arome'
ON DUPLICATE KEY UPDATE `sasia`=VALUES(`sasia`);

INSERT INTO `me_ofertat` (`slug`,`emri`,`pershkrimi`,`cmimi`,`aktiv`)
SELECT 'set-kujdes-i-perditeshem','Set kujdesi i përditshëm',
       'Përzgjedhje me çaj mente, vitaminë C me zinc dhe krem me calendula.',
       ROUND(SUM(p.`cmimi` * bundle.`sasia`),2),1
FROM (
  SELECT 'caj-mente' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'vitamina-c-me-zinc',1
  UNION ALL SELECT 'krem-me-calendula',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug` AND p.`aktiv`=1 AND p.`i_dukshem_ne_web`=1
JOIN `me_produktet_stoku` AS stock ON stock.`id`=p.`id` AND stock.`stoku_aktual`>=bundle.`sasia`
HAVING COUNT(DISTINCT p.`id`)=3
ON DUPLICATE KEY UPDATE
  `emri`=VALUES(`emri`),`pershkrimi`=VALUES(`pershkrimi`),`cmimi`=VALUES(`cmimi`),`aktiv`=1;

INSERT INTO `me_oferta_items` (`oferta_id`,`produkt_id`,`sasia`)
SELECT offer.`id`,p.`id`,bundle.`sasia`
FROM (
  SELECT 'caj-mente' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'vitamina-c-me-zinc',1
  UNION ALL SELECT 'krem-me-calendula',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug`
JOIN `me_ofertat` AS offer ON offer.`slug`='set-kujdes-i-perditeshem'
ON DUPLICATE KEY UPDATE `sasia`=VALUES(`sasia`);

INSERT INTO `me_ofertat` (`slug`,`emri`,`pershkrimi`,`cmimi`,`aktiv`)
SELECT 'set-perzgjedhje-herbabloom','Përzgjedhje HerbaBloom',
       'Set me pesë produkte të përzgjedhura nga çajrat, kujdesi bimor dhe suplementet.',
       ROUND(SUM(p.`cmimi` * bundle.`sasia`),2),1
FROM (
  SELECT 'caj-kamomili' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'vaj-esencial-lavande',1
  UNION ALL SELECT 'krem-me-aloe-vera',1
  UNION ALL SELECT 'vitamina-c-me-zinc',1
  UNION ALL SELECT 'spirulina',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug` AND p.`aktiv`=1 AND p.`i_dukshem_ne_web`=1
JOIN `me_produktet_stoku` AS stock ON stock.`id`=p.`id` AND stock.`stoku_aktual`>=bundle.`sasia`
HAVING COUNT(DISTINCT p.`id`)=5
ON DUPLICATE KEY UPDATE
  `emri`=VALUES(`emri`),`pershkrimi`=VALUES(`pershkrimi`),`cmimi`=VALUES(`cmimi`),`aktiv`=1;

INSERT INTO `me_oferta_items` (`oferta_id`,`produkt_id`,`sasia`)
SELECT offer.`id`,p.`id`,bundle.`sasia`
FROM (
  SELECT 'caj-kamomili' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'vaj-esencial-lavande',1
  UNION ALL SELECT 'krem-me-aloe-vera',1
  UNION ALL SELECT 'vitamina-c-me-zinc',1
  UNION ALL SELECT 'spirulina',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug`
JOIN `me_ofertat` AS offer ON offer.`slug`='set-perzgjedhje-herbabloom'
ON DUPLICATE KEY UPDATE `sasia`=VALUES(`sasia`);
