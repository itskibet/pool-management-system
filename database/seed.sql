USE pool_management;

INSERT INTO organizations (id, name, slug)
VALUES (1, 'Default Pool Hall', 'default-pool-hall')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO mpesa_settings (organization_id, payment_type, paybill_number, account_prefix)
VALUES (1, 'paybill', 'CHANGE_ME', 'TABLE')
ON DUPLICATE KEY UPDATE organization_id = VALUES(organization_id);

INSERT INTO tables (organization_id, table_number, name, price, status, mqtt_topic) VALUES
(1,'Table_01','Table 01',30.00,'available','pool/Table_01/unlock'),
(1,'Table_02','Table 02',30.00,'available','pool/Table_02/unlock'),
(1,'Table_03','Table 03',30.00,'available','pool/Table_03/unlock'),
(1,'Table_04','Table 04',30.00,'available','pool/Table_04/unlock'),
(1,'Table_05','Table 05',30.00,'available','pool/Table_05/unlock')
ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), mqtt_topic=VALUES(mqtt_topic);
