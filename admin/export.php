<?php
// admin/export.php - Mesin Penarik & Ekspor Laporan Presensi CSV (Excel Compatible)
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

// Pastikan user sudah login dan berstatus admin
checkAuth('admin');

// Bersihkan buffer output agar file CSV bersih dari spasi liar/warning
if (ob_get_level()) {
    ob_end_clean();
}

$filename = "Rekap_Presensi_" . date('Y-m-d_His') . ".csv";

// Header HTTP untuk unduhan file CSV otomatis
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// 1. Tulis UTF-8 BOM agar simbol teks tidak berantakan di Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// 2. Tentukan pemisah kolom titik-koma (standar regional Excel Indonesia/Eropa)
fwrite($output, "sep=;\n");
$delimiter = ';';

// 3. Header Kolom Rekapitulasi
fputcsv($output, [
    'No',
    'Nama Pegawai',
    'Email Akun',
    'Tanggal Presensi',
    'Jam Masuk',
    'Jam Pulang',
    'Jarak Masuk',
    'Status Kehadiran'
], $delimiter);

// 4. Query gabungan data presensi dengan akun pegawai
$stmt = $pdo->query("
    SELECT 
        u.nama, 
        u.email, 
        p.tanggal, 
        p.jam_masuk, 
        p.jam_pulang, 
        p.jarak_masuk_meter, 
        p.status 
    FROM presensi p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.tanggal DESC, p.jam_masuk DESC
");

$no = 1;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Format jarak dengan tanda koma desimal
    $jarakTeks = number_format((float)($row['jarak_masuk_meter'] ?? 0), 1, ',', '') . ' m';

    // Normalisasi status kehadiran
    $statusRaw = strtolower(trim($row['status'] ?? ''));
    if (in_array($statusRaw, ['tepat waktu', 'hadir', 'tepat_waktu'])) {
        $statusLabel = 'Tepat Waktu';
    } elseif ($statusRaw === 'terlambat') {
        $statusLabel = 'Terlambat';
    } else {
        // Fallback otomatis jika status di DB belum terisi
        if (!empty($row['jam_masuk'])) {
            $statusLabel = (strtotime($row['jam_masuk']) > strtotime('08:00:00')) ? 'Terlambat' : 'Tepat Waktu';
        } else {
            $statusLabel = ucfirst($statusRaw ?: '-');
        }
    }

    fputcsv($output, [
        $no++,
        $row['nama'],
        $row['email'] ?? '-',
        date('d/m/Y', strtotime($row['tanggal'])),
        $row['jam_masuk'] ?? '-',
        $row['jam_pulang'] ?? '-',
        $jarakTeks,
        $statusLabel
    ], $delimiter);
}

fclose($output);
exit;