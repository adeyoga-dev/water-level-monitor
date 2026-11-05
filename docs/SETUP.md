# Setup PHP Native dan MySQL

## Persyaratan

- PHP 8.2+ dengan `PDO`, `pdo_mysql`, `session`, `json`, `hash`, dan `fileinfo`.
- MySQL 8+ atau MariaDB 10.4+ dengan InnoDB.
- Ekstensi PHP `curl` hanya diperlukan untuk pengujian integrasi HTTP.
- Proses PHP harus memiliki izin menulis ke `storage/firmware` dan `storage/logs`.

Tidak memerlukan proses build frontend atau dependency Composer.

## Query database siap eksekusi

Buka **`database/schema.sql`**, salin seluruh isinya, lalu eksekusi melalui phpMyAdmin atau editor SQL MySQL. File tersebut sudah berisi:

```sql
CREATE DATABASE IF NOT EXISTS water_level_detection
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE water_level_detection;
```

Diikuti definisi lengkap `firmwares`, `devices`, `device_alert_ranges`, dan `sensor_logs`, termasuk foreign key dan indeks. File tidak menghapus tabel, memasukkan data contoh, atau mengubah password MySQL. Impor ulang tidak menghapus baris yang sudah ada, tetapi `IF NOT EXISTS` tidak memperbarui struktur tabel yang berbeda.

Alternatif: buka klien interaktif dengan `mysql -u root -p`, lalu jalankan:

```sql
SOURCE C:/path/to/water-level-detection/database/schema.sql;
SHOW TABLES;
```

Untuk nama database lain, ubah perintah `CREATE DATABASE` dan `USE`, lalu samakan `DB_DATABASE` pada `.env`. Jika hosting sudah menyediakan database, pilih database tersebut dan eksekusi bagian `CREATE TABLE` saja.

Akun aplikasi memerlukan izin `SELECT`, `INSERT`, `UPDATE`, dan `DELETE`. Pembuatan schema dapat menggunakan akun administrator terpisah.

### Instalasi yang sudah ada

Schema sebelumnya memakai nama tabel rentang dalam bahasa Indonesia. Jika sudah terpasang, pilih database aplikasi dan jalankan `database/migrations/001_english_alert_names.sql` sekali. Query mengganti nama tabel menjadi `device_alert_ranges` dan indeks unik menjadi `device_alert`, dengan mempertahankan baris serta hubungan foreign key. Jangan jalankan migrasi ini pada instalasi baru yang memakai schema terbaru. Pembaruan source tidak otomatis mengubah kredensial atau database yang sudah ada.

## Konfigurasi `.env`

File `.env` default sudah disediakan. Jika belum ada, salin `.env.example` menjadi `.env`:

```dotenv
APP_URL=http://192.168.1.10:8000
APP_TIMEZONE=Asia/Jakarta
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=water_level_detection
DB_USERNAME=your_mysql_user
DB_PASSWORD="your_mysql_password"
DB_CHARSET=utf8mb4
```

- `APP_URL` adalah URL root aplikasi yang dapat dijangkau ESP32, tanpa `/public` atau `/api`. Gunakan IP LAN/domain; `localhost` pada ESP32 merujuk perangkat itu sendiri.
- Host, port, username, dan password database harus sesuai instance Anda. Ganti nilai contoh `your_mysql_user` dan `your_mysql_password` dengan kredensial sebenarnya.
- Nilai berpetik mempertahankan spasi, `#`, dan `=`. Tanda petik luar dibuang; escape dan interpolasi tidak diproses. Komentar ditulis pada baris terpisah yang diawali `#`; komentar di belakang nilai tidak didukung.
- Variabel lingkungan proses lebih diutamakan daripada isi `.env`.
- Konfigurasi dibaca relatif terhadap root proyek, terlepas dari direktori kerja terminal. Perubahan berlaku pada request berikutnya.
- Waktu database disimpan sebagai UTC; tampilan dan filter tanggal menggunakan `APP_TIMEZONE`.

## Menjalankan secara lokal

Dari root proyek:

```sh
php -S 0.0.0.0:8000 -t public
```

Buka `http://localhost:8000` dari komputer atau `http://LAN-IP:8000` dari perangkat pada jaringan yang sama. Izinkan koneksi masuk TCP port 8000 jika diperlukan. Server bawaan PHP digunakan untuk pengembangan; gunakan Apache/PHP untuk pengoperasian terus-menerus.

**Document root wajib folder `public/`.** Link relatif terhadap root domain; pemasangan melalui subfolder URL tidak didukung. Konfigurasi, source aplikasi, SQL, dan file firmware berada di luar document root.

## Apache dan upload

Contoh VirtualHost; sesuaikan path dengan komputer Anda:

```apache
<VirtualHost *:80>
    ServerName water.local
    DocumentRoot "C:/path/to/water-level-detection/public"
    <Directory "C:/path/to/water-level-detection/public">
        DirectoryIndex index.php
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

Pastikan Apache memproses file PHP. URL rewrite dan symbolic link storage tidak diperlukan. Gunakan alamat yang dapat dijangkau ESP32 dan samakan `APP_URL`.

Untuk file hingga 16 MB, atur nilai berikut pada `php.ini` milik web server, lalu restart:

```ini
file_uploads = On
upload_max_filesize = 16M
post_max_size = 20M
max_execution_time = 120
```

Batas aplikasi tetap 16 MB meskipun limit PHP lebih tinggi. File disimpan sebagai `storage/firmware/<nama-acak>.bin`. Unduhan mengambil file berdasarkan ID database dan mengalirkan byte dengan `Content-Length`, tanpa memuat seluruh file ke memori PHP.

## Menambahkan perangkat

1. Buka **Devices → Add device**.
2. Isi ID unik yang membedakan huruf besar/kecil, misalnya `ESP32-001`: 1–64 huruf ASCII, angka, `-`, atau `_`.
3. Atur threshold alarm dalam cm, interval pengiriman, dan sakelar buzzer.
4. Isi ketiga rentang Siaga, misalnya:

| Label   | Batas bawah | Batas atas |
| ------- | ----------: | ---------: |
| Siaga 1 |      kosong |         20 |
| Siaga 2 |          20 |         40 |
| Siaga 3 |          40 |     kosong |

Pembacaan `20` masuk Siaga 2; `40` masuk Siaga 3. Batas bawah termasuk rentang, sedangkan batas atas tidak termasuk. Siaga 1 dan 2 boleh memiliki sisi terbuka. **Siaga 3 wajib memiliki batas bawah dan selalu tanpa batas atas**: misalnya `≥ 40 cm`, sehingga `40`, `50`, maupun `999` tetap Siaga 3. Form Siaga 3 hanya menyediakan batas bawah; server menolak batas atas yang dikirim manual. Ketiga rentang tidak boleh tumpang tindih. Celah diperbolehkan dan menghasilkan label **Unclassified**.

Pada konfigurasi lama, batas atas Siaga 3 diabaikan untuk klasifikasi pembacaan baru dan tampilan indikator. Menyimpan ulang pengaturan perangkat akan menyimpan batas atas tersebut sebagai `NULL`. Log historis tidak diubah dan tidak diperlukan migrasi database.

Perangkat baru tetap Offline sampai POST telemetry pertama berhasil. GET konfigurasi tidak mengubah status. Device ID tidak dapat diedit; buat perangkat baru jika identitas berubah. Penghapusan perangkat/firmware tidak disediakan agar riwayat dan URL OTA tetap tersedia.

## Deteksi Offline dan penjadwalan

Perangkat menjadi Offline setelah lebih dari `max(3 × update_interval, 30)` detik tanpa telemetry, atau ketika mengirim `device_status: 0`. Pembacaan terakhir tetap tersedia.

Dashboard memeriksa status saat pertama dibuka dan setiap 5 detik. Agar status tersimpan tetap diperbarui saat dashboard ditutup, jadwalkan `php scripts/mark-offline.php` setiap menit.

Linux cron:

```cron
* * * * * /usr/bin/php /var/www/water-level-detection/scripts/mark-offline.php
```

Windows Task Scheduler: jalankan setiap menit dengan Program `C:\develop\web\php\php.exe` dan Arguments `"C:\path\water-level-detection\scripts\mark-offline.php"`. Skrip menemukan `.env` melalui path absolut proyek.

## Firmware global

Upload `.bin` aplikasi ESP32 dengan versi seperti `v1.0.0` dan catatan pembaruan. Setiap rilis harus lebih tinggi secara numerik. Versi duplikat, downgrade, prerelease, dan angka dengan nol di depan seperti `v01.0.0` ditolak.

Versi yang ditulis saat upload **harus sama** dengan `FIRMWARE_VERSION` di dalam binary. Server menghitung SHA-256, tetapi tidak dapat memverifikasi board atau string versi di dalam firmware. Semua perangkat menerima target yang sama dan harus menggunakan board/partisi kompatibel. `current_firmware` bukan bukti OTA berhasil; periksa `reported_firmware` setelah reboot dan POST telemetry berikutnya.

## Pemecahan masalah

| Gejala                          | Pemeriksaan                                                                                  |
| ------------------------------- | -------------------------------------------------------------------------------------------- |
| Halaman error server            | Baca `storage/logs/php.log`; cek kredensial, database, dan impor schema                      |
| `Access denied`                 | Isi password database yang benar pada `.env`; template tidak menebak kredensial              |
| `could not find driver`         | Aktifkan `pdo_mysql` pada PHP yang menjalankan aplikasi                                      |
| Form upload kedaluwarsa         | Muat ulang; cek session dapat ditulis dan `post_max_size` lebih besar dari file              |
| Firmware tidak bisa disimpan    | Cek izin menulis ke `storage/firmware`                                                       |
| Konfigurasi ESP32 gagal         | Cek IP LAN, port, firewall, ID terdaftar, dan respons API                                    |
| OTA gagal                       | Cek board, partisi, ukuran slot, checksum, serta versi hasil kompilasi; lihat Serial Monitor |
| Perangkat tetap Offline         | GET tidak menandai Online; sensor harus menghasilkan nilai valid dan POST harus berhasil     |
| Pengaturan buzzer belum berlaku | ESP32 menerima perubahan pada GET/POST berikutnya sesuai interval                            |

Login dan token ditiadakan sesuai kebutuhan. Form dashboard menggunakan perlindungan CSRF melalui session; endpoint perangkat tidak membuka CORS bebas.
