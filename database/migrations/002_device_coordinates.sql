-- Existing installations only: select the application database first.
-- Run once. Fresh installations already include these columns in schema.sql.
-- Only the current location is stored. No location history is created.
ALTER TABLE devices
ADD COLUMN latitude DECIMAL(10, 7) NULL AFTER water_level,
ADD COLUMN longitude DECIMAL(11, 7) NULL AFTER latitude;