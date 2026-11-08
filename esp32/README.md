# Contoh Firmware ESP32

Sketch: `WaterLevelMonitor/WaterLevelMonitor.ino`.

## Persiapan

1. Install Arduino IDE dan **Arduino ESP32 core 3.x** melalui Boards Manager.
2. Install **ArduinoJson 7.x** melalui Library Manager. WiFi, HTTPClient, Update, dan mbedTLS tersedia dalam core ESP32.
3. Pilih board sebenarnya dan skema partisi yang mendukung OTA dengan dua slot aplikasi yang cukup besar untuk binary. Jangan gunakan skema No OTA.
4. Isi `WIFI_SSID`, `WIFI_PASSWORD`, `SERVER_URL`, `DEVICE_ID`, `FIRMWARE_VERSION`, `SENSOR_MOUNT_HEIGHT_CM`, `BUZZER_PIN`, dan polaritas buzzer. `SERVER_URL` harus IP komputer/server yang dapat dijangkau ESP32, bukan `localhost`.
5. Daftarkan `DEVICE_ID` yang sama pada dashboard.

Contoh menggunakan buzzer aktif pada GPIO 25. Sesuaikan pin/rangkaian dengan board dan gunakan driver yang sesuai kebutuhan arus buzzer.

## Sensor ultrasonik A01ANYUB V2 (UART)

Sensor pengguna: **A01ANYUB V2**, SKU penjual **KB5005**, output **UART**, supply **3,3–5 V**, blind zone **28 cm**, rentang objek datar **28–750 cm**, dan IP67 berdasarkan foto spesifikasi. Model DYP-A01ANYUB-V2.0 menggunakan UART otomatis. Sketch memakai HardwareSerial UART2 pada **ESP32 DevKit berbasis WROOM-32**; model board pengguna belum dikonfirmasi dan pin/UART perlu disesuaikan untuk C3/S3. Tidak perlu library sensor tambahan.

| Pin A01ANYUB V2 | Sambungan awal (supply sensor 3,3 V)                |
| --------------- | --------------------------------------------------- |
| VCC             | 3V3 dengan suplai arus yang memadai                 |
| GND             | GND bersama dengan ESP32                            |
| TX              | GPIO 26, yaitu RX UART2 ESP32                       |
| RX              | Biarkan tidak tersambung untuk mode nilai terproses |

GPIO 27 dikonfigurasi sebagai TX UART2 ESP32 tetapi tidak disambungkan ke sensor. Pin RX sensor adalah pemilih mode pada varian UART auto: floating/high menghasilkan nilai terproses yang lebih stabil, low menghasilkan nilai real-time. Tidak ada trigger Trig/Echo atau perintah polling UART. Ikuti label pin/datasheet unit, jangan menebak fungsi berdasarkan warna kabel.

Jika sensor diberi 5 V, pastikan level TX aman untuk GPIO 3,3 V. Gunakan level shifter bila TX berlevel 5 V; jangan menghubungkan sinyal 5 V langsung ke ESP32. Buzzer aktif tetap GPIO 25 melalui driver/transistor sesuai arus beban dan `BUZZER_ACTIVE_HIGH`.

Pasang muka sensor menghadap tegak lurus permukaan. Ukur jarak dari **muka sensor ke titik acuan tinggi air nol**, kemudian masukkan ke `SENSOR_MOUNT_HEIGHT_CM` (contoh awal 200 cm, wajib sesuaikan pemasangan). Rumus: `water_level = SENSOR_MOUNT_HEIGHT_CM - jarak_permukaan`. Contoh pemasangan 200 cm dengan jarak terukur 75 cm menghasilkan tinggi air 125 cm. Nilai yang dikirim adalah tinggi air, bukan jarak sensor.

UART diatur **9600 baud, 8N1**, frame empat byte `[0xFF, DATA_H, DATA_L, SUM]`. Checksum adalah `(0xFF + DATA_H + DATA_L) & 0xFF`. Jarak dalam mm adalah `(DATA_H << 8) | DATA_L`, kemudian dibagi 10 menjadi cm. Contoh frame `FF 07 A1 A7` berarti 1953 mm = 195,3 cm; dengan pemasangan 200 cm, tinggi air 4,7 cm.

Setiap pembacaan membuang buffer lama, menunggu maksimal 1,8 detik untuk lima frame baru, dan memerlukan minimal tiga frame valid. Median digunakan untuk meredam lonjakan; untuk jumlah genap, dua nilai tengah dirata-ratakan. Header sampah/checksum salah diabaikan dengan sinkronisasi ulang; paket terpotong direset setelah jeda byte lebih dari 50 ms. Tidak ada perhitungan durasi Echo atau kecepatan suara pada ESP32 karena sensor sudah mengirim jarak.

Frame dengan jarak **≤28 cm** atau **>750 cm** membatalkan pembacaan saat itu, meskipun ada sampel valid sebelumnya. Pabrikan menyatakan objek di dalam blind zone dapat dilaporkan sebagai 28 cm, sehingga kode menolak tepat 28 cm juga. Tinggi air negatif akibat acuan pemasangan ditolak. Pemasangan 200 cm hanya mengukur tinggi air kurang dari 172 cm secara andal dalam batas ini; letakkan sensor lebih dari 28 cm di atas permukaan air maksimum, dengan margin. Pembacaan invalid mematikan buzzer dan melewati telemetry sesuai perilaku firmware sebelumnya; ini bukan indikasi kondisi air aman.

Foto mencantumkan waktu respons 100 ms. Pada UART auto, mode nilai terproses dapat lebih lambat (beberapa ratus ms); timeout pengumpulan 1,8 detik memberi ruang untuk minimal tiga frame. Riak, busa, posisi sensor, dan pantulan dinding tetap memengaruhi pengukuran. Mode komunikasi ini khusus A01ANYUB UART auto, bukan varian UART controlled/PWM/RS485.

Rujukan: [model dan blind zone DYP A01](https://www.dypcn.com/new-arrival-china-dyp-a01-well-water-level-measurement-waterproof-ultrasonic-sensor-product/), [protokol UART DYP A01](https://www.dypcn.com/uploads/A01-Output-Interfaces.pdf), [batas listrik ESP32-WROOM-32](https://documentation.espressif.com/esp32-wroom-32_datasheet_en.html).

Sketch tidak mengirim data dummy. Sebelum pembacaan valid tersedia, Serial Monitor menampilkan penjelasan, buzzer tetap mati, dan telemetry dilewati. Pengambilan konfigurasi dan pengecekan OTA tetap berjalan; GET tidak menandai perangkat Online.

Login Admin hanya untuk browser. Jangan menaruh `ADMIN_EMAIL` atau `ADMIN_PASSWORD` dalam sketch; endpoint ESP32 tetap memakai kontrak API perangkat yang ada. Siaga 1/2/3 ditentukan server dari tinggi yang dikirim. Buzzer mengikuti `alarm_threshold` dan `buzzer_status` dari dashboard.

## Perilaku saat berjalan

- Boot: matikan buzzer, sambungkan Wi-Fi, dan ambil konfigurasi.
- Ambil kumpulan frame sensor setiap 5 detik (menunggu hingga 1,8 detik), lalu evaluasi buzzer memakai pembacaan/konfigurasi terbaru.
- Kirim telemetry sesuai interval. Interval kurang dari 5 detik dapat mengirim sampel yang sama beberapa kali.
- `buzzer_status: 0` mematikan buzzer segera setelah respons diterapkan.
- Pertahankan konfigurasi terakhir saat jaringan gagal dan coba sambungkan Wi-Fi kembali. Pembacaan offline tidak diantrekan.
- Request konfigurasi/telemetry berjalan sinkron dengan timeout 10 detik. Pembacaan sensor dapat tertunda saat request menunggu respons. Loop download OTA tetap memeriksa sensor/buzzer.
- Contoh menggunakan HTTP melalui LAN. HTTPS memerlukan `WiFiClientSecure` dan CA yang benar; mengganti URL saja belum menyiapkan TLS.

## Alur OTA

1. Naikkan `FIRMWARE_VERSION`, misalnya `v1.0.0` menjadi `v1.1.0`.
2. Ekspor **`.bin` aplikasi**, bukan bootloader, tabel partisi, atau merged flash image.
3. Upload melalui dashboard dengan versi yang sama persis dan catatan pembaruan.
4. ESP32 menerima versi/URL pada request berikutnya, membandingkan versi numerik, dan mengunduh hanya dari server yang dikonfigurasi.
5. `Update.h` menulis ke slot OTA sambil menghitung SHA-256. Hasilnya harus cocok sebelum `Update.end()` menyelesaikan image.
6. Jika berhasil, reboot dan laporkan versi baru. Jika gagal, batalkan dan coba kembali setelah minimal 5 menit; firmware yang sedang berjalan tetap aktif.

Flash firmware awal melalui USB. Semua perangkat harus menggunakan board dan susunan partisi kompatibel karena publikasi firmware bersifat global.

## Pengujian perangkat keras

- Kalibrasi dalam cm; pastikan pembacaan invalid menghasilkan `NAN`.
- Buka Serial Monitor 115200 baud: bandingkan `Distance` dan `Water level` dengan pengukuran manual pada beberapa posisi.
- Lepas kabel TX sensor: setelah siklus baca berikutnya telemetry harus berhenti, bukan mengirim tinggi palsu. Sambungkan kembali dan pastikan pengiriman pulih.
- Uji frame checksum salah, header sampah, frame terpotong, jarak 280/281/7500/7501 mm. Hanya frame lengkap dengan checksum benar dan jarak >280 sampai 7500 mm yang dapat dipakai.
- Untuk pemasangan 200 cm: jarak 75 cm menghasilkan sekitar 125 cm; jarak 150 cm menghasilkan sekitar 50 cm. Uji juga pantulan menyimpang dan jarak di luar rentang.
- Uji di bawah, tepat pada, dan di atas threshold; matikan sakelar buzzer saat air tinggi.
- Ubah interval dan amati waktu request.
- Putuskan Wi-Fi, periksa Offline, sambungkan kembali, dan periksa Online.
- Publikasikan versi lebih tinggi dan verifikasi `reported_firmware` setelah reboot.
- Uji file rusak, unduhan terputus, dan binary terlalu besar; image gagal tidak boleh menjadi target boot.

Sketch belum dikompilasi dengan toolchain ESP32 atau diuji pada perangkat fisik dalam lingkungan ini.

Terakhir diperbarui: 7 November 2026. Pengguna: operator perangkat Flow. Owner/PIC dan maintainer: Not confirmed. Tidak ada perubahan skema database atau endpoint server.
