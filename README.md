# Web Presensi Geofencing & Anti-Spoofing (Cybersecurity Approach)

Sistem presensi pegawai berbasis web modern yang mengintegrasikan validasi lokasi presisi tinggi (_Geofencing_ via Formula Haversine) dan bukti kehadiran visual (_Webcam Swafoto_). Aplikasi dirancang dengan prinsip pengamanan berlapis untuk mencegah manipulasi data kehadiran dan pemalsuan lokasi (_GPS spoofing_).

---

## 🚀 Fitur Utama

### 1. Keamanan & Autentikasi (Cybersecurity)

- **Role-Based Access Control (RBAC):** Pemisahan hak akses yang ketat antara peran `admin` dan `pegawai` (Session-hardened).
- **Proteksi CSRF:** Menggunakan token berbasis `random_bytes` dengan validasi `hash_equals` pada setiap request formulir sensitif.
- **Prepared Statements (PDO):** Seluruh query database terlindungi dari ancaman SQL Injection (`ATTR_EMULATE_PREPARES => false`).
- **XSS Sanitization:** Pembersihan input dan output menggunakan fungsi pembungkus `htmlspecialchars` (`ENT_QUOTES`).
- **Upload Directory Hardening:** Proteksi file `.htaccess` di folder `uploads/` untuk mencegah eksekusi skrip berbahaya (_Remote Code Execution_).

### 2. Validasi Geofencing (Formula Haversine)

- Perhitungan jarak matematis (_great-circle distance_) dilakukan di sisi server (Backend PHP), bukan di sisi peramban klien.
- Menolak pengiriman data koordinat jika jarak pegawai melampaui batas radius kantor (default: 100 meter dari titik target).
- Dilengkapi peta interaktif visual berbasis Leaflet.js & OpenStreetMap.

### 3. Presensi Otomatis & Swafoto Ber-watermark

- **Capture Real-Time:** Pengambilan foto instan melalui WebRTC API peramban saat tombol ditekan.
- **Watermarking Visual (GD Library):** Penyematan metadata visual permanen pada foto (nama sistem, tanggal, jam presisi hingga detik, dan koordinat lintang/bujur).
- **Status Kehadiran Otomatis:**
  - Datang $\le$ 08:00:00 WIB $\rightarrow$ **Tepat Waktu**
  - Datang $>$ 08:00:00 WIB $\rightarrow$ **Terlambat**
- **Satu Hari Satu Catatan:** Presensi pertama otomatis dicatat sebagai jam masuk; presensi berikutnya pada tanggal yang sama otomatis memperbarui jam pulang.

---

## 🛠️ Tumpukan Teknologi (Tech Stack)

- **Bahasa Pemrograman:** PHP 8.x (GD Library & PDO Aktif)
- **Akses Database:** PHP Data Objects (PDO) - MySQL / MariaDB
- **Desain UI:** Tailwind CSS (CDN)
- **Peta Interaktif:** Leaflet.js & OpenStreetMap
- **Klien API:** Browser Geolocation API & WebRTC MediaDevices API
- **Lingkungan Server:** Apache (XAMPP)

---

## 📂 Struktur Direktori Proyek

```text
web-presensi-cybersec/
│
├── admin/
│   ├── dashboard.php        # Ringkasan data presensi pegawai & log riwayat
│   ├── pegawai.php          # Manajemen data akun & master pegawai (CRUD)
│   ├── export.php           # Cetak & ekspor rekapitulasi data ke format CSV
│   └── lokasi.php           # Pengaturan koordinat titik pusat & radius kantor
│
├── pegawai/
│   ├── presensi.php         # Antarmuka presensi pegawai (Webcam & Leaflet Map)
│   └── riwayat.php          # Catatan kehadiran individual pegawai
│
├── config/
│   └── database.php         # Koneksi aman PDO, pembacaan .env, & session hardening
│
├── database/
│   └── schema.sql           # Skema DDL tabel users, presensi, & pengaturan_lokasi
│
├── includes/
│   ├── haversine.php        # Algoritma perhitungan radius Haversine
│   └── helpers.php          # Helper autentikasi, proteksi CSRF, & sanitasi input
│
├── security_tests/
│   └── test_cases.md        # Dokumen laporan skenario pengujian keamanan sistem
│
├── uploads/                 # Direktori penyimpanan berkas swafoto (.jpg)
│   └── .htaccess            # Aturan pencegahan eksekusi skrip di folder uploads
│
├── .env                     # Kredensial lingkungan lokal (diabaikan oleh git)
├── .env.example             # Contoh template konfigurasi lingkungan
├── .gitignore               # Konfigurasi pengabaian berkas sensitif git
├── login.php                # Gerbang autentikasi terpadu (Admin & Pegawai)
├── logout.php               # Penghancuran sesi login aman
└── README.md                # Dokumentasi proyek
```
