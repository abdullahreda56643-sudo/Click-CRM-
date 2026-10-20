-- Ensure `tasks` table has required columns, indexes, and FKs
SELECT COUNT(*) INTO @cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks';
SET @sql = IF(@cnt = 0,
  'CREATE TABLE tasks (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(200) NOT NULL,
      description TEXT,
      due_date DATE,
      priority ENUM(\'low\',\'medium\',\'high\') DEFAULT \'medium\',
      status ENUM(\'pending\',\'in_progress\',\'completed\') DEFAULT \'pending\',
      assigned_to INT NULL,
      created_by INT NULL,
      related_type ENUM(\'contact\',\'deal\',\'project\',\'ticket\') NULL,
      related_id INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )',
  'SELECT "tasks table already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure columns exist
SET @cols = 'id,title,description,due_date,priority,status,assigned_to,created_by,related_type,related_id,created_at,updated_at';
DROP TEMPORARY TABLE IF EXISTS tmp_cols;
CREATE TEMPORARY TABLE tmp_cols (col VARCHAR(100));

-- For each expected column, add if missing
SET @i = 1;
WHILE @i <= 12 DO
  SET @col = ELT(@i, 'id','title','description','due_date','priority','status','assigned_to','created_by','related_type','related_id','created_at','updated_at');
  SELECT COUNT(*) INTO @cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = @col;
  IF @cnt = 0 THEN
    IF @col = 'id' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY';
    ELSEIF @col = 'title' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN title VARCHAR(200) NOT NULL';
    ELSEIF @col = 'description' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN description TEXT';
    ELSEIF @col = 'due_date' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN due_date DATE';
    ELSEIF @col = 'priority' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN priority ENUM(\'low\',\'medium\',\'high\') DEFAULT \'medium\'';
    ELSEIF @col = 'status' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN status ENUM(\'pending\',\'in_progress\',\'completed\') DEFAULT \'pending\'';
    ELSEIF @col = 'assigned_to' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN assigned_to INT NULL';
    ELSEIF @col = 'created_by' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN created_by INT NULL';
    ELSEIF @col = 'related_type' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN related_type ENUM(\'contact\',\'deal\',\'project\',\'ticket\') NULL';
    ELSEIF @col = 'related_id' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN related_id INT NULL';
    ELSEIF @col = 'created_at' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
    ELSEIF @col = 'updated_at' THEN SET @sql = 'ALTER TABLE tasks ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
    END IF;
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
  SET @i = @i + 1;
END WHILE;

-- Indexes
SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_assigned_to';
SET @sql = IF(@cnt = 0, 'CREATE INDEX idx_tasks_assigned_to ON tasks(assigned_to)', 'SELECT "idx_tasks_assigned_to exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_status';
SET @sql = IF(@cnt = 0, 'CREATE INDEX idx_tasks_status ON tasks(status)', 'SELECT "idx_tasks_status exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign keys
SELECT COUNT(*) INTO @cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND CONSTRAINT_NAME = 'fk_tasks_assigned_to_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
SET @sql = IF(@cnt = 0, 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_assigned_to_users FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "fk exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND CONSTRAINT_NAME = 'fk_tasks_created_by_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
SET @sql = IF(@cnt = 0, 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_created_by_users FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL', 'SELECT "fk exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End

SHOW COLUMNS FROM tasks;
