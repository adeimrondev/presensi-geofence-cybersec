<?php
// admin/pegawai.php - Manajemen Data Pegawai, Role, & Reset Password
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

// Proteksi hak akses khusus admin
checkAuth('admin');

$currentAdminId = $_SESSION['user_id'] ?? 0;
$successMsg     = '';
$errorMsg       = '';

// 1. Penanganan POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    // A. Tambah Pegawai Baru
    if ($action === 'create') {
        $nama     = trim($_POST['nama'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = strtolower(trim($_POST['role'] ?? 'pegawai'));

        if (empty($nama) || empty($email) || empty($password)) {
            $errorMsg = 'Nama, email, dan password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Format alamat email tidak valid.';
        } elseif (strlen($password) < 6) {
            $errorMsg = 'Password minimal harus 6 karakter.';
        } elseif (!in_array($role, ['admin', 'pegawai'])) {
            $errorMsg = 'Role yang dipilih tidak valid.';
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                $errorMsg = 'Email sudah digunakan, silakan gunakan email lain.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (nama, email, password, role) 
                        VALUES (:nama, :email, :password, :role)
                    ");
                    $insertStmt->execute([
                        ':nama'     => $nama,
                        ':email'    => $email,
                        ':password' => $hashedPassword,
                        ':role'     => $role
                    ]);
                    $successMsg = "Pegawai baru '{$nama}' berhasil didaftarkan.";
                } catch (PDOException $e) {
                    $errorMsg = 'Gagal menyimpan pegawai ke basis data.';
                }
            }
        }
    }

    // B. Ubah Data Pegawai & Reset Password
    elseif ($action === 'update') {
        $userId      = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $nama        = trim($_POST['nama'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $role        = strtolower(trim($_POST['role'] ?? 'pegawai'));
        $newPassword = $_POST['new_password'] ?? '';

        if (!$userId || empty($nama) || empty($email)) {
            $errorMsg = 'Nama lengkap dan email tidak boleh kosong.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Format alamat email tidak valid.';
        } elseif (!in_array($role, ['admin', 'pegawai'])) {
            $errorMsg = 'Role yang dipilih tidak valid.';
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $checkStmt->execute([':email' => $email, ':id' => $userId]);
            if ($checkStmt->fetch()) {
                $errorMsg = 'Email tersebut sudah digunakan oleh akun lain.';
            } else {
                $finalRole = ($userId === $currentAdminId) ? 'admin' : $role;

                try {
                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            $errorMsg = 'Password baru minimal harus 6 karakter.';
                        } else {
                            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                            $updateStmt = $pdo->prepare("
                                UPDATE users 
                                SET nama = :nama, email = :email, role = :role, password = :password 
                                WHERE id = :id
                            ");
                            $updateStmt->execute([
                                ':nama'     => $nama,
                                ':email'    => $email,
                                ':role'     => $finalRole,
                                ':password' => $hashed,
                                ':id'       => $userId
                            ]);
                            $successMsg = "Data dan kata sandi baru untuk '{$nama}' berhasil diperbarui.";
                        }
                    } else {
                        $updateStmt = $pdo->prepare("
                            UPDATE users 
                            SET nama = :nama, email = :email, role = :role 
                            WHERE id = :id
                        ");
                        $updateStmt->execute([
                            ':nama'  => $nama,
                            ':email' => $email,
                            ':role'  => $finalRole,
                            ':id'    => $userId
                        ]);
                        $successMsg = "Data profil '{$nama}' berhasil diperbarui.";
                    }
                } catch (PDOException $e) {
                    $errorMsg = 'Gagal memperbarui data pegawai.';
                }
            }
        }
    }

    // C. Hapus Pegawai
    elseif ($action === 'delete') {
        $deleteId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

        if ($deleteId === false || empty($deleteId)) {
            $errorMsg = 'ID pegawai tidak valid.';
        } elseif ($deleteId === $currentAdminId) {
            $errorMsg = 'Aksi ditolak: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.';
        } else {
            try {
                $delPresensi = $pdo->prepare("DELETE FROM presensi WHERE user_id = :id");
                $delPresensi->execute([':id' => $deleteId]);

                $delUser = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $delUser->execute([':id' => $deleteId]);

                $successMsg = 'Data pegawai beserta riwayat presensinya berhasil dihapus.';
            } catch (PDOException $e) {
                $errorMsg = 'Gagal menghapus data pegawai.';
            }
        }
    }
}

// 2. Ambil Semua Data Pegawai
$usersStmt = $pdo->query("SELECT id, nama, email, role FROM users ORDER BY role ASC, nama ASC");
$usersList = $usersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pegawai - Panel Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen">
    <!-- Navbar Admin Seragam -->
    <nav
        class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-6 py-3.5 sticky top-0 z-30 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-3">
            <div
                class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-sm font-bold text-white tracking-wide">Panel Administrator</h1>
                    <span
                        class="text-[10px] font-mono uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-2 py-0.5 rounded-full font-semibold">User
                        Manager</span>
                </div>
                <p class="text-[11px] text-slate-400">Manajemen Hak Akses & Kredensial Pegawai</p>
            </div>
        </div>

        <!-- Pill Navigasi 5 Menu -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="dashboard.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard Rekap</span>
            </a>

            <a href="pegawai.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 text-white font-medium shadow-md shadow-indigo-600/30 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Data Pegawai</span>
            </a>

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

            <a href="export.php"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-400 border border-emerald-500/20 shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Ekspor CSV</span>
            </a>

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

    <main class="max-w-6xl mx-auto p-4 md:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Data Master Pegawai</h2>
            <p class="text-xs text-slate-400 mt-1">
                Kelola hak akses pengguna, daftarkan akun baru, atau atur peran sistem secara terpusat.
            </p>
        </div>

        <?php if (!empty($successMsg)): ?>
        <div id="alertMsg"
            class="p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span><?= escape($successMsg); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
        <div id="alertMsg"
            class="p-4 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-xs flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= escape($errorMsg); ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Tambah Pegawai Baru -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl lg:col-span-1 h-fit">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                    <h3 class="text-base font-semibold text-white">Tambah Pengguna Baru</h3>
                </div>

                <form id="createForm" action="pegawai.php" method="POST" autocomplete="off" class="space-y-4">
                    <input type="text" name="fake_usernameremembered" style="display:none" tabindex="-1">
                    <input type="password" name="fake_passwordremembered" style="display:none" tabindex="-1">

                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" id="inputNama" required placeholder="Contoh: Budi Santoso"
                            autocomplete="off"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Email Akun</label>
                        <input type="email" name="email" id="inputEmail" required placeholder="nama@cybersec.local"
                            autocomplete="new-password"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 font-mono transition">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
                        <input type="password" name="password" id="inputPass" required placeholder="Minimal 6 karakter"
                            autocomplete="new-password"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 font-mono transition">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Role / Hak Akses</label>
                        <select name="role" required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition">
                            <option value="pegawai">Pegawai</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                            class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-400 text-white font-medium rounded-xl shadow-lg shadow-indigo-600/30 hover:shadow-indigo-600/50 hover:-translate-y-0.5 active:translate-y-0 transition duration-150 text-xs flex items-center justify-center gap-2 cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                            <span>Simpan Pengguna Baru</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabel Daftar Pegawai -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl lg:col-span-2 overflow-hidden h-fit">
                <div class="px-6 py-4 border-b border-slate-800 flex justify-between items-center">
                    <h3 class="text-base font-semibold text-white">Daftar Akun Pengguna</h3>
                    <span
                        class="text-xs bg-slate-800 text-slate-400 px-3 py-1.5 rounded-full border border-slate-700 font-mono">
                        <?= count($usersList); ?> User Terdaftar
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead
                            class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="py-3.5 px-6">Nama</th>
                                <th class="py-3.5 px-6">Email Akun</th>
                                <th class="py-3.5 px-6 text-center">Role</th>
                                <th class="py-3.5 px-6 text-center w-52">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($usersList as $user): ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-4 px-6 font-medium text-white whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span><?= escape($user['nama']); ?></span>
                                        <?php if ((int)$user['id'] === (int)$currentAdminId): ?>
                                        <span
                                            class="text-[10px] bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded border border-indigo-500/30 font-medium">Anda</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-6 font-mono text-slate-400">
                                    <?= escape($user['email']); ?>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <?php if ($user['role'] === 'admin'): ?>
                                    <span
                                        class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/30">
                                        Admin
                                    </span>
                                    <?php else: ?>
                                    <span
                                        class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                                        Pegawai
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Tombol Edit -->
                                        <button type="button"
                                            onclick="openEditModal(<?= (int)$user['id']; ?>, '<?= escape($user['nama']); ?>', '<?= escape($user['email']); ?>', '<?= escape($user['role']); ?>')"
                                            class="inline-flex items-center justify-center gap-1.5 w-20 py-1.5 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/20 text-xs font-medium transition cursor-pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span>Edit</span>
                                        </button>

                                        <!-- Tombol Hapus atau Badge Terkunci -->
                                        <?php if ((int)$user['id'] !== (int)$currentAdminId): ?>
                                        <button type="button"
                                            onclick="openDeleteModal(<?= (int)$user['id']; ?>, '<?= escape($user['nama']); ?>')"
                                            class="inline-flex items-center justify-center gap-1.5 w-20 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-medium transition cursor-pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            <span>Hapus</span>
                                        </button>
                                        <?php else: ?>
                                        <div class="inline-flex items-center justify-center gap-1.5 w-20 py-1.5 rounded-lg bg-slate-900 text-slate-500 border border-slate-800 text-xs font-medium cursor-not-allowed select-none"
                                            title="Akun Anda yang sedang aktif">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="w-3.5 h-3.5 shrink-0 text-slate-500" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            <span>Kunci</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Edit Pegawai & Reset Password -->
    <div id="editModal"
        class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative space-y-4">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    <h4 class="text-sm font-bold text-white">Ubah Data Pengguna</h4>
                </div>
                <button type="button" onclick="closeEditModal()"
                    class="text-slate-500 hover:text-white transition cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form action="pegawai.php" method="POST" class="space-y-3.5 text-xs">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="editUserId">

                <div>
                    <label class="block font-medium text-slate-400 mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" id="editNama" required
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-200 focus:outline-none focus:border-sky-500 transition">
                </div>

                <div>
                    <label class="block font-medium text-slate-400 mb-1">Email Akun</label>
                    <input type="email" name="email" id="editEmail" required
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-200 font-mono focus:outline-none focus:border-sky-500 transition">
                </div>

                <div>
                    <label class="block font-medium text-slate-400 mb-1">Role / Peran</label>
                    <select name="role" id="editRole"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-200 focus:outline-none focus:border-sky-500 transition">
                        <option value="pegawai">Pegawai</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div class="pt-2 border-t border-slate-800/80">
                    <label class="block font-medium text-amber-400 mb-1">Reset Password Baru (Opsional)</label>
                    <input type="password" name="new_password" placeholder="Kosongkan jika sandi tidak diubah"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-200 focus:outline-none focus:border-amber-500 placeholder-slate-600 font-mono transition">
                    <p class="text-[10px] text-slate-500 mt-1">Isi minimal 6 karakter hanya bila ingin mengubah kata
                        sandi akun ini.</p>
                </div>

                <div class="flex items-center gap-3 pt-3">
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-2.5 px-4 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium rounded-xl transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-sky-600 hover:bg-sky-500 text-white font-medium rounded-xl shadow-lg shadow-sky-600/30 transition cursor-pointer">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Akun -->
    <div id="deleteModal"
        class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div
            class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-center space-y-4">
            <div
                class="w-12 h-12 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>

            <div>
                <h3 class="text-base font-bold text-white">Hapus Akun Pegawai</h3>
                <p class="text-xs text-slate-400 mt-1">Yakin ingin menghapus <span id="delUserNama"
                        class="text-rose-400 font-semibold"></span>? Seluruh riwayat presensinya akan ikut dihapus.</p>
            </div>

            <form action="pegawai.php" method="POST" class="flex items-center gap-3 pt-2 text-xs">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delUserId">

                <button type="button" onclick="closeDeleteModal()"
                    class="flex-1 py-2.5 px-4 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium rounded-xl transition cursor-pointer">
                    Batal
                </button>
                <button type="submit"
                    class="flex-1 py-2.5 px-4 bg-rose-600 hover:bg-rose-500 text-white font-medium rounded-xl shadow-lg shadow-rose-600/30 transition cursor-pointer">
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Logout -->
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

            <div class="flex items-center gap-3 pt-2 text-xs">
                <button type="button" onclick="closeLogoutModal()"
                    class="flex-1 py-2.5 px-4 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium rounded-xl border border-slate-700/60 transition cursor-pointer">
                    Batal
                </button>
                <a href="../logout.php"
                    class="flex-1 py-2.5 px-4 bg-rose-600 hover:bg-rose-500 text-white font-medium rounded-xl shadow-lg shadow-rose-600/30 transition text-center cursor-pointer">
                    Ya, Keluar
                </a>
            </div>
        </div>
    </div>

    <script>
    // 1. Bersihkan paksa autofill browser saat halaman termuat
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const emailInput = document.getElementById('inputEmail');
            const passInput = document.getElementById('inputPass');
            const namaInput = document.getElementById('inputNama');

            if (emailInput && passInput) {
                emailInput.value = '';
                passInput.value = '';
                if (namaInput) namaInput.value = '';
            }
        }, 100);
    });

    // 2. Hilangkan alert notifikasi secara halus setelah 4 detik
    const alertMsg = document.getElementById('alertMsg');
    if (alertMsg) {
        setTimeout(() => {
            alertMsg.style.transition = 'opacity 0.5s ease';
            alertMsg.style.opacity = '0';
            setTimeout(() => alertMsg.remove(), 500);
        }, 4000);
    }

    // 3. Kontrol Modal Edit
    const editModal = document.getElementById('editModal');
    const editUserId = document.getElementById('editUserId');
    const editNama = document.getElementById('editNama');
    const editEmail = document.getElementById('editEmail');
    const editRole = document.getElementById('editRole');

    function openEditModal(id, nama, email, role) {
        editUserId.value = id;
        editNama.value = nama;
        editEmail.value = email;
        editRole.value = role;
        editModal.classList.remove('hidden');
    }

    function closeEditModal() {
        editModal.classList.add('hidden');
    }

    // 4. Kontrol Modal Hapus
    const deleteModal = document.getElementById('deleteModal');
    const delUserId = document.getElementById('delUserId');
    const delUserNama = document.getElementById('delUserNama');

    function openDeleteModal(id, nama) {
        delUserId.value = id;
        delUserNama.textContent = `"${nama}"`;
        deleteModal.classList.remove('hidden');
    }

    function closeDeleteModal() {
        deleteModal.classList.add('hidden');
    }

    // 5. Kontrol Modal Logout
    const logoutModal = document.getElementById('logoutModal');

    function openLogoutModal() {
        if (logoutModal) logoutModal.classList.remove('hidden');
    }

    function closeLogoutModal() {
        if (logoutModal) logoutModal.classList.add('hidden');
    }

    // Event Listener Klik Luar Box & Tombol Escape
    window.addEventListener('click', (e) => {
        if (e.target === editModal) closeEditModal();
        if (e.target === deleteModal) closeDeleteModal();
        if (e.target === logoutModal) closeLogoutModal();
    });

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (editModal && !editModal.classList.contains('hidden')) closeEditModal();
            if (deleteModal && !deleteModal.classList.contains('hidden')) closeDeleteModal();
            if (logoutModal && !logoutModal.classList.contains('hidden')) closeLogoutModal();
        }
    });
    </script>
</body>

</html>