<?php
// library/session.php — User session guard + enrichment

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header("Location: /auth/login" . ($redirect ? "?redirect=$redirect" : ""));
    exit();
}

// ── Enrich session with IP & device info on first load ────────
if (!isset($_SESSION['_session_ip'])) {
    // Get real IP
    $headers = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    $real_ip = '0.0.0.0';
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) { $real_ip = $ip; break; }
        }
    }
    $_SESSION['_session_ip']     = $real_ip;
    $_SESSION['_session_ua']     = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['_session_start']  = time();

    // Update last_login & last_ip in DB (non-blocking)
    if (isset($conn) && isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $ip_e = mysqli_real_escape_string($conn, $real_ip);
        @mysqli_query($conn, "UPDATE users SET last_login=NOW(), last_ip='$ip_e' WHERE id=$uid");
    }
}
