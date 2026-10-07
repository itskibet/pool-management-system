USE pool_management;

ALTER TABLE payments
  ADD COLUMN external_reference VARCHAR(120) NULL AFTER account_reference;

ALTER TABLE payments
  ADD UNIQUE KEY uq_payments_external_reference (external_reference);
