USE pool_management;

-- Run once against the existing v0.2 local database.
-- Back up the database first. This migration preserves existing tables and payments.

CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO organizations (id, name, slug)
VALUES (1, 'Default Pool Hall', 'default-pool-hall')
ON DUPLICATE KEY UPDATE name = VALUES(name);

ALTER TABLE users ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE users MODIFY role ENUM('owner','admin','attendant','accountant') NOT NULL DEFAULT 'attendant';
UPDATE users SET organization_id = 1 WHERE organization_id IS NULL;
ALTER TABLE users ADD CONSTRAINT fk_users_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

CREATE TABLE mpesa_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL UNIQUE,
  payment_type ENUM('paybill','till') NOT NULL DEFAULT 'paybill',
  paybill_number VARCHAR(30) NOT NULL,
  account_prefix VARCHAR(50) NOT NULL DEFAULT 'TABLE',
  consumer_key VARCHAR(255) NULL,
  consumer_secret VARCHAR(255) NULL,
  passkey VARCHAR(255) NULL,
  shortcode VARCHAR(30) NULL,
  environment ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  confirmation_url VARCHAR(500) NULL,
  validation_url VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_mpesa_settings_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO mpesa_settings (organization_id, payment_type, paybill_number, account_prefix)
VALUES (1, 'paybill', 'CHANGE_ME', 'TABLE');

ALTER TABLE tables ADD COLUMN organization_id BIGINT UNSIGNED NULL;
UPDATE tables SET organization_id = 1 WHERE organization_id IS NULL;
ALTER TABLE tables DROP INDEX table_number;
ALTER TABLE tables ADD UNIQUE KEY uq_org_table_number (organization_id, table_number);
ALTER TABLE tables MODIFY organization_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE tables ADD CONSTRAINT fk_tables_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

ALTER TABLE payments ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE payments ADD COLUMN raw_callback JSON NULL;
UPDATE payments p INNER JOIN tables t ON t.id = p.table_id SET p.organization_id = t.organization_id WHERE p.organization_id IS NULL;
ALTER TABLE payments MODIFY organization_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE payments ADD UNIQUE KEY uq_transaction_id (transaction_id);
ALTER TABLE payments ADD CONSTRAINT fk_payments_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;
ALTER TABLE payments MODIFY payment_method ENUM('paybill','stk_push') NOT NULL DEFAULT 'paybill';

ALTER TABLE games ADD COLUMN organization_id BIGINT UNSIGNED NULL;
UPDATE games g INNER JOIN tables t ON t.id = g.table_id SET g.organization_id = t.organization_id WHERE g.organization_id IS NULL;
ALTER TABLE games MODIFY organization_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE games ADD CONSTRAINT fk_games_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

ALTER TABLE mqtt_commands ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE mqtt_commands MODIFY command ENUM('unlock','lock') NOT NULL;
UPDATE mqtt_commands c INNER JOIN tables t ON t.id = c.table_id SET c.organization_id = t.organization_id WHERE c.organization_id IS NULL;
ALTER TABLE mqtt_commands MODIFY organization_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE mqtt_commands ADD CONSTRAINT fk_commands_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

ALTER TABLE audit_logs ADD COLUMN organization_id BIGINT UNSIGNED NULL;
UPDATE audit_logs a INNER JOIN users u ON u.id = a.user_id SET a.organization_id = u.organization_id WHERE a.organization_id IS NULL AND a.user_id IS NOT NULL;
ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

CREATE INDEX idx_payments_org_status_created ON payments(organization_id, status, created_at);
CREATE INDEX idx_games_org_status_started ON games(organization_id, status, started_at);
CREATE INDEX idx_commands_org_status_created ON mqtt_commands(organization_id, status, created_at);
