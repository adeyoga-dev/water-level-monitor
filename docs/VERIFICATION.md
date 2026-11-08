# Verifikasi Implementasi

## Header viewer — 8 November 2026

Viewer tanpa sidebar memakai logo Flow dan tombol Dashboard, Maps, Login rata kiri. Konten selebar halaman; header gap 16 px dan navigasi gap 8 px memakai tokens. Pemeriksaan PHP syntax dan diff whitespace lulus. Browser terisolasi lebar 390/1440 px lulus untuk navigasi aktif, tautan Login, polling, Maps, akses Admin, serta tidak ada overflow. Screenshot mobile diperiksa. Runner khusus `temp/viewer-header/verify.mjs` memakai harness autentikasi dan melewati pemeriksaan popup zoom lama karena fixture masih mengharapkan Zoom out, sementara implementasi pengguna sekarang Zoom in; perilaku zoom tidak diubah dalam tugas header.

## Viewer publik — 7 November 2026

Dashboard `/viewer.php` dan Maps `/viewer-maps.php` dapat dibuka tanpa login. Pengujian pada database terisolasi lulus: GET viewer/polling, POST ditolak 405, akses langsung halaman dan API admin tetap ditolak meskipun memakai `viewer=1`, tidak ada tautan history/edit atau Saved logs, badge viewer selalu Online, dan status admin tetap mengikuti data. Snapshot empat tabel bisnis sebelum/sesudah akses viewer serta percobaan tulis yang ditolak identik. Tidak ada migrasi database.

Browser Chrome pada lebar 390 dan 1440 px memverifikasi dua menu viewer, polling dashboard, popup Maps tanpa edit, dan tidak ada overflow horizontal. Screenshot dashboard viewer diperiksa. Sebanyak 142 pemeriksaan aplikasi lama juga lulus; sintaks PHP/JS dan diff whitespace lulus. Peta memakai fixture pengujian untuk tile eksternal, bukan verifikasi ketersediaan layanan OpenStreetMap. Aturan Online tetap adalah presentasi viewer yang diminta pengguna, bukan pembuktian perangkat terhubung.

## Login Admin sederhana — 7 November 2026

Session PHP native memakai ADMIN_EMAIL/ADMIN_PASSWORD dari konfigurasi; tidak ada dependensi Composer, role Viewer, registrasi, atau SMTP. Runner `node tests/auth/verify.mjs --integration` lulus 142 pemeriksaan aplikasi serta HTTP/browser login: guest redirect/401, API konfigurasi perangkat publik, login email tanpa peka kapital, password salah, CSRF login/logout, akses Admin halaman/API, logout, URL fitur akun lama 404, dan desktop/mobile tanpa overflow. Database pengujian terisolasi, bukan database aplikasi.

Pengujian session terpisah mencakup session lama, kedaluwarsa delapan jam, pergantian kredensial, konfigurasi kosong, pembatasan lintas session, dan jendela waktu throttling. Cleanup database aplikasi belum dijalankan; membutuhkan persetujuan eksplisit. Kredensial Admin pada .env perlu diisi pengguna.

## Tema Flow / Webflow â€” 6 November 2026

Identitas Flow memakai logo tiga gelombang putih pada kotak hitam, tersimpan di `public/assets/images/flow-mark.svg`. Layout bersama memakainya untuk sidebar (36 px), footer (24 px), dan favicon SVG seluruh halaman. Ukuran mengikuti design tokens; gambar dekoratif didampingi teks Flow dan tautan sidebar memiliki nama aksesibel. Tidak membutuhkan CDN, build, atau perubahan logic aplikasi.

Pembaruan animasi permukaan air: garis biru pada panel Dashboard/detail menggunakan gelombang SVG dengan animasi CSS, dibatasi area plot. Tinggi acuan, skala, pembacaan sensor, dan logika JavaScript tidak berubah. Mode `prefers-reduced-motion` menampilkan garis datar semula; perangkat tanpa pembacaan tidak menampilkan gelombang. Verifikasi ulang lulus 111 pemeriksaan logika/API dan 54 pemeriksaan browser, termasuk gerakan gelombang serta reduced motion pada desktop/mobile. Screenshot gelombang desktop diperiksa.

- Referensi: `docs/DESIGN.md` dan spesifikasi UI pengguna. Tokens warna, tipografi, spacing, radius, dan shadow berada di `public/assets/css/design-tokens.css`; override Bootstrap berada di `public/assets/css/theme.css`. Vendor Bootstrap tidak diedit.
- Layout bersama menerapkan tema ke Dashboard, Devices, Sensor logs, OTA firmware, form administrasi device, dan detail/history. Posisi komponen desktop dipertahankan; stat card menjadi 1/2/4 kolom pada mobile/tablet/desktop. Menu mobile memakai disclosure HTML native yang dapat dioperasikan dengan keyboard.
- Inter dan Inconsolata disimpan lokal beserta lisensi SIL OFL. Tidak diperlukan build frontend atau migrasi database.
- Token PHP yang dieksekusi pada kedua file PHP yang berubah dibandingkan dengan HEAD dan identik. Perubahan JavaScript terbatas pada konfigurasi warna/font Chart.js; query, endpoint, filter, agregasi, polling, skala, dan data tidak diubah.
- Verifikasi pada database MariaDB sementara yang terisolasi: 111 pemeriksaan logika/API dan 52 pemeriksaan browser lulus. Lima halaman utama diperiksa pada 390, 768, 992, dan 1440 px; mencakup overflow, bobot font maksimal 600, tombol minimal 44 px, menu keyboard, stat grid, serta seluruh pemeriksaan history sebelumnya.
- Pemeriksaan sintaks PHP/JavaScript dan `git diff --check` lulus. Screenshot fixture diperiksa untuk dashboard desktop, form admin mobile, dan OTA firmware desktop. Browser menggunakan harness Chrome lokal karena masalah koneksi browser in-app yang sudah tercatat; ini bukan pengujian perangkat fisik.
- Catatan kontras: teks sekunder kecil menggunakan `--body-mid` dari palet agar terbaca. Kombinasi badge Siaga 3 putih di atas `#ee1d36` dipertahankan sesuai spesifikasi warna eksplisit; kontras sekitar 4,3:1 belum memenuhi AA 4,5:1 untuk teks kecil. Keputusan menggelapkan merah masih menunggu preferensi pengguna.

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

## Konversi Bootstrap â€” 5 November 2026

Seluruh halaman memakai Bootstrap 4.6.2 lokal dari `public/assets/vendor/bootstrap-4.6.2-dist/`. Folder distribusi dipindahkan dari root proyek tanpa mengubah isi vendor. Stylesheet khusus, font eksternal, dan Bootstrap CDN dihapus. Menu kiri menggunakan grid Bootstrap dan berpindah ke atas konten pada ponsel.

- Pemeriksaan sintaks seluruh 16 file PHP aplikasi/pengujian lulus; 9 pemeriksaan logika tanpa database lulus.
- Chrome headless lokal menjalankan 23 pemeriksaan fixture, termasuk semua halaman pada desktop 1440 px dan ponsel 390 px, aset lokal, label input, menu aktif, kondisi kosong, pesan validasi/sukses, escaping release notes, serta filter pada tautan pagination.
- Polling dashboard berhasil memperbarui tabel, mempertahankan data ketika koneksi gagal, mengakhiri loading ketika request dibatalkan setelah 15 detik, dan pulih pada percobaan berikutnya.
- Screenshot desktop/ponsel tersedia di `temp/bootstrap-ui/screenshots/`; rincian pemeriksaan berada di `temp/bootstrap-ui/verification.json`.
- Browser bawaan gagal tersambung dengan `codex/sandbox-state-meta: missing field sandboxPolicy`; pemeriksaan menggunakan Chrome lokal sebagai fallback.

Fixture merender template aplikasi dengan data buatan dan tidak memuat `.env`, menjalankan query database, atau memproses publikasi firmware. Pengujian integrasi MySQL/HTTP terhadap aplikasi sebenarnya tidak diulang pada konversi tampilan ini. Hasil ini tidak membuktikan komunikasi atau OTA pada ESP32 fisik. Tidak ada migrasi database untuk perubahan ini.

## Panel muka air â€” 5 November 2026

Bagian `Your devices` pada dashboard diganti panel SVG dengan pembacaan cm, status Online/Offline, waktu terakhir, dan tiga rentang Siaga dari pengaturan perangkat. Tombol detail/riwayat membuka halaman detail dengan grafik Chart.js sebagaimana dijelaskan di bagian berikutnya. Informasi `Kualitas Data: SIMULATOR` tidak ditampilkan. Komponen halaman tetap memakai Bootstrap 4.6.2 tanpa stylesheet tambahan. Halaman Devices tetap menggunakan tabel.

- Pemeriksaan sintaks PHP dan 9 pemeriksaan logika tanpa database lulus.
- Verifikasi awal panel menjalankan 18 pemeriksaan fixture di Chrome headless lokal: desktop/ponsel, label Siaga, skala/grafik, navigasi riwayat sebelum dijadikan tombol dummy, data kosong, Offline, pembacaan negatif, celah rentang, dan rentang sempit.
- Verifikasi sebelumnya saat tombol masih dummy mencakup 4 pemeriksaan fixture desktop/ponsel. Hasil historis tersedia di `temp/water-level-panel/button-verification.json`; tombol kini mengarah ke halaman detail.
- Grid panel menampilkan seluruh perangkat dengan dua kolom mulai lebar viewport 992 px dan satu kolom di bawahnya; satu perangkat tetap memakai lebar penuh. Sebanyak 17 pemeriksaan fixture lulus untuk 1, 2, 4, dan 5 perangkat pada viewport 1440, 1024, 768, serta 390 px, termasuk ID SVG unik dan kelengkapan panel setelah polling. Hasil tersedia di `temp/water-level-panel/grid-verification.json`.
- Kartu ringkasan dirapatkan menjadi layout horizontal dengan ikon SVG lokal di kiri serta judul/angka di kanan. Lima pemeriksaan fixture lulus pada viewport 1440, 1024, 768, dan 390 px serta setelah polling: tinggi kartu maksimal 100 px, ikon dekoratif, nilai tetap benar, dan tanpa overflow/CSS tambahan. Hasil dan screenshot berada di `temp/water-level-panel/summary-verification.json` dan `temp/water-level-panel/screenshots/*-summary.png`.
- Polling diuji saat pembacaan berubah, koneksi gagal, timeout 15 detik, dan pulih kembali; nilai terakhir dipertahankan pada kegagalan.
- Screenshot dan hasil pemeriksaan tersedia di `temp/water-level-panel/`.

Pemeriksaan memakai data buatan tanpa koneksi database. Integrasi database dan perangkat fisik tidak dijalankan. Skala grafik bersifat otomatis, tidak menunjukkan kapasitas fisik. Tidak ada perubahan schema atau kontrak telemetry ESP32.

## Grafik riwayat Chart.js â€” 5 November 2026

- Integrasi MySQL/HTTP lulus **68 pemeriksaan**, mencakup seluruh pengujian sebelumnya serta endpoint/page riwayat, timestamp UTC dalam milidetik, urutan waktu/ID, batas 500 pembacaan, kondisi kosong, metode/identifier invalid, tidak adanya efek tulis pada GET/penolakan, pemisahan log antarperangkat, dan klasifikasi historis yang tidak berubah saat rentang diedit.
- Pengujian memakai MariaDB 10.4.32 sementara dengan data directory baru di `temp/history-chart/`, port loopback acak, serta database acak berawalan `water_level_test_`. Identitas data directory diverifikasi sebelum pengujian. Semua konfigurasi koneksi PHP dan server HTTP diarahkan ke instance ini melalui environment proses; database aplikasi tidak diakses.
- Chrome headless lokal lulus **12 pemeriksaan** untuk tautan dashboard melalui keyboard, render Chart.js 4.6.1 pada desktop/ponsel, pergantian perangkat, satu titik, riwayat kosong, penolakan refresh ganda, kegagalan jaringan, timeout, kegagalan library lokal, dan pemulihan.
- Chart.js disajikan langsung dari `public/assets/vendor/chartjs-4.6.1/chart.umd.min.js` tanpa intersepsi byte library. Akses HTTPS diblokir selama pengujian untuk memverifikasi penggunaan offline. Kegagalan pemuatan library lokal juga diuji. Screenshot, hasil browser, dan output integrasi tersedia di `temp/history-chart/`.
- Instance database dan profil Chrome sementara dihentikan/dibersihkan setelah pengujian. Tidak ada migrasi aplikasi yang dijalankan. Perangkat ESP32 fisik tidak diuji.

## Filter periode grafik â€” 6 November 2026

- Default ketika membuka detail dari dashboard adalah 30 hari terakhir termasuk hari ini. Pemeriksaan browser desktop/ponsel memastikan kedua input tanggal sesuai periode lokal tersebut, grafik memiliki 30 interval harian, serta pilihan periode eksplisit dan tombol Today tetap bekerja.
- Integrasi logika/MySQL/HTTP lulus **89 pemeriksaan**. Tambahan mencakup tanggal awalâ€“akhir inklusif dalam zona aplikasi, pengelompokan jam/hari/bulan/tahun, rata-rata berbobot pembacaan, minimum/maksimum, seluruh data melampaui batas raw 500 log, celah bernilai null, tahun kabisat, jam DST berulang, pemisahan perangkat, validasi tanggal/interval, dan GET tanpa efek tulis.
- Chrome headless lokal lulus **20 pemeriksaan** untuk grafik offline dan layout desktop/ponsel, filter dan URL periode, label sumbu horizontal yang dibatasi sesuai lebar layar, ketiga seri agregat, kegagalan jaringan/timeout, draft yang bertahan saat gagal, pembatalan request usang, polling dijeda selama draft, konfirmasi Today yang dibatalkan/diterima, validasi kepadatan titik, serta pemulihan library lokal.
- Pengujian memakai server PHP aplikasi sebenarnya, Chart.js lokal tanpa perubahan, dan data buatan dalam instance MariaDB sementara dengan identitas data directory yang diverifikasi. Database aplikasi serta perangkat fisik tidak diuji. Akses HTTPS diblokir selama browser checks.
- Hasil terperinci di `temp/history-chart/integration.log` dan `temp/history-chart/verification.json`; screenshot `day-trend.png`, `month-trend.png`, `year-trend.png`, serta `mobile-daily-trend.png` berada di `temp/history-chart/screenshots/`. Database/profil browser sementara dibersihkan setelah pengujian. Tidak ada migrasi atau perubahan konfigurasi yang diperlukan.

## Siaga 3 tanpa batas atas dan label ringkas â€” 6 November 2026

- **13 pemeriksaan logika tanpa database** dan **97 pemeriksaan logika/MySQL/HTTP** lulus. Tambahan mencakup batas bawah Siaga 3 inklusif, pembacaan jauh di atas batas, penolakan batas atas/batas bawah kosong melalui form tanpa efek tulis, klasifikasi telemetry dengan konfigurasi lama, serta penyimpanan ulang batas atas sebagai `NULL`.
- **24 pemeriksaan browser** lulus pada server PHP dan database sementara yang terisolasi. Tambahan memverifikasi form Siaga 3 hanya memiliki batas bawah wajib, label SVG font 11 dengan kotak 68 Ã— 18, legenda hanya `â‰¥`, klasifikasi pembacaan 99 cm dengan batas lama 50 cm, serta layout desktop/ponsel tanpa overflow. Screenshot ada di `temp/history-chart/screenshots/desktop-siaga-labels.png` dan `mobile-siaga-labels.png`.
- Tidak ada perubahan data aplikasi, migrasi, atau pengujian perangkat fisik. Aturan baru diterapkan pada pembacaan baru dan indikator; klasifikasi log historis dipertahankan.

## Validasi batas antar-Siaga â€” 6 November 2026

- **17 pemeriksaan logika tanpa database** dan **105 pemeriksaan logika/MySQL/HTTP** lulus. Batas atas Siaga 1 tidak boleh sama dengan batas bawah Siaga 2; batas atas Siaga 2 tidak boleh sama dengan batas bawah Siaga 3. Kasus angka ekuivalen (`20`/`20.00`), nol, dan selisih desimal diuji. Pembuatan maupun perubahan perangkat dengan batas sama ditolak tanpa mengubah perangkat/rentangnya.
- **28 pemeriksaan browser** lulus, termasuk submit kedua pasangan batas yang sama pada desktop dan ponsel, pesan spesifik tiap pasangan, input tetap tersedia setelah gagal, dan tidak ada overflow. Pengujian memakai server PHP sebenarnya dan instance MariaDB sementara yang identitasnya diverifikasi; database aplikasi tidak diakses.
- Contoh rentang di form/dokumentasi disesuaikan menjadi tanpa batas bawahâ€“19, 20â€“39, serta â‰¥ 40 cm. Batas atas tetap eksklusif, sehingga nilai pada celah tidak diklasifikasikan. Data lama tetap dapat dibaca, tetapi harus memenuhi validasi baru sebelum pengaturan disimpan ulang. Tidak perlu migrasi.

## Tren per menit â€” 6 November 2026

- **111 pemeriksaan logika/MySQL/HTTP** lulus, termasuk agregasi menit dari seluruh pembacaan, rata-rata/minimum/maksimum/jumlah sampel, batas hari dalam zona lokal, interval kosong bernilai null, jarak waktu 60 detik, penolakan rentang lebih dari satu tanggal, dan 1.500 menit pada hari DST 25 jam.
- **32 pemeriksaan browser** lulus menggunakan server PHP aplikasi dan MariaDB sementara yang terisolasi. Mode Minute menampilkan 1.440 interval dengan label `HH:mm`, tooltip tanggal lengkap, titik terisolasi yang terlihat, serta pemulihan setelah validasi rentang. Validasi saat Apply menampilkan Bootstrap alert dengan fokus, tanpa request API, dan mempertahankan input/grafik; teks bantuan permanen tidak ditampilkan. Kondisi kosong dan layout desktop/ponsel tanpa overflow turut diverifikasi. Screenshot tersedia di `temp/history-chart/screenshots/desktop-minute-trend.png` dan `mobile-minute-trend.png`.
- Default 30 hari serta aturan Auto tetap berlaku. Mode Minute dipilih secara manual untuk satu tanggal. Chart.js tetap lokal dan tidak ada migrasi, perubahan database aplikasi, atau pengujian perangkat fisik.
