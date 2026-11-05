-- Existing installations only: select your application database first.
-- Do not run on a fresh database created from the current schema.sql.
-- These statements preserve the existing ranges and device relationships.
RENAME TABLE device_siaga_ranges TO device_alert_ranges;
ALTER TABLE device_alert_ranges
    DROP INDEX device_siaga,
    ADD UNIQUE KEY device_alert (device_id, alarm_status);
