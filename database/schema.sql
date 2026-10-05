CREATE DATABASE IF NOT EXISTS pool_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pool_management;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tables_pool (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  table_number VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  status ENUM('available','payment_pending','playing','offline') NOT NULL DEFAULT 'available',
  mqtt_topic VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_sessions (
  id CHAR(36) PRIMARY KEY,
  table_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('QR','MANUAL_MPESA','ASSISTED') NOT NULL,
  phone_number VARCHAR(20) NULL,
  account_reference VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('pending','completed','failed','expired','cancelled') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_sessions_table FOREIGN KEY (table_id) REFERENCES tables_pool(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id CHAR(36) NULL,
  table_id BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('QR','MANUAL_MPESA','ASSISTED') NOT NULL,
  phone_number VARCHAR(20) NULL,
  amount DECIMAL(10,2) NOT NULL,
  account_reference VARCHAR(100) NULL,
  merchant_request_id VARCHAR(100) NULL,
  checkout_request_id VARCHAR(100) NULL,
  mpesa_receipt VARCHAR(100) NULL,
  transaction_id VARCHAR(100) NULL,
  status ENUM('pending','confirmed','failed','cancelled','reversed') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  raw_callback JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_session FOREIGN KEY (session_id) REFERENCES payment_sessions(id),
  CONSTRAINT fk_payments_table FOREIGN KEY (table_id) REFERENCES tables_pool(id),
  UNIQUE KEY uq_checkout_request (checkout_request_id),
  UNIQUE KEY uq_mpesa_receipt (mpesa_receipt)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  table_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NULL,
  ended_at DATETIME NULL,
  duration_seconds INT UNSIGNED NULL,
  status ENUM('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_games_table FOREIGN KEY (table_id) REFERENCES tables_pool(id),
  CONSTRAINT fk_games_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mqtt_commands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  table_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  game_id BIGINT UNSIGNED NULL,
  topic VARCHAR(255) NOT NULL,
  command VARCHAR(100) NOT NULL,
  status ENUM('queued','published','failed') NOT NULL DEFAULT 'queued',
  published_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_commands_table FOREIGN KEY (table_id) REFERENCES tables_pool(id),
  CONSTRAINT fk_commands_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
  CONSTRAINT fk_commands_game FOREIGN KEY (game_id) REFERENCES games(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_payments_status_created ON payments(status, created_at);
CREATE INDEX idx_payments_table_created ON payments(table_id, created_at);
CREATE INDEX idx_games_status_started ON games(status, started_at);
CREATE INDEX idx_commands_status_created ON mqtt_commands(status, created_at);
CREATE INDEX idx_sessions_reference ON payment_sessions(account_reference);

INSERT IGNORE INTO tables_pool (table_number, name, price, status, mqtt_topic) VALUES
('TABLE01','Table 01',30.00,'available','pool/TABLE01/unlock'),
('TABLE02','Table 02',30.00,'available','pool/TABLE02/unlock'),
('TABLE03','Table 03',30.00,'available','pool/TABLE03/unlock'),
('TABLE04','Table 04',30.00,'available','pool/TABLE04/unlock'),
('TABLE05','Table 05',30.00,'available','pool/TABLE05/unlock');
