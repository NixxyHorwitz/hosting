<?php
require_once __DIR__ . '/library/admin_session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/mailer.php';

$page_title = "SMTP Tester";
include __DIR__ . '/library/header.php';

$message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp'])) {
    $test_email = mysqli_real_escape_string($conn, trim($_POST['test_email']));
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email tujuan tidak valid.";
        $alert_type = 'danger';
    } else {
        $GLOBALS['is_smtp_test'] = true;

        // Ensure template exists
        $cek_tpl = mysqli_query($conn, "SELECT id FROM email_templates WHERE name='test_connection'");
        if(mysqli_num_rows($cek_tpl) == 0) {
            mysqli_query($conn, "INSERT INTO email_templates (name, subject, body, variables) VALUES ('test_connection', 'Test Koneksi SMTP Berhasil', '<h2>Halo :nama:!</h2><p>Jika Anda membaca email ini, maka konfigurasi SMTP Anda beroperasi tanpa kendala.</p>', ':nama:')");
        }

        // Coba kirim email test
        $res = sendEmailTemplate($test_email, $test_email, 'test_connection', [
            'nama' => 'Konfigurasi Anda'
        ]);
        
        if ($res === true) {
            $message = "Email percobaan berhasil dikirim ke $test_email! Jika Anda tidak menerimanya, periksa folder Spam.";
            $alert_type = 'success';
        } else {
            $message = "Gagal. Konfigurasi SMTP belum benar. Error: " . (is_string($res) ? $res : 'Unknown error');
            $alert_type = 'danger';
        }
    }
}
?>

<div class="page-header">
    <h1><i class="ph-fill ph-paper-plane-tilt me-2 text-primary"></i> SMTP Tester</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/index') ?>">Admin</a></li>
            <li class="breadcrumb-item active">SMTP Tester</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card-c">
            <div class="ch">
                <h3 class="ct"><i class="ph-fill ph-envelope-simple me-2"></i> Kirim Test Email</h3>
            </div>
            <div class="cb">
                <?php if ($message): ?>
                <div class="alert alert-<?= $alert_type ?> d-flex align-items-center">
                    <i class="ph-fill ph-info border-0 me-2 fs-5"></i>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
                <?php endif; ?>

                <div class="alert alert-info" style="font-size:13px;">
                    Pastikan Anda telah mengisi konfigurasi SMTP di menu <b>Website Settings</b> sebelum menggunakan tester ini.
                </div>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:12px;font-weight:600;color:var(--mut);">Email Tujuan</label>
                        <input type="email" name="test_email" class="form-control form-control-sm" placeholder="anda@gmail.com" required>
                    </div>
                    <button type="submit" name="test_smtp" class="btn btn-primary btn-sm px-4"><i class="ph-fill ph-paper-plane-right me-1"></i> Uji Pengiriman</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/library/footer.php'; ?>
