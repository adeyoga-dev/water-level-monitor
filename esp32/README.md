# Firmware ESP32 — tinggi air A01ANYUB V2

Terakhir diperbarui: 9 November 2026. Peminta: pengguna proyek; departemen, owner/PIC dan maintainer: Not confirmed. Pengguna: operator ESP32 dan Admin SIPATA. Dokumen menjelaskan implementasi saat ini, bukan jaminan hasil uji perangkat fisik.

## Persiapan dan identitas

Sketch: `esp32/WaterLevelMonitor/WaterLevelMonitor.ino`. Target pada komentar kode adalah ESP32 Dev Module dengan framework Arduino, melalui PlatformIO. Kode memakai ArduinoJson 7.x serta WiFi, HTTPClient, Update, HardwareSerial, Preferences, mbedTLS, esp_sleep dan gpio dari lingkungan ESP32. Ada percabangan fungsi SHA untuk Arduino ESP32 core 2.x/3.x; kompatibilitas build belum diuji di lingkungan ini. Pilih board dan partisi dua slot aplikasi yang mendukung OTA. Firmware pertama diunggah melalui USB.

Isi Wi-Fi pada sketch tanpa menyalin kredensial ke dokumentasi. `DEVICE_ID` saat ini `ESP-001` dan harus sama persis dengan ID di menu Perangkat; Nama Pos Pantau hanya identitas tampilan. `FIRMWARE_VERSION` saat ini `v1.0.0`.

Samakan `SERVER_URL` dengan `APP_URL` web, termasuk protokol, host dan port, tanpa garis miring penutup. Gunakan alamat yang dapat dijangkau ESP32, bukan localhost. Document root web adalah `public/`. Sketch menggunakan WiFiClient/HTTP; mengganti URL menjadi HTTPS saja belum menyiapkan TLS. Akun Admin hanya untuk browser; endpoint perangkat tidak membutuhkan login Admin.

## Pin dan konstanta saat ini

| Pengaturan | Nilai | Peran |
| --- | --- | --- |
| SENSOR_RX_PIN | GPIO 16 | RX UART2 ESP32, menerima TX sensor |
| SENSOR_TX_PIN | GPIO 17 | TX UART2 ESP32, menuju RX sensor menurut wiring pada komentar sketch |
| SENSOR_BAUD | 9600, 8N1 | Komunikasi sensor |
| Buffer RX | 1024 byte | Diatur sebelum UART dimulai |
| BUZZER_PIN | GPIO 32 | Output buzzer/masukan kendali relay yang sesuai |
| BUZZER_ACTIVE_HIGH | false | LOW aktif, HIGH mati |
| SENSOR_MOUNT_HEIGHT_CM | 700 cm | Jarak muka sensor ke acuan tinggi air nol |
| SENSOR_MIN_DISTANCE_CM | 28 cm | Batas bawah yang diterima kode, inklusif |
| SENSOR_MAX_DISTANCE_CM | 750 cm | Batas atas yang diterima kode, inklusif |
| SENSOR_STALE_MS | 3000 ms | Usia maksimum frame valid saat perhitungan tinggi |
| SENSOR_INTERVAL_MS | 5000 ms | Interval menghitung tinggi dan mengevaluasi buzzer |
| SENSOR_WARMUP_MS | 2000 ms | Penjadwalan perhitungan pertama setelah startup |
| OTA_RETRY_MS | 300000 ms | Jeda minimum percobaan ulang OTA |

Komentar wiring terbaru menyebut putih/TX sensor ke GPIO 16 dan kuning/RX sensor ke GPIO 17. Cocokkan dengan label/datasheet unit serta board yang digunakan; warna kabel bukan identifikasi universal. GPIO ESP32 membutuhkan level sinyal yang sesuai. Kode UART ini tidak menggunakan Trig/Echo atau mengirim perintah polling ke sensor.

## Pengukuran dan data yang dikirim

`bacaJarak()` mencari frame empat byte `FF DATA_H DATA_L SUM`. Checksum harus sama dengan `(FF + DATA_H + DATA_L) & 0xFF`. Jarak mm adalah `(DATA_H << 8) | DATA_L`, lalu dibagi 10 menjadi cm. `pollSensor()` berjalan terus selama loop dan juga selama download OTA; hanya jarak 280 sampai 7500 mm yang menggantikan cache valid.

`readWaterLevelCm()` memakai cache terakhir. Jika belum ada nilai valid atau usianya lebih dari 3000 ms, hasilnya NAN. Jika valid:

```text
tinggi air = max(0, 700 − jarak sensor dalam cm)
```

Contoh: jarak 180 cm menghasilkan tinggi 520 cm. Nilai 700 harus sesuai pengukuran pemasangan, bukan disesuaikan agar alarm berbunyi. Jarak lebih dari tinggi pemasangan menghasilkan tinggi nol. Tidak ada validasi rentang konstanta pemasangan di fungsi ini.

Tidak ada median lima sampel, minimum tiga sampel, timeout koleksi 1,8 detik, reset jeda byte 50 ms, atau pembuangan buffer lama seperti versi sebelumnya. Tepat 28 cm diterima oleh implementasi saat ini; dokumentasi ini tidak menyatakan batas blind zone tersebut dapat diukur secara andal secara fisik. Frame salah/di luar rentang diabaikan; cache lama dapat tetap dipakai sampai dianggap basi pada evaluasi berikutnya. Karena perhitungan dilakukan setiap 5 detik, buzzer tidak dijamin berubah tepat pada detik ketiga setelah frame terakhir.

Serial Monitor 115200 baud mencetak `Tinggi air: ... cm`. Telemetry hanya membawa tinggi air, dibulatkan satu desimal sebelum serialisasi:

```json
{
    "device_id": "ESP-001",
    "water_level": 520.0,
    "device_status": 1,
    "firmware_version": "v1.0.0"
}
```

JSON dapat menuliskan angka bulat tanpa `.0`; nilainya tetap numerik cm. Jarak sensor dan `measurement_type` tidak dikirim oleh sketch terbaru. Web menerima penanda tipe yang tidak disertakan sebagai kontrak tinggi air. `POST /api/telemetry.php` memakai Content-Type application/json. Web mencatat tinggi dan Siaga secara transaksional di devices/sensor_logs. Kolom sensor_distance yang pernah dimigrasikan dipertahankan sebagai kolom tidak terpakai; pembacaan baru dan UI hanya memakai tinggi air. Data historis tidak dikonversi.

## Buzzer, konfigurasi lokal dan web

Kondisi aktif pada `evaluateBuzzer()` adalah Wi-Fi berstatus WL_CONNECTED, hasConfig, sensorValid, buzzerEnabled, dan tinggi air lebih besar dari alarmThreshold. Dengan ambang 500 cm: 499,9 dan 500 mati; 500,1 aktif. Karena aktif LOW, aktif menghasilkan GPIO 32 LOW; mati menghasilkan HIGH. Polaritas harus cocok dengan perangkat fisik.

Default bila kunci Preferences belum tersedia: ambang 500 cm, buzzer aktif, interval kirim 60 detik. Saat boot, `loadConfig()` membaca namespace NVS `water`, kunci `thr`, `buz` dan `int`, lalu menetapkan hasConfig=true. Nilai yang pernah disimpan lebih diutamakan daripada default; mengubah DEFAULT_ALARM_THRESHOLD tidak menimpa NVS lama.

Respons GET `/api/device-config.php?device_id=ESP-001` dan POST telemetry diterapkan oleh `applyConfig()`: status success, alarm_threshold numerik finite, buzzer_status integer 0/1, update_interval integer 1–86399 detik. Nilai valid menggantikan konfigurasi, disimpan ke Preferences bila berubah, dan buzzer dievaluasi kembali. Versi/URL/checksum OTA tidak disimpan ke Preferences.

Sensor valid: konfigurasi diterima melalui respons telemetry sesuai interval yang aktif. Sensor invalid: GET konfigurasi dicoba setiap 10 detik ketika Wi-Fi terhubung. Tidak ada GET wajib terpisah saat boot jika sensor sudah valid. Perubahan interval di web menunggu request berikutnya; interval kirim tidak mengubah interval penghitungan sensor 5 detik. Interval kirim lebih singkat dapat mengirim tinggi yang sama berulang kali.

Wi-Fi putus: loop mematikan buzzer dan mencoba reconnect setiap 10 detik; sensor tetap dipoll. WL_CONNECTED hanya menandakan sambungan Wi-Fi, bukan koneksi internet atau API sehat. Jika Wi-Fi masih tersambung tetapi API gagal, konfigurasi tersimpan tetap dipakai dan buzzer masih dapat aktif. Sampel offline tidak diantrekan.

Request GET/POST sinkron, timeout 10 detik, sehingga pembacaan/evaluasi dapat tertunda saat menunggu. Setelah gagal, request berikutnya mengikuti interval; tidak ada pengiriman ulang langsung atau jaminan waktu real-time.

## OTA

Respons server memuat latest_firmware, ota_url dan firmware_sha256. `tryOta()` berjalan saat Wi-Fi tersambung dan hanya mencoba versi numerik lebih tinggi, checksum string 64 karakter, serta URL berawalan SERVER_URL + `/api/firmware-download.php?id=`. Nilai null/kosong berarti tidak ada target yang dapat dipasang.

Upload `.bin` aplikasi melalui halaman Firmware OTA dengan versi sama seperti FIRMWARE_VERSION di binary; naikkan dari v1.0.0 untuk rilis berikutnya. Semua perangkat menerima target global dan memerlukan board/partisi kompatibel. Download membutuhkan status 200, Content-Length positif dan slot cukup. Update.h menulis image sambil menghitung SHA-256, memverifikasi checksum dan menyelesaikan image sebelum reboot. Kegagalan membatalkan update; percobaan berikutnya minimal 5 menit sejak percobaan sebelumnya. Sensor/buzzer tetap diperiksa pada loop download; timeout HTTP/download dapat menunda evaluasi.

Sesudah reboot, konfirmasi reported_firmware melalui telemetry yang berhasil, bukan hanya current_firmware di web. Kompilasi dan pengujian OTA fisik belum dilakukan di lingkungan ini.

## Sleep dan kontrol daya opsional

Saat ini SLEEP_ENABLED=false dan SENSOR_POWER_CONTROL=false: deep sleep dan pensaklaran daya sensor tidak aktif. WiFi.setSleep(true) tetap dipanggil untuk modem sleep; ini berbeda dari deep sleep seluruh ESP32.

Jika diaktifkan, sleep memakai interval kirim sebagai durasi timer. Tidak tidur saat kondisi alarm aktif. Ketika kondisi selesai terpenuhi, buzzer dimatikan, pin ditahan dengan GPIO hold, sensor dimatikan jika kontrol daya diaktifkan, lalu deep sleep. Kontrol sensor menggunakan GPIO 33 dan polaritas aktif HIGH. Batas Wi-Fi offline adalah 15 detik dan MAX_AWAKE_MS 30 detik; batas tersebut dievaluasi oleh loop dan dapat terlampaui akibat request sinkron. Alarm aktif juga menghalangi sleep meskipun batas menyala terlampaui.

Keterbatasan implementasi: telemetryDone diatur true setelah sendTelemetry() dipanggil, tanpa memeriksa apakah HTTP/konfigurasi berhasil. Jadi mode sleep dapat tidur setelah percobaan kirim gagal; ini bukan bukti data sudah tersimpan. Setelah bangun, konfigurasi lokal dipakai sampai respons web berikutnya diterima. Fitur sleep/daya belum diuji pada rangkaian fisik.

## Verifikasi operasional

1. Ukur pemasangan dan bandingkan Serial Monitor dengan tinggi manual, termasuk jarak di luar rentang serta frame checksum salah.
2. Dengan ambang 500, uji tinggi di bawah/tepat/di atas batas; periksa LOW/HIGH GPIO dan kondisi buzzer/relay sebenarnya. Uji sakelar buzzer mati.
3. Ubah interval lewat Perangkat, tunggu request berikutnya, lalu periksa interval telemetry. Reboot dan konfirmasi konfigurasi tersimpan.
4. Lepas TX sensor dan tunggu siklus evaluasi; telemetry invalid harus dilewati. Pulihkan kabel dan pastikan pengiriman pulih.
5. Bedakan Wi-Fi terputus dengan server/API tidak tersedia saat Wi-Fi masih tersambung; hasil buzzer berbeda sesuai kondisi di atas.
6. Uji OTA dengan versi lebih tinggi, unduhan terputus dan image tidak cocok. Konfirmasi versi setelah reboot.

Dokumentasi ini diperiksa terhadap sumber sketch dan kontrak API lokal. Belum ada hasil kompilasi toolchain ESP32 atau pengujian perangkat fisik. Tidak ada perubahan firmware, kredensial, endpoint, maupun database dalam pembaruan dokumentasi ini.
