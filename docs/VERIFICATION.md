# Verifikasi Implementasi

Verifikasi dilakukan pada 4 November 2026 menggunakan PHP 8.2.31 dan instance MariaDB 10.4.32 sementara yang terpisah dari database pengguna.

- Pemeriksaan sintaks PHP lulus.
- Pengujian integrasi aplikasi berbahasa Inggris lulus 53 pemeriksaan, termasuk mempertahankan tiga label Siaga dalam bahasa Indonesia dan migrasi tabel rentang tanpa kehilangan data.
- Cakupan meliputi pembacaan `.env`, nilai sensor terbatas, batas/celah/tumpang tindih rentang, aturan buzzer, validasi versi, impor schema, pembuatan/pembaruan perangkat, ID duplikat, dan identitas yang tidak dapat diubah.
- Pengujian HTTP mencakup GET konfigurasi tanpa perubahan status/log, telemetry, error, versi terlapor, deteksi Offline, halaman/form, CSRF, dan escape changelog.
- Trigger log yang sengaja gagal membuktikan pembaruan perangkat ikut dibatalkan.
- Pengujian upload multipart memverifikasi target global, SHA-256, unduhan identik, penolakan downgrade, dan pewarisan target pada perangkat baru.
- Ekstensi salah, file kosong, dan file lebih dari 16 MB ditolak. Trigger firmware yang sengaja gagal membuktikan pembersihan file dan dipertahankannya target sebelumnya.
- Pembuatan perangkat melalui HTTP serta kombinasi filter perangkat/status/tanggal lokal dengan pagination juga diuji.

Pengujian membuat database acak dan membersihkan database serta binary buatannya. Tabel aplikasi dan kredensial yang sudah ada tidak diubah.

File `.env` aplikasi masih memerlukan kredensial database pengguna. Akun root lokal menolak password kosong; pengujian menggunakan instance sementara terpisah. `.env` tetap memakai port normal 3306.

Verifikasi visual belum dapat dilakukan karena koneksi alat browser pada lingkungan ini gagal. CSS responsif sudah tersedia, tetapi tampilan desktop/ponsel belum diverifikasi melalui browser.

Toolchain dan board ESP32 tidak tersedia. Kompilasi untuk board sebenarnya, lengkapi fungsi sensor, dan verifikasi OTA/buzzer pada perangkat fisik.

Perubahan bahasa dokumentasi tidak mengubah kode aplikasi. Hasil 53 pemeriksaan di atas berasal dari verifikasi implementasi sebelumnya.
