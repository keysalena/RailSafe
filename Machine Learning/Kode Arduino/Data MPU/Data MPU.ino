/*
 * RailSafe - Node 1 (Sensor Rel)
 * Akuisisi Data MPU6050 menggunakan MPU6050_light
 */

#include <Wire.h>
#include <MPU6050_light.h>

MPU6050 mpu(Wire);

const unsigned long SAMPLING_PERIOD_MS = 10; // 100 Hz
unsigned long lastSampleTime = 0;

void setup() {
  Serial.begin(115200);
  delay(2000);
  Serial.println("\n--- [RailSafe Node 1: Inisialisasi MPU6050] ---");

  Wire.begin(21, 22); //
  byte status = mpu.begin();
  
  while (status != 0) {
    Serial.print("❌ ERROR: MPU6050 gagal init! Kode Status: ");
    Serial.println(status);
    delay(1000);
    status = mpu.begin();
  }

  Serial.println("✅ MPU6050 Berhasil Terkoneksi!");
  
  // Kalibrasi gyro otomatis saat diam
  Serial.println("Mengukur offset sensor... Jangan gerakkan modul!");
  delay(1000);
  mpu.calcOffsets(); 
  Serial.println("Selesai kalibrasi!");

  // Header CSV untuk dataset TinyML
  Serial.println("timestamp_ms,accX,accY,accZ,gyroX,gyroY,gyroZ");
}

void loop() {
  unsigned long currentMillis = millis();

  if (currentMillis - lastSampleTime >= SAMPLING_PERIOD_MS) {
    lastSampleTime = currentMillis;

    mpu.update();

    // MPU6050_light memberikan output percepatan dalam satuan G
    // Dikali 9.81 m/s^2 agar format seragam dengan library Adafruit
    float accX = mpu.getAccX() * 9.81;
    float accY = mpu.getAccY() * 9.81;
    float accZ = mpu.getAccZ() * 9.81;

    // Output Gyro dalam derajat per detik (deg/s)
    float gyroX = mpu.getGyroX();
    float gyroY = mpu.getGyroY();
    float gyroZ = mpu.getGyroZ();

    Serial.print(currentMillis);
    Serial.print(",");
    Serial.print(accX, 3);
    Serial.print(",");
    Serial.print(accY, 3);
    Serial.print(",");
    Serial.print(accZ, 3);
    Serial.print(",");
    Serial.print(gyroX, 3);
    Serial.print(",");
    Serial.print(gyroY, 3);
    Serial.print(",");
    Serial.println(gyroZ, 3);
  }
}