# API ESP32

Contoh base URL: `http://192.168.1.10:8000`. Endpoint perangkat `telemetry.php`, `device-config.php`, dan `firmware-download.php` tetap tanpa login atau token. Endpoint browser `dashboard.php` dan `device-history.php` membutuhkan session Admin: guest menerima 401. Daftarkan `device_id` di dashboard sebagai Admin terlebih dahulu; huruf besar/kecil dibedakan.

`station_name` (Nama Pos Pantau) dikelola melalui form Perangkat dan tidak wajib maupun digunakan oleh endpoint telemetry. ESP32 tetap mengirim `device_id` permanen; perubahan nama pos tidak membutuhkan perubahan sketch.

Samakan `APP_URL` pada web dengan `SERVER_URL` pada sketch, menggunakan alamat yang dapat dijangkau ESP32. Respons `ota_url` dibentuk dari `APP_URL`; sketch menolak URL download yang tidak memakai awalan server yang dikonfigurasi. Contoh `localhost` di bawah hanya untuk pengujian dari komputer server. Sketch saat ini menggunakan HTTP; mengganti URL menjadi HTTPS saja tidak menambahkan dukungan TLS.

## POST `/api/telemetry.php`

Gunakan `Content-Type: application/json`. Ukuran body maksimum 16 KB.

```json
{
    "device_id": "ESP32-001",
    "water_level": 25.5,
    "device_status": 1,
    "firmware_version": "v1.0.0"
}
```

| Field              | Validasi                                                                                      |
| ------------------ | --------------------------------------------------------------------------------------------- |
| `device_id`        | Wajib berupa string: 1â€“64 huruf ASCII, angka, `-`, atau `_`                                 |
| `water_level`      | Wajib berupa nilai numerik terbatas dalam cm; mendukung desimal                               |
| `device_status`    | Wajib berupa integer JSON `0` atau `1`; kirim `1` saat beroperasi                             |
| `firmware_version` | Opsional dengan format `vMAJOR.MINOR.PATCH`; versi tersimpan dipertahankan jika tidak dikirim |

Nama field yang benar adalah **`device_status`**, bukan `device_statust`. Boolean tidak diterima untuk field ini.

Server memvalidasi input, mengunci baris perangkat, memperbarui pembacaan/status terbaru dan waktu komunikasi, menentukan klasifikasi, lalu mencatat log dalam satu transaksi. Jika pencatatan log gagal, pembaruan perangkat juga dibatalkan.

Contoh respons berhasil `200`:

```json
{
    "status": "success",
    "alarm_threshold": 20,
    "buzzer_status": 1,
    "latest_firmware": "v1.1.0",
    "ota_url": "http://192.168.1.10:8000/api/firmware-download.php?id=1",
    "firmware_sha256": "<64 karakter heksadesimal SHA-256>",
    "update_interval": 60
}
```

- Kondisi buzzer: `buzzer_status == 1 && water_level > alarm_threshold`.
- Tepat pada threshold, buzzer tetap mati; sakelar bernilai `0` selalu menonaktifkannya.
- `update_interval` menggunakan detik, dari 1 hingga 86399. Dashboard menyediakan input jam:menit:detik.
- Versi firmware, URL, dan checksum semuanya bernilai `null` sampai firmware diunggah.
- Perubahan konfigurasi berlaku pada request berikutnya; tidak menggunakan push/WebSocket.
- Setiap POST berhasil mencatat satu log. Request tidak idempoten; pengiriman ulang dapat menghasilkan log duplikat.

Contoh Windows PowerShell:

```powershell
$body = @{
    device_id = 'ESP32-001'
    water_level = 25.5
    device_status = 1
    firmware_version = 'v1.0.0'
} | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri 'http://localhost:8000/api/telemetry.php' -ContentType 'application/json' -Body $body
```

Contoh cURL pada Bash:

```sh
curl -X POST http://localhost:8000/api/telemetry.php \
  -H 'Content-Type: application/json' \
  -d '{"device_id":"ESP32-001","water_level":25.5,"device_status":1,"firmware_version":"v1.0.0"}'
```

## GET `/api/device-config.php?device_id=ESP32-001`

Mengembalikan struktur konfigurasi yang sama. Digunakan saat boot dan pengecekan sebelum sensor siap. Tidak mencatat log, memperbarui `last_seen_at`, atau menandai perangkat Online.

```sh
curl 'http://localhost:8000/api/device-config.php?device_id=ESP32-001'
```

## GET `/api/firmware-download.php?id=1`

Mengalirkan binary dengan `Content-Type: application/octet-stream`, `Content-Length`, dan `Content-Disposition: attachment`. Gunakan `ota_url` dari respons, bukan menyusun path storage sendiri.

ESP32 membandingkan versi numerik (`v1.10.0 > v1.9.0`), mengunduh dan memverifikasi SHA-256, melakukan flashing melalui `Update.h`, lalu reboot hanya setelah berhasil. Firmware lama tetap dapat diunduh berdasarkan ID.

## Label Siaga

`sensor_logs.alarm_status`: `0` ditampilkan sebagai **Siaga 1**, `1` sebagai **Siaga 2**, `2` sebagai **Siaga 3**, dan `null` sebagai **Unclassified**. Setiap rentang mencakup batas bawah dan tidak mencakup batas atas. Siaga 3 hanya memakai batas bawah (`â‰¥`) tanpa batas atas. Batas atas Siaga 3 pada konfigurasi lama diabaikan saat mengklasifikasikan pembacaan baru; log historis tidak diubah.

Saat menyimpan pengaturan perangkat, server menolak batas atas Siaga 1 yang sama dengan batas bawah Siaga 2, serta batas atas Siaga 2 yang sama dengan batas bawah Siaga 3. Nilai numerik ekuivalen seperti `20` dan `20.00` tetap dianggap sama. Rentang tetap tidak boleh tumpang tindih; celah di antaranya menghasilkan `Unclassified`. Pembacaan konfigurasi lama dan log historis tidak mengubah data secara otomatis.

## Respons error

Pesan API tetap menggunakan bahasa Inggris sesuai implementasi:

```json
{ "status": "error", "message": "Device is not registered." }
```

| HTTP  | Arti                                           |
| ----- | ---------------------------------------------- |
| `404` | Perangkat, firmware, atau file tidak ditemukan |
| `405` | Metode salah; lihat header `Allow`             |
| `413` | Body telemetry melebihi 16 KB                  |
| `415` | Content-Type telemetry bukan application/json  |
| `422` | JSON, input, atau ID tidak valid               |
| `500` | Error internal; detail dicatat pada log server |

Saat jaringan gagal, contoh firmware mempertahankan konfigurasi terakhir yang valid. Sebelum konfigurasi pertama diterima, buzzer dinonaktifkan. Pembacaan sensor invalid tidak dikirim dan menonaktifkan buzzer; perangkat akhirnya menjadi Offline jika telemetry berhenti.

## Endpoint dashboard

`GET /api/viewer-dashboard.php` adalah endpoint publik khusus tampilan viewer. Respons berisi `status`, `html`, `total`, dan `online`, tanpa riwayat atau jumlah log. HTML tidak berisi tautan history/edit. `online` selalu sama dengan `total` sesuai aturan presentasi viewer; ini tidak mengubah status koneksi yang tersimpan. Endpoint Admin `/api/dashboard.php` tetap memerlukan login dan mengembalikan status sebenarnya. Halaman publik: `/viewer.php` dan `/viewer-maps.php`.

`GET /api/dashboard.php` memperbarui panel muka air setiap 5 detik setelah request sebelumnya selesai. Respons berisi HTML panel yang sudah di-escape, total perangkat, jumlah Online, dan jumlah log. Struktur field JSON tetap sama; field `html` kini merender indikator air SVG, rentang Siaga, status, dan pembacaan terakhir. Endpoint ini digunakan frontend, bukan kontrak telemetry ESP32.

Grafik memakai rentang perangkat yang sedang berlaku dengan label Siaga 1, Siaga 2, dan Siaga 3. Batas bawah inklusif, batas atas eksklusif; Siaga 3 selalu ditampilkan sebagai `â‰¥ batas bawah` dan areanya memanjang sampai puncak skala tampilan. Label Siaga pada SVG memakai font 11 dan kotak 68 Ã— 18 unit agar lebih ringkas. Skala sumbu ditentukan otomatis dari pembacaan dan batas rentang, bukan tinggi maksimum fisik. Ketika perangkat Offline, pembacaan terakhir tetap ditampilkan dengan keterangan Offline. Tanpa pembacaan, panel menampilkan `No readings yet` tanpa mengisi grafik air. Jika request gagal atau dibatalkan setelah 15 detik, browser mempertahankan panel terakhir, mengakhiri status loading, dan mencoba kembali.

## Detail perangkat dan grafik riwayat

Halaman `/device-history.php?device_id=ESP32-001` dibuka melalui tombol **View details & history**. Desktop menampilkan indikator air dan grafik berdampingan; layar kecil menumpuk keduanya. Grafik memakai Chart.js 4.6.1 lokal di `/assets/vendor/chartjs-4.6.1/chart.umd.min.js`, tanpa adapter tanggal atau plugin tambahan. File distribusi diunduh dari `https://cdn.jsdelivr.net/npm/chart.js@4.6.1/dist/chart.umd.min.js`, disimpan tanpa modifikasi, dan disertai `LICENSE.md`. Browser tidak mengakses CDN sehingga grafik dapat digunakan tanpa internet.

`GET /api/device-history.php?device_id=ESP32-001` mengembalikan:

- `status`: `success`.
- `device`: informasi perangkat, rentang Siaga saat ini, dan waktu/status komunikasi terakhir.
- `points`: maksimal 500 log terbaru untuk perangkat tersebut, diurutkan menaik berdasarkan waktu dan ID log. Setiap titik berisi `x` (Unix timestamp UTC dalam milidetik), `y` (ketinggian cm), serta `alarm_status` historis (`0`, `1`, `2`, atau `null`).
- `timezone`: zona waktu tampilan dari `APP_TIMEZONE`.
- `limit`: `500`.
- `html`: indikator perangkat yang diperbarui dengan escaping output.

Tanpa filter, respons juga berisi `filters: null`, `aggregation: raw`, `period: null`, dan `reading_count` untuk jumlah log yang dikirim. Warna titik mengikuti klasifikasi historis, sehingga perubahan rentang saat ini tidak mengubah label log lama. Sumbu waktu memakai timestamp numerik. Mode raw satu timestamp diberi ruang 30 detik di masing-masing sisi sumbu.

Halaman detail secara default memakai 30 hari terakhir termasuk hari ini berdasarkan `APP_TIMEZONE` (From date = hari ini dikurangi 29 hari, To date = hari ini). Input tanggal dan grafik langsung memakai periode ini dengan pengelompokan harian otomatis. Filter **From date**, **To date**, dan **Group by** diterapkan lewat **Apply filters**. URL halaman menyimpan filter yang berhasil diterapkan. Contoh endpoint:

`GET /api/device-history.php?device_id=ESP32-001&from=2026-01-01&to=2026-12-31&group_by=auto`

- `from` dan `to` wajib berpasangan dalam format tanggal kalender `YYYY-MM-DD`, keduanya inklusif dalam zona waktu aplikasi. Rentang tanggal yang didukung `1000-01-01` hingga `9999-12-30`; awal tidak boleh melewati akhir.
- `group_by`: `auto` (default), `minute`, `hour`, `day`, `month`, atau `year`. Auto memakai jam untuk satu hari, hari untuk 2â€“62 hari, bulan untuk 63â€“731 hari, dan tahun untuk rentang lebih panjang. Pilihan **Minute** menampilkan satu interval per menit dan mensyaratkan `from` serta `to` pada tanggal yang sama; memakai dua tanggal berbeda menghasilkan `422` dengan petunjuk memilih satu tanggal.
- Pada halaman grafik, validasi Minute dijalankan saat **Apply filters** ditekan. Jika tanggal berbeda, Bootstrap alert ditampilkan dan diberi fokus tanpa mengirim request. Teks bantuan permanen tidak ditampilkan. Input dan grafik terakhir dipertahankan; mengedit filter menutup alert. Validasi server tetap berlaku untuk request langsung.
- Agregasi mencakup **seluruh** log perangkat dalam periode, tanpa batas 500 log raw. Mode menit mendukung satu hari penuh (1.440 interval, hingga 1.500 pada hari DST 25 jam). Pengelompokan lain yang menghasilkan lebih dari 500 interval ditolak dengan `422`; pilih rentang lebih pendek atau interval lebih besar. Data tidak dipotong diam-diam.
- `filters` berisi pilihan tanggal dan pengelompokan yang diminta; `aggregation` berisi interval aktual. `limit` menjadi `null`, `reading_count` adalah jumlah seluruh pembacaan terpilih, dan `period` berisi batas sumbu `from`/`to` UTC milidetik.
- `points` berisi satu titik per interval kalender: `x`, `y` (rata-rata cm), `min`, `max`, `count`, dan `alarm_status: null`. Interval kosong memakai nilai `null` serta `count: 0`, bukan nol cm. Interval pertama yang parsial dimulai pada batas tanggal pilihan. Rata-rata dihitung langsung dari pembacaan, bukan rata-rata antarinterval.

Grafik periode menampilkan garis rata-rata, minimum, dan maksimum dengan tooltip jumlah pembacaan. Titik, label menit/jam/tanggal/bulan/tahun, dan jumlah label sumbu menyesuaikan interval serta lebar layar. Mode menit memakai label `HH:mm` dan tooltip tanggal/jam lengkap. Interval kosong menjadi celah garis; pembacaan terisolasi tetap ditampilkan sebagai titik meskipun intervalnya padat. Klasifikasi Siaga tetap tersedia pada indikator pembacaan terkini; rata-rata periode tidak diberi klasifikasi historis palsu. Zona waktu dikelola PHP, termasuk perubahan DST, tanpa membutuhkan tabel zona waktu MySQL.

Endpoint hanya membaca database dan tidak menambah log atau memperbarui status perangkat. Perangkat tak terdaftar menghasilkan `404`, identifier/filter tidak valid menghasilkan `422`, dan metode selain GET menghasilkan `405`. Halaman detail mengembalikan `404` untuk identifier tidak valid atau perangkat tidak ditemukan, serta `422` untuk filter invalid. Halaman dan API riwayat memerlukan login Admin; pengunjung tanpa login tidak memiliki akses riwayat.

Halaman menyediakan Refresh manual dan polling berurutan setiap 5 detik setelah request selesai untuk filter yang telah diterapkan. Perubahan filter menjeda polling, membatalkan request lama, dan mempertahankan draft. Respons usang diabaikan; kegagalan memuat tidak menghapus chart terakhir atau draft filter. Permintaan ganda dicegah dan request dibatalkan setelah 15 detik. Tombol **Today** mengembalikan periode ke hari ini dengan konfirmasi jika ada draft yang akan dibuang; navigasi juga memperingatkan draft belum diterapkan. Riwayat kosong menampilkan pesan tanpa data simulasi. Library lokal yang gagal dimuat menampilkan error dan tombol **Reload page**. Daftar log individual tetap tersedia pada halaman Sensor logs.

Terakhir diperbarui: 9 November 2026. Departemen peminta, owner/PIC, dan maintainer: Not confirmed. Pengguna: pemantau perangkat. Pembaruan dokumentasi ini memperjelas identitas perangkat, alamat OTA, dan akses Admin tanpa mengubah kontrak telemetry ESP32.
