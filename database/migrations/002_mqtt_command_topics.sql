USE pool_management;

UPDATE tables
SET mqtt_topic = CONCAT('pool/', table_number, '/command')
WHERE mqtt_topic LIKE 'pool/%/unlock';
