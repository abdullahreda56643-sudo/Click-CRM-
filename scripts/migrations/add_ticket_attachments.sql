-- Migration: add_ticket_attachments
-- Run this SQL against the application's database to create attachments table for tickets

CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ticket_id` INT NOT NULL,
  `reply_id` INT DEFAULT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `mime` VARCHAR(255) DEFAULT NULL,
  `size` INT DEFAULT 0,
  `uploaded_by` INT DEFAULT NULL,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY (`ticket_id`),
  KEY (`reply_id`),
  CONSTRAINT `fk_ticket_attachments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_attachments_reply` FOREIGN KEY (`reply_id`) REFERENCES `ticket_replies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
