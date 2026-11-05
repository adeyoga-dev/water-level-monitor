# API ESP32

Contoh base URL: `http://192.168.1.10:8000`. Endpoint menggunakan `/api/` tanpa login atau token. Daftarkan `device_id` di dashboard terlebih dahulu; huruf besar/kecil dibedakan.

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
| `device_id`        | Wajib berupa string: 1–64 huruf ASCII, angka, `-`, atau `_`                                   |
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

`sensor_logs.alarm_status`: `0` ditampilkan sebagai **Siaga 1**, `1` sebagai **Siaga 2**, `2` sebagai **Siaga 3**, dan `null` sebagai **Unclassified**. Setiap rentang mencakup batas bawah dan tidak mencakup batas atas. Klasifikasi log historis tidak berubah saat pengaturan diedit.

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

`GET /api/dashboard.php` memperbarui tabel browser setiap 5 detik. Respons berisi HTML tabel yang sudah di-escape, total perangkat, jumlah Online, dan jumlah log. Endpoint ini digunakan frontend, bukan kontrak telemetry ESP32.
