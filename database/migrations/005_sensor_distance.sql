ALTER TABLE devices ADD COLUMN sensor_distance DOUBLE NULL AFTER water_level;
ALTER TABLE sensor_logs ADD COLUMN sensor_distance DOUBLE NULL AFTER water_level;
