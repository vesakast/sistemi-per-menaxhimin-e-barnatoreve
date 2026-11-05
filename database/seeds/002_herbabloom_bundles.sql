-- Adds three more starter bundles from the existing public catalog.
-- Prices are the current sums of the component prices; no discounts are invented.
-- Run after seed 001 and after importing the real product catalog.
START TRANSACTION;

INSERT INTO `me_ofertat` (`slug`,`emri`,`pershkrimi`,`cmimi`,`aktiv`)
SELECT 'set-caj-dhe-vitamina','Set çaj dhe vitamina',
       'Përzgjedhje me Çaj Matcha dhe Vitaminë D3.',
       ROUND(SUM(p.`cmimi` * bundle.`sasia`),2),1
FROM (
  SELECT 'caj-matcha' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'vitamina-d3',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug` AND p.`aktiv`=1 AND p.`i_dukshem_ne_web`=1
JOIN `me_produktet_stoku` AS stock ON stock.`id`=p.`id` AND stock.`stoku_aktual`>=bundle.`sasia`
HAVING COUNT(DISTINCT p.`id`)=2
ON DUPLICATE KEY UPDATE
  `emri`=VALUES(`emri`),`pershkrimi`=VALUES(`pershkrimi`),`cmimi`=VALUES(`cmimi`),`aktiv`=1;

INSERT INTO `me_ofertat` (`slug`,`emri`,`pershkrimi`,`cmimi`,`aktiv`)
SELECT 'set-bime-dhe-vitamina','Përzgjedhje bimore dhe vitamina',
       'Set me Echinacea, Ashwagandha dhe Vitaminë C me Zinc.',
       ROUND(SUM(p.`cmimi` * bundle.`sasia`),2),1
FROM (
  SELECT 'echinacea' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'ashwagandha',1
  UNION ALL SELECT 'vitamina-c-me-zinc',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug` AND p.`aktiv`=1 AND p.`i_dukshem_ne_web`=1
JOIN `me_produktet_stoku` AS stock ON stock.`id`=p.`id` AND stock.`stoku_aktual`>=bundle.`sasia`
HAVING COUNT(DISTINCT p.`id`)=3
ON DUPLICATE KEY UPDATE
  `emri`=VALUES(`emri`),`pershkrimi`=VALUES(`pershkrimi`),`cmimi`=VALUES(`cmimi`),`aktiv`=1;

INSERT INTO `me_ofertat` (`slug`,`emri`,`pershkrimi`,`cmimi`,`aktiv`)
SELECT 'set-kujdesi-i-lekures','Set kujdesi për lëkurën',
       'Përzgjedhje me kremra Aloe Vera, Arnica dhe Calendula, së bashku me Vaj Esencial Lavande.',
       ROUND(SUM(p.`cmimi` * bundle.`sasia`),2),1
FROM (
  SELECT 'krem-me-aloe-vera' AS `slug`,1 AS `sasia`
  UNION ALL SELECT 'krem-me-arnica',1
  UNION ALL SELECT 'krem-me-calendula',1
  UNION ALL SELECT 'vaj-esencial-lavande',1
) AS bundle
JOIN `me_produktet` AS p ON p.`slug`=bundle.`slug` AND p.`aktiv`=1 AND p.`i_dukshem_ne_web`=1
JOIN `me_produktet_stoku` AS stock ON stock.`id`=p.`id` AND stock.`stoku_aktual`>=bundle.`sasia`
HAVING COUNT(DISTINCT p.`id`)=4
ON DUPLICATE KEY UPDATE
  `emri`=VALUES(`emri`),`pershkrimi`=VALUES(`pershkrimi`),`cmimi`=VALUES(`cmimi`),`aktiv`=1;

DELETE items
FROM `me_oferta_items` AS items
JOIN `me_ofertat` AS offer ON offer.`id`=items.`oferta_id`
WHERE offer.`slug` IN (
  'set-caj-dhe-vitamina',
  'set-bime-dhe-vitamina',
  'set-kujdesi-i-lekures'
);

INSERT INTO `me_oferta_items` (`oferta_id`,`produkt_id`,`sasia`)
SELECT offer.`id`,product.`id`,bundle.`sasia`
FROM (
  SELECT 'set-caj-dhe-vitamina' AS `offer_slug`,'caj-matcha' AS `product_slug`,1 AS `sasia`
  UNION ALL SELECT 'set-caj-dhe-vitamina','vitamina-d3',1
  UNION ALL SELECT 'set-bime-dhe-vitamina','echinacea',1
  UNION ALL SELECT 'set-bime-dhe-vitamina','ashwagandha',1
  UNION ALL SELECT 'set-bime-dhe-vitamina','vitamina-c-me-zinc',1
  UNION ALL SELECT 'set-kujdesi-i-lekures','krem-me-aloe-vera',1
  UNION ALL SELECT 'set-kujdesi-i-lekures','krem-me-arnica',1
  UNION ALL SELECT 'set-kujdesi-i-lekures','krem-me-calendula',1
  UNION ALL SELECT 'set-kujdesi-i-lekures','vaj-esencial-lavande',1
) AS bundle
JOIN `me_ofertat` AS offer ON offer.`slug`=bundle.`offer_slug`
JOIN `me_produktet` AS product ON product.`slug`=bundle.`product_slug`;

COMMIT;
