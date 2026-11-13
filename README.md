# RailSafe

RailSafe adalah prototipe sistem pemantauan perlintasan kereta api. Sistem menggabungkan deteksi getaran berbasis TinyML pada **Node 1**, komunikasi LoRa ke **Node 2** di pos perlintasan, kendali palang lokal, serta aplikasi Laravel untuk pencatatan dan pemantauan.

> **Peringatan keselamatan:** proyek ini adalah prototipe/demo. Firmware saat ini memakai ambang deteksi dan timer sederhana, tidak memastikan kereta sudah lewat sebelum membuka palang, serta belum memiliki redundansi atau sertifikasi keselamatan. Jangan gunakan untuk mengendalikan palang perlintasan kereta api sungguhan atau menggantikan sistem keselamatan resmi.

## Isi dokumen

- [Arsitektur](#arsitektur)
- [Struktur direktori](#struktur-direktori)
- [Perangkat keras dan sambungan](#perangkat-keras-dan-sambungan)
- [Cara kerja firmware](#cara-kerja-firmware)
- [Menyiapkan firmware ESP32](#menyiapkan-firmware-esp32)
- [Kode Arduino — penjelasan detail](#kode-arduino--penjelasan-detail)
- [Machine Learning — penjelasan detail](#machine-learning--penjelasan-detail)
- [Menyiapkan aplikasi Laravel](#menyiapkan-aplikasi-laravel)
- [Database seeder](#database-seeder)
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
| `Node 2 RailSafe/Node2/Node2.ino` | Firmware Node 2: palang, LoRa, LCD, tombol, dua ultrasonik, dan API Laravel. |
| `Machine Learning/Kode Arduino/Data MPU/Data MPU.ino` | Sketch untuk mengambil data mentah MPU6050 melalui Serial. |
| `Machine Learning/Pencatatan MPU.py` | Skrip Python untuk merekam keluaran Serial ke CSV. |
| `Machine Learning/Dummy File.py` | Pembuat dataset sintetis untuk idle, truk, dan kereta; bukan pengganti dataset pengukuran nyata. |
| `app/`, `routes/`, `resources/` | Aplikasi Laravel, controller, route, dan tampilan web. |
| `database/migrations/` | Skema tabel database. |
| `database/seeders/AccountSeeder.php` | Seeder akun demo (superadmin & operator). |
| `database/seeders/CrossingSeeder.php` | Seeder pos perlintasan, perangkat, log sensor awal, dan pengaturan default. |
| `database/seeders/SimulationSeeder.php` | Seeder riwayat log palang 7 hari (data simulasi, 78% otomatis / 22% manual). |
| `database/seeders/DatabaseSeeder.php` | Titik masuk utama; memanggil ketiga seeder di atas secara berurutan. |

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

Untuk buzzer yang memerlukan arus/tegangan lebih besar, jangan menggerakkannya langsung dari GPIO 15. Gunakan modul driver/transistor dan catu daya sesuai spesifikasi buzzer; satukan GND catu daya buzzer dengan GND ESP32. Firmware memberi sinyal aktif-HIGH.

## Cara kerja firmware

### Node 1: deteksi dan pengiriman

1. Membaca enam nilai MPU6050 dalam urutan `accX, accY, accZ, gyroX, gyroY, gyroZ`.
2. Mengumpulkan 100 sampel (600 nilai) dengan target interval 10 ms.
3. Menjalankan inferensi TinyML pada setiap jendela yang terkumpul.
4. Mengirim alert jika probabilitas kereta lebih dari `0.70`. Mengirim status-clear jika probabilitas kereta maksimal `0.30` dan idle lebih dari `0.60` atau truk lebih dari `0.75`. Prediksi yang belum pasti tidak mengirim status-clear.
5. Paket LoRa dari Node 1 terdiri dari:

   ```text
   RailSafe:ALERT_KERETA:<confidence>
   RailSafe:STATUS_CLEAR:0.00
   RailSafe:NODE_STATUS:<IDLE|TRUK|KERETA>:<confidence>
   ```

   `ALERT_KERETA` memulai countdown penutupan. `STATUS_CLEAR` dikirim hanya saat prediksi idle/truk cukup yakin. `NODE_STATUS` dikirim tiap 5 detik dengan kelas TinyML dan confidence agar Node 2 memperbarui status Node 1 di dashboard. Status yang hilang atau tidak pasti tidak dianggap sebagai bukti bahwa kereta sudah lewat.

### Node 2: palang dan sensor

- Node 2 menerima paket LoRa dengan sync word `0xF3`: `ALERT_KERETA`, `STATUS_CLEAR`, dan `NODE_STATUS` dari Node 1.
- Saat alert pertama diterima, Node 2 memulai countdown `countdown_seconds`. Petugas dapat menutup palang manual sebelum countdown habis; jika tidak, Node 2 mencoba menutup otomatis.
- Jika Node 2 menerima `STATUS_CLEAR` sebelum countdown habis, countdown dibatalkan dan LCD kembali menampilkan status aman/standby.
- Dua HC-SR04 dipindai setiap 2,5 detik; kedua sensor dibaca bergantian dengan jeda 60 ms. Satu bacaan di bawah 15 cm langsung menandai rintangan, sementara area harus bersih dalam dua sweep sebelum palang otomatis menutup kembali. Jika area terhalang saat countdown habis, palang ditahan terbuka. Jika objek terdeteksi saat palang otomatis tertutup, palang dibuka dan ditahan sampai area bersih. Jarak dicetak ke Serial Monitor setiap sweep.
- Tombol arcade bekerja dengan mode tahan untuk penutupan manual: saat ditekan ketika siaga/hitung mundur palang menutup, lalu saat dilepas palang terbuka dan sistem kembali standby. Jika palang sudah tertutup otomatis, tekan tombol untuk membuka melalui override petugas; alert berulang diabaikan sampai Node 1 mengirim `STATUS_CLEAR`. Tombol yang ditekan ketika sensor mendeteksi rintangan diabaikan.
- Saat hitung mundur, buzzer aktif 300 ms lalu jeda 100 ms. Saat palang tertutup, buzzer aktif 800 ms lalu jeda 120 ms. Saat rel terhalang, buzzer menyala terus.
- Timer `gate_reopen_seconds` hanya membuka palang yang ditutup otomatis setelah `STATUS_CLEAR` segar diterima berkelanjutan dan palang tertutup setidaknya selama interval tersebut. Palang manual menutup selama tombol ditahan dan terbuka saat tombol dilepas. Status Node 1 yang hilang menahan palang otomatis tetap tertutup. Status-clear berasal dari klasifikasi model, bukan konfirmasi fisik kereta sudah lewat.

### Integrasi Laravel

Node 2 mengirim `POST /api/gate-log` ke server Laravel (`LARAVEL_HOST`). Payload firmware:

```json
{
  "trigger_type": "AUTOMATIC",
  "gate_action": "CLOSED",
  "confidence_score": 0.83,
  "notes": "Kereta terdeteksi TinyML"
}
```

Untuk aksi tombol manual, `trigger_type` berisi `MANUAL` dengan `gate_action` bernilai `CLOSED` atau `OPENED`. Saat palang ditahan karena rintangan ultrasonik, dikirim `gate_action: "HELD"`. Ketika palang otomatis terbuka kembali setelah status aman terkonfirmasi, Node 2 mengirim log `gate_action: "OPENED"`. Tiap 30 detik Node 2 mengirim heartbeat dengan token NODE-02 dan meneruskan status terbaru Node 1 dengan token NODE-01; heartbeat Node 1 dilewati jika paket LoRa statusnya sudah kedaluwarsa. Node 2 juga mengambil parameter `GET /api/config` saat boot serta berkala.

## Menyiapkan firmware ESP32

Ada dua versi firmware Node 1 di repo ini dengan perbedaan library TinyML:

| File | Library MPU6050 | Library TinyML | Keterangan |
| --- | --- | --- | --- |
| `Node 1 RailSafe/Node1/Node1.ino` | `MPU6050_light` | `Chirale_TensorFlowLite` + `AllOpsResolver` (TFLite Micro langsung) | **Versi aktif.** Tensor arena 30 KB; mendukung operator EXPAND\_DIMS / RESHAPE yang disisipkan saat konversi Conv1D. |
| `Node 1 RailSafe/Node1.ino` *(lama)* | `Adafruit MPU6050` | `Eloquent TinyML` (wrapper) | Versi lama. Tensor arena 8 KB. Gunakan hanya jika library Chirale tidak tersedia. |

### Library yang dibutuhkan

**Node 1 (versi aktif):**

| Library | Sumber |
| --- | --- |
| `LoRa` | Sandeep Mistry (Arduino Library Manager) |
| `MPU6050_light` | rfetick (Arduino Library Manager) |
| `Chirale_TensorFlowLite` | Chirale (Arduino Library Manager) |

**Node 1 (versi lama):**

| Library | Sumber |
| --- | --- |
| `LoRa` | Sandeep Mistry |
| `Adafruit MPU6050` + `Adafruit Unified Sensor` | Adafruit |
| `Eloquent TinyML` | eloquentarduino (beserta TFLite Micro) |

**Node 2:**

| Library | Sumber |
| --- | --- |
| `LoRa` | Sandeep Mistry |
| `LiquidCrystal_I2C` | Frank de Brabander |
| `ESP32Servo` | Kevin Harrington |

### Langkah upload firmware

1. Pasang Arduino IDE (≥ 2.x) dan tambahkan dukungan board ESP32 melalui Board Manager (`https://espressif.github.io/arduino-esp32/package_esp32_index.json`).
2. Pasang library sesuai tabel di atas melalui **Tools → Manage Libraries**.
3. Pilih board **ESP32 Dev Module** dan port COM yang sesuai.
4. **Node 1:** Buka `Node 1 RailSafe/Node1/Node1.ino`. Pastikan `model_data.h` berada **di folder sketch yang sama**. Firmware memanggil simbol `g_RailSafe_model` dari header tersebut. Upload dan pantau Serial Monitor (115200 baud) — pesan `✅ Model TinyML RailSafe berhasil dimuat.` menandakan model berhasil dimuat.
5. **Node 2:** Buka `Node 2 RailSafe/Node2/Node2.ino`. Sesuaikan konstanta berikut di bagian atas file sebelum upload:

   ```cpp
   const char* WIFI_SSID     = "Nama_WiFi_Anda";
   const char* WIFI_PASSWORD = "Password_WiFi_Anda";
   const char* LARAVEL_HOST  = "http://<IP-LAN-SERVER>:8000";
   const char* DEVICE_TOKEN  = "rs_demo_node02_token"; // Bearer token perangkat
   ```

6. Upload ke masing-masing board. Periksa log Serial Monitor untuk memastikan inisialisasi LoRa, sensor, dan Wi-Fi berhasil.

> **Catatan dua salinan Node1:** Gunakan versi aktif di `Node 1 RailSafe/Node1/Node1.ino`. File `Node 1 RailSafe/Node1.ino` adalah versi lama yang menggunakan Adafruit MPU6050 dan Eloquent TinyML; hanya gunakan jika diperlukan dan pastikan kedua file selalu disinkronkan bila ada perubahan.

---

## Kode Arduino — penjelasan detail

### Node 1: alur inferensi TinyML

```
Boot
 ├─ Wire.begin(21, 22)  →  MPU6050_light.begin() + calcOffsets()
 ├─ initTinyML()        →  load model_data.h → AllocateTensors()
 └─ LoRa.begin(433E6)   →  setSyncWord(0xF3)

Loop (tiap 10 ms)
 └─ mpu.update()
     ├─ accX/Y/Z = getAccX/Y/Z() × 9.81  [m/s²]
     ├─ gyroX/Y/Z = getGetGyroX/Y/Z()    [deg/s]
     └─ input_buffer[sample_index × 6 .. +5] = nilai di atas

Ketika 100 sampel terkumpul (1 detik)
 ├─ Salin input_buffer → input_tensor->data.f[]
 ├─ interpreter->Invoke()
 ├─ Baca output: [prob_idle, prob_kereta, prob_truk]
 ├─ prob_kereta > 0.70  → kirim ALERT_KERETA
 ├─ prob_kereta ≤ 0.30 dan idle/truk yakin → kirim STATUS_CLEAR
 └─ prediksi tidak pasti → tidak kirim status aman
```

**Detail tensor arena:** `TENSOR_ARENA_SIZE` diset 30 KB (`30 * 1024`). Nilai ini diperlukan karena `AllOpsResolver` mendaftarkan semua operator built-in termasuk `EXPAND_DIMS` dan `RESHAPE` yang disisipkan otomatis saat model Conv1D dikonversi ke TFLite. Naikkan nilai ini jika `AllocateTensors()` gagal.

**Konversi satuan:** Firmware menggunakan rumus `getAccX() × 9.81` agar satuan akselerometer seragam (m/s²) dengan data CSV yang direkam oleh `Pencatatan_MPU.py`. Pastikan rumus ini **sama persis** di firmware dan skrip perekaman, jika tidak model akan menerima distribusi input yang berbeda dari data latih.

**Format paket LoRa:**

```
RailSafe:ALERT_KERETA:<confidence>
Contoh: RailSafe:ALERT_KERETA:0.83
RailSafe:STATUS_CLEAR:0.00
```

### Node 2: alur kendali palang

```
Boot
 ├─ Servo → bukaPalang() (posisi 0°)
 ├─ LCD   → "RailSafe READY / STATUS: AMAN"
 ├─ WiFi.begin() (timeout 5 detik, lanjut offline jika gagal)
 └─ LoRa.begin(433E6) → setSyncWord(0xF3)

Loop
 ├─ [A] cekTombolManual()         — tombol ditahan untuk menutup; dilepas untuk membuka manual
 ├─ [B] LoRa.parsePacket()
 │       ├─ ALERT_KERETA → mulai/lanjutkan countdown penutupan
 │       └─ STATUS_CLEAR → batalkan countdown yang belum selesai dan tampilkan status aman
 │           └─ Jika palang otomatis tertutup, lanjutkan konfirmasi aman
 ├─ [C] Countdown habis tanpa penutupan manual → cek dua sensor, lalu tutup otomatis
 ├─ [D] Palang otomatis terbuka setelah status-clear segar terkonfirmasi selama `gate_reopen_seconds`
 ├─ [E] Tombol membuka palang otomatis melalui override petugas; alert berulang ditahan sampai STATUS_CLEAR
 ├─ [F] Ambil konfigurasi dashboard saat boot dan setiap 30 detik
 └─ [G] Buzzer: countdown 300/100 ms, palang tertutup 800/120 ms, terhalang menyala terus
```

**tutupPalangOtomatis(confidence):**

```
1. ukurJarakHCSR04(sensor 1) + ukurJarakHCSR04(sensor 2)
2. Jika salah satu < 15 cm → tampil "AWAS KENDARAAN!"
   delay(1000) → ukur ulang kedua sensor
   Jika masih terhalang → return (palang tetap terbuka)
3. Jika area aman → `isGateClosed = true`, servo menutup ke 125°, LCD menampilkan "BAHAYA KERETA / PALANG DITUTUP"
4. Jika area masih terhalang → palang tetap terbuka dan percobaan otomatis diulang
5. kirimLogKeLaravel("AUTOMATIC", "CLOSED", confidence, "Kereta terdeteksi TinyML")
```

**Konstanta yang perlu disesuaikan di Node2.ino:**

| Konstanta | Default | Keterangan |
| --- | --- | --- |
| `WIFI_SSID` / `WIFI_PASSWORD` | *(kosong)* | SSID dan kata sandi jaringan |
| `LARAVEL_HOST` | `http://192.168.1.10:8000` | IP LAN server Laravel (tanpa trailing slash) |
| `DEVICE_TOKEN` | `rs_demo_node02_token` | Token otentikasi Bearer perangkat Node 2 |
| `closeCountdownMs` | `15000` ms | Waktu tunggu petugas sebelum Node 2 mencoba menutup otomatis; dari `countdown_seconds` |
| `autoResetTimeout` | `15000` ms | Durasi status-clear segar sebelum buka otomatis; dari `gate_reopen_seconds` |
| `TRIG_PIN_2` / `ECHO_PIN_2` | `26` / `25` | Sesuaikan jika sensor ultrasonik kedua dipasang di pin lain |
| `GATE_OPEN_ANGLE` / `GATE_CLOSE_ANGLE` | `0°` / `125°` | Sesuaikan jika servo terpasang terbalik |

---

## Machine Learning — penjelasan detail

### Alur pipeline data

```
[Hardware MPU6050]
      │  USB Serial 115200 baud
      ▼
Data MPU.ino  ──Serial──►  Pencatatan_MPU.py  ──►  <label>_<timestamp>.csv
(akuisisi)                  (rekam & simpan)

      ATAU

Dummy File.py  ──►  idle_test.csv / truk_test.csv / kereta_test.csv
(sintetis)          (hanya untuk uji bentuk data, bukan validasi akurasi)

      │
      ▼
[Notebook pelatihan — belum ada di repo]
      │  keras / TensorFlow → TFLite Conversion → xxd / numpy
      ▼
model_data.h  (array byte C++, simbol: g_RailSafe_model)
      │
      ▼
Node 1 — TensorFlow Lite Micro → inferensi di ESP32
```

### Format dataset CSV

Setiap baris mewakili satu sampel (interval 10 ms):

| Kolom | Satuan | Keterangan |
| --- | --- | --- |
| `timestamp_ms` | ms | Waktu dari `millis()` ESP32 |
| `accX` | m/s² | Akselerasi sumbu X (getAccX × 9.81) |
| `accY` | m/s² | Akselerasi sumbu Y |
| `accZ` | m/s² | Akselerasi sumbu Z (diam ≈ 9.81) |
| `gyroX` | deg/s | Kecepatan angular sumbu X |
| `gyroY` | deg/s | Kecepatan angular sumbu Y |
| `gyroZ` | deg/s | Kecepatan angular sumbu Z |

Satu window inferensi = **100 baris × 6 kolom = 600 nilai float**.

### Karakteristik sinyal per kelas (data sintetis Dummy File.py)

| Kelas | Pola akselerasi | Pola giroskop | Noise |
| --- | --- | --- | --- |
| **Idle** | accZ ≈ 9.81 g, XY ≈ 0 | ≈ 0 deg/s | σ kecil (0.08–0.10) |
| **Truk** | Sinusoidal 8 Hz, amplitudo ~1.2 | σ 0.15 deg/s | σ sedang (0.25–0.35) |
| **Kereta** | 25 Hz + 40 Hz + impak ±8 m/s² setiap 100 sampel | σ 0.6 deg/s | σ besar (0.8–1.2) |

> Data sintetis ini **tidak mewakili kondisi lapangan nyata** dan hanya digunakan untuk menguji bentuk dan format input model. Model yang sudah ada di `model_data.h` dilatih dari data terpisah.

### Langkah akuisisi data nyata

1. Upload `Machine Learning/Kode Arduino/Data MPU/Data MPU.ino` ke ESP32 yang dipasangi MPU6050 di rel / lokasi yang ingin diukur.
2. Sketch otomatis mencetak header CSV (`timestamp_ms,accX,...`) lalu streaming data ke Serial.
3. **Tutup Serial Monitor Arduino IDE** agar port tidak terkunci.
4. Edit konfigurasi di `Machine Learning/Pencatatan_MPU.py`:

   ```python
   PORT_COM     = 'COM4'      # Port COM ESP32 (cek di Device Manager)
   BAUDRATE     = 115200
   NAMA_LABEL   = 'kereta'    # 'kereta' | 'truk' | 'idle'
   DURASI_DETIK = 30          # Durasi rekam dalam detik
   ```

5. Install dependensi dan jalankan:

   ```powershell
   pip install pyserial
   python "Machine Learning/Pencatatan_MPU.py"
   ```

6. File CSV bernama `<label>_<unix_timestamp>.csv` dibuat di direktori kerja.
7. Ulangi untuk setiap kelas (`kereta`, `truk`, `idle`) dan setiap kondisi pengukuran.

> **Kalibrasi:** Sketch akuisisi menjalankan `mpu.calcOffsets()` saat boot. **Jangan gerakkan modul** selama kalibrasi berlangsung (sekitar 1 detik setelah pesan `Mengukur offset sensor...`). Pastikan orientasi pemasangan MPU6050 sama antara akuisisi data dan deployment di Node 1.

### Membuat dataset sintetis

```powershell
pip install numpy pandas
python "Machine Learning/Dummy File.py"
```

Menghasilkan tiga file: `idle_test.csv`, `truk_test.csv`, `kereta_test.csv` — masing-masing 1000 sampel (10 detik × 100 Hz). Gunakan hanya untuk memverifikasi format input sebelum melatih model dengan data nyata.

### Model TinyML (`model_data.h`)

File ini adalah hasil ekspor model TensorFlow Lite yang telah dikonversi ke array byte C++. Simbol utama yang digunakan firmware:

```cpp
alignas(16) const unsigned char g_RailSafe_model[];
```

Model dimuat oleh fungsi `initTinyML()` di Node 1 melalui `tflite::GetModel(g_RailSafe_model)`. Pipeline pelatihan lengkap (notebook, preprocessing, augmentasi, evaluasi, dan konversi ke `.h`) belum disertakan di repo ini.

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

## Database seeder

Seeder dipisah menjadi tiga kelas agar dapat dijalankan secara mandiri sesuai kebutuhan.

| Seeder | Isi |
| --- | --- |
| `AccountSeeder` | Akun demo superadmin (`superadmin@railsafe.test`) dan operator (`operator@railsafe.test`). |
| `CrossingSeeder` | Satu pos perlintasan demo, Node 1 & Node 2 beserta token demo, log sensor awal, dan pengaturan sistem default. Prasyarat: `AccountSeeder` sudah dijalankan. |
| `SimulationSeeder` | Riwayat log palang 7 hari dengan pola acak (78% otomatis TinyML, 22% manual). Prasyarat: `CrossingSeeder` sudah dijalankan. |
| `DatabaseSeeder` | Titik masuk utama. Memanggil `AccountSeeder → CrossingSeeder → SimulationSeeder` secara berurutan. |

### Menjalankan seeder

**Semua sekaligus** (digunakan bersama `migrate --seed`):

```powershell
php artisan migrate --seed
# atau tanpa migrasi ulang:
php artisan db:seed
```

**Seeder tertentu saja:**

```powershell
# Hanya akun demo
php artisan db:seed --class=AccountSeeder

# Hanya pos, perangkat, dan pengaturan (jalankan setelah AccountSeeder)
php artisan db:seed --class=CrossingSeeder

# Hanya tambah data simulasi log 7 hari (jalankan setelah CrossingSeeder)
php artisan db:seed --class=SimulationSeeder
```

> **Peringatan:** Jangan jalankan seeder berulang pada database berisi data yang ingin dipertahankan. Seeder tidak memeriksa duplikat secara keseluruhan; jalankan `migrate:fresh --seed` hanya di lingkungan pengembangan.

### Token demo perangkat

`CrossingSeeder` membuat dua perangkat dengan token demo:

| Perangkat | Token demo |
| --- | --- |
| NODE-01 (SENSOR\_NODE) | `rs_demo_node01_token` |
| NODE-02 (GATE\_CONTROLLER) | `rs_demo_node02_token` |

Token asli hanya tersedia saat dibuat atau diputar ulang. Hash token disimpan di database; **ganti token ini sebelum deploy ke jaringan yang lebih luas**.

## API perangkat

Semua rute API perangkat berada di middleware `device.token` dan dibatasi hingga 120 permintaan per menit.

| Metode dan path | Fungsi |
| --- | --- |
| `POST /api/gate-log` | Menyimpan satu aksi palang dari format firmware Node 2 saat ini. |
| `POST /api/telemetry` | Format telemetri/event yang lebih lengkap; memerlukan `event_id`, `event_type`, `control_mode`, dan `event_timestamp`. |
| `POST /api/heartbeat` | Memperbarui status NODE-01 dan NODE-02, label/confidence TinyML, jarak dua sensor ultrasonik, dan kesehatan sensor. Node 2 mengirim heartbeat kedua perangkat tiap 30 detik saat Wi-Fi tersedia. |
| `GET /api/config` | Node 2 mengambil `countdown_seconds` dan `gate_reopen_seconds` saat boot dan berkala. `confidence_threshold` belum diterapkan ke Node 1. |

### Mode demo dan token perangkat

`RAILSAFE_REQUIRE_TOKEN` di `.env` mengontrol autentikasi:

- `false` (nilai default contoh): permintaan tanpa token dipetakan ke perangkat `GATE_CONTROLLER` aktif pertama. Praktis untuk demo lokal satu pos.
- `true`: wajib mengirim `Authorization: Bearer <token>`.

Token perangkat dapat dibuat/diputar melalui halaman admin perangkat. Hash token disimpan di database; token asli hanya ditampilkan saat dibuat/diputar. Firmware Node 2 mengirim header `Authorization` memakai `DEVICE_TOKEN`. Jangan gunakan mode demo di jaringan yang tidak tepercaya.

## Akun dan simulasi demo

Seeder membuat data contoh: satu pos, dua perangkat, beberapa sensor, pengaturan, dan log riwayat. Kredensial demo:

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Superadmin | `superadmin@railsafe.test` | `password` |
| Operator | `operator@railsafe.test` | `password` |

Ganti/hapus akun dan kata sandi demo sebelum aplikasi dibuka ke jaringan yang lebih luas. Operator dibatasi ke pos yang ditugaskan; superadmin dapat mengelola akun, pos, perangkat, token, pengaturan, dan audit.

### Perintah simulasi (tanpa perangkat keras)

Gunakan perintah Artisan berikut untuk mengisi aktivitas tanpa hardware ESP32:

```powershell
# Simulasi penutupan otomatis oleh TinyML + heartbeat selama durasi buka ulang
php artisan railsafe:simulate --train

# Simulasi penutupan dan pembukaan manual oleh operator
php artisan railsafe:simulate --manual

# Kirim heartbeat berkala setiap 5 detik (tekan Ctrl+C untuk berhenti)
php artisan railsafe:simulate
```

`--train` membuat log penutupan otomatis dan mengirim heartbeat selama waktu buka ulang yang dikonfigurasi; `--manual` membuat log tutup/buka manual; tanpa opsi, perintah mengirim heartbeat tiap lima detik sampai dihentikan dengan Ctrl+C.

## Pengaturan sistem

| Pengaturan | Default | Catatan |
| --- | ---: | --- |
| `confidence_threshold` | 70 | Persen; Node 1 masih memakai batas tetap 0.70 dan belum membaca nilai dashboard. |
| `gate_reopen_seconds` | 15 | Durasi status-clear berulang sebelum penutupan otomatis dibuka. Status Node 1 yang hilang menahan palang otomatis tertutup. |
| `countdown_seconds` | 15 | Waktu petugas untuk menutup manual sebelum Node 2 menutup otomatis. |

Pengaturan dapat diubah oleh superadmin. Node 2 mengambil `GET /api/config` saat boot dan berkala setiap 30 detik. Node 1 masih memakai ambang deteksi 0,70 yang ditetapkan di firmware.

## Batasan yang diketahui

- Pembukaan otomatis Node 2 memakai paket `STATUS_CLEAR` dari klasifikasi Node 1, bukan sekadar ketiadaan alert. Status-clear tetap bukan konfirmasi fisik kereta benar-benar telah lewat.
- Jika ultrasonik tidak menerima pantulan, pembacaan dianggap `999 cm` (area kosong). Sensor gagal/terputus karena itu belum ditangani sebagai kondisi fault yang menahan palang.
- Pemindaian ultrasonik berjalan tiap 2,5 detik sesuai interval firmware, sehingga respons sensor dapat terlambat hingga sekitar 2,5 detik. Operasi HTTP sinkron dapat menambah jeda tersebut. Data jarak dashboard tetap diperbarui saat heartbeat Node 2 dikirim, setiap 30 detik.
- Palang yang ditutup otomatis tetap terkunci sampai status aman terkonfirmasi. Penutupan manual berlangsung selama tombol ditahan; saat tombol dilepas palang terbuka dan sistem kembali standby.
- Operasi servo, HTTP, `delay()`, dan pembacaan sensor bersifat blocking. Koneksi Wi-Fi/API yang lambat dapat menunda pembacaan LoRa dan tombol.
- Node 1 mengirim prediksi setiap jendela satu detik saat ambang kereta terlampaui. Sampling aktual dapat berubah akibat waktu inferensi dan operasi lain.
- Model header tersedia, tetapi pipeline pelatihan/evaluasi, metrik akurasi, data lapangan, serta prosedur kalibrasi pemasangan MPU belum didokumentasikan di repo.
- Database seeder dan API mode tanpa token ditujukan untuk demo. Kredensial, token, HTTP tanpa TLS, serta alamat IP lokal perlu ditinjau sebelum deployment.
- Dashboard menampilkan status palang dari log terakhir dan estimasi timer; Node 2 mengirim log saat palang otomatis terbuka kembali.

## Pemecahan masalah

| Gejala | Pemeriksaan |
| --- | --- |
| Node 1 gagal menemukan MPU6050 | Periksa catu daya, ground, SDA 21/SCL 22, dan alamat I²C modul. |
| Model gagal dimuat | Pastikan `model_data.h` ikut dalam folder sketch dan firmware memanggil `g_RailSafe_model`. Periksa board ESP32 dan library TinyML yang dipasang. |
| Node 2 tidak menerima status LoRa | Pastikan kedua node memakai 433 MHz, sync word `0xF3`, serta format `RailSafe:ALERT_KERETA:<confidence>` / `RailSafe:STATUS_CLEAR:0.00`. Periksa kabel SPI, antena, dan catu daya LoRa. |
| Palang tidak bergerak | Periksa catu daya servo, ground bersama, sambungan GPIO 13, dan rentang sudut servo. Jangan memberi daya servo dari pin 3,3 V ESP32. |
| Ultrasonik selalu mendeteksi kosong | Periksa TRIG/ECHO kedua sensor, ground bersama, arah sensor, batas ukur, dan level tegangan ECHO. Ambang firmware saat ini 15 cm. |
| LCD kosong | Periksa SDA/SCL, alamat I²C `0x27`, kontras, dan catu daya LCD. |
| Log API gagal | Pastikan server Laravel hidup, URL memakai IP LAN server (bukan `localhost`), firewall mengizinkan koneksi, Wi-Fi tersambung, database sudah dimigrasi, dan mode token sesuai firmware. Periksa kode HTTP di Serial Monitor. |
| Dashboard tidak bisa login | Jalankan `php artisan migrate --seed`, lalu gunakan akun demo di atas. Jangan jalankan seeder berulang pada database berisi data yang ingin dipertahankan; gunakan `migrate:fresh --seed` hanya di lingkungan pengembangan. |

## Lisensi dan kontribusi

Belum ada lisensi proyek khusus yang ditetapkan di repo. Tinjau lisensi dependency/library dan tetapkan lisensi proyek sebelum redistribusi atau penggunaan publik.
