/*
 * S.I.G.A.P / RailSafe - Node 2 (Pos PJL Receiver + Gate Controller + HMI)
 * Hardware: ESP32, LoRa Ra-02, LCD I2C 16x2, Servo MG996R, HC-SR04, Buzzer, Arcade Button
 *
 * ALUR:
 *  1. Sinyal kereta dari Node 1 diterima -> HITUNG MUNDUR 15 detik, buzzer berbunyi, LCD menampilkan sisa waktu.
 *     Dashboard menampilkan kotak peringatan dengan hitung mundur yang sama.
 *  2. Petugas menekan tombol sebelum waktu habis -> palang ditutup manual.
 *  3. Waktu habis tanpa respons:
 *       - HC-SR04 mendeteksi rintangan -> palang DITAHAN terbuka, sirine menyala, dashboard memberi peringatan
 *         "Rel terhalang". Begitu rintangan hilang, palang menutup otomatis.
 *       - Tidak ada rintangan -> palang menutup otomatis (fail-safe).
 *  4. Palang terbuka otomatis AUTO_RESET_TIMEOUT setelah sinyal kereta terakhir (penutupan otomatis saja).
 */

#include <Wire.h>
#include <SPI.h>
#include <LoRa.h>
#include <LiquidCrystal_I2C.h>
#include <ESP32Servo.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ==================== KONFIGURASI WIFI & LARAVEL ====================
const char* WIFI_SSID     = "Nama_WiFi_Anda";      // Ganti SSID WiFi
const char* WIFI_PASSWORD = "Password_WiFi_Anda";  // Ganti Password WiFi
const char* LARAVEL_BASE  = "http://192.168.1.10:8000/api"; // IP Laptop/Server Laravel, tanpa garis miring di akhir
const char* API_TOKEN     = "";  // Isi token NODE-02 jika RAILSAFE_REQUIRE_TOKEN=true di server

// ==================== PINOUT HARDWARE ====================
#define LORA_SCK  18
#define LORA_MISO 19
#define LORA_MOSI 23
#define LORA_SS   5
#define LORA_RST  14
#define LORA_DIO0 2

#define SERVO_PIN 13
#define BUZZER_PIN 15
#define BUTTON_PIN 4  // Arcade Button (Manual Override)

#define TRIG_PIN  12
#define ECHO_PIN  27

// ==================== INISIALISASI OBJEK ====================
LiquidCrystal_I2C lcd(0x27, 16, 2);
Servo gateServo;

// Posisi Palang Pintu (Sudut Servo)
const int GATE_OPEN_ANGLE  = 0;   // Palang Terbuka
const int GATE_CLOSE_ANGLE = 90;  // Palang Tertutup

// ==================== PARAMETER (bisa ditimpa dari /api/config) ====================
unsigned long COUNTDOWN_MS       = 15000; // Waktu petugas menutup palang sebelum fail-safe
unsigned long AUTO_RESET_TIMEOUT = 15000; // Palang terbuka lagi sekian ms setelah sinyal kereta terakhir
const long OBSTACLE_CM = 15;              // Jarak di bawah ini dianggap ada rintangan

// ==================== STATUS SISTEM ====================
enum Keadaan { SIAGA, HITUNG_MUNDUR, TERTUTUP, TERHALANG };
Keadaan keadaan = SIAGA;

bool ditutupManual = false;
float lastConfidence = 0.0;
int obstacleClearCount = 0;
int lastShownSec = -1;
bool beepOn = false;

unsigned long lastButtonPress = 0;
unsigned long lastTrainAlertTime = 0;
unsigned long countdownStart = 0;
unsigned long lastObstacleCheck = 0;
unsigned long lastBeep = 0;
unsigned long lastHeartbeat = 0;

void setup() {
  Serial.begin(115200);

  // 1. Setup Pin Input/Output
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  digitalWrite(BUZZER_PIN, LOW);

  // 2. Setup Servo MG996R
  ESP32PWM::allocateTimer(0);
  gateServo.setPeriodHertz(50); // Standard 50Hz servo
  gateServo.attach(SERVO_PIN, 500, 2400);
  bukaPalang(); // Posisi Awal: Terbuka

  // 3. Setup LCD I2C
  lcd.init();
  lcd.backlight();
  tampilkanStatusLCD("S.I.G.A.P READY", "STATUS: AMAN");

  // 4. Setup WiFi (Opsional untuk kirim telemetri ke Laravel)
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Menghubungkan ke WiFi...");
  int wifiTimeout = 0;
  while (WiFi.status() != WL_CONNECTED && wifiTimeout < 10) {
    delay(500);
    Serial.print(".");
    wifiTimeout++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Terhubung IP: " + WiFi.localIP().toString());
    ambilKonfigurasi(); // Ambil waktu hitung mundur dari dashboard
  } else {
    Serial.println("\nWiFi Offline (Mode Standalone/LoRa Only)");
  }

  // 5. Setup LoRa Ra-02 (433 MHz)
  SPI.begin(LORA_SCK, LORA_MISO, LORA_MOSI, LORA_SS);
  LoRa.setPins(LORA_SS, LORA_RST, LORA_DIO0);
  if (!LoRa.begin(433E6)) {
    Serial.println("ERROR: LoRa Ra-02 Gagal!");
    tampilkanStatusLCD("LORA ERROR!", "CEK KABEL");
    while (1);
  }
  LoRa.setSyncWord(0xF3); // Harus sama dengan Node 1
  Serial.println("LoRa Receiver Siap (433 MHz)");
}

void loop() {
  cekTombolManual();   // A. Arcade Button
  bacaLoRa();          // B. Sinyal kereta dari Node 1
  prosesKeadaan();     // C. Hitung mundur, rintangan, auto reset
  perbaruiBuzzer();    // D. Buzzer/sirine tanpa delay()
  kirimHeartbeat();    // E. Status berkala ke dashboard (hanya saat siaga)
}

// ==================== ALUR UTAMA ====================

void bacaLoRa() {
  int packetSize = LoRa.parsePacket();
  if (!packetSize) return;

  String incomingMsg = "";
  while (LoRa.available()) {
    incomingMsg += (char)LoRa.read();
  }
  Serial.println("LoRa Received: " + incomingMsg);

  // Format sinyal: "SIGAP:ALERT_KERETA:<confidence>"
  if (incomingMsg.startsWith("SIGAP:ALERT_KERETA")) {
    float confidence = 0.0;
    int lastColon = incomingMsg.lastIndexOf(':');
    if (lastColon != -1) {
      confidence = incomingMsg.substring(lastColon + 1).toFloat();
    }

    lastTrainAlertTime = millis(); // Sinyal berulang memperpanjang waktu palang tertutup
    lastConfidence = confidence;

    if (keadaan == SIAGA) {
      mulaiHitungMundur(confidence);
    }
  }
}

void mulaiHitungMundur(float confidence) {
  keadaan = HITUNG_MUNDUR;
  ditutupManual = false;
  countdownStart = millis();
  lastShownSec = -1;
  Serial.println("Kereta terdeteksi. Petugas punya waktu menutup palang.");
  kirimAlert(confidence); // Dashboard menampilkan kotak peringatan + hitung mundur
}

void prosesKeadaan() {
  unsigned long now = millis();

  switch (keadaan) {
    case HITUNG_MUNDUR: {
      unsigned long elapsed = now - countdownStart;
      if (elapsed >= COUNTDOWN_MS) {
        waktuHabis();
        break;
      }
      int sisa = (COUNTDOWN_MS - elapsed + 999) / 1000;
      if (sisa != lastShownSec) {
        lastShownSec = sisa;
        char baris2[17];
        snprintf(baris2, sizeof(baris2), "TUTUP PALANG %2ds", sisa);
        tampilkanStatusLCD("KERETA DATANG!", String(baris2));
      }
      break;
    }

    case TERHALANG:
      if (now - lastObstacleCheck > 500) {
        lastObstacleCheck = now;
        if (adaRintangan()) {
          obstacleClearCount = 0;
        } else {
          obstacleClearCount++;
          if (obstacleClearCount >= 3) { // Rel kosong selama ~1,5 detik
            tutupPalangOtomatis("Rintangan hilang, palang ditutup otomatis");
          }
        }
      }
      break;

    case TERTUTUP:
      // Auto reset hanya untuk penutupan otomatis. Penutupan manual dibuka petugas dengan tombol.
      if (!ditutupManual && now - lastTrainAlertTime > AUTO_RESET_TIMEOUT) {
        Serial.println("Waktu penutupan selesai. Membuka palang kembali...");
        bukaPalang();
        keadaan = SIAGA;
        tampilkanStatusLCD("S.I.G.A.P READY", "STATUS: AMAN");
        kirimGateLog("AUTOMATIC", 0, "Dibuka otomatis setelah kereta lewat", "OPENED", -1);
      }
      break;

    default:
      break;
  }
}

void waktuHabis() {
  // Petugas tidak merespons. Cek rintangan dulu sebelum menutup (fail-safe HC-SR04).
  if (adaRintangan()) {
    keadaan = TERHALANG;
    obstacleClearCount = 0;
    lastObstacleCheck = millis();
    Serial.println("WARN: Rel terhalang! Palang ditahan terbuka.");
    tampilkanStatusLCD("REL TERHALANG!", "PALANG DITAHAN");
    kirimGateLog("AUTOMATIC", 0, "Rel terhalang, palang ditahan terbuka", "HELD", 1);
  } else {
    tutupPalangOtomatis("Petugas tidak merespons, ditutup otomatis (fail-safe)");
  }
}

// ==================== FUNGSI-FUNGSI KONTROL ====================

void tutupPalangOtomatis(String notes) {
  gateServo.write(GATE_CLOSE_ANGLE);
  keadaan = TERTUTUP;
  ditutupManual = false;
  lastTrainAlertTime = millis(); // Hitung auto reset dimulai dari sekarang
  tampilkanStatusLCD("PALANG DITUTUP", "OTOMATIS");
  kirimGateLog("AUTOMATIC", lastConfidence, notes, "CLOSED", 0);
}

void tutupPalangManual(bool dalamHitungMundur) {
  gateServo.write(GATE_CLOSE_ANGLE);
  keadaan = TERTUTUP;
  ditutupManual = true;
  lastTrainAlertTime = millis();
  tampilkanStatusLCD("MANUAL OVERRIDE", "PALANG DITUTUP");
  kirimGateLog("MANUAL", 0,
               dalamHitungMundur ? "Ditutup petugas saat hitung mundur (Arcade Button)"
                                 : "Ditutup manual via Arcade Button",
               "CLOSED", -1);
}

void bukaPalang() {
  ditutupManual = false;
  gateServo.write(GATE_OPEN_ANGLE);
  digitalWrite(BUZZER_PIN, LOW);
}

void cekTombolManual() {
  if (digitalRead(BUTTON_PIN) != LOW) return;          // Active LOW
  if (millis() - lastButtonPress < 400) return;        // Debounce 400ms
  lastButtonPress = millis();

  switch (keadaan) {
    case SIAGA:
      tutupPalangManual(false);
      break;
    case HITUNG_MUNDUR:
      tutupPalangManual(true);   // Petugas merespons: ambil alih sebelum waktu habis
      break;
    case TERTUTUP:
      bukaPalang();
      keadaan = SIAGA;
      tampilkanStatusLCD("MANUAL OVERRIDE", "PALANG DIBUKA");
      kirimGateLog("MANUAL", 0, "Dibuka manual via Arcade Button", "OPENED", -1);
      break;
    case TERHALANG:
      break; // Keselamatan: tombol diabaikan selama ada rintangan di rel
  }
}

void perbaruiBuzzer() {
  unsigned long now = millis();
  unsigned long interval = 0;

  if (keadaan == TERHALANG) {          // Sirine darurat: menyala terus
    digitalWrite(BUZZER_PIN, HIGH);
    return;
  } else if (keadaan == HITUNG_MUNDUR) {
    interval = 100;                    // Bunyi cepat: segera tutup palang
  } else if (keadaan == TERTUTUP) {
    interval = 150;                    // Seperti sebelumnya
  } else {
    digitalWrite(BUZZER_PIN, LOW);
    beepOn = false;
    return;
  }

  if (now - lastBeep >= interval) {
    lastBeep = now;
    beepOn = !beepOn;
    digitalWrite(BUZZER_PIN, beepOn ? HIGH : LOW);
  }
}

long ukurJarakHCSR04() {
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH, 20000); // Timeout 20ms
  if (duration == 0) return 999;
  return duration * 0.034 / 2;
}

// 3 kali baca; rintangan jika minimal 2 pembacaan di bawah OBSTACLE_CM
bool adaRintangan() {
  int hits = 0;
  for (int i = 0; i < 3; i++) {
    long jarak = ukurJarakHCSR04();
    if (jarak > 0 && jarak < OBSTACLE_CM) hits++;
    delay(30);
  }
  return hits >= 2;
}

String pad16(String s) {
  while (s.length() < 16) s += ' ';
  return s.substring(0, 16);
}

void tampilkanStatusLCD(String baris1, String baris2) {
  lcd.setCursor(0, 0);
  lcd.print(pad16(baris1));
  lcd.setCursor(0, 1);
  lcd.print(pad16(baris2));
}

// ==================== FUNGSI REST API LARAVEL ====================

void kirimJson(String path, String json) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.setTimeout(1500); // Jangan menahan tombol/hitung mundur terlalu lama jika server lambat
  http.begin(String(LARAVEL_BASE) + path);
  http.addHeader("Content-Type", "application/json");
  if (strlen(API_TOKEN) > 0) http.addHeader("Authorization", String("Bearer ") + API_TOKEN);

  int httpResponseCode = http.POST(json);
  Serial.printf("HTTP POST %s -> %d\n", path.c_str(), httpResponseCode);
  http.end();
}

// Awal hitung mundur: dashboard menampilkan kotak peringatan
void kirimAlert(float confidence) {
  String json = "{";
  json += "\"confidence_score\":" + String(confidence, 2) + ",";
  json += "\"notes\":\"Getaran kereta terdeteksi, menunggu petugas\"";
  json += "}";
  kirimJson("/alert", json);
}

// Hasil aksi palang. obstacle: 1 = ada rintangan, 0 = tidak, -1 = tidak dikirim
void kirimGateLog(String triggerType, float confidence, String notes, String action, int obstacle) {
  String json = "{";
  json += "\"trigger_type\":\"" + triggerType + "\",";
  json += "\"confidence_score\":" + String(confidence, 2) + ",";
  json += "\"notes\":\"" + notes + "\",";
  json += "\"gate_action\":\"" + action + "\"";
  if (obstacle >= 0) json += String(",\"obstacle_detected\":") + (obstacle ? "true" : "false");
  json += "}";
  kirimJson("/gate-log", json);
}

// Status berkala (30 detik) agar dashboard tahu Node 2 masih hidup. Hanya saat siaga.
void kirimHeartbeat() {
  if (keadaan != SIAGA) return;
  if (millis() - lastHeartbeat < 30000) return;
  lastHeartbeat = millis();

  String json = "{";
  json += "\"rssi\":" + String(LoRa.packetRssi()) + ",";
  json += "\"distance_cm\":" + String(ukurJarakHCSR04());
  json += "}";
  kirimJson("/heartbeat", json);
}

// Ambil parameter dari dashboard (Parameter sistem). Restart Node 2 setelah mengubahnya.
long ambilAngka(String json, const char* key) {
  String k = String("\"") + key + "\":";
  int i = json.indexOf(k);
  if (i < 0) return -1;
  return json.substring(i + k.length()).toInt();
}

void ambilKonfigurasi() {
  HTTPClient http;
  http.setTimeout(2000);
  http.begin(String(LARAVEL_BASE) + "/config");
  http.addHeader("Accept", "application/json");
  if (strlen(API_TOKEN) > 0) http.addHeader("Authorization", String("Bearer ") + API_TOKEN);

  if (http.GET() == 200) {
    String body = http.getString();
    long c = ambilAngka(body, "countdown_seconds");
    long r = ambilAngka(body, "gate_reopen_seconds");
    if (c >= 5 && c <= 60) COUNTDOWN_MS = c * 1000UL;
    if (r >= 5 && r <= 600) AUTO_RESET_TIMEOUT = r * 1000UL;
    Serial.printf("Konfigurasi: hitung mundur %lu ms, auto reset %lu ms\n", COUNTDOWN_MS, AUTO_RESET_TIMEOUT);
  }
  http.end();
}
