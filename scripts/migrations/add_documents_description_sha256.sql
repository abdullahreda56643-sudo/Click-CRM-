-- Migration: add description and sha256 to documents
ALTER TABLE documents
ADD COLUMN description TEXT NULL AFTER file_type,
ADD COLUMN sha256 VARCHAR(64) NULL AFTER description;
