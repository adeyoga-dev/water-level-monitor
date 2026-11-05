# Flow — Deteksi Level Air

Proyek PHP native 8.2+ dengan MySQL/MariaDB, dashboard admin, API ESP32, riwayat sensor, dan pembaruan firmware OTA global. Dashboard langsung terbuka tanpa login; API perangkat tidak memerlukan token.

## Mulai menjalankan

1. Eksekusi [database/schema.sql](database/schema.sql) melalui phpMyAdmin atau klien MySQL.
2. Isi kredensial database pada `.env` di root proyek. Template tersedia pada [.env.example](.env.example).
3. Jalankan dari root proyek:

   ```sh
   php -S 0.0.0.0:8000 -t public
   ```

4. Buka `http://localhost:8000`, tambahkan perangkat, lalu atur threshold dan rentang Siaga.
5. Isi `APP_URL` dengan alamat LAN yang dapat dijangkau ESP32, misalnya `http://192.168.1.10:8000`, dan sesuaikan konfigurasi sketch.

Tidak memerlukan Composer, npm, Laravel, atau URL rewrite. Seluruh halaman memakai komponen dan warna bawaan Bootstrap 4.6.2 dari `public/assets/vendor/bootstrap-4.6.2-dist/`, tanpa CSS tambahan atau font eksternal. Ikon ringkasan menggunakan SVG inline lokal tanpa library ikon tambahan. Menu berada di kiri pada desktop dan di atas konten pada ponsel. Chart.js 4.6.1 beserta lisensinya disimpan di `public/assets/vendor/chartjs-4.6.1/`. Layout, ikon, dan grafik tersedia secara lokal tanpa koneksi internet atau CDN.

## Dokumentasi

- [Setup, query MySQL, Apache, penjadwalan, dan pemecahan masalah](docs/SETUP.md)
- [Kontrak API dan contoh request](docs/API.md)
- [Konfigurasi ESP32 dan pengujian perangkat keras](esp32/README.md)
- [Hasil verifikasi implementasi](docs/VERIFICATION.md)

## Pengujian

```sh
php tests/lint.php
php tests/run.php
php tests/run.php --integration
```

Pengujian integrasi memerlukan `pdo_mysql`, `curl`, izin membuat/menghapus database khusus pengujian, dan port `18765` yang kosong. Pengujian membuat database acak berawalan `water_level_test_`, menjalankan server PHP sementara, lalu membersihkan database serta file firmware buatannya. Kredensial dibaca dari `.env`; tabel aplikasi tidak digunakan untuk pengujian.

## Aturan aplikasi

- Ketinggian air menggunakan **cm**.
- Buzzer hanya berbunyi jika diaktifkan dan `water_level > alarm_threshold`.
- Klasifikasi Siaga merupakan label historis yang terpisah dari aturan buzzer. Label tampilannya adalah **Siaga 1**, **Siaga 2**, dan **Siaga 3**. Siaga 3 hanya memiliki batas bawah inklusif (`≥`) yang wajib diisi, tanpa batas atas.
- Dashboard menampilkan panel muka air untuk seluruh perangkat, dua kolom per baris pada desktop dan satu kolom pada layar kecil. Satu perangkat memakai lebar penuh. Panel berisi skala cm, rentang Siaga, status koneksi, dan pembacaan terakhir. Tombol detail/riwayat membuka indikator perangkat serta grafik dengan filter tanggal awal–akhir dan pengelompokan Auto/Hour/Day/Month/Year. Default 30 hari terakhir termasuk hari ini; grafik memperlihatkan rata-rata, minimum, dan maksimum seluruh pembacaan dalam periode. Pembaruan setiap 5 detik dijeda selama ada perubahan filter yang belum diterapkan. Skala indikator menyesuaikan pembacaan serta konfigurasi rentang; bukan kapasitas fisik atau persentase isi.
- Interval pengiriman diisi sebagai jam:menit:detik pada dashboard dan disimpan/dikirim sebagai detik; nilai default 60 detik.
- Firmware terbaru menjadi target global untuk semua perangkat dengan board dan partisi OTA yang kompatibel.
- `current_firmware` adalah versi target; `reported_firmware` adalah versi terpasang yang dilaporkan ESP32.
- Tampilan, pesan API, dan penamaan kode menggunakan bahasa Inggris. Dokumentasi menggunakan bahasa Indonesia; tiga label Siaga tetap bahasa Indonesia.

## Database dari versi sebelumnya

Jika sudah mengimpor schema lama yang memiliki tabel `device_siaga_ranges`, pilih database tersebut dan jalankan [001_english_alert_names.sql](database/migrations/001_english_alert_names.sql) **sekali** sebelum menggunakan versi ini. Query mengganti nama tabel rentang dan indeks unik tanpa menghapus data. Instalasi baru cukup memakai `schema.sql` terbaru.
