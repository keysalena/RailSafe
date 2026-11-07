# Sambungan ke firmware ESP32

## Yang sudah cocok tanpa mengubah Node2.ino

| Firmware | Laravel |
|---|---|
| `POST /api/gate-log` | endpoint `gateLog()`, tabel `gate_logs` |
| `trigger_type` = `AUTOMATIC` / `MANUAL` | kolom pemicu (Otomatis / Manual) |
| `confidence_score` 0 sampai 1 | disimpan sebagai persen (0,93 menjadi 93%). Untuk `MANUAL` nilainya (1.0) diabaikan |
| `notes` | kolom catatan. Teks yang mengandung "Dibuka" dicatat sebagai aksi **dibuka**, selain itu **ditutup** |
| tanpa header Authorization | diterima jika `RAILSAFE_REQUIRE_TOKEN=false` (dianggap NODE-02) |
| tanpa jam | waktu log memakai waktu server saat data diterima |

Jadi cukup isi `WIFI_SSID`, `WIFI_PASSWORD`, dan `LARAVEL_API` di Node2.ino.

## Tambahan opsional di Node2.ino

**1. Token (disarankan saat deployment, set `RAILSAFE_REQUIRE_TOKEN=true`)**: di `kirimLogKeLaravel()` tambahkan setelah `addHeader("Content-Type", ...)`:

```cpp
http.addHeader("Authorization", "Bearer TOKEN_NODE_02");  // dibuat di menu Pos dan perangkat
```

**2. Heartbeat** supaya status perangkat, RSSI, dan jarak HC-SR04 tampil di dashboard:

```cpp
const char* HEARTBEAT_API = "http://192.168.1.10:8000/api/heartbeat";
unsigned long lastHeartbeat = 0;

void kirimHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  http.setTimeout(2000);
  http.begin(HEARTBEAT_API);
  http.addHeader("Content-Type", "application/json");
  // http.addHeader("Authorization", "Bearer TOKEN_NODE_02");
  String j = "{\"rssi\":" + String(LoRa.packetRssi()) +
             ",\"distance_cm\":" + String(ukurJarakHCSR04()) + "}";
  http.POST(j);
  http.end();
}

// di dalam loop(), bagian paling atas:
if (millis() - lastHeartbeat > 30000) { lastHeartbeat = millis(); kirimHeartbeat(); }
```

Tanpa heartbeat, Node 2 hanya ditandai aktif saat ada log, dan dashboard akan menampilkannya "Terputus" setelah 90 detik tanpa laporan.

## Ketidakcocokan yang saya temukan di kode ESP

1. **Awalan paket LoRa berbeda.** `Node 1 RailSafe/Node1.ino` mengirim `RailSafe:...`, sedangkan Node 2 hanya mengenali `SIGAP:...`. Hanya `Node1/Node1.ino` yang cocok. Samakan salah satunya.
2. **Ambang 70% vs proposal 85%.** Node 1 memakai `prob_kereta > 0.70`; proposal menulis 85%.
3. **Belum ada hitung mundur 15 detik.** Node 2 menutup palang langsung saat sinyal diterima. Proposal: peringatan dulu, petugas diberi 15 detik, baru fail-safe. Pemeriksaan HC-SR04 saat ini hanya menunda 1 detik lalu tetap menutup, bukan menahan palang dan membunyikan sirine seperti di proposal.
4. **Palang terbuka otomatis tidak dicatat.** Dashboard memperkirakan status palang dari log terakhir dan `gate_reopen_seconds` (15 dtk), jadi hanya perkiraan.
5. **Emoji di LCD.** `"🚨 BAHAYA KERETA"` tidak tampil di LCD 16x2 (muncul karakter acak). Pakai teks biasa.
6. **Buzzer memakai `delay()`** (2 x 150 ms per putaran) saat palang tertutup, sehingga tombol dan paket LoRa bisa terlambat dibaca. `millis()` lebih aman.
