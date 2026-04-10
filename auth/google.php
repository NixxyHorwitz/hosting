<?php
/**
 * Google OAuth 2.0 Handler
 * Handles both the redirect to Google and the callback processing.
 * Route: /auth/google (via index.php routing)
 */

if (!defined('NS1')) include __DIR__ . '/../config/database.php';

// Load Google credentials from DB
$g_res = mysqli_query($conn, "SELECT google_client_id, google_client_secret FROM settings LIMIT 1");
$g_set = mysqli_fetch_assoc($g_res) ?? [];
$G_CLIENT_ID     = trim($g_set['google_client_id'] ?? '');
$G_CLIENT_SECRET = trim($g_set['google_client_secret'] ?? '');
$G_REDIRECT_URI  = base_url('auth/google');

// If credentials not configured
if (empty($G_CLIENT_ID) || empty($G_CLIENT_SECRET)) {
    $_SESSION['auth_error'] = 'Google SSO belum dikonfigurasi. Silakan hubungi administrator.';
    header('Location: ' . base_url('auth/login'));
    exit;
}

// ─── Step 1: Redirect to Google ───────────────────────────────────────────────
if (!isset($_GET['code'])) {
    // Generate state token (CSRF protection)
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => $G_CLIENT_ID,
        'redirect_uri'  => $G_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// ─── Step 2: Handle Callback ──────────────────────────────────────────────────

// CSRF check
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    $_SESSION['auth_error'] = 'Permintaan OAuth tidak valid (state mismatch). Silakan coba lagi.';
    header('Location: ' . base_url('auth/login'));
    exit;
}
unset($_SESSION['oauth_state']);

// Error from Google
if (isset($_GET['error'])) {
    $_SESSION['auth_error'] = 'Login Google dibatalkan: ' . htmlspecialchars($_GET['error']);
    header('Location: ' . base_url('auth/login'));
    exit;
}

$code = $_GET['code'];

// Exchange code for access token
$token_response = curlPost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $G_CLIENT_ID,
    'client_secret' => $G_CLIENT_SECRET,
    'redirect_uri'  => $G_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

$token_data = json_decode($token_response, true);

if (empty($token_data['access_token'])) {
    $err = $token_data['error_description'] ?? $token_data['error'] ?? 'Token exchange gagal.';
    $_SESSION['auth_error'] = 'Google OAuth Error: ' . $err;
    header('Location: ' . base_url('auth/login'));
    exit;
}

// Fetch user profile
$profile_json = curlGet(
    'https://www.googleapis.com/oauth2/v3/userinfo',
    $token_data['access_token']
);
$profile = json_decode($profile_json, true);

if (empty($profile['sub'])) {
    $_SESSION['auth_error'] = 'Gagal mengambil profil dari Google. Silakan coba lagi.';
    header('Location: ' . base_url('auth/login'));
    exit;
}

$google_id  = mysqli_real_escape_string($conn, $profile['sub']);
$g_email    = mysqli_real_escape_string($conn, strtolower($profile['email'] ?? ''));
$g_name     = mysqli_real_escape_string($conn, $profile['name'] ?? '');
$g_picture  = mysqli_real_escape_string($conn, $profile['picture'] ?? '');
$reg_ip     = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$reg_ip     = mysqli_real_escape_string($conn, trim(explode(',', $reg_ip)[0]));

// Check if user exists by google_id OR email
$check = mysqli_query($conn, "SELECT * FROM users WHERE google_id='$google_id' OR email='$g_email' LIMIT 1");

if (mysqli_num_rows($check) > 0) {
    // Existing user — log them in
    $user = mysqli_fetch_assoc($check);

    if ($user['status'] !== 'active') {
        $_SESSION['auth_error'] = 'Akun Anda belum diaktifkan. Hubungi administrator.';
        header('Location: ' . base_url('auth/login'));
        exit;
    }

    // Update google_id & avatar in case it's a new link
    mysqli_query($conn, "UPDATE users SET
        google_id='$google_id',
        avatar_url='$g_picture',
        last_login=NOW(),
        last_ip='$reg_ip'
        WHERE id='{$user['id']}'");

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_nama'] = $user['nama'];
    $_SESSION['auth_success'] = 'Selamat datang kembali, ' . htmlspecialchars($user['nama']) . '!';

} else {
    // New user — redirect to registration completion page
    if (empty($g_email)) {
        $_SESSION['auth_error'] = 'Akun Google tidak memiliki email yang dapat digunakan.';
        header('Location: ' . base_url('auth/login'));
        exit;
    }

    $sesid = bin2hex(random_bytes(16));
    $_SESSION['google_reg'][$sesid] = [
        'email'      => $g_email,
        'nama'       => $g_name,
        'google_id'  => $google_id,
        'avatar_url' => $g_picture,
        'expires'    => time() + 3600
    ];

    header('Location: ' . base_url('auth/register/google/' . $sesid));
    exit;
}

// Redirect to intended destination or dashboard
$redirect = $_SESSION['redirect_after_login'] ?? base_url('hosting');
unset($_SESSION['redirect_after_login']);
header('Location: ' . $redirect);
exit;


// ─── Helper functions ─────────────────────────────────────────────────────────

function curlPost(string $url, array $data): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ?: '';
}

function curlGet(string $url, string $access_token): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ?: '';
}
