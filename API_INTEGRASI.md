# RailSafe Dashboard (Laravel)

File aplikasi untuk ditempel ke proyek Laravel 11 atau 12 baru (bukan proyek utuh; folder `vendor` dibuat Composer).
Disesuaikan dengan firmware `Node2.ino` (endpoint `/api/gate-log`). Detail sambungan ke ESP32 ada di `ESP32_INTEGRASI.md`.

## Pasang

```bash
composer create-project laravel/laravel railsafe
cd railsafe
```

1. Salin seluruh isi zip ke folder `railsafe`, timpa file yang sama
   (`bootstrap/app.php`, `app/Models/User.php`, `database/seeders/DatabaseSeeder.php`, `routes/web.php`).
2. Di `.env` tambahkan:
   ```
   APP_TIMEZONE=Asia/Jakarta
   RAILSAFE_REQUIRE_TOKEN=false
   ```
   (`false` agar firmware tanpa token bisa langsung mengirim. Ubah ke `true` untuk deployment.)
3. Jalankan:
   ```bash
   php artisan migrate --seed
   php artisan serve --host=0.0.0.0
   ```
   `--host=0.0.0.0` perlu agar ESP32 di Wi-Fi yang sama bisa menjangkau laptop. Isi `LARAVEL_API` di Node2.ino dengan `http://<IP-laptop>:8000/api/gate-log`.

| Akun | Email | Kata sandi |
|---|---|---|
| Superadmin | superadmin@railsafe.test | password |
| Operator (pos PJL-MLG-01) | operator@railsafe.test | password |

Ganti kata sandi dan token demo sebelum dipakai di luar demo.

## Demo tanpa alat

```bash
php artisan railsafe:simulate            # heartbeat terus-menerus (terminal kedua)
php artisan railsafe:simulate --train    # palang ditutup otomatis (banner merah + hitung mundur)
php artisan railsafe:simulate --manual   # petugas menutup lalu membuka palang
```

## API

| Endpoint | Dipakai oleh |
|---|---|
| `POST /api/gate-log` | **Node2.ino saat ini.** Body: `trigger_type` (AUTOMATIC/MANUAL), `confidence_score`, `notes` |
| `POST /api/heartbeat` | Opsional: rssi, jarak HC-SR04, baterai, status sensor |
| `POST /api/telemetry` | Opsional: format lengkap proposal (respons petugas, rintangan), idempoten per `event_id` |
| `GET /api/config` | Opsional: ambang keyakinan dan waktu palang terbuka kembali |

```bash
curl -X POST http://localhost:8000/api/gate-log -H "Content-Type: application/json" \
  -d '{"trigger_type":"AUTOMATIC","confidence_score":0.93,"notes":"Kereta terdeteksi TinyML"}'
```

## Halaman

- Operator dan superadmin: Dashboard, Riwayat kejadian (filter, ekspor CSV, catatan evaluasi).
- Superadmin saja: Kelola akun, Pos dan perangkat, Parameter sistem, Audit log.
- Operator hanya melihat pos yang ditugaskan kepadanya.

Tombol buka/tutup palang dari browser sengaja tidak ada: proposal menetapkan website hanya untuk monitoring.