<?php
// pegawai/history.php - Riwayat Presensi Pegawai
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

checkAuth();

$userId = $_SESSION['user_id'] ?? null;

// Ambil riwayat presensi milik pegawai yang sedang login
$stmt = $pdo->prepare("
    SELECT tanggal, jam_masuk, jam_pulang, latitude_masuk, longitude_masuk, jarak_masuk_meter, status, foto 
    FROM presensi 
    WHERE user_id = :user_id 
    ORDER BY tanggal DESC, jam_masuk DESC
");
$stmt->execute([':user_id' => $userId]);
$history = $stmt->fetchAll();

// Kalkulasi statistik sederhana
$totalPresensi = count($history);
$totalTepatWaktu = 0;
$totalTerlambat = 0;

foreach ($history as $h) {
    $st = strtolower(trim($h['status'] ?? ''));
    if (in_array($st, ['tepat waktu', 'hadir', 'tepat_waktu'])) {
        $totalTepatWaktu++;
    } elseif ($st === 'terlambat') {
        $totalTerlambat++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Presensi - Geofencing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen">
    <!-- Navbar Seragam Pegawai Sticky Top -->
    <nav
        class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-6 py-3.5 sticky top-0 z-30 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div
                class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-sm font-bold text-white tracking-wide">Riwayat Presensi</h1>
                <p class="text-[11px] text-slate-400">Pegawai: <span
                        class="text-indigo-400 font-medium"><?= escape($_SESSION['nama'] ?? 'Pegawai'); ?></span></p>
            </div>
        </div>

        <!-- Tombol Aksi Navbar Modern Glass -->
        <div class="flex items-center gap-2.5 text-xs">
            <!-- Tombol Kembali ke Halaman Presensi Bergaya Modern Glass -->
            <a href="presensi.php"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/90 text-slate-200 hover:text-white border border-slate-700/70 hover:border-indigo-500/50 shadow-sm transition-all duration-150 cursor-pointer group">
                <div
                    class="w-5 h-5 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white transition duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </div>
                <span class="font-medium tracking-wide">Halaman Presensi</span>
            </a>

            <!-- Tombol Keluar Modern Rose -->
            <button type="button" onclick="openLogoutModal()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 transition cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span class="font-medium">Keluar</span>
            </button>
        </div>
    </nav>

    <!-- Konten Utama Halaman -->
    <main class="max-w-5xl mx-auto p-4 md:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Catatan Kehadiran Saya</h2>
            <p class="text-xs text-slate-400 mt-1">Rekap data jam masuk, jam pulang, jarak lokasi, dan bukti swafoto
                harian.</p>
        </div>

        <!-- Kartu Ringkasan Kehadiran Mandiri -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
                <span class="text-xs text-slate-400 font-medium">Total Kehadiran</span>
                <p class="text-2xl font-bold text-white mt-1 font-mono"><?= $totalPresensi; ?> <span
                        class="text-xs font-normal text-slate-500">Hari</span></p>
            </div>
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
                <span class="text-xs text-slate-400 font-medium">Tepat Waktu</span>
                <p class="text-2xl font-bold text-emerald-400 mt-1 font-mono"><?= $totalTepatWaktu; ?></p>
            </div>
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
                <span class="text-xs text-slate-400 font-medium">Terlambat</span>
                <p class="text-2xl font-bold text-amber-400 mt-1 font-mono"><?= $totalTerlambat; ?></p>
            </div>
        </div>

        <!-- Tabel Riwayat Presensi -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead
                        class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-4 px-6">Tanggal</th>
                            <th class="py-4 px-6">Jam Masuk</th>
                            <th class="py-4 px-6">Jam Pulang</th>
                            <th class="py-4 px-6">Jarak Lokasi</th>
                            <th class="py-4 px-6 text-center">Status</th>
                            <th class="py-4 px-6 text-center">Bukti Swafoto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" class="py-12 px-6 text-center text-slate-500 text-sm">
                                Belum ada data riwayat presensi yang tercatat.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($history as $row): ?>
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="py-4 px-6 font-medium text-white whitespace-nowrap">
                                <?= date('d M Y', strtotime($row['tanggal'])); ?>
                            </td>
                            <td class="py-4 px-6 font-mono text-emerald-400">
                                <?= escape($row['jam_masuk'] ?? '-'); ?>
                            </td>
                            <td class="py-4 px-6 font-mono">
                                <?php if (!empty($row['jam_pulang'])): ?>
                                <span class="text-sky-400"><?= escape($row['jam_pulang']); ?></span>
                                <?php else: ?>
                                <span class="text-slate-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 font-mono">
                                <?= number_format((float)($row['jarak_masuk_meter'] ?? 0), 1); ?> m
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?php 
                                    $st = strtolower(trim($row['status'] ?? ''));
                                    if (empty($st) && !empty($row['jam_masuk'])) {
                                        $st = (strtotime($row['jam_masuk']) > strtotime('08:00:00')) ? 'terlambat' : 'tepat waktu';
                                    }

                                    if ($st === 'tepat waktu' || $st === 'hadir' || $st === 'tepat_waktu') {
                                        $badgeClass = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
                                        $label = 'Tepat Waktu';
                                    } elseif ($st === 'terlambat') {
                                        $badgeClass = 'bg-amber-500/10 text-amber-400 border border-amber-500/30';
                                        $label = 'Terlambat';
                                    } else {
                                        $badgeClass = 'bg-slate-800 text-slate-300 border border-slate-700';
                                        $label = ucfirst($st ?: '-');
                                    }
                                ?>
                                <span
                                    class="inline-block px-3 py-1 text-xs font-semibold rounded-full border <?= $badgeClass; ?>">
                                    <?= $label; ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?php 
                                    $fotoUrl = '';
                                    if (!empty($row['foto'])) {
                                        if (file_exists(dirname(__DIR__) . '/uploads/' . $row['foto'])) {
                                            $fotoUrl = '../uploads/' . rawurlencode($row['foto']);
                                        }
                                    }
                                ?>
                                <?php if (!empty($fotoUrl)): ?>
                                <button type="button"
                                    onclick="openPhotoModal('<?= $fotoUrl; ?>', '<?= date('d M Y', strtotime($row['tanggal'])); ?> - <?= escape($row['jam_masuk'] ?? '-'); ?>')"
                                    class="inline-flex justify-center group cursor-pointer focus:outline-none"
                                    title="Klik untuk perbesar">
                                    <img src="<?= $fotoUrl; ?>" alt="Swafoto"
                                        class="w-10 h-10 object-cover rounded-xl border border-slate-700 group-hover:scale-110 group-hover:border-indigo-500 transition duration-150 shadow">
                                </button>
                                <?php else: ?>
                                <span class="text-xs text-slate-500 italic">Tidak ada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Pop-up Swafoto Pegawai -->
    <div id="photoModal"
        class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div
            class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl relative">
            <div class="px-5 py-4 border-b border-slate-800 flex justify-between items-center">
                <div>
                    <h4 class="text-sm font-bold text-white">Bukti Swafoto Presensi</h4>
                    <p id="modalPhotoInfo" class="text-[11px] font-mono text-slate-400 mt-0.5">-</p>
                </div>
                <button type="button" onclick="closePhotoModal()"
                    class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-4 bg-slate-950 flex items-center justify-center min-h-[300px]">
                <img id="modalImg" src="" alt="Swafoto Presensi"
                    class="w-full max-h-[460px] object-contain rounded-xl border border-slate-800 shadow-md">
            </div>

            <div class="px-5 py-3 border-t border-slate-800 flex justify-end">
                <button type="button" onclick="closePhotoModal()"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-xl transition cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>
    </div>

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
    // 1. Kontrol Modal Logout
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

    // 2. Kontrol Modal Swafoto
    const photoModal = document.getElementById('photoModal');
    const modalImg = document.getElementById('modalImg');
    const modalPhotoInfo = document.getElementById('modalPhotoInfo');

    function openPhotoModal(url, info) {
        modalImg.src = url;
        modalPhotoInfo.textContent = info;
        photoModal.classList.remove('hidden');
    }

    function closePhotoModal() {
        photoModal.classList.add('hidden');
        modalImg.src = '';
    }

    if (photoModal) {
        photoModal.addEventListener('click', (e) => {
            if (e.target === photoModal) closePhotoModal();
        });
    }

    // Tutup modal dengan tombol Escape
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (logoutModal && !logoutModal.classList.contains('hidden')) closeLogoutModal();
            if (photoModal && !photoModal.classList.contains('hidden')) closePhotoModal();
        }
    });
    </script>
</body>

</html>