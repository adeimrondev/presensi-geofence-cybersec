<?php
// logout.php - Penghancuran Sesi Aman
require_once __DIR__ . '/config/database.php';

// 1. Kosongkan array sesi
$_SESSION = [];

// 2. Hapus cookie sesi dari browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Hancurkan data sesi di server
session_destroy();

// 4. Alihkan kembali ke halaman login
header("Location: login.php");
exit;