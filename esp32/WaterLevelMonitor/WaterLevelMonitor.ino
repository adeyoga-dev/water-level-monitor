// ESP32 Water Level Monitor - A01ANYUB V2 (UART) + Buzzer + Telemetry + OTA
// PlatformIO: Espressif ESP32 Dev Module, framework Arduino, library ArduinoJson 7.x
#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <Update.h>
#include <ArduinoJson.h>
#include <mbedtls/sha256.h>
#include <math.h>
#include <HardwareSerial.h>
#include <Preferences.h>
#include <esp_sleep.h>
#include <driver/gpio.h>

// Kompatibel untuk Arduino core 2.x dan 3.x
#if defined(ESP_ARDUINO_VERSION_MAJOR) && ESP_ARDUINO_VERSION_MAJOR >= 3
#define SHA_STARTS(c) mbedtls_sha256_starts((c), 0)
#define SHA_UPDATE(c, b, l) mbedtls_sha256_update((c), (b), (l))
#define SHA_FINISH(c, d) mbedtls_sha256_finish((c), (d))
#else
#define SHA_STARTS(c) mbedtls_sha256_starts_ret((c), 0)
#define SHA_UPDATE(c, b, l) mbedtls_sha256_update_ret((c), (b), (l))
#define SHA_FINISH(c, d) mbedtls_sha256_finish_ret((c), (d))
#endif

// ===== Pengaturan WiFi & Server =====
const char *WIFI_SSID = "DIAN K.R";
const char *WIFI_PASSWORD = "DQR138Bu";
const String SERVER_URL = "http://192.168.18.51:8000";
const String DEVICE_ID = "ESP-001";
const String FIRMWARE_VERSION = "v1.0.0";

// ===== Buzzer =====
const int BUZZER_PIN = 32; // Buzzer aktif di GPIO 32
const bool BUZZER_ACTIVE_HIGH = false;

// ===== Sensor A01ANYUB V2 (UART) =====
// Putih (TX sensor) -> GPIO 16, Kuning (RX sensor) -> GPIO 17
HardwareSerial sensorSerial(2);
const int SENSOR_RX_PIN = 16;
const int SENSOR_TX_PIN = 17;
const uint32_t SENSOR_BAUD = 9600;
const float SENSOR_MOUNT_HEIGHT_CM = 700.0f; // Sensor dipasang 7 m dari dasar, menghadap ke bawah
const float SENSOR_MIN_DISTANCE_CM = 28.0f;
const float SENSOR_MAX_DISTANCE_CM = 750.0f;
const uint32_t SENSOR_STALE_MS = 3000; // data dianggap basi setelah 3 detik
const uint32_t SENSOR_INTERVAL_MS = 5000;
const uint32_t OTA_RETRY_MS = 300000;

// ===== Setting manual (dipakai kalau belum pernah dapat config dari server) =====
const float DEFAULT_ALARM_THRESHOLD = 500.0f; // cm (tinggi air), buzzer bunyi jika air di atas ini
const bool DEFAULT_BUZZER_ENABLED = true;
const uint32_t DEFAULT_INTERVAL_S = 60;

// ===== Mode sleep =====
// true  = ESP32 deep sleep di antara pengiriman (hemat daya)
// false = selalu menyala
const bool SLEEP_ENABLED = false;
const uint32_t WIFI_TIMEOUT_MS = 15000; // batas tunggu WiFi sebelum lanjut offline
const uint32_t SENSOR_WARMUP_MS = 2000; // tunggu sensor siap setelah dinyalakan

// ===== Kontrol daya sensor lewat MOSFET (hemat baterai) =====
// true = sensor hanya diberi daya saat ESP32 bangun, mati total saat sleep
const bool SENSOR_POWER_CONTROL = false;
const int SENSOR_POWER_PIN = 33;            // ke Gate MOSFET
const bool SENSOR_POWER_ACTIVE_HIGH = true; // N-MOSFET low-side = true
const uint32_t MAX_AWAKE_MS = 30000;        // batas maksimal menyala per siklus

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

// ===== Pembacaan sensor =====
uint8_t buf[4];
int lastJarakMm = -1;
uint32_t lastJarakMs = 0;

// Membaca satu paket data, mengembalikan jarak dalam mm (-1 jika gagal)
int bacaJarak()
{
  while (sensorSerial.available() >= 4)
  {
    if (sensorSerial.read() != 0xFF)
      continue; // cari header

    buf[0] = 0xFF;
    buf[1] = sensorSerial.read();
    buf[2] = sensorSerial.read();
    buf[3] = sensorSerial.read();

    uint8_t sum = (buf[0] + buf[1] + buf[2]) & 0xFF;
    if (sum == buf[3])
    {
      return (buf[1] << 8) | buf[2];
    }
  }
  return -1;
}

// Dipanggil terus di loop: simpan jarak terakhir yang valid
void pollSensor()
{
  int jarak = bacaJarak();
  // Abaikan bacaan di luar jangkauan sensor (28 cm - 750 cm)
  if (jarak >= SENSOR_MIN_DISTANCE_CM * 10 && jarak <= SENSOR_MAX_DISTANCE_CM * 10)
  {
    lastJarakMm = jarak;
    lastJarakMs = millis();
  }
}

// Hitung tinggi air dari dasar (cm)
float readWaterLevelCm()
{
  pollSensor();
  if (lastJarakMm <= 0 || (uint32_t)(millis() - lastJarakMs) > SENSOR_STALE_MS)
  {
    Serial.println("Sensor belum mengirim data baru.");
    return NAN;
  }

  float distance = lastJarakMm / 10.0f;
  float tinggi = SENSOR_MOUNT_HEIGHT_CM - distance;
  if (tinggi < 0)
    tinggi = 0;

  Serial.printf("Tinggi air: %.1f cm\n", tinggi);
  return tinggi;
}

void setBuzzer(bool on)
{
  digitalWrite(BUZZER_PIN, (on == BUZZER_ACTIVE_HIGH) ? HIGH : LOW);
}

void evaluateBuzzer()
{
  // Buzzer hanya boleh bunyi saat internet tersambung
  bool online = WiFi.status() == WL_CONNECTED;
  setBuzzer(online && hasConfig && sensorValid && buzzerEnabled && latestWaterLevel > alarmThreshold);
}

bool isNewerVersion(String remote, String local)
{
  unsigned long r[3], l[3];
  if (sscanf(remote.c_str(), "v%lu.%lu.%lu", &r[0], &r[1], &r[2]) != 3 ||
      sscanf(local.c_str(), "v%lu.%lu.%lu", &l[0], &l[1], &l[2]) != 3)
    return false;
  for (int i = 0; i < 3; i++)
  {
    if (r[i] > l[i])
      return true;
    if (r[i] < l[i])
      return false;
  }
  return false;
}

// ===== Simpan / muat config di memori flash (tetap ada saat offline & setelah sleep) =====
Preferences prefs;

void saveConfig()
{
  prefs.begin("water", false);
  if (prefs.getFloat("thr", NAN) != alarmThreshold)
    prefs.putFloat("thr", alarmThreshold);
  if (prefs.getBool("buz", !buzzerEnabled) != buzzerEnabled)
    prefs.putBool("buz", buzzerEnabled);
  if (prefs.getUInt("int", 0) != sendIntervalMs / 1000)
    prefs.putUInt("int", sendIntervalMs / 1000);
  prefs.end();
}

void loadConfig()
{
  prefs.begin("water", true);
  alarmThreshold = prefs.getFloat("thr", DEFAULT_ALARM_THRESHOLD);
  buzzerEnabled = prefs.getBool("buz", DEFAULT_BUZZER_ENABLED);
  sendIntervalMs = prefs.getUInt("int", DEFAULT_INTERVAL_S) * 1000UL;
  prefs.end();
  hasConfig = true; // pakai setting tersimpan / manual
  Serial.printf("Config lokal: threshold %.1f cm, buzzer %s, interval %lu s\n",
                alarmThreshold, buzzerEnabled ? "ON" : "OFF", (unsigned long)(sendIntervalMs / 1000));
}

bool applyConfig(const String &body)
{
  JsonDocument doc;
  if (deserializeJson(doc, body) || doc["status"].as<String>() != "success")
    return false;
  if (!doc["alarm_threshold"].is<double>() || !doc["buzzer_status"].is<int>() ||
      !doc["update_interval"].is<uint32_t>())
    return false;

  uint32_t seconds = doc["update_interval"].as<uint32_t>();
  int enabled = doc["buzzer_status"].as<int>();
  float threshold = doc["alarm_threshold"].as<float>();
  if (seconds < 1 || seconds > 86399 || !isfinite(threshold) || (enabled != 0 && enabled != 1))
    return false;

  alarmThreshold = threshold;
  buzzerEnabled = (enabled == 1);
  sendIntervalMs = seconds * 1000UL;
  hasConfig = true;
  latestFirmware = doc["latest_firmware"].is<const char *>() ? doc["latest_firmware"].as<String>() : "";
  otaUrl = doc["ota_url"].is<const char *>() ? doc["ota_url"].as<String>() : "";
  firmwareSha256 = doc["firmware_sha256"].is<const char *>() ? doc["firmware_sha256"].as<String>() : "";
  saveConfig();
  evaluateBuzzer();
  return true;
}

bool fetchConfig()
{
  WiFiClient client;
  HTTPClient http;
  if (!http.begin(client, SERVER_URL + "/api/device-config.php?device_id=" + DEVICE_ID))
    return false;
  http.setTimeout(10000);
  int status = http.GET();
  bool ok = (status == 200) && applyConfig(http.getString());
  if (!ok)
    Serial.printf("Config failed: HTTP %d\n", status);
  http.end();
  return ok;
}

void sendTelemetry()
{
  WiFiClient client;
  HTTPClient http;
  if (!http.begin(client, SERVER_URL + "/api/telemetry.php"))
    return;
  http.setTimeout(10000);
  http.addHeader("Content-Type", "application/json");

  JsonDocument doc;
  doc["device_id"] = DEVICE_ID;
  doc["water_level"] = roundf(latestWaterLevel * 10.0f) / 10.0f; // tinggi air, 1 desimal
  doc["device_status"] = 1;
  doc["firmware_version"] = FIRMWARE_VERSION;
  String body;
  serializeJson(doc, body);

  int status = http.POST(body);
  bool ok = (status == 200) && applyConfig(http.getString());
  Serial.printf("Telemetry: HTTP %d, config %s\n", status, ok ? "OK" : "failed");
  http.end();
}

void tryOta()
{
  if (!hasConfig || !isNewerVersion(latestFirmware, FIRMWARE_VERSION) || firmwareSha256.length() != 64)
    return;
  if (!otaUrl.startsWith(SERVER_URL + "/api/firmware-download.php?id="))
    return;
  if (otaAttempted && (uint32_t)(millis() - lastOtaTry) < OTA_RETRY_MS)
    return;
  otaAttempted = true;
  lastOtaTry = millis();

  WiFiClient client;
  HTTPClient http;
  if (!http.begin(client, otaUrl))
    return;
  http.setTimeout(15000);
  int status = http.GET();
  int length = http.getSize();
  if (status != 200 || length <= 0 || !Update.begin((size_t)length, U_FLASH))
  {
    Serial.printf("OTA could not start: HTTP %d, size %d\n", status, length);
    http.end();
    return;
  }

  mbedtls_sha256_context sha;
  mbedtls_sha256_init(&sha);
  bool ok = SHA_STARTS(&sha) == 0;
  uint8_t buffer[1024];
  int remaining = length;
  uint32_t lastData = millis();
  WiFiClient *stream = http.getStreamPtr();

  while (ok && remaining > 0)
  {
    int available = stream->available();
    if (available > 0)
    {
      size_t wanted = min((size_t)remaining, min((size_t)available, sizeof(buffer)));
      int received = stream->read(buffer, wanted);
      if (received <= 0)
      {
        ok = false;
        break;
      }
      ok = SHA_UPDATE(&sha, buffer, received) == 0;
      if (ok)
        ok = Update.write(buffer, received) == (size_t)received;
      remaining -= received;
      lastData = millis();
    }
    else if (!http.connected() || (uint32_t)(millis() - lastData) > 15000)
    {
      ok = false;
    }
    // Sensor & buzzer tetap dicek selama download
    pollSensor();
    if ((uint32_t)(millis() - lastSample) >= SENSOR_INTERVAL_MS)
    {
      lastSample = millis();
      latestWaterLevel = readWaterLevelCm();
      sensorValid = isfinite(latestWaterLevel);
      evaluateBuzzer();
    }
    delay(1);
  }

  unsigned char digest[32];
  if (ok)
    ok = SHA_FINISH(&sha, digest) == 0;
  mbedtls_sha256_free(&sha);

  if (ok)
  {
    String checksum;
    char hex[3];
    for (int i = 0; i < 32; i++)
    {
      snprintf(hex, sizeof(hex), "%02x", digest[i]);
      checksum += hex;
    }
    ok = checksum.equalsIgnoreCase(firmwareSha256);
  }
  if (ok && remaining == 0)
    ok = Update.end() && Update.isFinished();
  else
    ok = false;
  if (!ok)
    Update.abort();
  http.end();

  if (ok)
  {
    Serial.println("OTA verified and flashed. Rebooting...");
    setBuzzer(false);
    delay(500);
    ESP.restart();
  }
  else
  {
    Serial.println("OTA failed; current firmware stays active. Retry in 5 minutes.");
  }
}

// ===== Deep sleep =====
bool telemetryDone = false;
uint32_t bootMs = 0;

void goToSleep()
{
  uint32_t sleepS = sendIntervalMs / 1000;
  Serial.printf("Sleep %lu detik...\n", (unsigned long)sleepS);
  Serial.flush();
  setBuzzer(false);
  gpio_hold_en((gpio_num_t)BUZZER_PIN); // kunci buzzer tetap mati saat sleep
  if (SENSOR_POWER_CONTROL)
  {
    digitalWrite(SENSOR_POWER_PIN, SENSOR_POWER_ACTIVE_HIGH ? LOW : HIGH); // sensor OFF
    gpio_hold_en((gpio_num_t)SENSOR_POWER_PIN);
  }
  gpio_deep_sleep_hold_en();
  esp_sleep_enable_timer_wakeup((uint64_t)sleepS * 1000000ULL);
  esp_deep_sleep_start();
}

void setup()
{
  Serial.begin(115200);
  bootMs = millis();
  gpio_hold_dis((gpio_num_t)BUZZER_PIN); // lepas kunci pin setelah bangun
  pinMode(BUZZER_PIN, OUTPUT);
  setBuzzer(false);

  if (SENSOR_POWER_CONTROL)
  {
    gpio_hold_dis((gpio_num_t)SENSOR_POWER_PIN);
    pinMode(SENSOR_POWER_PIN, OUTPUT);
    digitalWrite(SENSOR_POWER_PIN, SENSOR_POWER_ACTIVE_HIGH ? HIGH : LOW); // sensor ON
  }

  sensorSerial.setRxBufferSize(1024);
  sensorSerial.begin(SENSOR_BAUD, SERIAL_8N1, SENSOR_RX_PIN, SENSOR_TX_PIN);

  if (esp_sleep_get_wakeup_cause() == ESP_SLEEP_WAKEUP_TIMER)
    Serial.println("Bangun dari sleep");
  Serial.println("Water Level Monitor start, server: " + SERVER_URL);
  loadConfig();

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(true); // modem sleep saat WiFi menyala
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  // Bacaan pertama setelah sensor siap
  lastSample = millis() - SENSOR_INTERVAL_MS + SENSOR_WARMUP_MS;
  lastSend = millis() - sendIntervalMs;
  lastConfigTry = millis() - 10000;
}

void loop()
{
  pollSensor(); // baca sensor terus, dengan atau tanpa internet
  uint32_t t = millis();

  // 1. Sensor & buzzer: selalu jalan, tidak tergantung WiFi
  if ((uint32_t)(t - lastSample) >= SENSOR_INTERVAL_MS)
  {
    lastSample = t;
    latestWaterLevel = readWaterLevelCm();
    sensorValid = isfinite(latestWaterLevel);
    if (!sensorValid)
      Serial.println("Pembacaan tidak valid, telemetry dilewati.");
    evaluateBuzzer();
  }

  bool online = WiFi.status() == WL_CONNECTED;

  // 2. Bagian online: kirim data, ambil config, OTA
  if (online)
  {
    if (!sensorValid && (uint32_t)(t - lastConfigTry) >= 10000)
    {
      lastConfigTry = t;
      fetchConfig();
    }
    if (sensorValid && (uint32_t)(millis() - lastSend) >= sendIntervalMs)
    {
      lastSend = millis();
      sendTelemetry();
      telemetryDone = true;
    }
    tryOta();
  }
  else
  {
    setBuzzer(false); // internet putus: buzzer langsung mati
  }
  if (!online && (uint32_t)(t - lastWifiTry) >= 10000)
  {
    lastWifiTry = t;
    Serial.println("WiFi offline: buzzer OFF, sensor tetap membaca");
    WiFi.reconnect();
  }

  // 3. Mode sleep
  if (SLEEP_ENABLED)
  {
    uint32_t awake = millis() - bootMs;
    bool alarmOn = online && buzzerEnabled && sensorValid && latestWaterLevel > alarmThreshold;
    bool selesai = telemetryDone                                          // data sudah terkirim
                   || (!online && awake > WIFI_TIMEOUT_MS && sensorValid) // offline, sudah baca sensor
                   || awake > MAX_AWAKE_MS;                               // pengaman
    if (alarmOn)
    {
      // Jangan tidur saat alarm aktif: buzzer tetap bunyi sampai kondisi aman
    }
    else if (selesai)
    {
      goToSleep();
    }
  }

  delay(5);
}
