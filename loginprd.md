# PRODUCT REQUIREMENTS DOCUMENT (PRD)
# RailSafe – Modern Glassmorphism Login Page

**Project:** RailSafe – Sistem Pemantauan Perlintasan Kereta Api  
**Feature:** Login Page Redesign  
**Priority:** High  
**Type:** UI/UX Improvement & Frontend Implementation

---

## 1. PROJECT OVERVIEW

RailSafe merupakan sistem pemantauan perlintasan kereta api yang dirancang untuk membantu petugas memantau kondisi perangkat, aktivitas perlintasan, dan kejadian yang berkaitan dengan keselamatan perjalanan kereta api.

Halaman login merupakan halaman pertama yang diakses petugas sebelum masuk ke pusat kendali RailSafe.

Tujuan pengembangan ini adalah melakukan redesign halaman login agar terlihat lebih modern, profesional, futuristik, dan memiliki identitas visual yang kuat dengan menerapkan konsep **Modern Glassmorphism, Soft Blur, dan Railway Visualization**.

Desain harus tetap mempertahankan kemudahan penggunaan dan fungsi autentikasi yang sudah berjalan.

## 2. DESIGN REFERENCE

Gunakan screenshot halaman login RailSafe yang diberikan sebagai referensi desain awal dan mockup redesign sebagai acuan visual utama.

Karakteristik desain yang diinginkan:

- Modern dan minimalis.
- Dominasi warna navy blue dan electric blue.
- Efek glassmorphism dengan backdrop blur.
- Background menggunakan gradasi dan ornamen blur.
- Ilustrasi kereta menjadi elemen visual utama.
- Login card menggunakan transparansi dan efek frosted glass.
- Animasi kereta bergerak dari kanan ke kiri.

Jangan membuat desain yang terlalu ramai atau menggunakan terlalu banyak ornamen yang mengganggu fokus pengguna.

## 3. DESIGN SYSTEM

### 3.1 Color Palette

Gunakan palet warna berikut sebagai standar desain.

| Element | Color |
|---|---|
| Primary Navy | #102957 |
| Dark Navy | #0B1B3A |
| Primary Blue | #2563EB |
| Electric Blue | #3B82F6 |
| Light Blue | #DBEAFE |
| Accent Yellow | #FFC247 |
| White | #FFFFFF |
| Glass Background | rgba(255,255,255,0.16) |
| Glass Border | rgba(255,255,255,0.35) |

Gunakan warna kuning sebagai aksen untuk logo, highlight judul, dan elemen keselamatan perkeretaapian.

### 3.2 Typography

Gunakan font modern seperti:
- Inter
- Plus Jakarta Sans
- Poppins

Prioritaskan font yang sudah tersedia di project. Hindari menambahkan dependency baru apabila tidak diperlukan.

### 3.3 Visual Effects

Implementasikan:
- Glassmorphism.
- Backdrop blur.
- Soft shadow.
- Gradient background.
- Subtle glow.
- Smooth transition.
- Rounded corners.
- Layered background elements.

Hindari penggunaan efek berlebihan yang menyebabkan tampilan terlihat terlalu mencolok.

---

## 4. PAGE LAYOUT

Halaman login dibagi menjadi dua area visual utama.

### 4.1 Left Section – Hero & Railway Illustration

Area kiri berfungsi sebagai identitas visual RailSafe.

Komponen:

**A. Brand Identity**
- Logo RailSafe berada di kiri atas.
- Pertahankan identitas logo yang sudah digunakan.
- Ukuran logo harus proporsional.

**B. Hero Text**

Gunakan konten berikut:

Eyebrow:
"SISTEM PEMANTAUAN PERLINTASAN"

Main Heading:
"Masuk ke pusat kendali."

Highlight kata "kendali." menggunakan warna kuning.

Description:
"Pantau dan kelola perlintasan kereta api dengan lebih aman, cepat, dan efisien."

**C. Feature Highlights**

Tampilkan tiga fitur unggulan menggunakan icon:

1. Keamanan Terjamin.
2. Monitoring Real-Time.
3. Data Akurat.

Gunakan icon yang relevan seperti Shield Check, Zap, dan Chart Bar.

**D. Railway Illustration**

Ilustrasi kereta menjadi elemen visual paling menonjol di halaman login.

Ketentuan:
- Letakkan ilustrasi pada bagian bawah hingga tengah area kiri.
- Kereta harus menghadap ke kiri.
- Kereta harus terlihat seperti bergerak memasuki area perlintasan dari arah kanan menuju kiri.
- Gunakan perspektif yang natural.
- Ilustrasi harus menyatu dengan background.
- Tambahkan efek pencahayaan atau motion blur secara halus.
- Jangan sampai ilustrasi menutupi judul atau feature highlights.

PENTING:
Gunakan aset gambar yang sudah tersedia di project apabila relevan. Jika belum tersedia, cari aset ilustrasi kereta yang sesuai atau gunakan aset lokal baru yang disiapkan untuk project.

Jangan menggunakan emoji sebagai pengganti ilustrasi kereta.

**E. Footer**

Tampilkan:
"RailSafe © 2025"

Letakkan di kiri bawah dengan ukuran font kecil dan warna putih transparan.

### 4.2 Right Section – Login Card

Login card berada di sisi kanan halaman dan menjadi fokus interaksi pengguna.

Gunakan konsep Glassmorphism.

CSS visual yang diharapkan:

- Background putih transparan.
- Backdrop blur sekitar 20–30px.
- Border putih transparan.
- Border radius sekitar 20–24px.
- Shadow lembut.
- Padding yang cukup.
- Lebar card sekitar 400–450px pada desktop, disesuaikan dengan proporsi halaman.

Komponen login:

**A. Card Header**

Badge:
"Akses Khusus"

Heading:
"Selamat datang kembali!"

Description:
"Masuk untuk melanjutkan ke sistem."

**B. Email Input**
- Label: Email.
- Placeholder: nama@contoh.id.
- Icon email.
- Input memiliki background putih transparan.
- Border tipis dan rounded.

**C. Password Input**
- Label: Kata Sandi.
- Placeholder: Masukkan kata sandi.
- Icon lock.
- Tambahkan tombol show/hide password.
- Password harus tersembunyi secara default.

**D. Login Button**

Text:
"Masuk ke Dashboard"

Ketentuan:
- Lebar mengikuti card.
- Background gradient biru.
- Rounded corners.
- Hover effect.
- Active state.
- Loading state apabila proses autentikasi berlangsung.

**E. Additional Information**

Tampilkan teks kecil:
"Akun dikelola oleh administrator RailSafe."

Jangan menambahkan fitur registrasi apabila sebelumnya tidak tersedia.

---

## 5. TRAIN ANIMATION

Ini merupakan salah satu elemen utama redesign.

Buat animasi kereta yang bergerak dari kanan menuju kiri, seperti kereta sedang memasuki area perlintasan.

### Animation Requirements

1. Kereta awalnya berada sedikit di luar area visual sebelah kanan.
2. Kereta bergerak secara horizontal ke kiri.
3. Gerakan harus smooth menggunakan CSS animation atau teknologi animasi yang sudah tersedia.
4. Gunakan easing yang natural.
5. Animasi hanya berjalan pada area ilustrasi dan tidak mengganggu komponen login.
6. Setelah mencapai posisi akhir, kereta berhenti pada posisi yang sudah ditentukan.
7. Hindari animasi yang terus-menerus mengulang apabila menyebabkan gangguan visual.

Contoh konsep CSS:

```css
@keyframes trainEntering {
    from {
        transform: translateX(250px);
        opacity: 0;
    }

    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.train-illustration {
    animation: trainEntering 3s ease-out forwards;
}
```

Sesuaikan nilai transform dengan ukuran dan posisi aktual ilustrasi.

Tambahkan dukungan `prefers-reduced-motion` agar pengguna yang mengurangi animasi sistem tetap mendapatkan pengalaman yang nyaman.

---

## 6. BACKGROUND DESIGN

Background harus memiliki kedalaman visual.

Implementasikan beberapa layer:

1. Base gradient navy dan blue.
2. Radial gradient sebagai sumber pencahayaan.
3. Ornamen lingkaran transparan.
4. Blur shapes pada beberapa sudut halaman.
5. Soft glow di sekitar area ilustrasi.
6. Railway crossing silhouette atau elemen perlintasan sebagai dekorasi tambahan apabila sesuai.

Gunakan CSS pseudo-elements jika memungkinkan agar tidak perlu menambahkan terlalu banyak aset gambar.

Pastikan background tidak mengganggu keterbacaan teks.

---

## 7. RESPONSIVE DESIGN

Halaman wajib mendukung berbagai ukuran layar.

### Desktop (>= 1024px)
- Layout dua kolom.
- Hero section di kiri.
- Login card di kanan.
- Ilustrasi kereta terlihat jelas.
- Proporsi layout seimbang.

### Tablet (768px–1023px)
- Sesuaikan ukuran kedua area.
- Kurangi ukuran ilustrasi apabila diperlukan.
- Login card tetap mudah diakses.
- Hindari tabrakan elemen.

### Mobile (< 768px)
- Ubah layout menjadi satu kolom.
- Login card menjadi fokus utama.
- Hero section tetap menampilkan logo dan judul.
- Ilustrasi kereta diperkecil dan ditempatkan sebagai elemen dekoratif.
- Tidak boleh terjadi horizontal scrolling.
- Input dan tombol harus mudah digunakan dengan sentuhan.

---

## 8. FUNCTIONAL REQUIREMENTS

Redesign ini hanya berfokus pada peningkatan UI/UX.

WAJIB mempertahankan seluruh fungsi yang sudah ada.

Ketentuan:

1. Autentikasi tetap menggunakan sistem backend yang sekarang.
2. Form login tetap mengirimkan email dan password sesuai mekanisme yang sudah diterapkan.
3. Validasi input tetap berjalan.
4. Pesan error login tetap ditampilkan.
5. Session dan redirect setelah login tidak boleh berubah.
6. CSRF protection tetap aktif.
7. Middleware dan authorization tidak boleh diubah.
8. Tombol show/hide password harus berfungsi.
9. Loading state harus muncul ketika proses login sedang berlangsung.
10. Jangan mengubah struktur database, model, migration, atau mekanisme autentikasi.

Jangan membuat sistem login baru.

---

## 9. TECHNICAL IMPLEMENTATION

Sebelum melakukan perubahan, Codex harus:

1. Memeriksa struktur project.
2. Mengidentifikasi framework dan versi yang digunakan.
3. Menemukan halaman login yang sedang digunakan.
4. Mengidentifikasi file CSS, JavaScript, dan aset gambar.
5. Memahami mekanisme autentikasi yang sudah berjalan.
6. Mengidentifikasi komponen layout yang bisa digunakan kembali.

Ketentuan implementasi:

- Ikuti arsitektur project yang sudah ada.
- Jangan mengganti framework atau struktur project.
- Gunakan CSS modular apabila sesuai dengan struktur project.
- Hindari dependency tambahan yang tidak diperlukan.
- Gunakan aset lokal untuk ilustrasi apabila tersedia.
- Optimalkan ukuran gambar.
- Hindari inline CSS berlebihan.
- Pastikan tidak ada konflik dengan stylesheet global.
- Jangan menghapus kode atau komponen yang masih digunakan.

Jika project menggunakan Laravel Blade, pertahankan penggunaan Blade, route, CSRF, dan mekanisme autentikasi yang sudah tersedia.

---

## 10. PERFORMANCE & ACCESSIBILITY

Pastikan:

- Animasi tidak menyebabkan lag.
- Gunakan transform dan opacity untuk animasi.
- Gambar memiliki ukuran optimal.
- Kontras teks tetap terbaca.
- Semua input mempunyai label.
- Tombol dapat diakses menggunakan keyboard.
- Focus state terlihat jelas.
- Tambahkan alt text pada ilustrasi kereta.
- Glassmorphism tetap memiliki fallback apabila backdrop-filter tidak didukung browser.
- Layout tidak mengalami pergeseran yang mengganggu saat halaman dimuat.

---

## 11. ACCEPTANCE CRITERIA

Implementasi dianggap selesai apabila memenuhi seluruh kriteria berikut:

- [ ] Desain login sudah mengikuti konsep modern glassmorphism.
- [ ] Warna sesuai identitas RailSafe.
- [ ] Logo RailSafe tetap tampil.
- [ ] Hero heading dan deskripsi tampil dengan benar.
- [ ] Ilustrasi kereta menghadap kiri.
- [ ] Animasi kereta bergerak dari kanan ke kiri.
- [ ] Ilustrasi tidak bertabrakan dengan teks.
- [ ] Login card menggunakan efek frosted glass.
- [ ] Input email dan password berfungsi.
- [ ] Show/hide password berfungsi.
- [ ] Login tetap terhubung ke backend yang lama.
- [ ] Validasi dan pesan error tetap berjalan.
- [ ] Loading state berfungsi.
- [ ] Layout responsif desktop, tablet, dan mobile.
- [ ] Tidak ada horizontal scrolling.
- [ ] Tidak ada error JavaScript atau console error baru.
- [ ] Tidak ada fitur autentikasi yang rusak.

---

## 12. FINAL INSTRUCTION FOR CODEX AGENT

Implementasikan redesign halaman login RailSafe berdasarkan seluruh spesifikasi di atas.

Prioritaskan kualitas visual, keseimbangan layout, dan konsistensi desain.

Perhatian khusus pada ilustrasi kereta: jangan sekadar menempatkan gambar di halaman. Atur ukuran, posisi, perspektif, dan animasinya agar terlihat seperti kereta yang bergerak masuk dari arah kanan menuju kiri.

Gunakan pendekatan visual yang profesional, modern, bersih, dan sesuai dengan sistem pemantauan keselamatan perlintasan kereta api.

Jangan hanya memberikan contoh kode atau tutorial. Lakukan perubahan langsung pada file project yang relevan.

Setelah implementasi:
1. Jelaskan file apa saja yang diubah.
2. Jelaskan perubahan desain yang dilakukan.
3. Jelaskan bagaimana animasi kereta bekerja.
4. Jalankan pemeriksaan atau build yang relevan.
5. Laporkan apabila ada fitur yang belum berhasil diimplementasikan.

**IMPORTANT: UI/UX REDESIGN ONLY. Preserve all existing authentication logic, backend functionality, routes, database structures, and security mechanisms.**