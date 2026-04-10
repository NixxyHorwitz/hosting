<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';

// =========================
// AMBIL ORDER + EMAIL USER
// =========================
$orderQuery = $conn->query("
    SELECT orders.*, users.email, users.nama 
    FROM orders 
    LEFT JOIN users ON orders.user_id = users.id 
    WHERE orders.status='pending' 
    AND orders.status_pembayaran='success'
    LIMIT 1
");

if ($orderQuery->num_rows == 0) {
    die("Tidak ada order pending");
}

$order = $orderQuery->fetch_assoc();

// =========================
// DATA USER
// =========================
$username = 'client' . rand(100,999);
$password = '54skjnasgha#jkjdh!' . rand(1000,9999);
$email    = $order['email'];
$domain   = $order['domain'];
$package  = $order['whm_package_name']; 

if (empty($email)) {
    die("❌ Email user tidak ditemukan");
}

require_once __DIR__ . '/../core/mailer.php';

// =========================
// INIT WHM
// =========================
$whm_id = (int)$order['whm_id'];
$whmQuery = $conn->query("SELECT * FROM whm_servers WHERE id = '$whm_id'");
if ($whmQuery->num_rows == 0) {
    die("❌ Server WHM (ID: $whm_id) tidak ditemukan atau sudah dihapus.");
}
$whm_server = $whmQuery->fetch_assoc();
$whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);

try {

    // =========================
    // STEP 1: SIMPAN DATA + LANGSUNG ACTIVE
    // =========================
    $stmt = $conn->prepare("UPDATE orders SET status='active', username=?, password=? WHERE id=?");

    if (!$stmt) {
        die("❌ Prepare error (awal): " . $conn->error);
    }

    $stmt->bind_param("ssi", $username, $password, $order['id']);
    $stmt->execute();
    $stmt->close();

    // =========================
    // STEP 2: CREATE CPANEL
    // =========================
    $whm->createAccount([
        'username'      => $username,
        'domain'        => $domain,
        'password'      => $password,
        'contactemail'  => $email,
        'plan'          => $package
    ]);

    // Send Email
    sendEmailTemplate($email, $order['nama'], 'order_hosting', [
        'nama'     => $order['nama'],
        'domain'   => $domain,
        'username' => $username,
        'password' => $password
    ]);

    // =========================
    // OUTPUT
    // =========================
    echo "<h2>✅ Hosting berhasil dibuat</h2>";
    echo "Domain: $domain <br>";
    echo "Username: $username <br>";
    echo "Password: $password <br>";

} catch (Exception $e) {

    // kalau gagal → balikin ke pending
    if ($conn->ping()) {
        $stmt = $conn->prepare("UPDATE orders SET status='pending' WHERE id=?");

        if ($stmt) {
            $stmt->bind_param("i", $order['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<h2>❌ Gagal:</h2>";
    echo $e->getMessage();
}