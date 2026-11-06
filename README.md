# RailSafe

RailSafe adalah prototipe sistem pemantauan perlintasan kereta api. Sistem menggabungkan deteksi getaran berbasis TinyML pada **Node 1**, komunikasi LoRa ke **Node 2** di pos perlintasan, kendali palang lokal, serta aplikasi Laravel untuk pencatatan dan pemantauan.

> **Peringatan keselamatan:** proyek ini adalah prototipe/demo. Firmware saat ini memakai ambang deteksi dan timer sederhana, tidak memastikan kereta sudah lewat sebelum membuka palang, serta belum memiliki redundansi atau sertifikasi keselamatan. Jangan gunakan untuk mengendalikan palang perlintasan kereta api sungguhan atau menggantikan sistem keselamatan resmi.

## Isi dokumen

- [Arsitektur](#arsitektur)
- [Struktur direktori](#struktur-direktori)
- [Perangkat keras dan sambungan](#perangkat-keras-dan-sambungan)
- [Cara kerja firmware](#cara-kerja-firmware)
- [Menyiapkan firmware ESP32](#menyiapkan-firmware-esp32)
- [Menyiapkan aplikasi Laravel](#menyiapkan-aplikasi-laravel)
- [API perangkat](#api-perangkat)
- [Akun dan simulasi demo](#akun-dan-simulasi-demo)
- [Pengaturan sistem](#pengaturan-sistem)
- [Batasan yang diketahui](#batasan-yang-diketahui)
- [Pemecahan masalah](#pemecahan-masalah)

## Arsitektur

```text
MPU6050 ──I²C──> Node 1 (ESP32 + TinyML)
                         │
                    LoRa 433 MHz
                         │
                         v
        Node 2 (ESP32 + penerima LoRa)
          ├── servo palang
          ├── LCD, buzzer, tombol operator
          ├── 2 sensor ultrasonik
          └── Wi-Fi / HTTP ──> Laravel API ──> basis data ──> dashboard
```

- **Node 1** mengambil data akselerometer dan giroskop MPU6050, menjalankan model yang disimpan di `Node 1 RailSafe/model_data.h`, lalu mengirim peringatan kereta melalui LoRa.
- **Node 2** menerima paket, mengendalikan servo dan antarmuka lokal, memeriksa area palang dengan dua sensor ultrasonik, serta mengirim log aksi ke aplikasi Laravel bila Wi-Fi tersedia.
- **Aplikasi Laravel** menyediakan dashboard, riwayat kejadian, ekspor CSV, pengelolaan pos/perangkat, pengaturan, dan API untuk perangkat ESP32.

## Struktur direktori

| Direktori/file | Kegunaan |
| --- | --- |
| `Node 1 RailSafe/Node1.ino` | Firmware Node 1: MPU6050, TinyML, dan pemancar LoRa. Ini salinan utama; `model_data.h` ada di direktori yang sama. |
| `Node 1 RailSafe/model_data.h` | Model TinyML hasil ekspor sebagai array byte C++. |
| `Node 1 RailSafe/Node1/Node1.ino` | Salinan firmware Node 1. Samakan perubahan dengan file utama bila salinan ini masih digunakan. |
| `Node 2 RailSafe/Node2.ino` | Firmware Node 2: palang, LoRa, LCD, tombol, dua ultrasonik, dan API Laravel. |
| `Machine Learning/Kode Arduino/Data MPU/Data MPU.ino` | Sketch untuk mengambil data mentah MPU6050 melalui Serial. |
| `Machine Learning/Pencatatan MPU.py` | Skrip Python untuk merekam keluaran Serial ke CSV. |
| `Machine Learning/Dummy File.py` | Pembuat dataset sintetis untuk idle, truk, dan kereta; bukan pengganti dataset pengukuran nyata. |
| `app/`, `routes/`, `resources/` | Aplikasi Laravel, controller, route, dan tampilan web. |
| `database/migrations/`, `database/seeders/` | Skema database dan data demo. |

## Perangkat keras dan sambungan

### Node 1

Perangkat yang disebut oleh firmware:

- ESP32
- MPU6050
- Modul LoRa Ra-02 berbasis SX1278, 433 MHz

| Modul | Sinyal | GPIO ESP32 |
| --- | --- | ---: |
| MPU6050 | SDA | 21 |
| MPU6050 | SCL | 22 |
| LoRa | SCK | 18 |
| LoRa | MISO | 19 |
| LoRa | MOSI | 23 |
| LoRa | NSS/CS | 5 |
| LoRa | RESET | 14 |
| LoRa | DIO0 | 2 |

Firmware mengatur MPU6050 ke rentang akselerometer ±4 g, giroskop ±500°/s, dan bandwidth filter 44 Hz.

### Node 2

Perangkat yang disebut oleh firmware:

- ESP32
- LoRa Ra-02/SX1278, 433 MHz
- LCD I²C 16×2, alamat `0x27`
- Servo MG996R
- Buzzer aktif
- Tombol arcade dengan `INPUT_PULLUP`
- Dua sensor ultrasonik HC-SR04

| Modul | Sinyal | GPIO ESP32 |
| --- | --- | ---: |
| LoRa | SCK / MISO / MOSI | 18 / 19 / 23 |
| LoRa | NSS/CS / RESET / DIO0 | 5 / 14 / 2 |
| LCD I²C | SDA / SCL | I²C bawaan ESP32 (umumnya 21 / 22) |
| Servo | Sinyal | 13 |
| Buzzer | Sinyal | 15 |
| Tombol | Sinyal ke GND saat ditekan | 4 |
| Ultrasonik 1 | TRIG / ECHO | 12 / 27 |
| Ultrasonik 2 | TRIG / ECHO | 26 / 25 |

Pin ultrasonik kedua ditetapkan di `Node2.ino`; ubah `TRIG_PIN_2` dan `ECHO_PIN_2` jika rangkaian memakai pin lain. Pastikan tegangan sinyal ECHO HC-SR04 aman untuk GPIO ESP32 3,3 V; gunakan pembagi tegangan/level shifter bila modul memberikan sinyal 5 V. Catu daya servo sebaiknya sesuai kebutuhan arus servo dan memiliki ground bersama dengan ESP32.

## Cara kerja firmware

### Node 1: deteksi dan pengiriman

1. Membaca enam nilai MPU6050 dalam urutan `accX, accY, accZ, gyroX, gyroY, gyroZ`.
2. Mengumpulkan 100 sampel (600 nilai) dengan target interval 10 ms.
3. Menjalankan inferensi TinyML pada setiap jendela yang terkumpul.
4. Mengirim peringatan jika probabilitas kelas kereta lebih dari `0.70`. Kelas truk di atas `0.75` hanya dicatat di Serial Monitor dan tidak memicu palang.
5. Paket LoRa peringatan berbentuk:

   ```text
   RailSafe:ALERT_KERETA:<confidence>
   ```

   Contoh: `RailSafe:ALERT_KERETA:0.83`.

### Node 2: palang dan sensor

- Node 2 menerima paket LoRa dengan sync word `0xF3` dan hanya memproses paket berawalan `RailSafe:ALERT_KERETA`.
- Bila salah satu sensor mengukur objek kurang dari 15 cm, Node 2 menunggu satu detik lalu membaca ulang kedua sensor. Jika objek masih terdeteksi, palang tetap terbuka dan fungsi kembali. Peringatan LoRa berikutnya akan mencoba kembali proses tersebut.
- Jika area terdeteksi kosong pada pembacaan awal atau ulang, servo diperintahkan ke sudut tutup (`90°`). Sudut buka adalah `0°`.
- Tombol manual men-toggle palang dan mengirim log manual ke server bila Wi-Fi terhubung.
- Saat palang tertutup, buzzer dibunyikan dengan pola jeda 150 ms.
- Palang otomatis dibuka setelah 15 detik sejak peringatan LoRa terakhir. Ini timer prototipe, bukan deteksi kereta lewat.

### Integrasi Laravel

Node 2 mengirim `POST /api/gate-log` ke alamat yang ditentukan di konstanta `LARAVEL_API`. Payload firmware saat ini:

```json
{
  "trigger_type": "AUTOMATIC",
  "confidence_score": 0.83,
  "notes": "Kereta terdeteksi TinyML"
}
```

Untuk aksi tombol, `trigger_type` berisi `MANUAL`. Controller Laravel mengubah skor otomatis dalam rentang 0–1 menjadi persen. Skor manual diabaikan dan disimpan sebagai `null`. Aksi buka/tutup saat ini dikenali dari teks `notes` (teks yang memuat “dibuka” akan disimpan sebagai `OPENED`).

## Menyiapkan firmware ESP32

1. Pasang Arduino IDE atau Arduino CLI dan dukungan board ESP32.
2. Pasang library yang digunakan sketch:
   - `LoRa` (Sandeep Mistry)
   - `Adafruit MPU6050`
   - `Adafruit Unified Sensor`
   - `Eloquent TinyML` beserta dependensi TensorFlow Lite Micro yang diperlukan versinya
   - `LiquidCrystal_I2C`
   - `ESP32Servo`
3. Pilih board dan port ESP32 yang sesuai.
4. Buka firmware Node 1 bersama `model_data.h`. Pastikan file header berada di folder sketch yang sama dan pemanggilan model memakai simbol `g_RailSafe_model`.
5. Buka `Node 2 RailSafe/Node2.ino`, sesuaikan pin ultrasonik kedua, SSID/kata sandi Wi-Fi, serta URL Laravel pada konstanta di bagian atas file.
6. Unggah firmware ke kedua board. Buka Serial Monitor pada 115200 baud untuk melihat status inisialisasi dan prediksi.

> Di repo ada dua salinan `Node1.ino`. Gunakan file utama di `Node 1 RailSafe/Node1.ino` bersama `model_data.h`, atau pastikan salinan di subfolder tetap identik dan dapat menemukan header tersebut. File perekaman MPU adalah sketch terpisah, bukan firmware Node 1 operasional.

### Mengumpulkan data MPU

1. Unggah `Machine Learning/Kode Arduino/Data MPU/Data MPU.ino` ke ESP32 akuisisi.
2. Tutup Serial Monitor Arduino agar port tidak terkunci.
3. Di `Machine Learning/Pencatatan MPU.py`, sesuaikan `PORT_COM`, label `NAMA_LABEL`, dan lama rekam.
4. Pasang dependensi Python yang digunakan skrip (`pyserial`), lalu jalankan skrip. CSV dibuat di direktori kerja saat perintah dijalankan.

Sketch akuisisi menggunakan `Wire.begin(21, 22)`, sesuai dengan sambungan I²C Node 1 pada tabel di atas.

`Machine Learning/Dummy File.py` menghasilkan sinyal sintetis untuk uji bentuk data. Repo ini belum menyertakan notebook/script pelatihan dan evaluasi model yang lengkap; jangan menganggap dataset sintetis sebagai validasi akurasi lapangan.

## Menyiapkan aplikasi Laravel

### Kebutuhan

- PHP **8.3 atau lebih baru** dan ekstensi yang dibutuhkan Laravel
- Composer
- Node.js dan npm
- Database. Konfigurasi contoh menggunakan SQLite.

### Instalasi lokal (PowerShell)

Jalankan dari direktori proyek:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan migrate --seed
npm install
npm run build
php artisan serve --host 0.0.0.0 --port 8000
```

Jika `.env` sudah ada, jangan menimpanya dengan `.env.example`; cukup pastikan `APP_KEY` terisi dan `DB_CONNECTION=sqlite`. Untuk database MySQL/PostgreSQL, atur `DB_*` sesuai server dan jangan membuat file SQLite. Perangkat ESP32 harus dapat mengakses IP komputer/server pada jaringan yang sama; `localhost` dari ESP32 menunjuk ke ESP32 itu sendiri, bukan komputer Laravel.

### Menjalankan tampilan saat pengembangan

Untuk perubahan frontend, jalankan server Laravel dan Vite di dua terminal:

```powershell
php artisan serve --host 0.0.0.0 --port 8000
npm run dev -- --host 0.0.0.0
```

## API perangkat

Semua rute API perangkat berada di middleware `device.token` dan dibatasi hingga 120 permintaan per menit.

| Metode dan path | Fungsi |
| --- | --- |
| `POST /api/gate-log` | Menyimpan satu aksi palang dari format firmware Node 2 saat ini. |
| `POST /api/telemetry` | Format telemetri/event yang lebih lengkap; memerlukan `event_id`, `event_type`, `control_mode`, dan `event_timestamp`. |
| `POST /api/heartbeat` | Memperbarui status perangkat dan kesehatan sensor. Opsional; firmware saat ini belum memanggilnya. |
| `GET /api/config` | Mengambil nilai pengaturan dari server. Firmware saat ini belum menerapkan nilai tersebut ke ambang/timer lokal. |

### Mode demo dan token perangkat

`RAILSAFE_REQUIRE_TOKEN` di `.env` mengontrol autentikasi:

- `false` (nilai default contoh): permintaan tanpa token dipetakan ke perangkat `GATE_CONTROLLER` aktif pertama. Praktis untuk demo lokal satu pos.
- `true`: wajib mengirim `Authorization: Bearer <token>`.

Token perangkat dapat dibuat/diputar melalui halaman admin perangkat. Hash token disimpan di database; token asli hanya ditampilkan saat dibuat/diputar. Firmware Node 2 saat ini **belum** mengirim header `Authorization`, jadi mode wajib-token tidak akan menerima log sampai firmware dilengkapi token dan header tersebut. Jangan gunakan mode demo di jaringan yang tidak tepercaya.

## Akun dan simulasi demo

Seeder membuat data contoh: satu pos, dua perangkat, beberapa sensor, pengaturan, dan log riwayat. Kredensial demo:

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Superadmin | `superadmin@railsafe.test` | `password` |
| Operator | `operator@railsafe.test` | `password` |

Ganti/hapus akun dan kata sandi demo sebelum aplikasi dibuka ke jaringan yang lebih luas. Operator dibatasi ke pos yang ditugaskan; superadmin dapat mengelola akun, pos, perangkat, token, pengaturan, dan audit.

Perintah simulasi untuk mengisi aktivitas tanpa perangkat keras:

```powershell
php artisan railsafe:simulate --train
php artisan railsafe:simulate --manual
php artisan railsafe:simulate
```

`--train` membuat log penutupan otomatis dan mengirim heartbeat selama waktu buka ulang yang dikonfigurasi; `--manual` membuat log tutup/buka manual; tanpa opsi, perintah mengirim heartbeat tiap lima detik sampai dihentikan dengan Ctrl+C.

## Pengaturan sistem

| Pengaturan | Default | Catatan |
| --- | ---: | --- |
| `confidence_threshold` | 70 | Persen; sama dengan batas 0.70 di firmware Node 1 saat ini, tetapi nilai dari API belum dibaca firmware. |
| `gate_reopen_seconds` | 15 | Waktu buka ulang pada ringkasan dashboard dan simulasi. Firmware Node 2 memiliki konstanta 15 detik sendiri. |
| `countdown_seconds` | 15 | Disediakan untuk alur proposal; belum digunakan firmware saat ini. |

Pengaturan dapat diubah oleh superadmin. Firmware belum mengambil `GET /api/config`, sehingga perubahan di dashboard tidak otomatis mengubah konfigurasi ESP32.

## Batasan yang diketahui

- Pembukaan palang Node 2 menggunakan timeout setelah peringatan terakhir, bukan konfirmasi bahwa kereta telah lewat.
- Jika ultrasonik tidak menerima pantulan, pembacaan dianggap `999 cm` (area kosong). Sensor gagal/terputus karena itu belum ditangani sebagai kondisi fault yang menahan palang.
- Pemeriksaan objek dilakukan ketika peringatan kereta masuk, bukan pemantauan kontinu di area palang.
- Tombol manual menggunakan debounce berbasis waktu; menahan tombol dapat menyebabkan toggle berulang.
- Operasi servo, HTTP, `delay()`, dan pembacaan sensor bersifat blocking. Koneksi Wi-Fi/API yang lambat dapat menunda pembacaan LoRa dan tombol.
- Node 1 mengirim prediksi setiap jendela satu detik saat ambang kereta terlampaui. Sampling aktual dapat berubah akibat waktu inferensi dan operasi lain.
- Model header tersedia, tetapi pipeline pelatihan/evaluasi, metrik akurasi, data lapangan, serta prosedur kalibrasi pemasangan MPU belum didokumentasikan di repo.
- Database seeder dan API mode tanpa token ditujukan untuk demo. Kredensial, token, HTTP tanpa TLS, serta alamat IP lokal perlu ditinjau sebelum deployment.
- Dashboard menampilkan status palang dari log terakhir dan estimasi timer; firmware belum mengirim log saat palang otomatis terbuka kembali.

## Pemecahan masalah

| Gejala | Pemeriksaan |
| --- | --- |
| Node 1 gagal menemukan MPU6050 | Periksa catu daya, ground, SDA 21/SCL 22, dan alamat I²C modul. |
| Model gagal dimuat | Pastikan `model_data.h` ikut dalam folder sketch dan firmware memanggil `g_RailSafe_model`. Periksa board ESP32 dan library TinyML yang dipasang. |
| Node 2 tidak menerima peringatan | Pastikan kedua node memakai 433 MHz, sync word `0xF3`, serta format `RailSafe:ALERT_KERETA:<confidence>`. Periksa kabel SPI, antena, dan catu daya LoRa. |
| Palang tidak bergerak | Periksa catu daya servo, ground bersama, sambungan GPIO 13, dan rentang sudut servo. Jangan memberi daya servo dari pin 3,3 V ESP32. |
| Ultrasonik selalu mendeteksi kosong | Periksa TRIG/ECHO kedua sensor, ground bersama, arah sensor, batas ukur, dan level tegangan ECHO. Ambang firmware saat ini 15 cm. |
| LCD kosong | Periksa SDA/SCL, alamat I²C `0x27`, kontras, dan catu daya LCD. |
| Log API gagal | Pastikan server Laravel hidup, URL memakai IP LAN server (bukan `localhost`), firewall mengizinkan koneksi, Wi-Fi tersambung, database sudah dimigrasi, dan mode token sesuai firmware. Periksa kode HTTP di Serial Monitor. |
| Dashboard tidak bisa login | Jalankan `php artisan migrate --seed`, lalu gunakan akun demo di atas. Jangan jalankan seeder berulang pada database berisi data yang ingin dipertahankan karena seeder membuat akun/riwayat demo baru. |

## Lisensi dan kontribusi

Belum ada lisensi proyek khusus yang ditetapkan di repo. Tinjau lisensi dependency/library dan tetapkan lisensi proyek sebelum redistribusi atau penggunaan publik.
