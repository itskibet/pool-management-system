USE pool_management;

-- Platform administration, client onboarding, and daily 4% platform billing.
ALTER TABLE users
  ADD COLUMN is_system_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER active;

UPDATE users
SET is_system_admin = 1
WHERE role = 'owner' AND organization_id = 1;

CREATE TABLE daily_closings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  business_date DATE NOT NULL,
  gross_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  platform_fee_rate DECIMAL(5,2) NOT NULL DEFAULT 4.00,
  platform_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('closed') NOT NULL DEFAULT 'closed',
  closed_by BIGINT UNSIGNED NULL,
  closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_daily_closing_org_date (organization_id, business_date),
  CONSTRAINT fk_daily_closing_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_daily_closing_user FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_daily_closings_org_date ON daily_closings(organization_id, business_date);
