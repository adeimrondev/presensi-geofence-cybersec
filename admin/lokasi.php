<?php
// admin/lokasi.php - Pengaturan Titik Geofencing Kantor
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

// Wajib role admin
checkAuth('admin');

$successMsg = '';
$errorMsg   = '';

// 1. Ambil data lokasi aktif saat ini
$stmt = $pdo->query("SELECT * FROM settings_lokasi ORDER BY id ASC LIMIT 1");
$office = $stmt->fetch();

if (!$office) {
    $pdo->query("INSERT INTO settings_lokasi (nama_lokasi, latitude, longitude, radius_meter) 
                 VALUES ('FKOM UNIKU Kampus 2', -6.963280, 108.483980, 150)");
    $stmt = $pdo->query("SELECT * FROM settings_lokasi ORDER BY id ASC LIMIT 1");
    $office = $stmt->fetch();
}

// 2. Proses pembaruan koordinat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $namaLokasi  = trim($_POST['nama_lokasi'] ?? '');
    $latitude    = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude   = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $radiusMeter = filter_var($_POST['radius_meter'] ?? null, FILTER_VALIDATE_INT);

    if (empty($namaLokasi) || $latitude === false || $longitude === false || $radiusMeter === false || $radiusMeter <= 0) {
        $errorMsg = 'Seluruh data wajib diisi dengan format angka yang valid.';
    } else {
        try {
            $targetId = !empty($office['id']) ? (int)$office['id'] : 1;
            
            $update = $pdo->prepare("
                UPDATE settings_lokasi 
                SET nama_lokasi = :nama_lokasi, 
                    latitude = :lat, 
                    longitude = :lon, 
                    radius_meter = :radius 
                WHERE id = :target_id
            ");
            $update->execute([
                ':nama_lokasi' => $namaLokasi,
                ':lat'         => $latitude,
                ':lon'         => $longitude,
                ':radius'      => $radiusMeter,
                ':target_id'   => $targetId
            ]);

            if ($update->rowCount() === 0) {
                $fallbackUpdate = $pdo->prepare("
                    UPDATE settings_lokasi 
                    SET nama_lokasi = :nama_lokasi, 
                        latitude = :lat, 
                        longitude = :lon, 
                        radius_meter = :radius 
                    LIMIT 1
                ");
                $fallbackUpdate->execute([
                    ':nama_lokasi' => $namaLokasi,
                    ':lat'         => $latitude,
                    ':lon'         => $longitude,
                    ':radius'      => $radiusMeter
                ]);
            }

            $successMsg = 'Titik geofencing berhasil diperbarui!';
            
            // Refresh data terbaru langsung dari database
            $stmt = $pdo->query("SELECT * FROM settings_lokasi ORDER BY id ASC LIMIT 1");
            $office = $stmt->fetch();
        } catch (PDOException $e) {
            $errorMsg = 'Gagal menyimpan perubahan ke basis data: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurasi Geofencing - Panel Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
    .leaflet-pane {
        z-index: 10 !important;
    }

    .leaflet-bottom,
    .leaflet-top {
        z-index: 20 !important;
    }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen">
    <!-- Navbar Admin Modern Lengkap & Seragam -->
    <nav
        class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-6 py-3.5 sticky top-0 z-30 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-3">
            <div
                class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-lg shadow-emerald-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-sm font-bold text-white tracking-wide">Panel Administrator</h1>
                    <span
                        class="text-[10px] font-mono uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded-full font-semibold">Geofencing</span>
                </div>
                <p class="text-[11px] text-slate-400">Konfigurasi Radius & Pusat Koordinat Kantor</p>
            </div>
        </div>

        <!-- Pill Group Navigasi Lengkap 5 Menu -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <!-- Dashboard Rekap -->
            <a href="dashboard.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard Rekap</span>
            </a>

            <!-- Data Pegawai -->
            <a href="pegawai.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Data Pegawai</span>
            </a>

            <!-- Titik Geofencing (Status Aktif) -->
            <a href="lokasi.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-medium shadow-md shadow-emerald-600/30 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Titik Lokasi</span>
            </a>

            <!-- Ekspor CSV -->
            <a href="export.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-400 border border-emerald-500/20 shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Ekspor CSV</span>
            </a>

            <!-- Tombol Keluar dengan Pemicu Modal Modern -->
            <button type="button" onclick="openLogoutModal()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 transition cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Keluar</span>
            </button>
        </div>
    </nav>

    <!-- Lebar max-w-6xl Seragam -->
    <main class="max-w-6xl mx-auto p-4 md:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Pengaturan Geofencing Kantor</h2>
            <p class="text-xs text-slate-400 mt-1">
                Tentukan koordinat pusat presensi dan batas toleransi jarak meter. Geser marker atau klik langsung pada
                peta.
            </p>
        </div>

        <?php if (!empty($successMsg)): ?>
        <div id="alertMsg"
            class="p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-sm flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span><?= escape($successMsg); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
        <div id="alertMsg"
            class="p-4 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-sm flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= escape($errorMsg); ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Input Koordinat -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl lg:col-span-1 h-fit space-y-4">

                <!-- Tombol Pintasan FKOM UNIKU -->
                <button type="button" id="btnPresetUniku"
                    class="w-full py-2.5 px-3 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 rounded-xl text-xs font-medium flex items-center justify-center gap-2 transition cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span>Set ke FKOM UNIKU Kampus 2</span>
                </button>

                <form action="lokasi.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nama Lokasi / Kantor</label>
                        <input type="text" name="nama_lokasi" id="inputNama" required
                            value="<?= escape($office['nama_lokasi']); ?>"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-emerald-500 font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Latitude</label>
                            <input type="text" name="latitude" id="inputLat" required
                                value="<?= (float)$office['latitude']; ?>"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs font-mono text-emerald-400 focus:outline-none focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Longitude</label>
                            <input type="text" name="longitude" id="inputLon" required
                                value="<?= (float)$office['longitude']; ?>"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs font-mono text-emerald-400 focus:outline-none focus:border-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Batas Radius Validasi
                            (Meter)</label>
                        <div class="relative">
                            <input type="number" name="radius_meter" id="inputRadius" min="10" max="10000" required
                                value="<?= (int)$office['radius_meter']; ?>"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-amber-400 font-mono focus:outline-none focus:border-emerald-500">
                            <span class="absolute right-3.5 top-2.5 text-xs text-slate-500 font-medium">Meter</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1.5">Pegawai di luar radius ini ditolak otomatis saat
                            presensi.</p>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                            class="w-full py-3 px-4 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-medium rounded-xl shadow-lg shadow-emerald-600/30 transition duration-150 text-sm flex items-center justify-center gap-2 cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tampilan Peta Interaktif -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl lg:col-span-2 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium text-slate-400">Peta Penentuan Titik (Klik untuk pindah)</span>
                    <span id="radiusBadge"
                        class="text-[11px] text-emerald-400 font-mono bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                        Radius: <?= (int)$office['radius_meter']; ?>m
                    </span>
                </div>
                <div id="officeMap"
                    class="w-full h-[470px] rounded-xl border border-slate-800 bg-slate-950 overflow-hidden"></div>
            </div>
        </div>
    </main>

    <!-- Modal Konfirmasi Logout Modern Dark Slate -->
    <div id="logoutModal"
        class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div
            class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-center space-y-4">
            <div
                class="w-12 h-12 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </div>

            <div>
                <h3 class="text-base font-bold text-white tracking-wide">Konfirmasi Keluar</h3>
                <p class="text-xs text-slate-400 mt-1">Apakah Anda yakin ingin mengakhiri sesi dan keluar dari sistem?
                </p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="button" onclick="closeLogoutModal()"
                    class="flex-1 py-2.5 px-4 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-xl border border-slate-700/60 transition cursor-pointer">
                    Batal
                </button>
                <a href="../logout.php"
                    class="flex-1 py-2.5 px-4 bg-rose-600 hover:bg-rose-500 text-white text-xs font-medium rounded-xl shadow-lg shadow-rose-600/30 transition text-center cursor-pointer">
                    Ya, Keluar
                </a>
            </div>
        </div>
    </div>

    <script>
    const defaultLat = <?= (float)$office['latitude']; ?>;
    const defaultLon = <?= (float)$office['longitude']; ?>;
    let defaultRadius = <?= (int)$office['radius_meter']; ?>;

    const inputNama = document.getElementById('inputNama');
    const inputLat = document.getElementById('inputLat');
    const inputLon = document.getElementById('inputLon');
    const inputRadius = document.getElementById('inputRadius');
    const radiusBadge = document.getElementById('radiusBadge');
    const btnPresetUniku = document.getElementById('btnPresetUniku');

    // Inisialisasi Peta Leaflet
    const map = L.map('officeMap').setView([defaultLat, defaultLon], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Lingkaran Radius Geofencing (Hijau Transparan dengan Border Terang)
    let radiusCircle = L.circle([defaultLat, defaultLon], {
        color: '#10b981',
        weight: 2,
        fillColor: '#10b981',
        fillOpacity: 0.2,
        radius: defaultRadius
    }).addTo(map);

    // Marker Kantor
    let marker = L.marker([defaultLat, defaultLon], {
        draggable: true
    }).addTo(map);

    // Fungsi Generator Isi Balon Popup Informatif
    function generatePopupContent(nama, radiusVal, latVal, lonVal) {
        return `
            <div class="text-slate-800 text-xs space-y-1 p-0.5 min-w-[170px]">
                <div class="font-bold text-sm text-indigo-950 flex items-center gap-1">
                    🏢 ${nama || 'Titik Kantor'}
                </div>
                <div class="text-emerald-700 font-semibold flex items-center gap-1">
                    🎯 Batas Radius: <b>${radiusVal} meter</b>
                </div>
                <div class="font-mono text-[11px] text-slate-500 pt-0.5 border-t border-slate-200">
                    ${latVal.toFixed(6)}, ${lonVal.toFixed(6)}
                </div>
                <div class="text-[10px] text-amber-700 italic pt-0.5">
                    *Tarik pin atau klik peta untuk pindah
                </div>
            </div>
        `;
    }

    // Pasang popup dan langsung buka
    marker.bindPopup(generatePopupContent(inputNama.value, defaultRadius, defaultLat, defaultLon)).openPopup();

    // Auto-Fit agar lingkaran besar tetap terlihat di layar saat pertama kali buka
    setTimeout(() => {
        map.invalidateSize();
        if (defaultRadius > 500) {
            map.fitBounds(radiusCircle.getBounds().pad(0.1));
        }
    }, 200);

    function updateCoordinates(lat, lon) {
        inputLat.value = lat.toFixed(6);
        inputLon.value = lon.toFixed(6);

        marker.setLatLng([lat, lon]);
        radiusCircle.setLatLng([lat, lon]);

        const currentRad = parseInt(inputRadius.value) || defaultRadius;
        marker.setPopupContent(generatePopupContent(inputNama.value, currentRad, lat, lon)).openPopup();
    }

    marker.on('dragend', function(e) {
        const pos = marker.getLatLng();
        updateCoordinates(pos.lat, pos.lng);
    });

    map.on('click', function(e) {
        updateCoordinates(e.latlng.lat, e.latlng.lng);
    });

    // Update real-time saat angka radius diubah
    inputRadius.addEventListener('input', function() {
        const rad = parseInt(this.value) || 0;
        if (rad > 0) {
            radiusCircle.setRadius(rad);
            radiusBadge.textContent = `Radius: ${rad}m`;
            const pos = marker.getLatLng();
            marker.setPopupContent(generatePopupContent(inputNama.value, rad, pos.lat, pos.lng));

            // Otomatis menyesuaikan zoom jika radius dibuat sangat besar
            if (rad >= 1000) {
                map.fitBounds(radiusCircle.getBounds().pad(0.1));
            }
        }
    });

    // Update real-time saat nama lokasi diketik
    inputNama.addEventListener('input', function() {
        const rad = parseInt(inputRadius.value) || defaultRadius;
        const pos = marker.getLatLng();
        marker.setPopupContent(generatePopupContent(this.value, rad, pos.lat, pos.lng));
    });

    // Preset Cepat FKOM UNIKU Kampus 2
    btnPresetUniku.addEventListener('click', function() {
        const unikuLat = -6.963280;
        const unikuLon = 108.483980;

        inputNama.value = "FKOM UNIKU Kampus 2";
        inputRadius.value = 150;
        radiusBadge.textContent = "Radius: 150m";

        updateCoordinates(unikuLat, unikuLon);
        radiusCircle.setRadius(150);
        map.setView([unikuLat, unikuLon], 17);
    });

    function handleManualInput() {
        const lat = parseFloat(inputLat.value);
        const lon = parseFloat(inputLon.value);
        if (!isNaN(lat) && !isNaN(lon)) {
            updateCoordinates(lat, lon);
            map.panTo([lat, lon]);
        }
    }
    inputLat.addEventListener('change', handleManualInput);
    inputLon.addEventListener('change', handleManualInput);

    // Hilangkan alert notifikasi secara halus setelah 4 detik
    const alertMsg = document.getElementById('alertMsg');
    if (alertMsg) {
        setTimeout(() => {
            alertMsg.style.transition = 'opacity 0.5s ease';
            alertMsg.style.opacity = '0';
            setTimeout(() => alertMsg.remove(), 500);
        }, 4000);
    }

    // Kontrol Modal Logout Modern
    const logoutModal = document.getElementById('logoutModal');

    function openLogoutModal() {
        if (logoutModal) logoutModal.classList.remove('hidden');
    }

    function closeLogoutModal() {
        if (logoutModal) logoutModal.classList.add('hidden');
    }

    if (logoutModal) {
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) closeLogoutModal();
        });
    }

    // Tutup modal dengan tombol Escape
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && logoutModal && !logoutModal.classList.contains('hidden')) {
            closeLogoutModal();
        }
    });
    </script>
</body>

</html>