CREATE DATABASE IF NOT EXISTS pool_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pool_management;

CREATE TABLE organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','admin','attendant','accountant') NOT NULL DEFAULT 'attendant',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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

CREATE TABLE tables (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  table_number VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  status ENUM('available','payment_pending','playing','offline') NOT NULL DEFAULT 'available',
  mqtt_topic VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_table_number (organization_id, table_number),
  CONSTRAINT fk_tables_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  table_id BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('paybill','stk_push') NOT NULL DEFAULT 'paybill',
  phone_number VARCHAR(20) NULL,
  amount DECIMAL(10,2) NOT NULL,
  account_reference VARCHAR(100) NULL,
  merchant_request_id VARCHAR(100) NULL,
  checkout_request_id VARCHAR(100) NULL,
  mpesa_receipt VARCHAR(100) NULL,
  transaction_id VARCHAR(100) NULL,
  status ENUM('pending','confirmed','failed','cancelled') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  raw_callback JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_table FOREIGN KEY (table_id) REFERENCES tables(id),
  UNIQUE KEY uq_checkout_request (checkout_request_id),
  UNIQUE KEY uq_mpesa_receipt (mpesa_receipt),
  UNIQUE KEY uq_transaction_id (transaction_id)
) ENGINE=InnoDB;

CREATE TABLE games (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  table_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NULL,
  ended_at DATETIME NULL,
  duration_seconds INT UNSIGNED NULL,
  status ENUM('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_games_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_games_table FOREIGN KEY (table_id) REFERENCES tables(id),
  CONSTRAINT fk_games_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE mqtt_commands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  table_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  game_id BIGINT UNSIGNED NULL,
  topic VARCHAR(255) NOT NULL,
  command ENUM('unlock','lock') NOT NULL,
  status ENUM('queued','published','failed') NOT NULL DEFAULT 'queued',
  published_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_commands_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_commands_table FOREIGN KEY (table_id) REFERENCES tables(id),
  CONSTRAINT fk_commands_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
  CONSTRAINT fk_commands_game FOREIGN KEY (game_id) REFERENCES games(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_payments_org_status_created ON payments(organization_id, status, created_at);
CREATE INDEX idx_games_org_status_started ON games(organization_id, status, started_at);
CREATE INDEX idx_commands_org_status_created ON mqtt_commands(organization_id, status, created_at);
