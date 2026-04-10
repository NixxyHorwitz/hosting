<?php
// Menggunakan __DIR__ agar path tetap aman saat dipanggil oleh sistem cron
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';
require_once __DIR__ . '/../core/mailer.php';


$now = date('Y-m-d H:i:s');

echo "--- Memulai Proses Cron Suspend: $now ---\n";

/**
 * LOGIKA:
 * 1. Cari akun yang 'active' tapi 'expiry_date' sudah lewat.
 * 2. Suspend di WHM.
 * 3. Update status akun jadi 'suspended'.
 * 4. Update status_pembayaran jadi 'pending' (agar mereka harus bayar lagi untuk perpanjang).
 */

$query_expired = mysqli_query($conn, "SELECT orders.id, orders.username, orders.domain, orders.whm_id, users.email, users.nama FROM orders LEFT JOIN users ON orders.user_id = users.id WHERE orders.status = 'active' AND orders.expiry_date <= '$now'");

if (mysqli_num_rows($query_expired) > 0) {
    while ($row = mysqli_fetch_assoc($query_expired)) {
        $id = $row['id'];
        $user = $row['username'];
        $whm_id = (int)$row['whm_id'];

        // Ambil Data WHM Node
        $whmQuery = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id'");
        $whm_server = mysqli_fetch_assoc($whmQuery);
        
        if(!$whm_server) {
            echo "[ERROR] Gagal memproses $user: WHM Node $whm_id tidak ditemukan.\n";
            continue;
        }

        $whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);

        try {
            // 1. Perintah Suspend ke WHM
            $whm->suspendAccount($user, "Masa aktif habis. Silahkan lakukan pembayaran perpanjangan.");

            // 2. Update Database (Status Akun & Status Pembayaran)
            $update = mysqli_query($conn, "UPDATE orders SET 
                status = 'suspended', 
                status_pembayaran = 'pending' 
                WHERE id = '$id'");
            
            if ($update) {
                echo "[SUCCESS] Akun $user telah di-suspend dan status_pembayaran di-reset ke pending.\n";
                sendEmailTemplate($row['email'], $row['nama'], 'suspend_hosting', [
                    'nama' => $row['nama'],
                    'domain' => $row['domain']
                ]);
            }
        } catch (Exception $e) {
            echo "[ERROR] Gagal memproses $user: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "Tidak ada akun yang expired saat ini.\n";
}

echo "--- Proses Selesai ---\n";