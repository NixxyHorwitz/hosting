<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/mailer.php';

$now = date('Y-m-d H:i:s');
// Notifikasi untuk yang akan expired dalam 7 hari kedepan
$target_date = date('Y-m-d', strtotime('+7 days'));

echo "--- Memulai Cron Grace Period: $now ---\n";

$query_settings = mysqli_query($conn, "SELECT payment_api_key, payment_secret_key FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($query_settings);
$api_key = $settings['payment_api_key'];
$secret_key = $settings['payment_secret_key'];

$query = mysqli_query($conn, "
    SELECT orders.id, orders.user_id, orders.domain, orders.expiry_date, orders.total_harga, users.email, users.nama 
    FROM orders 
    LEFT JOIN users ON orders.user_id = users.id 
    WHERE orders.status = 'active' AND DATE(orders.expiry_date) = '$target_date'
");

if (mysqli_num_rows($query) > 0) {
    while ($row = mysqli_fetch_assoc($query)) {
        try {
            // 1. Buat Tagihan ke API Asteelass
            $amount = $row['total_harga'];
            $data_post = json_encode(['amount' => $amount]);
            
            $ch = curl_init("https://asteelass.icu/api/deposit.php");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-KEY: ' . $api_key,
                'X-SECRET-KEY: ' . $secret_key
            ]);
            $response = curl_exec($ch);
            $result = json_decode($response, true);
            curl_close($ch);

            if (isset($result['status']) && $result['status'] == true) {
                // 2. Insert ke tabel invoices
                $ref_id = $result['reference_id'];
                $qr_url = $result['qr_code_url'] ?? '';
                $user_id = $row['user_id'];
                $order_id = $row['id'];
                $due_date = $row['expiry_date'];
                
                // Mencegah duplikasi tagihan per order
                $cek = mysqli_query($conn, "SELECT id FROM invoices WHERE order_id = '$order_id' AND jenis_tagihan = 'perpanjangan' AND status = 'unpaid'");
                if (mysqli_num_rows($cek) == 0) {
                    $sql_inv = "INSERT INTO invoices (user_id, order_id, jenis_tagihan, total_tagihan, status, reference_id, qr_code_url, date_due) 
                                VALUES ('$user_id', '$order_id', 'perpanjangan', '$amount', 'unpaid', '$ref_id', '$qr_url', '$due_date')";
                    mysqli_query($conn, $sql_inv);
                }
            }

            $sent = sendEmailTemplate($row['email'], $row['nama'], 'grace_period', [
                'nama' => $row['nama'],
                'domain' => $row['domain']
            ]);
            
            if ($sent) {
                echo "[SUCCESS] Notifikasi masa tenggang ke {$row['domain']} terkirim.\n";
            } else {
                echo "[ERROR] Gagal mengirim email ke {$row['domain']}\n";
            }
        } catch (Exception $e) {
            echo "[ERROR] exception: {$e->getMessage()}\n";
        }
    }
} else {
    echo "Tidak ada hosting yang masuk masa tenggang hari ini.\n";
}

echo "--- Proses Selesai ---\n";
