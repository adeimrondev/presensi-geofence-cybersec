<?php
// includes/helpers.php - Security Helpers & Access Guards

function escape($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

function checkAuth($requiredRole = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Belum login sama sekali -> arahkan ke login
    if (empty($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }

    // 2. Jika role di sesi belum tersimpan atau kosong, beri default 'pegawai'
    if (empty($_SESSION['role'])) {
        $_SESSION['role'] = 'pegawai';
    }

    // 3. Jika pemanggilan checkAuth() tanpa argumen role, langsung lolos
    if ($requiredRole === null) {
        return;
    }

    $currentRole  = strtolower(trim($_SESSION['role']));
    $expectedRole = strtolower(trim($requiredRole));

    // 4. Toleransi penamaan role pegawai & employee
    $isEmployeeMatch = in_array($currentRole, ['pegawai', 'employee']) && in_array($expectedRole, ['pegawai', 'employee']);
    $isAdminMatch    = ($currentRole === 'admin' && $expectedRole === 'admin');

    if ($isEmployeeMatch || $isAdminMatch) {
        return;
    }

    // 5. Jika akun pegawai mencoba akses modul employee/pegawai, loloskan
    if (in_array($expectedRole, ['pegawai', 'employee'])) {
        $_SESSION['role'] = 'pegawai';
        return;
    }

    // 6. Tolak akses jika role bertentangan dengan tampilan kartu keamanan profesional
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak (RBAC)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl text-center space-y-6">
        <!-- Ikon Keamanan Gembok Merah -->
        <div
            class="w-16 h-16 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>

        <!-- Teks Informasi Role -->
        <div class="space-y-2">
            <span class="text-xs font-mono font-semibold tracking-widest uppercase text-rose-400">403 Forbidden &bull;
                RBAC Guard</span>
            <h1 class="text-xl font-bold text-white tracking-tight">Hak Akses Tidak Memenuhi</h1>
            <p class="text-xs text-slate-400 leading-relaxed">
                Sistem mendeteksi role akun Anda adalah <span
                    class="text-amber-400 font-mono font-semibold"><?= escape($currentRole ?: '(guest)'); ?></span>.
                Halaman ini dikhususkan untuk hak akses <span
                    class="text-indigo-400 font-mono font-semibold"><?= escape($expectedRole); ?></span>.
            </p>
        </div>

        <!-- Tombol Navigasi Alternatif -->
        <div class="pt-2 flex flex-col gap-2.5">
            <a href="../pegawai/presensi.php"
                class="w-full py-2.5 px-4 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-xl border border-slate-700 transition">
                &larr; Kembali ke Presensi Saya
            </a>
            <a href="../logout.php"
                class="w-full py-2.5 px-4 bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 text-xs font-medium rounded-xl transition">
                Ganti Akun (Logout Sesi Ini)
            </a>
        </div>
    </div>
</body>

</html>
<?php
    exit;
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
        http_response_code(403);
        die("403 Forbidden: CSRF token validation failed.");
    }
}