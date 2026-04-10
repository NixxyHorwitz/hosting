<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/mailer.php';

if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

$message_type = "";
$message_text = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forgot'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $q     = mysqli_query($conn, "SELECT id, nama FROM users WHERE email = '$email'");
    if (mysqli_num_rows($q) > 0) {
        $user   = mysqli_fetch_assoc($q);
        $token  = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        mysqli_query($conn, "UPDATE users SET reset_token='$token', reset_expiry='$expiry' WHERE id='{$user['id']}'");
        $reset_link = base_url('auth/reset/' . $token);
        sendEmailTemplate($email, $user['nama'], 'forgot_password', ['nama' => $user['nama'], 'reset_link' => $reset_link]);
        $message_type = "success";
    } else {
        $message_type = "error";
        $message_text = "Email tidak ditemukan di sistem kami.";
    }
}

$_ls = @mysqli_query($conn, "SELECT site_name, site_logo FROM settings LIMIT 1");
$_s  = ($_ls ? mysqli_fetch_assoc($_ls) : []) ?? [];
$_site_name = htmlspecialchars($_s['site_name'] ?? 'SobatHosting');
$_site_logo = $_s['site_logo'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password — <?= $_site_name ?></title>
    <?php if(!empty($_site_logo)): ?><link rel="icon" href="/uploads/<?= htmlspecialchars($_site_logo) ?>" type="image/x-icon"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style><?php include __DIR__ . '/auth_shared.css.php'; ?></style>
</head>
<body>

<!-- Brand Panel -->
<div class="brand-panel">
    <a href="/" class="brand-logo">
        <div class="brand-logo-icon"><i class="ph ph-cloud-arrow-up"></i></div>
        <?= $_site_name ?>
    </a>
    <div>
        <div class="brand-tagline">Pulihkan<br>Akses Anda</div>
        <div class="brand-desc">Jangan khawatir! Kami akan membantu Anda mendapatkan kembali akses ke akun Anda dengan aman.</div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> <?= $_site_name ?> · Semua hak dilindungi</div>
</div>

<!-- Center Card -->
<div class="auth-center">
    <div class="auth-card">
        <div class="auth-icon-wrap"><i class="ph ph-envelope-open"></i></div>
        <div class="auth-card-title">Lupa Password?</div>
        <div class="auth-card-sub">Masukkan email Anda dan kami akan mengirimkan link untuk membuat password baru.</div>

        <?php if($message_type === 'error'): ?>
        <div class="alert-box alert-err"><i class="ph-fill ph-warning-circle"></i><?= htmlspecialchars($message_text) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="field-group">
                <label class="field-label">Alamat Email <span class="req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-envelope field-icon"></i>
                    <input type="email" name="email" class="form-input" placeholder="contoh@email.com" required>
                </div>
            </div>
            <button type="submit" name="forgot" class="btn-submit">
                <i class="ph ph-paper-plane-tilt"></i> Kirim Link Reset
            </button>
        </form>

        <p class="bottom-note" style="margin-top:20px;">
            <a href="<?= base_url('auth/login') ?>"><i class="ph ph-arrow-left"></i> Kembali ke Login</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if($message_type === 'success'): ?>
Swal.fire({
    icon: 'success',
    title: 'Email Terkirim!',
    html: 'Cek inbox email Anda untuk link reset password.<br><small style="color:#6c757d;">Link berlaku selama 1 jam.</small>',
    confirmButtonColor: '#3b5bdb',
    confirmButtonText: 'OK'
}).then(() => { window.location.href = '<?= base_url('auth/login') ?>'; });
<?php endif; ?>
</script>
</body>
</html>
