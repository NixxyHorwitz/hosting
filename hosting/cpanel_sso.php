<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php'; 
require_once __DIR__ . '/../core/WHMClient.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    die("❌ Akses ditolak. Silakan login kembali.");
}

$id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];
$app = isset($_GET['app']) ? $_GET['app'] : '';

$query = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$id' AND user_id = '$user_id' LIMIT 1");

if (!$query) {
    die("❌ Query Error: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("❌ Akun hosting (ID: $id) tidak ditemukan atau Anda tidak memiliki akses ke data ini.");
}

$cpanel_user = $data['username']; 

if (empty($cpanel_user)) {
    die("❌ Username cPanel untuk pesanan ini masih kosong di database.");
}

try {
    $whm_id = (int)$data['whm_id'];
    $whmQuery = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id'");
    $whm_server = mysqli_fetch_assoc($whmQuery);
    if(!$whm_server) { die("❌ Server WHM tidak ditemukan."); }
    $whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);
    $session = $whm->createCpanelSession($cpanel_user, $app);

    if (isset($session['data']['url'])) {
        $loginUrl = $session['data']['url'];
        header("Location: " . $loginUrl);
        exit;
    } else {
        $reason = isset($session['metadata']['reason']) ? $session['metadata']['reason'] : 'Gagal mendapatkan token dari server.';
        throw new Exception($reason);
    }
} catch (Exception $e) {
    echo "<h3>Gagal Login Otomatis</h3>";
    echo "Pesan Error: " . $e->getMessage();
    echo "<br><hr><small>Pastikan IP Server Hosting sudah di-whitelist dan Token WHM valid.</small>";
}
