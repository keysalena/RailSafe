/*
 * RailSafe - Node 1 (Sensor Rel)
 * Program Akuisisi Data Getaran MPU6050 untuk Dataset TinyML
 * 
 * Pinout ESP32 ke MPU6050:
 * - VCC -> 3.3V / 5V
 * - GND -> GND
 * - SDA -> GPIO 21
 * - SCL -> GPIO 22
 */

#include <Wire.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>

Adafruit_MPU6050 mpu;

// Pengaturan Sampling Rate untuk TinyML
const unsigned long SAMPLING_PERIOD_MS = 10; // 10 ms = 100 Hz (100 sampel/detik)
unsigned long lastSampleTime = 0;

void setup() {
  Serial.begin(115200);
  while (!Serial) delay(10); // Tunggu Serial Siap

  Wire.begin(21, 22); // Inisialisasi I2C (SDA = GPIO 21, SCL = GPIO 22)

  // Inisialisasi Sensor MPU6050
  if (!mpu.begin()) {
    Serial.println("ERROR: MPU6050 tidak terdeteksi! Periksa sambungan kabel.");
    while (1) {
      delay(10);
    }
  }

  // Konfigurasi Range Akselerometer & Filter MPU6050
  // Range +-4G cocok untuk mengukur getaran rel kereta api & truk
  mpu.setAccelerometerRange(MPU6050_RANGE_4_G);
  mpu.setGyroRange(MPU6050_RANGE_500_DEG);
  mpu.setFilterBandwidth(MPU6050_BAND_44_HZ); // Low-pass filter untuk membuang noise frekuensi tinggi

  delay(100);

  // Cetak Header CSV untuk Serial Monitor / Log File
  Serial.println("timestamp_ms,accX,accY,accZ,gyroX,gyroY,gyroZ");
}

void loop() {
  unsigned long currentMillis = millis();

  // Pengambilan sampel setiap 10 ms (100 Hz)
  if (currentMillis - lastSampleTime >= SAMPLING_PERIOD_MS) {
    lastSampleTime = currentMillis;

    sensors_event_t a, g, temp;
    mpu.getEvent(&a, &g, &temp);

    // Format output CSV: timestamp_ms, accX, accY, accZ, gyroX, gyroY, gyroZ
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