/*
 * RailSafe - Node 2 (Pos PJL Receiver + Gate Controller + HMI)
 * Hardware: ESP32, LoRa Ra-02, LCD I2C 16x2, Servo MG996R, HC-SR04, Buzzer, Arcade Button
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
const char* LARAVEL_API   = "http://10.33.187.52:8000"; // IP Laptop/Server Laravel

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

#define TRIG_PIN_1  12
#define ECHO_PIN_1  27
#define TRIG_PIN_2  26  // Sesuaikan dengan pin TRIG sensor ultrasonik kedua
#define ECHO_PIN_2  25  // Sesuaikan dengan pin ECHO sensor ultrasonik kedua

// ==================== INISIALISASI OBJEK ====================
LiquidCrystal_I2C lcd(0x27, 16, 2);
Servo gateServo;

// Posisi Palang Pintu (Sudut Servo)
const int GATE_OPEN_ANGLE  = 0;   // Palang Terbuka
const int GATE_CLOSE_ANGLE = 125;  // Palang Tertutup

// Status Sistem
bool isGateClosed = false;
bool isManualOverride = false;
unsigned long lastButtonPress = 0;
unsigned long lastTrainAlertTime = 0;
const unsigned long AUTO_RESET_TIMEOUT = 15000; // Auto reset 15 detik setelah kereta lewat

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

  // 2. Setup Servo MG996R
  ESP32PWM::allocateTimer(0);
  gateServo.setPeriodHertz(50); // Standard 50Hz servo
  gateServo.attach(SERVO_PIN, 500, 2400);
  bukaPalang(); // Posisi Awal: Terbuka

  // 3. Setup LCD I2C
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
    Serial.println("\n✅ WiFi Terhubung IP: " + WiFi.localIP().toString());
  } else {
    Serial.println("\n⚠️ WiFi Offline (Mode Standalone/LoRa Only)");
  }

  // 5. Setup LoRa Ra-02 (433 MHz)
  SPI.begin(LORA_SCK, LORA_MISO, LORA_MOSI, LORA_SS);
  LoRa.setPins(LORA_SS, LORA_RST, LORA_DIO0);
  if (!LoRa.begin(433E6)) {
    Serial.println("❌ ERROR: LoRa Ra-02 Gagal!");
    tampilkanStatusLCD("LORA ERROR!", "CEK KABEL");
    while (1);
  }
  LoRa.setSyncWord(0xF3); // Harus sama dengan Node 1
  Serial.println("✅ LoRa Receiver Siap (433 MHz)");
}

void loop() {
  // A. CEK TOMBOL MANUAL OVERRIDE (ARCADE BUTTON)
  cekTombolManual();

  // B. BACA PAKET LORA MASUK (OTOMATIS DARINODE 1)
  int packetSize = LoRa.parsePacket();
  if (packetSize) {
    String incomingMsg = "";
    while (LoRa.available()) {
      incomingMsg += (char)LoRa.read();
    }
    Serial.println("📥 LoRa Received: " + incomingMsg);

    // Parsing format sinyal: "RailSafe:ALERT_KERETA:<confidence>"
    if (incomingMsg.startsWith("RailSafe:ALERT_KERETA")) {
      float confidence = 0.0;
      int lastColon = incomingMsg.lastIndexOf(':');
      if (lastColon != -1) {
        confidence = incomingMsg.substring(lastColon + 1).toFloat();
      }

      lastTrainAlertTime = millis();
      if (!isGateClosed) {
        tutupPalangOtomatis(confidence);
      }
    }
  }

  // C. AUTO RESET PALANG (Jika Kereta Sudah Lewat & Tidak dalam Mode Manual)
  if (isGateClosed && !isManualOverride) {
    if (millis() - lastTrainAlertTime > AUTO_RESET_TIMEOUT) {
      Serial.println("🟢 Waktu penutupan selesai. Membuka palang kembali...");
      bukaPalang();
      tampilkanStatusLCD("RailSafe READY", "STATUS: AMAN");
    }
  }

  // D. BUNYI BUZZER KETIKA PALANG TERTUTUP
  if (isGateClosed) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(150);
    digitalWrite(BUZZER_PIN, LOW);
    delay(150);
  }
}

// ==================== FUNGSI-FUNGSI KONTROL ====================

void tutupPalangOtomatis(float confidence) {
  // Cek Keberadaan Kendaraan Terjebak di Bawah Palang (Fail-Safe HC-SR04)
  long jarak1 = ukurJarakHCSR04(TRIG_PIN_1, ECHO_PIN_1);
  long jarak2 = ukurJarakHCSR04(TRIG_PIN_2, ECHO_PIN_2);
  bool adaObjek = (jarak1 > 0 && jarak1 < 15) || (jarak2 > 0 && jarak2 < 15);
  if (adaObjek) {
    Serial.printf("WARN: Objek terdeteksi; sensor 1: %ld cm, sensor 2: %ld cm. Cek ulang dalam 1 detik.\n", jarak1, jarak2);
    tampilkanStatusLCD("AWAS KENDARAAN!", "MENUNGGU CLEAR");
    delay(1000);

    jarak1 = ukurJarakHCSR04(TRIG_PIN_1, ECHO_PIN_1);
    jarak2 = ukurJarakHCSR04(TRIG_PIN_2, ECHO_PIN_2);
    if ((jarak1 > 0 && jarak1 < 15) || (jarak2 > 0 && jarak2 < 15)) {
      Serial.printf("WARN: Area masih terhalang; sensor 1: %ld cm, sensor 2: %ld cm. Palang tetap terbuka.\n", jarak1, jarak2);
      tampilkanStatusLCD("AREA TERHALANG", "PALANG TETAP BUKA");
      return;
    }
  }

  isGateClosed = true;
  gateServo.write(GATE_CLOSE_ANGLE);
  tampilkanStatusLCD("🚨 BAHAYA KERETA", "PALANG DITUTUP!");
  
  // Kirim Log ke Laravel
  kirimLogKeLaravel("AUTOMATIC", confidence, "Kereta terdeteksi TinyML");
}

void bukaPalang() {
  isGateClosed = false;
  isManualOverride = false;
  gateServo.write(GATE_OPEN_ANGLE);
  digitalWrite(BUZZER_PIN, LOW);
}

void cekTombolManual() {
  if (digitalRead(BUTTON_PIN) == LOW) { // Button Pressed (Active LOW)
    if (millis() - lastButtonPress > 400) { // Debounce 400ms
      lastButtonPress = millis();
      
      isGateClosed = !isGateClosed; // Toggle State
      isManualOverride = true;      // Tandai penutupan manual oleh operator

      if (isGateClosed) {
        gateServo.write(GATE_CLOSE_ANGLE);
        tampilkanStatusLCD("MANUAL OVERRIDE", "PALANG DITUTUP");
        kirimLogKeLaravel("MANUAL", 1.0, "Ditutup manual via Arcade Button");
      } else {
        bukaPalang();
        tampilkanStatusLCD("MANUAL OVERRIDE", "PALANG DIBUKA");
        kirimLogKeLaravel("MANUAL", 1.0, "Dibuka manual via Arcade Button");
      }
    }
  }
}

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

void tampilkanStatusLCD(String baris1, String baris2) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(baris1);
  lcd.setCursor(0, 1);
  lcd.print(baris2);
}

// ==================== FUNGSI REST API LARAVEL ====================
void kirimLogKeLaravel(String triggerType, float confidence, String notes) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(LARAVEL_API);
    http.addHeader("Content-Type", "application/json");

    // JSON Payload sesuai skema tabel gate_logs
    String jsonPayload = "{";
    jsonPayload += "\"trigger_type\":\"" + triggerType + "\",";
    jsonPayload += "\"confidence_score\":" + String(confidence, 2) + ",";
    jsonPayload += "\"notes\":\"" + notes + "\"";
    jsonPayload += "}";

    int httpResponseCode = http.POST(jsonPayload);
    Serial.printf("📡 HTTP POST REST API Status: %d\n", httpResponseCode);
    http.end();
  }
}