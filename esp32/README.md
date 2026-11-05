# Contoh Firmware ESP32

Sketch: `WaterLevelMonitor/WaterLevelMonitor.ino`.

## Persiapan

1. Install Arduino IDE dan **Arduino ESP32 core 3.x** melalui Boards Manager.
2. Install **ArduinoJson 7.x** melalui Library Manager. WiFi, HTTPClient, Update, dan mbedTLS tersedia dalam core ESP32.
3. Pilih board sebenarnya dan skema partisi yang mendukung OTA dengan dua slot aplikasi yang cukup besar untuk binary. Jangan gunakan skema No OTA.
4. Isi `WIFI_SSID`, `WIFI_PASSWORD`, `SERVER_URL`, `DEVICE_ID`, `FIRMWARE_VERSION`, `BUZZER_PIN`, dan polaritas buzzer.
5. Daftarkan `DEVICE_ID` yang sama pada dashboard.

Contoh menggunakan buzzer aktif pada GPIO 25. Sesuaikan pin/rangkaian dengan board dan gunakan driver yang sesuai kebutuhan arus buzzer.

## Integrasi sensor

Model sensor belum ditentukan, sehingga `readWaterLevelCm()` sengaja mengembalikan `NAN`. Ganti dengan driver sensor sebenarnya dan kalibrasi dalam cm. Untuk sensor ultrasonik, hitung tinggi air sebagai tinggi pemasangan dikurangi jarak permukaan. Kembalikan `NAN` ketika timeout atau pembacaan invalid.

Sketch tidak mengirim data dummy. Sebelum pembacaan valid tersedia, Serial Monitor menampilkan penjelasan, buzzer tetap mati, dan telemetry dilewati. Pengambilan konfigurasi dan pengecekan OTA tetap berjalan; GET tidak menandai perangkat Online.

## Perilaku saat berjalan

- Boot: matikan buzzer, sambungkan Wi-Fi, dan ambil konfigurasi.
- Baca sensor setiap 5 detik dan evaluasi buzzer memakai pembacaan/konfigurasi terbaru.
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
- Uji di bawah, tepat pada, dan di atas threshold; matikan sakelar buzzer saat air tinggi.
- Ubah interval dan amati waktu request.
- Putuskan Wi-Fi, periksa Offline, sambungkan kembali, dan periksa Online.
- Publikasikan versi lebih tinggi dan verifikasi `reported_firmware` setelah reboot.
- Uji file rusak, unduhan terputus, dan binary terlalu besar; image gagal tidak boleh menjadi target boot.

Sketch belum dikompilasi dengan toolchain ESP32 atau diuji pada perangkat fisik dalam lingkungan ini.
