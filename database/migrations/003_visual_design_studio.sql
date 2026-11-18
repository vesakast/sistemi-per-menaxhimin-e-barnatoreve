-- Stores the administrator's unpublished design draft and the visitor-facing design.
-- Run once after the existing customer/offer migrations.
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
