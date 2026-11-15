<?php
// includes/helpers.php - Security Helpers & Access Guards

// 1. Anti-XSS output encoding
function escape($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// 2. Role-Based Access Control (RBAC Guard)
function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }
    if ($requiredRole !== null && $_SESSION['role'] !== $requiredRole) {
        http_response_code(403);
        die("403 Forbidden: You do not have permission to access this resource.");
    }
}

// 3. CSRF Token Generator & Validator
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