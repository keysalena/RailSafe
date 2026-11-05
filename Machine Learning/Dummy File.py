import numpy as np
import pandas as pd
import time

def generate_dummy_dataset(filename, label, duration_sec=10, sample_rate_hz=100):
    num_samples = duration_sec * sample_rate_hz
    t_ms = np.arange(0, num_samples * 10, 10) # 10 ms interval

    if label == 'idle':
        accX = np.random.normal(0.0, 0.08, num_samples)
        accY = np.random.normal(0.0, 0.08, num_samples)
        accZ = 9.81 + np.random.normal(0.0, 0.10, num_samples)
        gyroX = np.random.normal(0.0, 0.02, num_samples)
        gyroY = np.random.normal(0.0, 0.02, num_samples)
        gyroZ = np.random.normal(0.0, 0.02, num_samples)

    elif label == 'truk':
        vib = 1.2 * np.sin(2 * np.pi * 8.0 * (t_ms / 1000.0))
        accX = vib * 0.6 + np.random.normal(0.0, 0.25, num_samples)
        accY = vib * 0.4 + np.random.normal(0.0, 0.25, num_samples)
        accZ = 9.81 + vib + np.random.normal(0.0, 0.35, num_samples)
        gyroX = np.random.normal(0.0, 0.15, num_samples)
        gyroY = np.random.normal(0.0, 0.15, num_samples)
        gyroZ = np.random.normal(0.0, 0.15, num_samples)

    elif label == 'kereta':
        vib_high = 4.5 * np.sin(2 * np.pi * 25.0 * (t_ms / 1000.0)) + 3.0 * np.cos(2 * np.pi * 40.0 * (t_ms / 1000.0))
        impacts = np.zeros(num_samples)
        for idx in range(50, num_samples, 100):
            impacts[idx:idx+5] = np.random.choice([8.0, -8.0], size=5)
            
        accX = vib_high * 0.8 + impacts + np.random.normal(0.0, 0.8, num_samples)
        accY = vib_high * 0.6 + impacts * 0.5 + np.random.normal(0.0, 0.8, num_samples)
        accZ = 9.81 + vib_high + impacts + np.random.normal(0.0, 1.2, num_samples)
        gyroX = np.random.normal(0.0, 0.6, num_samples)
        gyroY = np.random.normal(0.0, 0.6, num_samples)
        gyroZ = np.random.normal(0.0, 0.6, num_samples)

    df = pd.DataFrame({
        'timestamp_ms': t_ms,
        'accX': np.round(accX, 3),
        'accY': np.round(accY, 3),
        'accZ': np.round(accZ, 3),
        'gyroX': np.round(gyroX, 3),
        'gyroY': np.round(gyroY, 3),
        'gyroZ': np.round(gyroZ, 3)
    })
    
    df.to_csv(filename, index=False)
    print(f"File {filename} berhasil dibuat ({num_samples} sampel).")

# Buat 3 file data sintetis
generate_dummy_dataset('idle_test.csv', 'idle', duration_sec=10)
generate_dummy_dataset('truk_test.csv', 'truk', duration_sec=10)
generate_dummy_dataset('kereta_test.csv', 'kereta', duration_sec=10)