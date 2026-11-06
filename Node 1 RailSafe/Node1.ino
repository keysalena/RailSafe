/*
 * RailSafe - Node 1 (Sensor Rel + TinyML + LoRa TX)
 * Hardware: ESP32, MPU6050, LoRa Ra-02 (SX1278 433MHz)
 */

#include <Wire.h>
#include <SPI.h>
#include <LoRa.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <eloquent_tinyml.h>

#include "model_data.h" // Header model hasil ekspor Colab

// Config TinyML Model
#define NUMBER_OF_INPUTS 600   // 100 sampel x 6 sumbu (accX,Y,Z, gyroX,Y,Z)
#define NUMBER_OF_OUTPUTS 3    // 3 Kelas: 0 = idle, 1 = kereta, 2 = truk
#define TENSOR_ARENA_SIZE 8 * 1024 // 8 KB SRAM

Eloquent::TinyML::TfLite<NUMBER_OF_INPUTS, NUMBER_OF_OUTPUTS, TENSOR_ARENA_SIZE> ml;

// Pinout MPU6050 (I2C)
#define SDA_PIN 21
#define SCL_PIN 22
Adafruit_MPU6050 mpu;

// Pinout LoRa Ra-02 (SPI)
#define LORA_SCK 18
#define LORA_MISO 19
#define LORA_MOSI 23
#define LORA_SS 5
#define LORA_RST 14
#define LORA_DIO0 2

// Buffer Windowing (100 Sampel = 1 Detik pada 100 Hz)
const int WINDOW_SIZE = 100;
float input_buffer[NUMBER_OF_INPUTS];
int sample_index = 0;

unsigned long lastSampleTime = 0;
const unsigned long SAMPLING_PERIOD_MS = 10; // 100 Hz

void setup() {
  Serial.begin(115200);
  delay(500);

  // 1. Inisialisasi MPU6050
  Wire.begin(SDA_PIN, SCL_PIN);
  if (!mpu.begin()) {
    Serial.println("❌ ERROR: MPU6050 tidak terdeteksi!");
    while (1) delay(10);
  }
  mpu.setAccelerometerRange(MPU6050_RANGE_4_G);
  mpu.setGyroRange(MPU6050_RANGE_500_DEG);
  mpu.setFilterBandwidth(MPU6050_BAND_44_HZ);

  // 2. Inisialisasi Model TinyML
  if (!ml.begin(g_RailSafe_model)) {
    Serial.println("❌ ERROR: Gagal memuat Model TinyML!");
    while (1) delay(10);
  }
  Serial.println("✅ Model TinyML berhasil dimuat di ESP32.");

  // 3. Inisialisasi LoRa Ra-02
  SPI.begin(LORA_SCK, LORA_MISO, LORA_MOSI, LORA_SS);
  LoRa.setPins(LORA_SS, LORA_RST, LORA_DIO0);

  if (!LoRa.begin(433E6)) { // Frekuensi 433 MHz
    Serial.println("❌ ERROR: LoRa Ra-02 gagal diinisialisasi!");
    while (1) delay(10);
  }
  LoRa.setSyncWord(0xF3); // Network Sync Word RailSafe
  Serial.println("✅ LoRa Ra-02 Transmit Siap (433 MHz).");
}

void loop() {
  unsigned long currentMillis = millis();

  // Pengambilan Sampel 100 Hz (Tiap 10 ms)
  if (currentMillis - lastSampleTime >= SAMPLING_PERIOD_MS) {
    lastSampleTime = currentMillis;

    sensors_event_t a, g, temp;
    mpu.getEvent(&a, &g, &temp);

    // Isi Buffer secara Berurutan (accX, accY, accZ, gyroX, gyroY, gyroZ)
    int idx = sample_index * 6;
    input_buffer[idx + 0] = a.acceleration.x;
    input_buffer[idx + 1] = a.acceleration.y;
    input_buffer[idx + 2] = a.acceleration.z;
    input_buffer[idx + 3] = g.gyro.x;
    input_buffer[idx + 4] = g.gyro.y;
    input_buffer[idx + 5] = g.gyro.z;

    sample_index++;

    // Jika Buffer sudah penuh (100 Sampel / 1 Detik), Jalankan Inferensi
    if (sample_index >= WINDOW_SIZE) {
      sample_index = 0; // Reset index buffer

      float y_pred[NUMBER_OF_OUTPUTS] = {0};
      
      // Jalankan Prediksi TinyML
      ml.predict(input_buffer, y_pred);

      float prob_idle   = y_pred[0];
      float prob_kereta = y_pred[1];
      float prob_truk   = y_pred[2];

      Serial.printf("Prediksi -> Idle: %.2f | Kereta: %.2f | Truk: %.2f\n", 
                    prob_idle, prob_kereta, prob_truk);

      // Logika Keputusan & Transmisi LoRa
      if (prob_kereta > 0.70) { // Ambang batas keyakinan 70%
        Serial.println("🚨 KERETA TERDETEKSI! Mengirim sinyal LoRa ke Pos PJL...");
        kirimSinyalLoRa("ALERT_KERETA", prob_kereta);
      } else if (prob_truk > 0.75) {
        Serial.println("🚛 Truk melintas (Diabaikan).");
      } else {
        Serial.println("🟢 Status Rel: Aman/Idle.");
      }
    }
  }
}

// Fungsi Pengiriman Paket LoRa
void kirimSinyalLoRa(String status, float confidence) {
  LoRa.beginPacket();
  LoRa.print("RailSafe:");
  LoRa.print(status);
  LoRa.print(":");
  LoRa.print(confidence, 2);
  LoRa.endPacket();
}
