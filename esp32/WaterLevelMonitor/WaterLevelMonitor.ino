// Arduino ESP32 core 3.x; install ArduinoJson 7.x. Select an OTA-capable partition.
#include <WiFi.h>
#include <HTTPClient.h>
#include <Update.h>
#include <ArduinoJson.h>
#include <mbedtls/sha256.h>
#include <math.h>

const char* WIFI_SSID = "YOUR_WIFI";
const char* WIFI_PASSWORD = "YOUR_PASSWORD";
const String SERVER_URL = "http://192.168.1.10:8000";
const String DEVICE_ID = "ESP32-001";
const String FIRMWARE_VERSION = "v1.0.0";
const int BUZZER_PIN = 25; // Active buzzer; adapt pin and voltage to your board.
const bool BUZZER_ACTIVE_HIGH = true;
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
  // Replace with the real sensor driver and calibration.
  // For an ultrasonic sensor: water height = mounting height - measured distance.
  // Return NAN when the sensor is disconnected or measurement is invalid.
  return NAN;
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
    if (!sensorValid) Serial.println("No valid sensor reading. Configure readWaterLevelCm(); telemetry skipped.");
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
