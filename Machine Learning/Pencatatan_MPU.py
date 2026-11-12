import serial
import time

# ==================== KONFIGURASI ====================
PORT_COM = 'COM4'         # Ubah sesuai Port COM ESP32 di laptop Anda
BAUDRATE = 115200         # Harus sama dengan Serial.begin di Arduino
NAMA_LABEL = 'kereta'       # Ganti label: 'kereta', 'truk', atau 'idle'
DURASI_DETIK = 30         # Durasi rekam per file (misal 30 detik)
JUMLAH_PERULANGAN = 46   # <-- TAMBAHAN: Jumlah file/sesi yang ingin direkam
# =====================================================

def rekam_data(sesi_ke):
    try:
        print(f"\n--- Memulai Sesi {sesi_ke} dari {JUMLAH_PERULANGAN} ---")
        print(f"Menghubungkan ke {PORT_COM}...")
        ser = serial.Serial(PORT_COM, BAUDRATE, timeout=1)
        time.sleep(2)  # Waktu tunggu inisialisasi koneksi serial

        # Nama file ditambahkan nomor sesi agar rapi
        nama_file = f"{NAMA_LABEL}_sesi{sesi_ke}_{int(time.time())}.csv"
        print(f"Mulai merekam data label '{NAMA_LABEL}' selama {DURASI_DETIK} detik...")

        with open(nama_file, 'w', encoding='utf-8') as f:
            start_time = time.time()
            jumlah_baris = 0

            while (time.time() - start_time) < DURASI_DETIK:
                if ser.in_waiting > 0:
                    line = ser.readline().decode('utf-8', errors='ignore').strip()
                    # Hanya simpan baris data mentah bernilai angka/header
                    if line and ',' in line:
                        f.write(line + '\n')
                        jumlah_baris += 1
                        print(f"\rData tercatat: {jumlah_baris} sampel", end="")

        ser.close()
        print(f"\nSelesai! File berhasil disimpan: {nama_file}")

    except Exception as e:
        print(f"\nError: {e}")
        print("Pastikan Serial Monitor di Arduino IDE SUDAH DITUTUP sebelum menjalankan skrip.")

if __name__ == "__main__":
    # Menjalankan fungsi rekam_data() sebanyak JUMLAH_PERULANGAN
    for i in range(1, JUMLAH_PERULANGAN + 1):
        rekam_data(i)
        
        # Jeda waktu antar sesi sebelum memulai perekaman berikutnya
        if i < JUMLAH_PERULANGAN:
            print("Jeda 3 detik sebelum sesi berikutnya...")
            time.sleep(3) 
            
    print("\nSeluruh proses perekaman telah selesai!")