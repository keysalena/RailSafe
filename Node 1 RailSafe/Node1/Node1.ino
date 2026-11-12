
//  * RailSafe - Node 1 (Sensor Rel + TinyML + LoRa TX)
//  * Hardware: ESP32, MPU6050 (MPU6050_light), LoRa Ra-02 (SX1278 433MHz)


#include <Wire.h>
#include <SPI.h>
#include <LoRa.h>
#include <MPU6050_light.h>

#include <Chirale_TensorFlowLite.h>

#include <tensorflow/lite/micro/all_ops_resolver.h>
#include <tensorflow/lite/micro/micro_interpreter.h>
#include <tensorflow/lite/micro/micro_log.h>
#include <tensorflow/lite/schema/schema_generated.h>

#include "model_data.h"

// Konfigurasi Model TinyML
#define NUMBER_OF_INPUTS 600          // 100 sampel x 6 sumbu (accX,Y,Z, gyroX,Y,Z)
#define NUMBER_OF_OUTPUTS 3           // 3 Kelas: 0 = idle, 1 = kereta, 2 = truk
#define TENSOR_ARENA_SIZE (30 * 1024) // Dinaikkan dari 10KB -> 30KB karena AllOpsResolver
                                       // + operator tambahan (EXPAND_DIMS/RESHAPE) butuh
                                       // lebih banyak scratch memory. Naikkan lagi jika
                                       // AllocateTensors() masih gagal.

// --- Objek TensorFlow Lite Micro ---
const tflite::Model* tfl_model = nullptr;
tflite::MicroInterpreter* interpreter = nullptr;
TfLiteTensor* input_tensor  = nullptr;
TfLiteTensor* output_tensor = nullptr;

alignas(16) uint8_t tensor_arena[TENSOR_ARENA_SIZE];

// Inisialisasi MPU6050_light (Bus I2C SDA=21, SCL=22)
MPU6050 mpu(Wire);

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
unsigned long lastNodeStatusSentAt = 0;
const unsigned long NODE_STATUS_INTERVAL_MS = 5000; // Status berkala ke Node 2

// Prototipe Fungsi
void kirimSinyalLoRa(String status, float confidence);
bool initTinyML();

void setup() {
  Serial.begin(115200);
  delay(2000); // Jeda 2 detik agar tegangan bus I2C & SPI stabil
  Serial.println("\n--- [RailSafe Node 1: Inisialisasi Sistem] ---");

  // 1. Inisialisasi MPU6050_light (Dengan Retry Loop seperti kode Anda)
  Wire.begin(21, 22);
  byte status = mpu.begin();

  while (status != 0) {
    Serial.print("❌ ERROR: MPU6050 gagal init! Kode Status: ");
    Serial.println(status);
    delay(1000);
    status = mpu.begin();
  }
  Serial.println("✅ MPU6050 Berhasil Terkoneksi!");

  // Kalibrasi offset gyro/accel
  Serial.println("Mengukur offset sensor... Jangan gerakkan modul!");
  delay(1000);
  mpu.calcOffsets();
  Serial.println("✅ Selesai kalibrasi MPU6050!");

  // 2. Inisialisasi Model TinyML (TFLite Micro + AllOpsResolver)
  if (!initTinyML()) {
    Serial.println("❌ ERROR: Gagal memuat Model TinyML!");
    while (1) delay(500);
  }
  Serial.println("✅ Model TinyML RailSafe berhasil dimuat.");

  // 3. Inisialisasi LoRa Ra-02
  SPI.begin(LORA_SCK, LORA_MISO, LORA_MOSI, LORA_SS);
  LoRa.setPins(LORA_SS, LORA_RST, LORA_DIO0);

  if (!LoRa.begin(433E6)) {
    Serial.println("❌ ERROR: LoRa Ra-02 gagal diinisialisasi!");
    while (1) delay(500);
  }
  LoRa.setSyncWord(0xF3); // Sync Word RailSafe
  Serial.println("✅ LoRa Ra-02 Transmit Siap (433 MHz).");
  Serial.println("\n--- [Sistem RailSafe Node 1 Berjalan] ---");
}

void loop() {
  unsigned long currentMillis = millis();

  // Pengambilan Sampel 100 Hz (Tiap 10 ms)
  if (currentMillis - lastSampleTime >= SAMPLING_PERIOD_MS) {
    lastSampleTime = currentMillis;

    mpu.update();

    // Menggunakan rumus konversi yang 100% SAMA dengan skrip perekaman CSV Anda
    float accX  = mpu.getAccX() * 9.81f;
    float accY  = mpu.getAccY() * 9.81f;
    float accZ  = mpu.getAccZ() * 9.81f;

    float gyroX = mpu.getGyroX(); // Dalam deg/s (Sesuai CSV)
    float gyroY = mpu.getGyroY(); // Dalam deg/s (Sesuai CSV)
    float gyroZ = mpu.getGyroZ(); // Dalam deg/s (Sesuai CSV)

    // Isi Buffer secara Berurutan (accX, accY, accZ, gyroX, gyroY, gyroZ)
    int idx = sample_index * 6;
    input_buffer[idx + 0] = accX;
    input_buffer[idx + 1] = accY;
    input_buffer[idx + 2] = accZ;
    input_buffer[idx + 3] = gyroX;
    input_buffer[idx + 4] = gyroY;
    input_buffer[idx + 5] = gyroZ;

    sample_index++;

    // Jika Buffer sudah penuh (100 Sampel = 1 Detik), Jalankan Inferensi
    if (sample_index >= WINDOW_SIZE) {
      sample_index = 0; // Reset index buffer

      // Salin buffer ke tensor input TFLite Micro
      for (int i = 0; i < NUMBER_OF_INPUTS; i++) {
        input_tensor->data.f[i] = input_buffer[i];
      }

      // Jalankan Prediksi TinyML
      TfLiteStatus invoke_status = interpreter->Invoke();
      if (invoke_status != kTfLiteOk) {
        Serial.println("❌ ERROR: Invoke() gagal, inferensi dilewati.");
        return;
      }

      float prob_idle   = output_tensor->data.f[0];
      float prob_kereta = output_tensor->data.f[1];
      float prob_truk   = output_tensor->data.f[2];

      Serial.printf("Prediksi -> Idle: %.2f | Kereta: %.2f | Truk: %.2f\n",
                    prob_idle, prob_kereta, prob_truk);

      // Logika Keputusan & Transmisi LoRa
      if (prob_kereta > 0.70) { // Threshold keyakinan 70%
        Serial.println("🚨 KERETA TERDETEKSI! Mengirim sinyal LoRa ke Pos PJL...");
        kirimSinyalLoRa("ALERT_KERETA", prob_kereta);
      } else if (prob_kereta <= 0.30 && (prob_idle > 0.60 || prob_truk > 0.75)) {
        // Status aman yang tegas diperlukan Node 2 untuk membuka penutupan otomatis.
        Serial.println("🟢 Tidak ada kereta terdeteksi. Mengirim status aman ke Pos PJL...");
        kirimSinyalLoRa("STATUS_CLEAR", 0.0);
      } else if (prob_truk > 0.75) {
        Serial.println("🚛 Truk melintas; keyakinan kereta belum cukup untuk status aman.");
      } else {
        Serial.println("⚠️ Prediksi belum cukup pasti; tidak mengirim status aman.");
      }
      // Node 1 tidak mengakses Laravel langsung. Status TinyML dikirim via LoRa
      // setiap 5 detik agar Node 2 dapat meneruskannya ke dashboard.
      if (millis() - lastNodeStatusSentAt >= NODE_STATUS_INTERVAL_MS) {
        const char* label = "IDLE";
        float confidence = prob_idle;
        if (prob_kereta >= prob_idle && prob_kereta >= prob_truk) {
          label = "KERETA";
          confidence = prob_kereta;
        } else if (prob_truk >= prob_idle && prob_truk >= prob_kereta) {
          label = "TRUK";
          confidence = prob_truk;
        }
        kirimSinyalLoRa(String("NODE_STATUS:") + label, confidence);
        lastNodeStatusSentAt = millis();
      }
    }
  }
}

// Inisialisasi TensorFlow Lite Micro: load model, pasang AllOpsResolver,
// alokasikan tensor, dan ambil pointer tensor input/output.
bool initTinyML() {
  tfl_model = tflite::GetModel(g_RailSafe_model);
  if (tfl_model->version() != TFLITE_SCHEMA_VERSION) {
    Serial.println("❌ ERROR: Versi skema model tidak cocok dengan TFLite Micro.");
    return false;
  }

  // AllOpsResolver mendaftarkan SEMUA operator built-in (termasuk EXPAND_DIMS,
  // RESHAPE, SQUEEZE yang disisipkan otomatis saat convert Conv1D -> TFLite).
  static tflite::AllOpsResolver resolver;

  static tflite::MicroInterpreter static_interpreter(
      tfl_model, resolver, tensor_arena, TENSOR_ARENA_SIZE);
  interpreter = &static_interpreter;

  TfLiteStatus allocate_status = interpreter->AllocateTensors();
  if (allocate_status != kTfLiteOk) {
    Serial.println("❌ ERROR: AllocateTensors() gagal (tensor_arena mungkin kurang besar).");
    return false;
  }

  input_tensor  = interpreter->input(0);
  output_tensor = interpreter->output(0);

  return true;
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
