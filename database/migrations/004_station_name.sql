-- Keep old devices and their histories intact; administrators name existing stations.
ALTER TABLE devices ADD COLUMN station_name VARCHAR(150) NULL AFTER device_id;
