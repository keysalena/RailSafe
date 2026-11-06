/*
 * RailSafe - Node 1 (Sensor Rel)
 * Program Akuisisi Data Getaran MPU6050 untuk Dataset TinyML
 * 
 * Pinout ESP32 ke MPU6050:
 * - VCC -> 3.3V
 * - GND -> GND
 * - SDA -> GPIO 21
 * - SCL -> GPIO 22
 */

#include <Wire.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>

Adafruit_MPU6050 mpu;

const unsigned long SAMPLING_PERIOD_MS = 10; // 10 ms = 100 Hz (100 sampel/detik)
unsigned long lastSampleTime = 0;

void setup() {
  Serial.begin(115200);
  
  // Beri waktu 2 detik agar Anda sempat membuka Serial Monitor setelah Reset/Upload
  delay(2000); 
  Serial.println("\n--- [RailSafe Node 1: Inisialisasi MPU6050] ---");

  Wire.begin(21, 22); // SDA = GPIO 21, SCL = GPIO 22

  // Coba inisialisasi MPU6050 (Alamat default 0x68)
  while (!mpu.begin(0x68)) {
    Serial.println("❌ ERROR: MPU6050 tidak terdeteksi! Periksa kabel VCC(3.3V), GND, SDA(21), SCL(22). Retrying...");
    delay(1000); // Terus coba lagi setiap 1 detik sampai sensor terhubung
  }

  Serial.println("✅ MPU6050 Berhasil Terkoneksi!");

  // Konfigurasi Range Akselerometer +-4G & Low-Pass Filter 44Hz
  mpu.setAccelerometerRange(MPU6050_RANGE_4_G);
  mpu.setGyroRange(MPU6050_RANGE_500_DEG);
  mpu.setFilterBandwidth(MPU6050_BAND_44_HZ);

  delay(500);

  // Header CSV untuk dataset TinyML
  Serial.println("timestamp_ms,accX,accY,accZ,gyroX,gyroY,gyroZ");
}

void loop() {
  unsigned long currentMillis = millis();

  // Pengambilan sampel presisi 100 Hz (tiap 10 ms)
  if (currentMillis - lastSampleTime >= SAMPLING_PERIOD_MS) {
    lastSampleTime = currentMillis;

    sensors_event_t a, g, temp;
    mpu.getEvent(&a, &g, &temp);

    // Format output CSV
    Serial.print(currentMillis);
    Serial.print(",");
    Serial.print(a.acceleration.x, 3);
    Serial.print(",");
    Serial.print(a.acceleration.y, 3);
    Serial.print(",");
    Serial.print(a.acceleration.z, 3);
    Serial.print(",");
    Serial.print(g.gyro.x, 3);
    Serial.print(",");
    Serial.print(g.gyro.y, 3);
    Serial.print(",");
    Serial.println(g.gyro.z, 3);
  }
}