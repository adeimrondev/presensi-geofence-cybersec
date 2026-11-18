<?php
// login.php - Autentikasi Pengguna & Penanganan Sesi
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email dan kata sandi wajib diisi.";
    } else {
        $stmt = $pdo->prepare("SELECT id, nama, email, password, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            
            $userRole = !empty($user['role']) ? strtolower(trim($user['role'])) : 'pegawai';
            $_SESSION['role'] = $userRole;

            session_write_close();

            if ($userRole === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header('Location: pegawai/presensi.php');
            }
            exit;
        } else {
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
    <style>
    /* Mencegah background putih saat autofill browser aktif */
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus {
        -webkit-text-fill-color: #f8fafc !important;
        -webkit-box-shadow: 0 0 0px 1000px #020617 inset !important;
        transition: background-color 5000s ease-in-out 0s;
    }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-8">
        <!-- Header Brand -->
        <div class="mb-7 text-center flex flex-col items-center">
            <div
                class="flex items-center justify-center w-12 h-12 bg-indigo-600/20 text-indigo-400 rounded-xl mb-3.5 border border-indigo-500/30 shadow-lg shadow-indigo-600/10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="text-xl font-bold tracking-tight text-white">Sistem Presensi Geofence</h1>
            <p class="text-xs text-slate-400 mt-1">Cyber Security & Anti-Spoofing Attendance</p>
        </div>

        <?php if (!empty($error)): ?>
        <div
            class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= escape($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Form Autentikasi -->
        <form method="POST" action="" class="space-y-4">
            <div>
                <label for="email" class="block text-xs font-medium text-slate-300 mb-1.5">Alamat Email</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                    </span>
                    <input type="email" id="email" name="email" required autocomplete="email"
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition font-mono"
                        placeholder="nama@cybersec.local">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-medium text-slate-300 mb-1.5">Kata Sandi</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </span>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                        class="w-full pl-10 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition font-mono"
                        placeholder="••••••••">

                    <!-- Tombol Intip Password -->
                    <button type="button" id="togglePasswordBtn"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500 hover:text-slate-300 transition cursor-pointer">
                        <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="pt-1">
                <button type="submit"
                    class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-xs rounded-xl shadow-lg shadow-indigo-600/30 hover:shadow-indigo-600/50 transition duration-150 cursor-pointer">
                    Masuk ke Sistem
                </button>
            </div>
        </form>

        <!-- Kredensial Pengujian (Demo) Auto-Fill Interaktif -->
        <div class="mt-6 pt-5 border-t border-slate-800/80 text-xs text-slate-400 space-y-2.5">
            <div class="flex items-center justify-between">
                <span class="font-semibold text-slate-300 text-[11px]">Kredensial Pengujian (Demo):</span>
                <span class="text-[10px] text-slate-500 italic">Klik tombol untuk auto-fill</span>
            </div>

            <!-- Baris Demo Admin -->
            <div
                class="flex items-center justify-between bg-slate-950 p-2 rounded-xl border border-slate-800/80 font-mono text-[11px]">
                <div class="space-x-1 truncate mr-2">
                    <span class="text-indigo-400 font-semibold">Admin:</span>
                    <span class="text-slate-300">admin@cybersec.local</span>
                </div>
                <button type="button" onclick="fillCredential('admin@cybersec.local', 'Password123!')"
                    class="px-2 py-0.5 rounded bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-[10px] font-sans transition cursor-pointer">
                    Pakai
                </button>
            </div>

            <!-- Baris Demo Pegawai -->
            <div
                class="flex items-center justify-between bg-slate-950 p-2 rounded-xl border border-slate-800/80 font-mono text-[11px]">
                <div class="space-x-1 truncate mr-2">
                    <span class="text-sky-400 font-semibold">Pegawai:</span>
                    <span class="text-slate-300">pegawai@cybersec.local</span>
                </div>
                <button type="button" onclick="fillCredential('pegawai@cybersec.local', 'Password123!')"
                    class="px-2 py-0.5 rounded bg-sky-600/20 hover:bg-sky-600/30 text-sky-300 border border-sky-500/30 text-[10px] font-sans transition cursor-pointer">
                    Pakai
                </button>
            </div>
        </div>
    </div>

    <script>
    // 1. Toggle Show / Hide Password
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePasswordBtn.addEventListener('click', () => {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        if (isPassword) {
            passwordInput.setAttribute('type', 'text');
            // Ikon Mata Dicoret
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
            `;
        } else {
            passwordInput.setAttribute('type', 'password');
            // Ikon Mata Normal
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            `;
        }
    });

    // 2. Auto-Fill Demo Kredensial
    function fillCredential(email, pass) {
        const emailEl = document.getElementById('email');
        const passEl = document.getElementById('password');

        emailEl.value = email;
        passEl.value = pass;

        // Efek kedip visual penanda sukses terisi
        [emailEl, passEl].forEach(el => {
            el.classList.add('border-indigo-500');
            setTimeout(() => el.classList.remove('border-indigo-500'), 500);
        });
    }
    </script>
</body>

</html>