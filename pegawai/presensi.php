<?php
// pegawai/presensi.php - Presensi Geofencing & Swafoto dengan Peta Leaflet.js
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/haversine.php';

// Proteksi autentikasi umum (memastikan user sudah login)
checkAuth();

$userId = $_SESSION['user_id'] ?? null;
$today  = date('Y-m-d');

// Cek apakah pegawai sudah presensi hari ini
$stmt = $pdo->prepare("SELECT * FROM presensi WHERE user_id = :user_id AND tanggal = :tanggal LIMIT 1");
$stmt->execute([':user_id' => $userId, ':tanggal' => $today]);
$existingAttendance = $stmt->fetch();

// Status penyelesaian presensi harian
$isCompleted = !empty($existingAttendance['jam_masuk']) && !empty($existingAttendance['jam_pulang']);

// Ambil konfigurasi lokasi kantor aktif dari database
$locStmt = $pdo->query("SELECT * FROM settings_lokasi ORDER BY id ASC LIMIT 1");
$office = $locStmt->fetch();

if (!$office) {
    die("Titik lokasi kantor belum dikonfigurasi oleh administrator.");
}

// Penanganan pengiriman presensi via AJAX JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    // Kunci jika presensi hari ini sudah komplit (masuk & pulang)
    if ($isCompleted) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Anda sudah menyelesaikan seluruh presensi (masuk & pulang) untuk hari ini.'
        ]);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $payload  = json_decode($rawInput, true);

    if (!$payload) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format request data tidak valid.']);
        exit;
    }

    $latitude    = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude   = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $accuracy    = filter_var($payload['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
    $imageBase64 = $payload['image'] ?? '';

    if ($latitude === false || $longitude === false || empty($imageBase64)) {  
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Koordinat GPS dan foto selfie wajib dikirimkan.']);
        exit;
    }

    // Proteksi Anomali Mock / Fake GPS
    if ($accuracy !== false && $accuracy !== null && ($accuracy <= 0 || $accuracy > 300)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Terdeteksi anomali sinyal GPS (Akurasi: {$accuracy}m). Matikan mock location / pastikan GPS akurat."
        ]);
        exit;
    }

    // 1. Validasi Haversine di Server (Anti-Spoofing Core)
    $distance = calculateHaversineDistance(
        $latitude,
        $longitude,
        (float)$office['latitude'],
        (float)$office['longitude']
    );

    if ($distance > (float)$office['radius_meter']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Presensi ditolak. Anda berada di luar radius kantor! Jarak Anda: " . number_format($distance, 1) . " m (Maksimal: {$office['radius_meter']} m)."
        ]);
        exit;
    }

    // 2. Simpan Bukti Foto Aman ke folder uploads root
    $uploadDir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Dekode base64 foto
    if (preg_match('/^data:image\/(\w+);base64,/', $imageBase64, $type)) {
        $data = substr($imageBase64, strpos($imageBase64, ',') + 1);
        $data = base64_decode($data);
        if ($data === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Gagal membaca format gambar.']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format gambar tidak dikenali.']);
        exit;
    }

    $fileName = 'absen_' . $userId . '_' . time() . '.jpg';
    $filePath = $uploadDir . $fileName;
    file_put_contents($filePath, $data);

    // 3. Pencatatan Waktu Presensi
    $nowTime       = date('H:i:s');
    $jamMasukBatas = '08:00:00'; // Batas toleransi presensi masuk tepat waktu

    try {
        if ($existingAttendance) {
            // Update jam pulang sekali saja
            $update = $pdo->prepare("UPDATE presensi SET jam_pulang = :jam_pulang WHERE id = :id");
            $update->execute([
                ':jam_pulang' => $nowTime,
                ':id'         => $existingAttendance['id']
            ]);
            echo json_encode(['success' => true, 'message' => 'Presensi pulang berhasil dicatat pada pukul ' . $nowTime]);
        } else {
            // Tentukan status kedatangan otomatis berdasarkan jam batas masuk
            $status = (strtotime($nowTime) > strtotime($jamMasukBatas)) ? 'terlambat' : 'tepat waktu';

            // Insert presensi masuk
            $insert = $pdo->prepare("INSERT INTO presensi 
                (user_id, tanggal, jam_masuk, latitude_masuk, longitude_masuk, jarak_masuk_meter, status, foto)
                VALUES (:user_id, :tanggal, :jam_masuk, :latitude, :longitude, :jarak, :status, :foto)");
            $insert->execute([
                ':user_id'   => $userId,
                ':tanggal'   => $today,
                ':jam_masuk' => $nowTime,
                ':latitude'  => $latitude,
                ':longitude' => $longitude,
                ':jarak'     => round($distance, 2),
                ':status'    => $status,
                ':foto'      => $fileName
            ]);
            echo json_encode(['success' => true, 'message' => "Presensi masuk berhasil! Status: " . ucfirst($status)]);
        }
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem database.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Pegawai - Geofencing Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Leaflet CSS & JS untuk Peta OpenStreetMap -->
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
    <!-- Navbar Pegawai Seragam -->
    <nav
        class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-6 py-3.5 sticky top-0 z-30 flex justify-between items-center">
        <div>
            <h1 class="text-sm font-bold text-white tracking-wide">Presensi Geofencing</h1>
            <p class="text-[11px] text-slate-400">Selamat bertugas, <span
                    class="text-indigo-400 font-medium"><?= escape($_SESSION['nama'] ?? 'Pegawai'); ?></span></p>
        </div>
        <div class="flex items-center gap-2.5 text-xs">
            <a href="history.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Riwayat Saya</span>
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

    <main class="max-w-4xl mx-auto p-4 md:p-8 space-y-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
            <div>
                <h2 class="text-xl font-bold text-white tracking-tight">Verifikasi Lokasi & Swafoto</h2>
                <p class="text-xs text-slate-400 mt-1">
                    Pastikan wajah Anda tampak jelas di kamera dan izin GPS peramban aktif.
                </p>
            </div>

            <!-- Alert Notifikasi Status Presensi Selesai / Pesan Sistem -->
            <?php if ($isCompleted): ?>
            <div
                class="p-4 rounded-xl text-xs bg-emerald-500/10 text-emerald-300 border border-emerald-500/30 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-400 shrink-0" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Anda telah menyelesaikan seluruh rangkaian presensi (masuk pukul <b
                        class="font-mono"><?= escape($existingAttendance['jam_masuk']); ?></b> dan pulang pukul <b
                        class="font-mono"><?= escape($existingAttendance['jam_pulang']); ?></b>) untuk hari ini.</span>
            </div>
            <?php endif; ?>

            <div id="statusAlert" class="hidden p-4 rounded-xl text-xs flex items-center gap-2"></div>

            <div class="space-y-5">
                <!-- Grid Kamera & Peta Sejajar -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Kamera Webcam (Tampilan Efek Mirror Alami) -->
                    <div class="space-y-1.5">
                        <span class="text-xs font-medium text-slate-400">Kamera Wajah Real-Time</span>
                        <div
                            class="relative rounded-xl overflow-hidden bg-slate-950 aspect-video border border-slate-800 flex items-center justify-center">
                            <video id="webcam" autoplay playsinline
                                class="w-full h-full object-cover -scale-x-100"></video>
                            <canvas id="snapshotCanvas" class="hidden"></canvas>
                        </div>
                    </div>

                    <!-- Peta Geofencing Leaflet -->
                    <div class="space-y-1.5">
                        <span class="text-xs font-medium text-slate-400">Radius Kantor & Posisi Anda</span>
                        <div id="map"
                            class="w-full aspect-video rounded-xl border border-slate-800 bg-slate-950 flex items-center justify-center text-xs text-slate-500">
                            Menyiapkan peta...
                        </div>
                    </div>
                </div>

                <!-- Info Status Geofencing -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Pusat Kantor:</span>
                        <span class="font-mono text-slate-200"><?= escape($office['nama_lokasi']); ?> (Radius
                            <?= escape($office['radius_meter']); ?>m)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Koordinat Anda:</span>
                        <span id="coordsDisplay" class="font-mono text-amber-400">Mendeteksi GPS...</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-800/80 pt-2">
                        <span class="text-slate-400">Jadwal Masuk:</span>
                        <span class="font-mono text-emerald-400">Maks. 08:00:00 WIB</span>
                    </div>
                </div>

                <!-- Tombol Submit Dinamis & Terkunci Otomatis -->
                <button id="btnSubmit" <?= $isCompleted ? 'disabled' : ''; ?>
                    class="w-full py-3.5 px-4 <?= $isCompleted ? 'bg-slate-800 text-slate-500 cursor-not-allowed border border-slate-700/60' : 'bg-indigo-600 hover:bg-indigo-500 disabled:bg-slate-800 disabled:text-slate-500 text-white shadow-lg shadow-indigo-600/30 cursor-pointer'; ?> font-medium rounded-xl transition duration-150">
                    <?php 
                        if ($isCompleted) {
                            echo '✓ Presensi Hari Ini Telah Selesai';
                        } elseif ($existingAttendance) {
                            echo 'Rekam Presensi Pulang';
                        } else {
                            echo 'Rekam Presensi Masuk';
                        }
                    ?>
                </button>
            </div>
        </div>
    </main>

    <!-- Modal Konfirmasi Logout Bergaya Modern Dark Slate -->
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
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('snapshotCanvas');
    const btnSubmit = document.getElementById('btnSubmit');
    const coordsDisplay = document.getElementById('coordsDisplay');
    const statusAlert = document.getElementById('statusAlert');
    const logoutModal = document.getElementById('logoutModal');
    const isAlreadyCompleted = <?= $isCompleted ? 'true' : 'false'; ?>;

    // Kontrol Modal Logout
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
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && logoutModal && !logoutModal.classList.contains('hidden')) {
            closeLogoutModal();
        }
    });

    // Koordinat Kantor dari PHP
    const officeLat = <?= (float)$office['latitude']; ?>;
    const officeLon = <?= (float)$office['longitude']; ?>;
    const officeRadius = <?= (float)$office['radius_meter']; ?>;
    const officeName = "<?= escape($office['nama_lokasi']); ?>";

    let userLat = null;
    let userLon = null;
    let userAccuracy = null;
    let map = null;
    let userMarker = null;

    // 1. Inisialisasi Kamera Webcam
    navigator.mediaDevices.getUserMedia({
            video: {
                width: 640,
                height: 480
            },
            audio: false
        })
        .then(stream => {
            video.srcObject = stream;
        })
        .catch(err => {
            showAlert('Tidak dapat mengakses kamera perangkat. Harap izinkan akses webcam.', 'error');
        });

    // 2. Inisialisasi & Render Peta Leaflet dengan Penanda Posisi Jelas & Anti-Blank
    function initMap(lat, lon) {
        document.getElementById('map').innerHTML = '';

        // Tetapkan koordinat awal dan level zoom langsung saat inisialisasi
        map = L.map('map').setView([lat, lon], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        // Lingkaran Radius Kantor (Hijau Transparan)
        const officeCircle = L.circle([officeLat, officeLon], {
            color: '#10b981',
            fillColor: '#10b981',
            fillOpacity: 0.2,
            radius: officeRadius
        }).addTo(map).bindPopup(`<b>🏢 ${officeName}</b><br>Radius: ${officeRadius} meter`);

        // Marker Titik Kantor
        const officeMarker = L.marker([officeLat, officeLon]).addTo(map)
            .bindPopup(`<b>🏢 Titik Pusat Kantor</b><br>${officeName}`);

        // Lingkaran Akurasi Posisi Pegawai (Biru Muda)
        const accuracyRadius = (userAccuracy && userAccuracy > 0) ? userAccuracy : 15;
        L.circle([lat, lon], {
            color: '#38bdf8',
            fillColor: '#38bdf8',
            fillOpacity: 0.25,
            radius: accuracyRadius,
            weight: 1
        }).addTo(map);

        // Marker Khusus Posisi Pegawai dengan Popup Otomatis Terbuka
        userMarker = L.marker([lat, lon]).addTo(map)
            .bindPopup(`<b>📍 Posisi Anda Sekarang</b><br>Akurasi: ±${Math.round(accuracyRadius)}m`)
            .openPopup();

        // Garis penghubung titik user ke kantor
        L.polyline([
            [lat, lon],
            [officeLat, officeLon]
        ], {
            color: '#6366f1',
            weight: 2.5,
            dashArray: '5, 8'
        }).addTo(map);

        // Refresh ukuran kontainer Leaflet dan sesuaikan cakupan sudut pandang (fit bounds)
        setTimeout(() => {
            map.invalidateSize();
            try {
                const groupBounds = L.featureGroup([officeMarker, userMarker, officeCircle]);
                map.fitBounds(groupBounds.getBounds().pad(0.2));
            } catch (e) {
                map.setView([lat, lon], 16);
            }
        }, 200);
    }

    // 3. Ambil Geolocation Browser & Validasi Keaslian Akurasi
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(
            pos => {
                userLat = pos.coords.latitude;
                userLon = pos.coords.longitude;
                userAccuracy = pos.coords.accuracy;

                coordsDisplay.textContent =
                    `${userLat.toFixed(6)}, ${userLon.toFixed(6)} (Akurasi: ±${Math.round(userAccuracy)}m)`;

                // Deteksi anomali GPS
                if (userAccuracy <= 0 || userAccuracy > 300) {
                    showAlert(
                        `Akurasi sinyal GPS meragukan (±${Math.round(userAccuracy)}m). Matikan mock location untuk presensi.`,
                        'error');
                    btnSubmit.disabled = true;
                } else {
                    // Tombol hanya diaktifkan jika hari ini memang belum selesai
                    if (!isAlreadyCompleted) {
                        btnSubmit.disabled = false;
                    }
                }

                initMap(userLat, userLon);
            },
            err => {
                coordsDisplay.textContent = 'Izin GPS Ditolak / Tidak Aktif';
                showAlert('Akses lokasi diperlukan untuk validasi geofencing.', 'error');
                initMap(officeLat, officeLon);
            }, {
                enableHighAccuracy: true,
                timeout: 10000
            }
        );
    } else {
        coordsDisplay.textContent = 'Peramban tidak mendukung Geolocation.';
    }

    function showAlert(msg, type) {
        statusAlert.className = 'p-4 rounded-xl text-xs flex items-center gap-2';
        if (type === 'error') {
            statusAlert.classList.add('bg-rose-500/10', 'text-rose-300', 'border', 'border-rose-500/30');
        } else {
            statusAlert.classList.add('bg-emerald-500/10', 'text-emerald-300', 'border', 'border-emerald-500/30');
        }
        statusAlert.textContent = msg;
    }

    // 4. Tangani Pengiriman Presensi dengan Watermark Otomatis & Mirror Jepretan
    btnSubmit.addEventListener('click', async () => {
        if (isAlreadyCompleted) return;

        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Memverifikasi Presensi...';

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');

        // Ambil frame kamera dengan posisi mirror horizontal
        ctx.save();
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        ctx.restore(); // Kembalikan koordinat normal agar watermark tidak terbalik

        // Sematkan Stempel Keamanan / Watermark Real-Time
        const now = new Date();
        const dateStr = now.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('id-ID');
        const locStr = (userLat && userLon) ? `${userLat.toFixed(5)}, ${userLon.toFixed(5)}` : 'GPS Aktif';

        // Background pita watermark semi-transparan (tinggi 32px di bawah)
        ctx.fillStyle = 'rgba(15, 23, 42, 0.75)';
        ctx.fillRect(0, canvas.height - 32, canvas.width, 32);

        // Teks watermark (terbaca normal dari kiri ke kanan)
        ctx.fillStyle = '#ffffff';
        ctx.font = '12px monospace';
        ctx.fillText(`GEO-PRESENSI | ${dateStr} ${timeStr} | ${locStr}`, 12, canvas.height - 11);

        const imageBase64 = canvas.toDataURL('image/jpeg', 0.85);

        try {
            const response = await fetch('presensi.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    latitude: userLat,
                    longitude: userLon,
                    accuracy: userAccuracy,
                    image: imageBase64
                })
            });

            const result = await response.json();
            if (result.success) {
                showAlert(result.message, 'success');
                setTimeout(() => window.location.href = 'history.php', 2000);
            } else {
                showAlert(result.message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Coba Lagi';
            }
        } catch (error) {
            showAlert('Gagal menghubungi server.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Coba Lagi';
        }
    });
    </script>
</body>

</html>