-- Migration: add missing assigned_to columns to core tables

-- Add to contacts: add column if missing
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'assigned_to';
SET @sql = IF(@cnt = 0, 'ALTER TABLE contacts ADD COLUMN assigned_to INT NULL', 'SELECT "contacts.assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index if missing
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND INDEX_NAME = 'idx_contacts_assigned_to';
SET @sql = IF(@cnt = 0, 'CREATE INDEX idx_contacts_assigned_to ON contacts(assigned_to)', 'SELECT "idx_contacts_assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add FK constraint if missing
SELECT COUNT(*) INTO @cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND CONSTRAINT_NAME = 'fk_contacts_assigned_to_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
SET @sql = IF(@cnt = 0, 'ALTER TABLE contacts ADD CONSTRAINT fk_contacts_assigned_to_users FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "fk_contacts_assigned_to_users already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add to tasks
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'assigned_to';
SET @sql = IF(@cnt = 0, 'ALTER TABLE tasks ADD COLUMN assigned_to INT NULL', 'SELECT "tasks.assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_assigned_to';
SET @sql = IF(@cnt = 0, 'CREATE INDEX idx_tasks_assigned_to ON tasks(assigned_to)', 'SELECT "idx_tasks_assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND CONSTRAINT_NAME = 'fk_tasks_assigned_to_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
SET @sql = IF(@cnt = 0, 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_assigned_to_users FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "fk_tasks_assigned_to_users already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add to tickets
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'assigned_to';
SET @sql = IF(@cnt = 0, 'ALTER TABLE tickets ADD COLUMN assigned_to INT NULL', 'SELECT "tickets.assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND INDEX_NAME = 'idx_tickets_assigned_to';
SET @sql = IF(@cnt = 0, 'CREATE INDEX idx_tickets_assigned_to ON tickets(assigned_to)', 'SELECT "idx_tickets_assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND CONSTRAINT_NAME = 'fk_tickets_assigned_to_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
SET @sql = IF(@cnt = 0, 'ALTER TABLE tickets ADD CONSTRAINT fk_tickets_assigned_to_users FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "fk_tickets_assigned_to_users already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add to projects
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'assigned_to';
SET @sql = IF(@cnt = 0, 'ALTER TABLE projects ADD COLUMN assigned_to INT NULL', 'SELECT "projects.assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add to deals
SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deals' AND COLUMN_NAME = 'assigned_to';
SET @sql = IF(@cnt = 0, 'ALTER TABLE deals ADD COLUMN assigned_to INT NULL', 'SELECT "deals.assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deals' AND INDEX_NAME = 'idx_deals_assigned_to';
SET @sql = IF(@cnt = 0, 'CREATE INDEX idx_deals_assigned_to ON deals(assigned_to)', 'SELECT "idx_deals_assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deals' AND CONSTRAINT_NAME = 'fk_deals_assigned_to_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
SET @sql = IF(@cnt = 0, 'ALTER TABLE deals ADD CONSTRAINT fk_deals_assigned_to_users FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "fk_deals_assigned_to_users already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_assigned_to';
SET @sql = IF(@cnt = 0, 'CREATE INDEX idx_projects_assigned_to ON projects(assigned_to)', 'SELECT "idx_projects_assigned_to already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND CONSTRAINT_NAME = 'fk_projects_assigned_to_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
SET @sql = IF(@cnt = 0, 'ALTER TABLE projects ADD CONSTRAINT fk_projects_assigned_to_users FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "fk_projects_assigned_to_users already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of migration

SHOW COLUMNS FROM contacts LIKE 'assigned_to';
DESCRIBE contacts;
