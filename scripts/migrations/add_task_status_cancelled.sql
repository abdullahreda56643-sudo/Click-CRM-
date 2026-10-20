-- Migration: add 'cancelled' to tasks.status enum
ALTER TABLE tasks MODIFY COLUMN status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending';
