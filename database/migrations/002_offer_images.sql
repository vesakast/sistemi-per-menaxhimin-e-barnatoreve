-- Add optional offer image URLs to an existing installation.
-- Run once after database/migrations/001_customers_and_offers.sql.

ALTER TABLE `me_ofertat`
  ADD COLUMN `imazhi` varchar(255) DEFAULT NULL AFTER `pershkrimi`;
