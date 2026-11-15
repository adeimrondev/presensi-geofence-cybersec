<?php
// login.php - Autentikasi Pengguna & Penanganan Sesi
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

// Jika pengguna sudah dalam keadaan login, alihkan langsung
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email dan kata sandi wajib diisi.";
    } else {
        // Query aman tanpa interpolasi string langsung (Anti-SQLi)
        $stmt = $pdo->prepare("SELECT id, nama, email, password, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerasi ID sesi untuk mencegah Session Fixation attack
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            // Alihkan berdasarkan hak akses (Role-Based Access Control)
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: employee/attendance.php");
            }
            exit;
        } else {
            // Pesan kesalahan sengaja dibuat umum agar tidak membocorkan keberadaan email
            $error = "Kombinasi email atau kata sandi tidak valid.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Geofencing Attendance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl p-8">
        <div class="mb-8 text-center">
            <div
                class="inline-flex items-center justify-center w-14 h-14 bg-indigo-600/20 text-indigo-400 rounded-xl mb-4 border border-indigo-500/30">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Sistem Presensi Geofence</h1>
            <p class="text-sm text-slate-400 mt-1">Cyber Security & Anti-Spoofing Attendance</p>
        </div>

        <?php if (!empty($error)): ?>
        <div
            class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= escape($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Alamat Email</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                    placeholder="nama@cybersec.local">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Kata Sandi</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                    placeholder="••••••••">
            </div>

            <button type="submit"
                class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium rounded-xl shadow-lg shadow-indigo-600/30 transition duration-150 ease-in-out">
                Masuk ke Sistem
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-700/60 text-xs text-slate-400 space-y-2">
            <p class="font-semibold text-slate-300">Kredensial Pengujian (Demo):</p>
            <div class="flex justify-between font-mono bg-slate-900/60 p-2 rounded-lg border border-slate-800">
                <span>Admin: admin@cybersec.local</span>
                <span class="text-slate-500">Password123!</span>
            </div>
            <div class="flex justify-between font-mono bg-slate-900/60 p-2 rounded-lg border border-slate-800">
                <span>Pegawai: pegawai@cybersec.local</span>
                <span class="text-slate-500">Password123!</span>
            </div>
        </div>
    </div>
</body>

</html>