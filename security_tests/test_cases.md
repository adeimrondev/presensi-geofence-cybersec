# Dokumen Pengujian Keamanan (Security Test Cases)

**Sistem Presensi Berbasis Geofencing & Anti-Spoofing**

Dokumen ini memuat skenario pengujian fungsional dan keamanan sistem presensi untuk membuktikan ketahanan aplikasi terhadap manipulasi lokasi, bypassing autentikasi, serta eksploitasi web.

---

## 1. Ringkasan Hasil Pengujian

| Kategori Pengujian                   | Total Skenario | Lolos (Passed) | Gagal (Failed) |   Status   |
| ------------------------------------ | :------------: | :------------: | :------------: | :--------: |
| Geofencing & Anti-Spoofing           |       2        |       2        |       0        | **PASSED** |
| Autentikasi & Otorisasi (RBAC)       |       3        |       3        |       0        | **PASSED** |
| SQL Injection (SQLi)                 |       2        |       2        |       0        | **PASSED** |
| Cross-Site Request Forgery (CSRF)    |       1        |       1        |       0        | **PASSED** |
| Direct Script Execution (`uploads/`) |       2        |       2        |       0        | **PASSED** |
| Manipulasi Bukti Foto (Watermarking) |       1        |       1        |       0        | **PASSED** |

---

## 2. Rincian Skenario Pengujian

### TC-01: Validasi Jarak Geofencing (Dalam Radius Kantor)

- **Target Uji**: Endpoint Presensi Pegawai (`pegawai/presensi.php`).
- **Metode**: Pengguna melakukan submit absensi masuk dengan posisi GPS berada di dalam radius toleransi kantor (100 meter dari koordinat target).
- **Hasil yang Diharapkan**: Sistem menerima data presensi, menghitung jarak akurat (Haversine Formula), dan menyimpan catatan ke basis data.
- **Hasil Aktual**: Presensi berhasil disimpan dengan status kehadiran dan jarak tertera akurat.
- **Status**: **PASSED**

---

### TC-02: Penolakan Presensi di Luar Radius (Anti-Spoofing Radius)

- **Target Uji**: Endpoint Presensi Pegawai (`pegawai/presensi.php`).
- **Metode**: Pengguna mencoba melakukan presensi saat berada di luar radius kantor yang telah ditetapkan administrator.
- **Hasil yang Diharapkan**: Sistem menolak presensi dengan notifikasi peringatan dan tidak menyimpan data kehadiran ke tabel presensi.
- **Hasil Aktual**: Muncul pesan error penolakan lokasi di luar jangkauan kantor.
- **Status**: **PASSED**

---

### TC-03: Pencegahan Akses Halaman Admin oleh Pegawai (RBAC Bypassing)

- **Target Uji**: `admin/dashboard.php`, `admin/pegawai.php`, `admin/lokasi.php`.
- **Metode**: Pengguna login menggunakan kredensial akun peran `pegawai`, kemudian mengakses URL panel administrator secara langsung melalui address bar browser.
- **Hasil yang Diharapkan**: Sesi mendeteksi peran pengguna bukan `admin` dan langsung me-redirect ke antarmuka pegawai tanpa membocorkan data admin.
- **Hasil Aktual**: Pengguna dialihkan kembali ke rute yang sesuai dengan hak aksesnya.
- **Status**: **PASSED**

---

### TC-04: Ketahanan terhadap SQL Injection pada Form Autentikasi

- **Target Uji**: Form Login (`login.php`).
- **Payload Uji**:
  ```sql
  ' OR '1'='1' --
  admin@cybersec.local' #
  ```
