<?php
// admin/dashboard.php - Pusat Kendali & Rekap Presensi Admin
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

// Wajib role admin
checkAuth('admin');

$today = date('Y-m-d');

// 1. Ambil Statistik Kartu Hari Ini
$statTotalUser    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$statHadirHariIni = $pdo->query("SELECT COUNT(*) FROM presensi WHERE tanggal = '$today'")->fetchColumn();
$statTepatWaktu   = $pdo->query("SELECT COUNT(*) FROM presensi WHERE tanggal = '$today' AND (LOWER(TRIM(status)) IN ('tepat waktu', 'hadir', 'tepat_waktu') OR status IS NULL OR status = '')")->fetchColumn();
$statTerlambat    = $pdo->query("SELECT COUNT(*) FROM presensi WHERE tanggal = '$today' AND LOWER(TRIM(status)) = 'terlambat'")->fetchColumn();

// 2. Ambil Riwayat Rekap Presensi Seluruh Pegawai
$stmt = $pdo->query("
    SELECT p.id, p.tanggal, p.jam_masuk, p.jam_pulang, p.jarak_masuk_meter, p.status, p.foto, 
           u.nama, u.email 
    FROM presensi p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.tanggal DESC, p.jam_masuk DESC
");
$attendances = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Presensi Geofencing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen">
    <!-- Navbar Admin Modern Lengkap & Seragam -->
    <nav
        class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-6 py-3.5 sticky top-0 z-30 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-3">
            <div
                class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-sm font-bold text-white tracking-wide">Panel Administrator</h1>
                    <span
                        class="text-[10px] font-mono uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-2 py-0.5 rounded-full font-semibold">Dashboard</span>
                </div>
                <p class="text-[11px] text-slate-400">Login sebagai: <span
                        class="text-indigo-400 font-mono"><?= escape($_SESSION['nama'] ?? 'Admin'); ?></span></p>
            </div>
        </div>

        <!-- Pill Group Navigasi Lengkap 5 Menu -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <!-- Dashboard Rekap (Status Aktif) -->
            <a href="dashboard.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 text-white font-medium shadow-md shadow-indigo-600/30 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"
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

            <!-- Titik Geofencing -->
            <a href="lokasi.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Titik Lokasi</span>
            </a>

            <!-- Ekspor CSV Navbar -->
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

    <!-- Lebar Container max-w-6xl Seragam -->
    <main class="max-w-6xl mx-auto p-4 md:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Ikhtisar Kehadiran</h2>
            <p class="text-xs text-slate-400 mt-1">Pemantauan data kehadiran pegawai secara real-time berbasis
                verifikasi geolokasi.</p>
        </div>

        <!-- 4 Kartu Statistik -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <p class="text-xs text-slate-400 font-medium">Total Akun</p>
                <p class="text-2xl font-bold text-white mt-1"><?= (int)$statTotalUser; ?> <span
                        class="text-xs font-normal text-slate-500">orang</span></p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <p class="text-xs text-slate-400 font-medium">Presensi Hari Ini</p>
                <p class="text-2xl font-bold text-indigo-400 mt-1"><?= (int)$statHadirHariIni; ?> <span
                        class="text-xs font-normal text-slate-500">kali</span></p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <p class="text-xs text-slate-400 font-medium">Tepat Waktu</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1"><?= (int)$statTepatWaktu; ?></p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <p class="text-xs text-slate-400 font-medium">Terlambat</p>
                <p class="text-2xl font-bold text-amber-400 mt-1"><?= (int)$statTerlambat; ?></p>
            </div>
        </div>

        <!-- Tabel Rekap Presensi + Fitur Live Filter & Tombol Ekspor Cepat -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div
                class="px-6 py-4 border-b border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                    <h3 class="text-base font-semibold text-white">Log Kehadiran Pegawai</h3>
                    <span id="counterBadge" class="text-xs text-slate-400 font-mono">
                        Menampilkan <?= count($attendances); ?> data
                    </span>
                </div>

                <!-- Input Filter Interaktif + Tombol Ekspor CSV -->
                <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                    <div class="relative flex-1 md:w-56">
                        <input type="text" id="searchInput" placeholder="Cari nama / email..."
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                    <button type="button" id="resetBtn"
                        class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs transition cursor-pointer">
                        Reset
                    </button>
                    <!-- Tombol Unduh CSV Langsung -->
                    <a href="export.php"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-semibold shadow-sm transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Unduh CSV</span>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="logTable" class="w-full text-left text-sm text-slate-300">
                    <thead
                        class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-6">Pegawai</th>
                            <th class="py-3.5 px-6">Tanggal</th>
                            <th class="py-3.5 px-6">Jam Masuk</th>
                            <th class="py-3.5 px-6">Jam Pulang</th>
                            <th class="py-3.5 px-6">Jarak Lokasi</th>
                            <th class="py-3.5 px-6 text-center">Status</th>
                            <th class="py-3.5 px-6 text-center">Bukti Swafoto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($attendances)): ?>
                        <tr id="emptyRow">
                            <td colspan="7" class="py-8 px-6 text-center text-slate-500">
                                Belum ada catatan presensi yang masuk ke sistem.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($attendances as $row): ?>
                        <tr class="hover:bg-slate-800/40 transition data-row">
                            <td class="py-4 px-6 font-medium text-white whitespace-nowrap col-nama">
                                <div><?= escape($row['nama']); ?></div>
                                <span
                                    class="text-xs font-mono text-slate-500"><?= escape($row['email'] ?? ''); ?></span>
                            </td>
                            <td class="py-4 px-6 text-slate-300 whitespace-nowrap col-tanggal">
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
                                    if ($st === 'tepat waktu' || $st === 'hadir' || $st === 'tepat_waktu') {
                                        $badgeClass = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
                                        $label = 'Tepat Waktu';
                                    } elseif ($st === 'terlambat') {
                                        $badgeClass = 'bg-amber-500/10 text-amber-400 border border-amber-500/30';
                                        $label = 'Terlambat';
                                    } else {
                                        if (!empty($row['jam_masuk'])) {
                                            $badgeClass = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
                                            $label = 'Tepat Waktu';
                                        } else {
                                            $badgeClass = 'bg-slate-800 text-slate-400 border border-slate-700';
                                            $label = '-';
                                        }
                                    }
                                ?>
                                <span
                                    class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full border <?= $badgeClass; ?>">
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
                                    $tanggalFormatted = date('d M Y', strtotime($row['tanggal']));
                                    $jamMasukTeks = escape($row['jam_masuk'] ?? '-');
                                    $infoModal = $tanggalFormatted . ' • Pukul ' . $jamMasukTeks . ' WIB';
                                ?>
                                <?php if (!empty($fotoUrl)): ?>
                                <!-- Tombol Preview Modal 1 Baris Utuh Tanpa Syntax Error -->
                                <button type="button"
                                    onclick="openPhotoModal('<?= $fotoUrl; ?>', '<?= escape($row['nama']); ?>', '<?= $infoModal; ?>')"
                                    class="inline-flex justify-center group cursor-pointer focus:outline-none"
                                    title="Klik untuk perbesar">
                                    <img src="<?= $fotoUrl; ?>" alt="Swafoto"
                                        class="w-10 h-10 object-cover rounded-lg border border-slate-700 group-hover:scale-110 group-hover:border-indigo-500 transition duration-150 shadow">
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

    <!-- Modal Popup Preview Swafoto Modern -->
    <div id="photoModal"
        class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div
            class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
            <!-- Header Modal -->
            <div class="px-5 py-4 border-b border-slate-800 flex justify-between items-center">
                <div>
                    <h4 id="modalPegawaiNama" class="text-sm font-bold text-white">Bukti Swafoto</h4>
                    <p id="modalPegawaiInfo" class="text-[11px] font-mono text-slate-400 mt-0.5">-</p>
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

            <!-- Gambar Resolusi Penuh -->
            <div class="p-4 bg-slate-950 flex items-center justify-center min-h-[300px]">
                <img id="modalImg" src="" alt="Swafoto Presensi"
                    class="w-full max-h-[460px] object-contain rounded-xl border border-slate-800 shadow-md">
            </div>

            <!-- Footer Modal -->
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

    <!-- Skrip Interaktif: Live Filter, Modal Foto, & Modal Logout -->
    <script>
    // 1. Live Filter Search
    const searchInput = document.getElementById('searchInput');
    const resetBtn = document.getElementById('resetBtn');
    const rows = document.querySelectorAll('.data-row');
    const counterBadge = document.getElementById('counterBadge');

    function applyFilter() {
        const query = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
            const namaEmail = row.querySelector('.col-nama').textContent.toLowerCase();
            const match = namaEmail.includes(query);

            if (match) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (counterBadge) {
            counterBadge.textContent = `Menampilkan ${visibleCount} data`;
        }
    }

    searchInput.addEventListener('input', applyFilter);
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        applyFilter();
    });

    // 2. Modal Pop-up Swafoto
    const photoModal = document.getElementById('photoModal');
    const modalImg = document.getElementById('modalImg');
    const modalPegawaiNama = document.getElementById('modalPegawaiNama');
    const modalPegawaiInfo = document.getElementById('modalPegawaiInfo');

    function openPhotoModal(imgUrl, nama, info) {
        modalImg.src = imgUrl;
        modalPegawaiNama.textContent = `Swafoto: ${nama}`;
        modalPegawaiInfo.textContent = info;
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

    // 3. Modal Logout Modern
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
        if (e.key === 'Escape') {
            if (photoModal && !photoModal.classList.contains('hidden')) closePhotoModal();
            if (logoutModal && !logoutModal.classList.contains('hidden')) closeLogoutModal();
        }
    });
    </script>
</body>

</html>