// Arduino ESP32 core 3.x; install ArduinoJson 7.x. Select an OTA-capable partition.
#include <WiFi.h>
#include <HTTPClient.h>
#include <Update.h>
#include <ArduinoJson.h>
#include <mbedtls/sha256.h>
#include <math.h>
#include <HardwareSerial.h>

const char* WIFI_SSID = "YOUR_WIFI";
const char* WIFI_PASSWORD = "YOUR_PASSWORD";
const String SERVER_URL = "http://192.168.1.10:8000";
const String DEVICE_ID = "ESP32-001";
const String FIRMWARE_VERSION = "v1.0.0";
const int BUZZER_PIN = 25; // Active buzzer; adapt pin and voltage to your board.
const bool BUZZER_ACTIVE_HIGH = true;
// DYP-A01ANYUB V2 UART auto output; ESP32 DevKit / WROOM-32.
// Sensor TX -> GPIO 26 at 3.3 V logic. Sensor RX stays unconnected (processed mode).
HardwareSerial sensorSerial(2);
const int SENSOR_RX_PIN = 26;
const int SENSOR_TX_PIN = 27; // ESP32 UART TX: leave physically unconnected.
const uint32_t SENSOR_BAUD = 9600;
const float SENSOR_MOUNT_HEIGHT_CM = 200.0f; // Sensor face to the zero-water reference.
const float SENSOR_MIN_DISTANCE_CM = 28.0f;
const float SENSOR_MAX_DISTANCE_CM = 750.0f;
const uint32_t SENSOR_READ_TIMEOUT_MS = 1800;
const uint32_t FRAME_GAP_TIMEOUT_MS = 50;
const uint8_t SENSOR_SAMPLES = 5;
const uint8_t MIN_VALID_SAMPLES = 3;
const uint32_t SENSOR_INTERVAL_MS = 5000;
const uint32_t OTA_RETRY_MS = 300000;

float latestWaterLevel = NAN;
float alarmThreshold = 0;
bool buzzerEnabled = false;
bool hasConfig = false;
bool sensorValid = false;
uint32_t sendIntervalMs = 60000;
uint32_t lastSample = 0, lastSend = 0, lastWifiTry = 0;
uint32_t lastConfigTry = 0, lastOtaTry = 0;
bool otaAttempted = false;
String latestFirmware, otaUrl, firmwareSha256;

float readWaterLevelCm() {
    float distances[SENSOR_SAMPLES];
    uint8_t count = 0;
    uint8_t frame[4] = {};
    uint8_t frameSize = 0;
    if (!isfinite(SENSOR_MOUNT_HEIGHT_CM) || SENSOR_MOUNT_HEIGHT_CM <= SENSOR_MIN_DISTANCE_CM ||
        SENSOR_MOUNT_HEIGHT_CM > SENSOR_MAX_DISTANCE_CM) {
        Serial.println("Invalid sensor mounting height. Check calibration constants.");
        return NAN;
    }
    // Drop buffered readings from before HTTP/OTA delays; only use fresh frames.
    int buffered = sensorSerial.available();
    while (buffered-- > 0) sensorSerial.read();
    uint32_t started = millis();
    uint32_t lastByte = started;
    while (count < SENSOR_SAMPLES && (uint32_t)(millis() - started) < SENSOR_READ_TIMEOUT_MS) {
        if ((uint32_t)(millis() - lastByte) > FRAME_GAP_TIMEOUT_MS) frameSize = 0;
        if (!sensorSerial.available()) {
            delay(1);
            continue;
        }
        int received = sensorSerial.read();
        if (received < 0) continue;
        lastByte = millis();
        if (frameSize == 0 && received != 0xFF) continue;
        frame[frameSize++] = static_cast<uint8_t>(received);
        if (frameSize < 4) continue;
        uint8_t checksum = static_cast<uint8_t>(frame[0] + frame[1] + frame[2]);
        if (checksum != frame[3]) {
            // Slide to a possible next header, including 0xFF inside a damaged frame.
            uint8_t next = 1;
            while (next < 4 && frame[next] != 0xFF) next++;
            frameSize = 4 - next;
            for (uint8_t index = 0; index < frameSize; index++) frame[index] = frame[next + index];
            continue;
        }
        uint16_t distanceMm = (static_cast<uint16_t>(frame[1]) << 8) | frame[2];
        frameSize = 0;
        float distance = distanceMm / 10.0f;
        // 280 mm may mean the target is anywhere inside the blind zone.
        if (distance <= SENSOR_MIN_DISTANCE_CM || distance > SENSOR_MAX_DISTANCE_CM) {
            Serial.printf("UART distance out of usable range: %u mm\n", static_cast<unsigned int>(distanceMm));
            return NAN;
        }
        // Insert in order so isolated distance spikes cannot dominate the median.
        uint8_t position = count;
        while (position > 0 && distances[position - 1] > distance) {
            distances[position] = distances[position - 1];
            position--;
        }
        distances[position] = distance;
        count++;
    }
    if (count < MIN_VALID_SAMPLES) {
        Serial.println("Not enough fresh UART frames. Check sensor power, TX wiring and baud rate.");
        return NAN;
    }
    float distance = count % 2 == 0
        ? (distances[count / 2 - 1] + distances[count / 2]) / 2.0f
        : distances[count / 2];
    float level = SENSOR_MOUNT_HEIGHT_CM - distance;
    // Reject impossible calibration results instead of reporting a false zero.
    if (!isfinite(level) || level < 0) return NAN;
    Serial.printf("Distance: %.1f cm | Water level: %.1f cm | Valid frames: %u/%u\n",
        distance, level, static_cast<unsigned int>(count), static_cast<unsigned int>(SENSOR_SAMPLES));
    return level;
}

void setBuzzer(bool on) {
  digitalWrite(BUZZER_PIN, (on == BUZZER_ACTIVE_HIGH) ? HIGH : LOW);
}
void evaluateBuzzer() {
  setBuzzer(hasConfig && sensorValid && buzzerEnabled && latestWaterLevel > alarmThreshold);
}

bool isNewerVersion(String remote, String local) {
  unsigned long r[3], l[3];
  if (sscanf(remote.c_str(), "v%lu.%lu.%lu", &r[0], &r[1], &r[2]) != 3 ||
      sscanf(local.c_str(), "v%lu.%lu.%lu", &l[0], &l[1], &l[2]) != 3) return false;
  for (int i=0; i<3; i++) {
    if (r[i] > l[i]) return true;
    if (r[i] < l[i]) return false;
  }
  return false;
}

bool applyConfig(const String& body) {
  JsonDocument doc;
  if (deserializeJson(doc, body) || doc["status"].as<String>() != "success") return false;
  if (!doc["alarm_threshold"].is<double>() || !doc["buzzer_status"].is<int>() || !doc["update_interval"].is<uint32_t>()) return false;
  uint32_t seconds = doc["update_interval"].as<uint32_t>();
  int enabled = doc["buzzer_status"].as<int>();
  float threshold = doc["alarm_threshold"].as<float>();
  if (seconds < 1 || seconds > 86399 || !isfinite(threshold) || (enabled != 0 && enabled != 1)) return false;
  alarmThreshold = threshold;
  buzzerEnabled = enabled == 1;
  sendIntervalMs = seconds * 1000UL;
  hasConfig = true;
  latestFirmware = doc["latest_firmware"].is<const char*>() ? doc["latest_firmware"].as<String>() : "";
  otaUrl = doc["ota_url"].is<const char*>() ? doc["ota_url"].as<String>() : "";
  firmwareSha256 = doc["firmware_sha256"].is<const char*>() ? doc["firmware_sha256"].as<String>() : "";
  evaluateBuzzer(); // A disabled flag turns the buzzer off immediately.
  return true;
}

bool fetchConfig() {
  WiFiClient client;
  HTTPClient http;
  if (!http.begin(client, SERVER_URL + "/api/device-config.php?device_id=" + DEVICE_ID)) return false;
  http.setTimeout(10000);
  int status = http.GET();
  bool ok = status == 200 && applyConfig(http.getString());
  if (!ok) Serial.printf("Config failed: HTTP %d\n", status);
  http.end();
  return ok;
}

void sendTelemetry() {
  WiFiClient client;
  HTTPClient http;
  if (!http.begin(client, SERVER_URL + "/api/telemetry.php")) return;
  http.setTimeout(10000);
  http.addHeader("Content-Type", "application/json");
  JsonDocument doc;
  doc["device_id"] = DEVICE_ID;
  doc["water_level"] = latestWaterLevel;
  doc["device_status"] = 1;
  doc["firmware_version"] = FIRMWARE_VERSION;
  String body;
  serializeJson(doc, body);
  int status = http.POST(body);
  bool ok = status == 200 && applyConfig(http.getString());
  Serial.printf("Telemetry: HTTP %d, config %s\n", status, ok ? "OK" : "failed");
  http.end();
}

void tryOta() {
  if (!hasConfig || !isNewerVersion(latestFirmware, FIRMWARE_VERSION) || firmwareSha256.length() != 64) return;
  // Only download from this configured server. This example uses HTTP on a LAN.
  if (!otaUrl.startsWith(SERVER_URL + "/api/firmware-download.php?id=")) return;
  if (otaAttempted && (uint32_t)(millis() - lastOtaTry) < OTA_RETRY_MS) return;
  otaAttempted = true;
  lastOtaTry = millis();
  WiFiClient client;
  HTTPClient http;
  if (!http.begin(client, otaUrl)) return;
  http.setTimeout(15000);
  int status = http.GET();
  int length = http.getSize();
  if (status != 200 || length <= 0 || !Update.begin((size_t)length, U_FLASH)) {
    Serial.printf("OTA could not start: HTTP %d, size %d\n", status, length);
    http.end();
    return;
  }
  mbedtls_sha256_context sha;
  mbedtls_sha256_init(&sha);
  bool ok = mbedtls_sha256_starts(&sha, 0) == 0;
  uint8_t buffer[1024];
  int remaining = length;
  uint32_t lastData = millis();
  WiFiClient* stream = http.getStreamPtr();
  while (ok && remaining > 0) {
    int available = stream->available();
    if (available > 0) {
      size_t wanted = min((size_t)remaining, min((size_t)available, sizeof(buffer)));
      int received = stream->read(buffer, wanted);
      if (received <= 0) { ok = false; break; }
      ok = mbedtls_sha256_update(&sha, buffer, received) == 0;
      if (ok) ok = Update.write(buffer, received) == (size_t)received;
      remaining -= received;
      lastData = millis();
    } else if (!http.connected() || (uint32_t)(millis() - lastData) > 15000) {
      ok = false;
    }
    // Continue sensor/buzzer checks while a firmware download is in progress.
    if ((uint32_t)(millis() - lastSample) >= SENSOR_INTERVAL_MS) {
      lastSample = millis();
      latestWaterLevel = readWaterLevelCm();
      sensorValid = isfinite(latestWaterLevel);
      evaluateBuzzer();
    }
    delay(1);
  }
  unsigned char digest[32];
  if (ok) ok = mbedtls_sha256_finish(&sha, digest) == 0;
  mbedtls_sha256_free(&sha);
  String checksum;
  if (ok) {
    char hex[3];
    for (int i=0; i<32; i++) { snprintf(hex, sizeof(hex), "%02x", digest[i]); checksum += hex; }
    ok = checksum.equalsIgnoreCase(firmwareSha256);
  }
  if (ok && remaining == 0) ok = Update.end() && Update.isFinished();
  else ok = false;
  if (!ok) Update.abort();
  http.end();
  if (ok) {
    Serial.println("OTA verified and flashed. Rebooting...");
    setBuzzer(false);
    delay(500);
    ESP.restart();
  } else Serial.println("OTA failed; current firmware stays active. Retry in 5 minutes.");
}

void setup() {
  Serial.begin(115200);
    sensorSerial.begin(SENSOR_BAUD, SERIAL_8N1, SENSOR_RX_PIN, SENSOR_TX_PIN);
  pinMode(BUZZER_PIN, OUTPUT);
  setBuzzer(false);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  lastSample = millis() - SENSOR_INTERVAL_MS;
  lastSend = millis() - sendIntervalMs;
  lastConfigTry = millis() - 10000;
}

void loop() {
  uint32_t t = millis();
  if ((uint32_t)(t-lastSample) >= SENSOR_INTERVAL_MS) {
    lastSample=t;
    latestWaterLevel=readWaterLevelCm();
    sensorValid=isfinite(latestWaterLevel);
    if (!sensorValid) Serial.println("Invalid ultrasonic reading. Check wiring, range and mounting height; telemetry skipped.");
    evaluateBuzzer();
  }
  if (WiFi.status() != WL_CONNECTED) {
    if ((uint32_t)(t-lastWifiTry) >= 10000) { lastWifiTry=t; WiFi.reconnect(); }
    delay(5);
    return;
  }
  // Also poll config at the send interval when the sensor is invalid so admin can disable buzzer.
  if (!hasConfig || !sensorValid) {
    uint32_t poll = hasConfig ? sendIntervalMs : 10000;
    if ((uint32_t)(t-lastConfigTry) >= poll) { lastConfigTry=t; fetchConfig(); }
  }
  if (sensorValid && (uint32_t)(millis()-lastSend) >= sendIntervalMs) {
    lastSend=millis();
    sendTelemetry();
  }
  tryOta();
  delay(5);
}
