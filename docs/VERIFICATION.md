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

Pada verifikasi awal 4 November 2026, verifikasi visual belum dapat dilakukan karena koneksi alat browser pada lingkungan ini gagal.

Toolchain dan board ESP32 tidak tersedia. Kompilasi untuk board sebenarnya, lengkapi fungsi sensor, dan verifikasi OTA/buzzer pada perangkat fisik.

Perubahan bahasa dokumentasi tidak mengubah kode aplikasi. Hasil 53 pemeriksaan di atas berasal dari verifikasi implementasi sebelumnya.

## Konversi Bootstrap — 5 November 2026

Seluruh halaman memakai Bootstrap 4.6.2 lokal dari `public/assets/vendor/bootstrap-4.6.2-dist/`. Folder distribusi dipindahkan dari root proyek tanpa mengubah isi vendor. Stylesheet khusus, font eksternal, dan Bootstrap CDN dihapus. Menu kiri menggunakan grid Bootstrap dan berpindah ke atas konten pada ponsel.

- Pemeriksaan sintaks seluruh 16 file PHP aplikasi/pengujian lulus; 9 pemeriksaan logika tanpa database lulus.
- Chrome headless lokal menjalankan 23 pemeriksaan fixture, termasuk semua halaman pada desktop 1440 px dan ponsel 390 px, aset lokal, label input, menu aktif, kondisi kosong, pesan validasi/sukses, escaping release notes, serta filter pada tautan pagination.
- Polling dashboard berhasil memperbarui tabel, mempertahankan data ketika koneksi gagal, mengakhiri loading ketika request dibatalkan setelah 15 detik, dan pulih pada percobaan berikutnya.
- Screenshot desktop/ponsel tersedia di `temp/bootstrap-ui/screenshots/`; rincian pemeriksaan berada di `temp/bootstrap-ui/verification.json`.
- Browser bawaan gagal tersambung dengan `codex/sandbox-state-meta: missing field sandboxPolicy`; pemeriksaan menggunakan Chrome lokal sebagai fallback.

Fixture merender template aplikasi dengan data buatan dan tidak memuat `.env`, menjalankan query database, atau memproses publikasi firmware. Pengujian integrasi MySQL/HTTP terhadap aplikasi sebenarnya tidak diulang pada konversi tampilan ini. Hasil ini tidak membuktikan komunikasi atau OTA pada ESP32 fisik. Tidak ada migrasi database untuk perubahan ini.
