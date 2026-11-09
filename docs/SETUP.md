# Setup PHP Native dan MySQL

## Login Admin — 7 November 2026

Isi `ADMIN_EMAIL` dan `ADMIN_PASSWORD` di `.env`. Tidak ada kredensial bawaan. Gunakan password kuat dan bungkus nilai dengan tanda kutip bila perlu mempertahankan spasi. Password lama dari tabel akun tidak dapat dipulihkan menjadi teks asli. Akun hasil script lama tidak digunakan.

Login `/login.php` memberi akses semua halaman bisnis. Session maksimal delapan jam, perubahan kredensial membatalkan session, dan Logout memakai POST + CSRF. Lima kegagalan login per IP dalam 15 menit memblokir percobaan berikutnya dengan 429 sampai kegagalan tertua keluar dari jendela waktu. Folder nonpublik `storage/auth` harus dapat ditulis PHP. Jangan mengekspos root proyek; document root tetap `public`.

Tidak ada registrasi, akun Viewer, Users, reset password, email verifikasi, SMTP, atau kebutuhan Composer. Guest halaman admin diarahkan ke login; API admin mengembalikan 401. Endpoint ESP32 telemetry, device-config, firmware-download tetap publik.

Viewer publik tersedia di `/viewer.php` (Dashboard) dan `/viewer-maps.php` (Maps), tanpa login. Navigasi hanya dua halaman tersebut; tidak ada tombol history, edit lokasi, atau tambah perangkat. Dashboard viewer polling `/api/viewer-dashboard.php` setiap lima detik; endpoint hanya menerima GET dan tidak menyediakan history atau jumlah log. Mengirim `viewer=1` ke URL admin tidak membuka akses. Halaman viewer juga hanya menerima GET.

Viewer memakai header tanpa sidebar: logo Flow, Dashboard, Maps, dan Login berkelompok rata kiri. Jarak logo ke navigasi 16 px dan antartombol 8 px, memakai design tokens. Navigasi membungkus ke baris berikutnya pada layar sempit tanpa merenggangkan tombol. Login menuju `/login.php`; sidebar Admin tetap tersedia.

Sesuai permintaan tampilan, badge koneksi viewer selalu **Online**, jumlah Online sama dengan jumlah perangkat dan Offline nol. Ini label presentasi, bukan hasil pemeriksaan konektivitas. Status asli tetap ada di database dan tampil di Admin; permintaan viewer tidak menjalankan pembaruan status offline. Tinggi air, label Siaga, koordinat, dan waktu pengukuran tetap berasal dari data sebenarnya. Ringkasan Saved logs dihilangkan dari dashboard Admin dan viewer; halaman Sensor logs tetap tersedia untuk Admin.

Untuk tabel akun yang sudah dimigrasikan, jalankan `php scripts/cleanup-auth.php` untuk preview. Setelah persetujuan eksplisit penghapusan database, jalankan `php scripts/cleanup-auth.php --apply`. Script menghapus delapan tabel autentikasi beserta akun lama, menolak foreign key ke tabel tersebut, dan tidak menargetkan tabel bisnis. DDL MySQL tidak dapat di-rollback: jika terjadi kegagalan sebagian, periksa hasil lalu jalankan ulang setelah penyebabnya diperbaiki. Skema instalasi baru tidak membuat tabel akun. Jangan menjalankan ulang migrasi historis `003_auth.sql`.

Tes: `php tests/auth/session.php` dan `node tests/auth/verify.mjs --integration`. Runner kedua memiliki database MariaDB sementara dan Chrome sendiri di Windows; binary mengikuti path runner. Artefak di `temp/auth-tests`. Owner/PIC dan maintainer: Not confirmed.

## Persyaratan

- PHP 8.2+ dengan `PDO`, `pdo_mysql`, `session`, `json`, `hash`, dan `fileinfo`.
- MySQL 8+ atau MariaDB 10.4+ dengan InnoDB.
- Ekstensi PHP `curl` hanya diperlukan untuk pengujian integrasi HTTP.
- Proses PHP harus memiliki izin menulis ke `storage/firmware` dan `storage/logs`.

Tidak memerlukan proses build frontend. Tidak memerlukan Composer.

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

Fitur Maps membutuhkan kolom `devices.latitude DECIMAL(10,7) NULL` dan `devices.longitude DECIMAL(11,7) NULL`. Untuk instalasi sebelum fitur ini, pilih database aplikasi lalu jalankan `database/migrations/002_device_coordinates.sql` sekali setelah persetujuan perubahan schema. Migrasi hanya menambah kolom; lokasi perangkat lama tetap kosong. Instalasi baru sudah mendapatkan kolom ini melalui `schema.sql`. Jalankan migrasi sebelum menyimpan perangkat dengan source baru.

Schema sebelumnya memakai nama tabel rentang dalam bahasa Indonesia. Jika sudah terpasang, pilih database aplikasi dan jalankan `database/migrations/001_english_alert_names.sql` sekali. Query mengganti nama tabel menjadi `device_alert_ranges` dan indeks unik menjadi `device_alert`, dengan mempertahankan baris serta hubungan foreign key. Jangan jalankan migrasi ini pada instalasi baru yang memakai schema terbaru. Pembaruan source tidak otomatis mengubah kredensial atau database yang sudah ada.

## Konfigurasi `.env`

### Maps dan koordinat perangkat

- URL: `/maps.php`, input lokasi pada `/device-edit.php?id=ID` melalui menu Devices. Owner/PIC, departemen, dan maintainer: Not confirmed. Pengguna mengikuti akses admin publik aplikasi yang sudah ada; tidak ada login/role baru.
- Leaflet **1.9.4 stable** disimpan lokal di `public/assets/vendor/leaflet-1.9.4/` dengan lisensi BSD-2-Clause. Peta dasar memakai `https://tile.openstreetmap.org/{z}/{x}/{y}.png`, dengan atribusi OpenStreetMap yang tetap terlihat. Gambar peta memerlukan internet; tidak disediakan pengunduhan offline/prefetch massal. Ikuti [kebijakan tile OSM](https://operations.osmfoundation.org/policies/tiles/).
- Tampilan awal mencakup Indonesia (batas tampilan sekitar 94,5â€“141,5 BT dan 11,5 LSâ€“6,5 LU), termasuk jika belum ada perangkat berkoordinat. Peta tidak otomatis memperbesar kumpulan pin saat dibuka.
- Paste pasangan angka `latitude, longitude` dari Google Maps menggunakan titik desimal, misalnya `-7.566712, 110.833458`. Link berbagi Google Maps tidak diproses. Latitude valid -90 sampai 90; longitude -180 sampai 180; koordinat nol valid. Presisi penyimpanan tujuh angka desimal.
- Lokasi opsional. Mengosongkan field menghapus lokasi saat ini. Update ditransaksikan bersama konfigurasi device. Tidak ada tabel/audit riwayat lokasi dan tidak ada penulisan ke `sensor_logs` karena edit koordinat.
- Nama perangkat, status Online/Offline, level air, Siaga, dan pengukuran terakhir tersedia di popup. Daftar juga menampilkan perangkat tanpa koordinat. Show on map memperbesar pin; Show Indonesia kembali ke tampilan awal. Status/koordinat merupakan snapshot saat halaman dibuka; Reload data memuat versi terbaru.
- Form memakai CSRF dan validasi server. Input gagal dipertahankan dengan respons 422; input yang berubah ditandai Unsaved changes dan dilindungi prompt keluar browser. ID URL menentukan perangkat yang diedit, bukan hidden input yang bisa dimanipulasi.
- Bila library/tile gagal dimuat, pesan error ditampilkan dan daftar perangkat tetap dapat digunakan. Pengujian browser memakai tile tiruan tanpa mengunduh tile OSM otomatis.
- Implementasi: `app/admin.php::parseCoordinates/saveDevice`, `public/device-edit.php`, `public/assets/device-edit.js`, `public/maps.php`, `public/assets/maps.js`, dan layout/theme bersama. Terakhir diperbarui: 6 November 2026.

### Nilai environment

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

Nama Pos Pantau adalah identitas utama pada dashboard Admin/viewer, peta dan popup, daftar perangkat, filter/catatan sensor, serta panel riwayat. Isian wajib, maksimal 150 karakter Unicode tanpa karakter kontrol, dapat diubah dan tidak harus unik. Device ID tetap identitas teknis permanen untuk ESP32, filter URL, serta relasi logs; mengganti nama tidak mengubah ID atau logs lama. Catatan sensor menampilkan nama pos saat ini, bukan salinan nama historis.

Instalasi baru sudah memiliki `devices.station_name` pada `schema.sql`. Database lama membutuhkan `database/migrations/004_station_name.sql`: preview `php scripts/migrate-station-name.php`, lalu setelah persetujuan eksplisit jalankan dengan `--apply`. Kolom nullable untuk mempertahankan data lama tanpa mengarang nama; tampilan memakai “Pos pantau belum dinamai” sampai Admin mengisinya melalui Ubah perangkat. Migrasi database aplikasi belum dijalankan oleh pembaruan kode ini.

1. Buka **Devices â†’ Add device**.
2. Isi ID unik yang membedakan huruf besar/kecil, misalnya `ESP32-001`: 1â€“64 huruf ASCII, angka, `-`, atau `_`.
3. Atur threshold alarm dalam cm, interval pengiriman, dan sakelar buzzer.
4. Isi ketiga rentang Siaga, misalnya:

| Label   | Batas bawah | Batas atas |
| ------- | ----------: | ---------: |
| Siaga 1 |      kosong |         19 |
| Siaga 2 |          20 |         39 |
| Siaga 3 |          40 |     kosong |

Pembacaan `20` masuk Siaga 2; `40` masuk Siaga 3. Batas bawah termasuk rentang, sedangkan batas atas tidak termasuk. Siaga 1 dan 2 boleh memiliki sisi terbuka. **Siaga 3 wajib memiliki batas bawah dan selalu tanpa batas atas**: misalnya `â‰¥ 40 cm`, sehingga `40`, `50`, maupun `999` tetap Siaga 3. Form Siaga 3 hanya menyediakan batas bawah; server menolak batas atas yang dikirim manual. Ketiga rentang tidak boleh tumpang tindih. Batas atas Siaga 1 tidak boleh sama dengan batas bawah Siaga 2; batas atas Siaga 2 tidak boleh sama dengan batas bawah Siaga 3. Perbandingan memakai nilai numerik, sehingga `20` dan `20.00` dianggap sama. Tidak ada minimum selisih satu cm; desimal yang berbeda tetap didukung. Celah menghasilkan label **Unclassified**; pada contoh di atas, `19 â‰¤ air < 20` dan `39 â‰¤ air < 40` tidak masuk rentang Siaga.

Pada konfigurasi lama, batas atas Siaga 3 diabaikan untuk klasifikasi pembacaan baru dan tampilan indikator. Menyimpan ulang pengaturan perangkat akan menyimpan batas atas tersebut sebagai `NULL`. Log historis tidak diubah dan tidak diperlukan migrasi database.

Konfigurasi lama dengan batas antar-Siaga yang sama tetap dibaca tanpa mengubah data, tetapi harus diperbaiki sebelum pengaturan dapat disimpan ulang. Kegagalan validasi mempertahankan input form dan tidak mengubah pengaturan/rentang perangkat.

Perangkat baru tetap Offline sampai POST telemetry pertama berhasil. GET konfigurasi tidak mengubah status. Device ID tidak dapat diedit; buat perangkat baru jika identitas berubah. Penghapusan perangkat/firmware tidak disediakan agar riwayat dan URL OTA tetap tersedia.

## Deteksi Offline dan penjadwalan

Perangkat menjadi Offline setelah lebih dari `max(3 Ã— update_interval, 30)` detik tanpa telemetry, atau ketika mengirim `device_status: 0`. Pembacaan terakhir tetap tersedia.

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

Halaman bisnis memerlukan login Admin; endpoint perangkat tetap tanpa token. Form dashboard menggunakan perlindungan CSRF melalui session; endpoint perangkat tidak membuka CORS bebas.
