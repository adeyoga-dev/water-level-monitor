CREATE DATABASE IF NOT EXISTS water_level_detection CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE water_level_detection;

CREATE TABLE IF NOT EXISTS firmwares (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(64) NOT NULL UNIQUE,
    file_path VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    device_status TINYINT UNSIGNED NOT NULL DEFAULT 0,
    water_level DOUBLE NULL,
    update_interval INT UNSIGNED NOT NULL DEFAULT 60,
    alarm_threshold DOUBLE NOT NULL,
    buzzer_status TINYINT UNSIGNED NOT NULL DEFAULT 0,
    current_firmware VARCHAR(64) NULL,
    reported_firmware VARCHAR(64) NULL,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS device_alert_ranges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    alarm_status TINYINT UNSIGNED NOT NULL,
    min_level DOUBLE NULL,
    max_level DOUBLE NULL,
    UNIQUE KEY device_alert (device_id, alarm_status),
    CONSTRAINT fk_range_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS sensor_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    water_level DOUBLE NOT NULL,
    alarm_status TINYINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    KEY logs_device_time (device_id, created_at, id),
    KEY logs_time (created_at, id),
    KEY logs_alarm_time (alarm_status, created_at),
    CONSTRAINT fk_log_device FOREIGN KEY (device_id) REFERENCES devices (device_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB;