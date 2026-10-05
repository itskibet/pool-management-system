USE pool_management;

ALTER TABLE mqtt_commands
  MODIFY status ENUM('queued','processing','published','failed') NOT NULL DEFAULT 'queued',
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
