/*
 * RailSafe - Node 2 (Pos PJL Receiver + Gate Controller + HMI)
 * Hardware: ESP32, LoRa Ra-02, LCD I2C 16x2, Servo MG996R, 2x HC-SR04, Buzzer, Arcade Button
 *
 * ALUR:
 *  1. Sinyal kereta dari Node 1 diterima -> HITUNG MUNDUR 15 detik, buzzer berbunyi, LCD menampilkan sisa waktu.
 *     Dashboard menampilkan kotak peringatan dengan hitung mundur yang sama.
 *  2. Petugas menahan tombol -> palang ditutup manual; tombol dilepas -> palang dibuka kembali.
 *  3. Waktu habis tanpa respons:
 *       - Salah satu / kedua HC-SR04 mendeteksi rintangan -> palang DITAHAN terbuka, sirine menyala,
 *         dashboard memberi peringatan "Rel terhalang". Begitu rintangan hilang, palang menutup otomatis.
 *       - Tidak ada rintangan -> palang menutup otomatis (fail-safe).
 *  4. Palang otomatis terbuka setelah Node 1 mengirim STATUS_CLEAR berulang dan
 *     konfirmasi aman bertahan selama AUTO_RESET_TIMEOUT. Penutupan manual terkunci.
 */

#include <Wire.h>
#include <SPI.h>
#include <LoRa.h>
#include <LiquidCrystal_I2C.h>
#include <ESP32Servo.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ==================== KONFIGURASI WIFI & LARAVEL ====================
const char* WIFI_SSID     = "yuem";      // Ganti SSID WiFi
const char* WIFI_PASSWORD = "ujiansmk";  // Ganti Password WiFi
const char* LARAVEL_BASE  = "http://10.33.187.52:8000/api"; // Base endpoint API Laravel
const char* API_TOKEN_NODE1 = "rs_demo_node01_token"; // Token perangkat NODE-01
const char* API_TOKEN_NODE2 = "rs_demo_node02_token"; // Token perangkat NODE-02

// ==================== PINOUT HARDWARE ====================
#define LORA_SCK  18
#define LORA_MISO 19
#define LORA_MOSI 23
#define LORA_SS    5
#define LORA_RST  14
#define LORA_DIO0  2

#define SERVO_PIN  13
#define BUZZER_PIN 15
#define BUTTON_PIN 4   // Arcade Button (Manual Override)

const unsigned long BUZZER_COUNTDOWN_ON_MS = 300;
const unsigned long BUZZER_COUNTDOWN_OFF_MS = 100;
const unsigned long BUZZER_CLOSED_ON_MS = 800;
const unsigned long BUZZER_CLOSED_OFF_MS = 120;

// Sensor Ultrasonik 1
#define TRIG_PIN_1 12
#define ECHO_PIN_1 27

// Sensor Ultrasonik 2
#define TRIG_PIN_2 26
#define ECHO_PIN_2 25

// ==================== INISIALISASI OBJEK ====================
LiquidCrystal_I2C lcd(0x27, 16, 2);
Servo gateServo;

// Posisi Palang Pintu (Sudut Servo)
const int GATE_OPEN_ANGLE  = 0;   // Palang Terbuka
const int GATE_CLOSE_ANGLE = 125;  // Palang Tertutup

// ==================== PARAMETER (bisa ditimpa dari /api/config) ====================
unsigned long COUNTDOWN_MS       = 15000; // Waktu petugas menutup palang sebelum fail-safe
unsigned long AUTO_RESET_TIMEOUT = 15000; // Konfirmasi status aman sebelum auto-open
const long OBSTACLE_CM = 15;              // Jarak di bawah ini dianggap ada rintangan
const unsigned long ULTRASONIC_SCAN_INTERVAL_MS = 2500; // Satu sweep kedua sensor tiap 2,5 detik
const unsigned long ULTRASONIC_SENSOR_GAP_MS = 60;     // Jeda antarsensor untuk mengurangi crosstalk
const int OBSTACLE_CONFIRM_READS = 1;
const int OBSTACLE_CLEAR_READS = 2;

// ==================== STATUS SISTEM ====================
enum Keadaan { SIAGA, HITUNG_MUNDUR, TERTUTUP, TERHALANG };
Keadaan keadaan = SIAGA;

bool ditutupManual = false;
float lastConfidence = 0.0;
long jarakUltrasonik1 = 100;
long jarakUltrasonik2 = 100;
int obstacleNearCount1 = 0;
int obstacleNearCount2 = 0;
int obstacleClearCount1 = 0;
int obstacleClearCount2 = 0;
bool obstacleSensor1 = false;
bool obstacleSensor2 = false;
int lastShownSec = -1;
bool beepOn = false;
bool buttonRawState = false;
bool buttonStableState = false;
bool manualCloseHeld = false;
String node1MlLabel = "UNKNOWN";
float node1MlConfidence = 0.0;

unsigned long lastTrainAlertTime = 0;
unsigned long lastNodeStatusTime = 0;
unsigned long noTrainSince = 0;
unsigned long autoClosedAt = 0;
unsigned long lastNode1StatusAt = 0;
unsigned long countdownStart = 0;
unsigned long lastUltrasonicScanAt = 0;
unsigned long lastBeep = 0;
unsigned long lastHeartbeat = 0;
unsigned long lastConfigRefresh = 0;
unsigned long buttonStateChangedAt = 0;
const unsigned long NODE_STATUS_STALE_MS = 3500;
const unsigned long CONFIG_REFRESH_MS = 30000;
const unsigned long BUTTON_DEBOUNCE_MS = 40;

void setup() {
  Serial.begin(115200);

  // 1. Setup Pin Input/Output
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  
  pinMode(TRIG_PIN_1, OUTPUT);
  pinMode(ECHO_PIN_1, INPUT);
  pinMode(TRIG_PIN_2, OUTPUT);
  pinMode(ECHO_PIN_2, INPUT);

  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(TRIG_PIN_1, LOW);
  digitalWrite(TRIG_PIN_2, LOW);

  // 2. Setup Servo MG996R
  ESP32PWM::allocateTimer(0);
  gateServo.setPeriodHertz(50); // Standard 50Hz servo
  gateServo.attach(SERVO_PIN, 500, 2400);
  bukaPalang(); // Posisi Awal: Terbuka

  // 3. Setup LCD I2C
  Wire.begin(21, 22); // SDA 21, SCL 22 pada ESP32
  lcd.init();
  lcd.backlight();
  tampilkanStatusLCD("RailSafe READY", "STATUS: AMAN");

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
  lastConfigRefresh = millis();

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
  pantauUltrasonik();  // Pantau area terus-menerus
  prosesKeadaan();     // C. Hitung mundur, rintangan, auto reset
  perbaruiBuzzer();    // D. Buzzer/sirine tanpa delay()
  kirimHeartbeat();    // E. Status kedua node dikirim berkala ke dashboard
  if (millis() - lastConfigRefresh >= CONFIG_REFRESH_MS) {
    lastConfigRefresh = millis();
    ambilKonfigurasi();
  }
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

  // Telemetri Node 1: RailSafe:NODE_STATUS:<label>:<confidence>
  if (incomingMsg.startsWith("RailSafe:NODE_STATUS:")) {
    const int labelStart = String("RailSafe:NODE_STATUS:").length();
    int separator = incomingMsg.indexOf(':', labelStart);
    if (separator > labelStart) {
      String label = incomingMsg.substring(labelStart, separator);
      float confidence = incomingMsg.substring(separator + 1).toFloat();
      if (label == "IDLE" || label == "TRUK" || label == "KERETA") {
        node1MlLabel = label;
        node1MlConfidence = confidence;
        lastNode1StatusAt = millis();
        Serial.printf("Status Node 1: %s (%.1f%%)\n", label.c_str(), confidence * 100.0f);
      }
    }
  } else if (incomingMsg.startsWith("RailSafe:ALERT_KERETA")) {
    float confidence = 0.0;
    int lastColon = incomingMsg.lastIndexOf(':');
    if (lastColon != -1) {
      confidence = incomingMsg.substring(lastColon + 1).toFloat();
    }

    lastTrainAlertTime = millis(); // Sinyal berulang memperpanjang waktu palang tertutup
    lastNodeStatusTime = lastTrainAlertTime;
    noTrainSince = 0;
    lastConfidence = confidence;

    if (keadaan == SIAGA) {
      mulaiHitungMundur(confidence);
    }
  } else if (incomingMsg.startsWith("RailSafe:STATUS_CLEAR")) {
    lastNodeStatusTime = millis();
    if (noTrainSince == 0) noTrainSince = lastNodeStatusTime;

    // Batalkan penutupan yang masih tertunda saat Node 1 yakin kereta sudah tidak terdeteksi.
    if (keadaan == HITUNG_MUNDUR || keadaan == TERHALANG) {
      bool sedangTerhalang = (keadaan == TERHALANG);
      keadaan = SIAGA;
      countdownStart = 0;
      lastShownSec = -1;
      digitalWrite(BUZZER_PIN, LOW);
      tampilkanStatusLCD("RailSafe READY", "STATUS: AMAN");
      Serial.println("STATUS_CLEAR diterima; penutupan tertunda dibatalkan.");

      // Beri tahu dashboard agar hitung mundur / peringatan rel terhalang ikut berhenti.
      kirimGateLog("AUTOMATIC", 0,
                   sedangTerhalang ? "Kereta tidak terdeteksi lagi, penahanan palang dibatalkan"
                                   : "Getaran kereta berhenti sebelum waktu habis, penutupan dibatalkan",
                   "CANCELLED", -1);
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
      if (!adaRintangan()) {
        tutupPalangOtomatis("Rintangan hilang, palang ditutup otomatis");
      }
      break;

    case TERTUTUP:
      // Jika objek masuk setelah palang otomatis turun, buka kembali dan tahan
      // sampai area terdeteksi kosong secara stabil.
      if (!ditutupManual && adaRintangan()) {
        tahanPalangKarenaRintangan("Objek terdeteksi saat palang otomatis tertutup");
        break;
      }
      // Auto-open hanya untuk palang otomatis dengan STATUS_CLEAR segar berkelanjutan.
      if (!ditutupManual && noTrainSince != 0 &&
          now - lastNodeStatusTime <= NODE_STATUS_STALE_MS &&
          now - noTrainSince >= AUTO_RESET_TIMEOUT &&
          now - autoClosedAt >= AUTO_RESET_TIMEOUT) {
        Serial.println("Waktu penutupan selesai. Membuka palang kembali...");
        bukaPalang();
        keadaan = SIAGA;
        tampilkanStatusLCD("RailSafe READY", "STATUS: AMAN");
        kirimGateLog("AUTOMATIC", 0, "Dibuka otomatis setelah kereta lewat", "OPENED", -1);
      }
      break;

    default:
      break;
  }

  // Paket STATUS_CLEAR yang kedaluwarsa berarti status Node 1 tidak diketahui.
  if (noTrainSince != 0 && now - lastNodeStatusTime > NODE_STATUS_STALE_MS) {
    noTrainSince = 0;
    Serial.println("STATUS_CLEAR Node 1 kedaluwarsa; auto-open ditahan.");
  }
}

void waktuHabis() {
  // Petugas tidak merespons. Gunakan hasil pantauan ultrasonik terkini.
  if (adaRintangan()) {
    tahanPalangKarenaRintangan("Rel terhalang, palang ditahan terbuka");
  } else {
    tutupPalangOtomatis("Petugas tidak merespons, ditutup otomatis (fail-safe)");
  }
}

void tahanPalangKarenaRintangan(String notes) {
  bukaPalang();
  keadaan = TERHALANG;
  ditutupManual = false;
  Serial.println("WARN: Rintangan terdeteksi; palang ditahan/dibuka.");
  tampilkanStatusLCD("KENDARAAN ADA!", "PALANG DITAHAN");
  kirimGateLog("AUTOMATIC", lastConfidence, notes, "HELD", 1);
}

// ==================== FUNGSI-FUNGSI KONTROL ====================

void tutupPalangOtomatis(String notes) {
  gateServo.write(GATE_CLOSE_ANGLE);
  keadaan = TERTUTUP;
  ditutupManual = false;
  lastTrainAlertTime = millis(); // Hitung auto reset dimulai dari sekarang
  autoClosedAt = millis();
  tampilkanStatusLCD("PALANG DITUTUP", "OTOMATIS");
  kirimGateLog("AUTOMATIC", lastConfidence, notes, "CLOSED", 0);
}

void tutupPalangManual(bool dalamHitungMundur) {
  gateServo.write(GATE_CLOSE_ANGLE);
  keadaan = TERTUTUP;
  ditutupManual = true;
  lastTrainAlertTime = millis();
  autoClosedAt = 0;
  tampilkanStatusLCD("MANUAL OVERRIDE", "PALANG DITUTUP");
  kirimGateLog("MANUAL", 0,
               dalamHitungMundur ? "Ditutup petugas saat hitung mundur (Arcade Button)"
                                 : "Ditutup manual via Arcade Button",
               "CLOSED", -1);
}

void bukaPalang() {
  ditutupManual = false;
  autoClosedAt = 0;
  countdownStart = 0;
  gateServo.write(GATE_OPEN_ANGLE);
  digitalWrite(BUZZER_PIN, LOW);
}

void cekTombolManual() {
  bool rawPressed = digitalRead(BUTTON_PIN) == LOW; // Active LOW
  unsigned long now = millis();

  if (rawPressed != buttonRawState) {
    buttonRawState = rawPressed;
    buttonStateChangedAt = now;
  }
  if (now - buttonStateChangedAt < BUTTON_DEBOUNCE_MS || buttonStableState == buttonRawState) return;

  buttonStableState = buttonRawState;
  if (buttonStableState) {
    // Tepi tekan: tutup dan tahan palang selama tombol tetap ditekan.
    if (keadaan == SIAGA || keadaan == HITUNG_MUNDUR) {
      bool duringCountdown = keadaan == HITUNG_MUNDUR;
      tutupPalangManual(duringCountdown);
      manualCloseHeld = true;
    } else if (keadaan == TERHALANG) {
      Serial.println("Tombol manual diabaikan selama sensor mendeteksi rintangan.");
    }
    // Jika palang sudah tertutup otomatis, tombol tidak membukanya.
  } else if (manualCloseHeld) {
    // Tepi lepas: hanya pembukaan manual yang dipicu oleh tombol ini.
    manualCloseHeld = false;
    if (keadaan == TERTUTUP && ditutupManual) {
      bukaPalang();
      keadaan = SIAGA;
      tampilkanStatusLCD("RailSafe READY", "STATUS: AMAN");
      kirimGateLog("MANUAL", 0, "Dibuka saat Arcade Button dilepas", "OPENED", -1);
    }
  }
}

void perbaruiBuzzer() {
  unsigned long now = millis();
  unsigned long interval;

  if (keadaan == TERHALANG) {          // Sirine darurat: menyala terus
    beepOn = true;
    digitalWrite(BUZZER_PIN, HIGH);
    return;
  } else if (keadaan == HITUNG_MUNDUR) {
    interval = beepOn ? BUZZER_COUNTDOWN_ON_MS : BUZZER_COUNTDOWN_OFF_MS;
  } else if (keadaan == TERTUTUP) {
    interval = beepOn ? BUZZER_CLOSED_ON_MS : BUZZER_CLOSED_OFF_MS;
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

// Fungsi generik pembacaan HC-SR04 untuk pin mana pun
long ukurJarakHCSR04(int trigPin, int echoPin) {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 20000); // Timeout 20ms
  if (duration == 0) return 999;
  return duration * 0.034 / 2;
}

void perbaruiStatusRintangan(long jarak, int& nearCount, int& clearCount, bool& sensorTerhalang) {
  if (jarak < OBSTACLE_CM) {
    nearCount = min(nearCount + 1, OBSTACLE_CONFIRM_READS);
    clearCount = 0;
    if (nearCount >= OBSTACLE_CONFIRM_READS) sensorTerhalang = true;
  } else {
    nearCount = 0;
    clearCount = min(clearCount + 1, OBSTACLE_CLEAR_READS);
    if (clearCount >= OBSTACLE_CLEAR_READS) sensorTerhalang = false;
  }
}

// Jalankan satu sweep kedua sensor setiap 2,5 detik dengan jeda antarsensor.
void pantauUltrasonik() {
  unsigned long now = millis();
  if (now - lastUltrasonicScanAt < ULTRASONIC_SCAN_INTERVAL_MS) return;

  jarakUltrasonik1 = ukurJarakHCSR04(TRIG_PIN_1, ECHO_PIN_1);
  perbaruiStatusRintangan(jarakUltrasonik1, obstacleNearCount1, obstacleClearCount1, obstacleSensor1);
  delay(ULTRASONIC_SENSOR_GAP_MS);
  jarakUltrasonik2 = ukurJarakHCSR04(TRIG_PIN_2, ECHO_PIN_2);
  perbaruiStatusRintangan(jarakUltrasonik2, obstacleNearCount2, obstacleClearCount2, obstacleSensor2);

  lastUltrasonicScanAt = millis();
  Serial.printf("Ultrasonik: S1=%ld cm (%s), S2=%ld cm (%s), rintangan=%s\n",
                jarakUltrasonik1, obstacleSensor1 ? "TERHALANG" : "clear",
                jarakUltrasonik2, obstacleSensor2 ? "TERHALANG" : "clear",
                adaRintangan() ? "YA" : "TIDAK");
}

bool adaRintangan() {
  return obstacleSensor1 || obstacleSensor2;
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

void kirimJson(String path, String json, const char* deviceToken) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.setTimeout(1500); // Jangan menahan tombol/hitung mundur terlalu lama jika server lambat
  http.begin(String(LARAVEL_BASE) + path);
  http.addHeader("Content-Type", "application/json");
  if (deviceToken && strlen(deviceToken) > 0) {
    http.addHeader("Authorization", String("Bearer ") + deviceToken);
  }

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
  kirimJson("/alert", json, API_TOKEN_NODE2);
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
  kirimJson("/gate-log", json, API_TOKEN_NODE2);
}

// Kirim heartbeat masing-masing perangkat dengan token yang mengidentifikasi node-nya.
void kirimHeartbeat() {
  if (millis() - lastHeartbeat < 30000) return;
  lastHeartbeat = millis();

  long jarak1 = jarakUltrasonik1;
  long jarak2 = jarakUltrasonik2;
  long jarakTerdekat = min(jarak1, jarak2); // Cache dari pemantauan kontinu

  String node2Json = "{";
  node2Json += "\"firmware_version\":\"RailSafe-Node2\",";
  node2Json += "\"rssi\":" + String(LoRa.packetRssi()) + ",";
  node2Json += "\"distance_cm\":" + String(jarakTerdekat) + ",";
  node2Json += "\"sensors\":[";
  node2Json += "{\"type\":\"LORA\",\"state\":\"ACTIVE\",\"health\":\"OK\",\"detail\":\"Penerima LoRa aktif\"},";
  node2Json += "{\"type\":\"SERVO\",\"state\":\"" + String(keadaan == TERTUTUP ? "ACTIVE" : "STANDBY") + "\",\"health\":\"OK\",\"detail\":\"" + String(keadaan == TERTUTUP ? "Palang tertutup" : "Palang terbuka") + "\"},";
  node2Json += "{\"type\":\"ULTRASONIC_1\",\"state\":\"ACTIVE\",\"health\":\"OK\",\"detail\":\"" + String(jarak1) + " cm\"},";
  node2Json += "{\"type\":\"ULTRASONIC_2\",\"state\":\"ACTIVE\",\"health\":\"OK\",\"detail\":\"" + String(jarak2) + " cm\"}]}";
  kirimJson("/heartbeat", node2Json, API_TOKEN_NODE2);

  // Node 1 dianggap masih terhubung hanya bila paket status LoRa-nya baru.
  // Jika paket berhenti, heartbeat NODE-01 juga berhenti agar dashboard menandai offline.
  if (lastNode1StatusAt != 0 && millis() - lastNode1StatusAt <= 15000) {
    String node1Json = "{";
    node1Json += "\"firmware_version\":\"RailSafe-Node1\",";
    node1Json += "\"ml_label\":\"" + node1MlLabel + "\",";
    node1Json += "\"ml_confidence\":" + String(node1MlConfidence * 100.0f, 2) + ",";
    node1Json += "\"sensors\":[";
    node1Json += "{\"type\":\"MPU6050\",\"state\":\"ACTIVE\",\"health\":\"OK\",\"detail\":\"TinyML " + node1MlLabel + " " + String(node1MlConfidence * 100.0f, 1) + "%\"},";
    node1Json += "{\"type\":\"LORA\",\"state\":\"ACTIVE\",\"health\":\"OK\",\"detail\":\"Status diterima " + String((millis() - lastNode1StatusAt) / 1000) + " dtk lalu\"}]}";
    kirimJson("/heartbeat", node1Json, API_TOKEN_NODE1);
  } else {
    Serial.println("Heartbeat NODE-01 dilewati: status LoRa Node 1 sudah kedaluwarsa.");
  }
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
  if (strlen(API_TOKEN_NODE2) > 0) http.addHeader("Authorization", String("Bearer ") + API_TOKEN_NODE2);

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
