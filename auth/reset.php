<?php
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

// Get token from URL segment (router passes as $params[0] or $_GET['id'])
$token = $_GET['params'][0] ?? $_GET['id'] ?? '';
if (empty($token)) { header("Location: " . base_url('auth/forgot')); exit(); }

$message_type  = "";
$message_text  = "";
$valid_token   = false;
$user          = null;

$q = mysqli_query($conn, "SELECT id, nama, reset_expiry FROM users WHERE reset_token='$token' LIMIT 1");
if (mysqli_num_rows($q) > 0) {
    $user = mysqli_fetch_assoc($q);
    if (strtotime($user['reset_expiry']) > time()) {
        $valid_token = true;
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset'])) {
            $p1 = $_POST['password'] ?? '';
            $p2 = $_POST['password_confirm'] ?? '';
            if (strlen($p1) < 6) {
                $message_type = "error";
                $message_text = "Password minimal 6 karakter.";
            } elseif ($p1 !== $p2) {
                $message_type = "error";
                $message_text = "Konfirmasi password tidak cocok.";
            } else {
                $hash = password_hash($p1, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password='$hash', reset_token=NULL, reset_expiry=NULL WHERE id='{$user['id']}'");
                $message_type = "success";
            }
        }
    } else {
        $message_text = "Link reset password sudah kedaluwarsa (lebih dari 1 jam).";
    }
} else {
    $message_text = "Link reset password tidak valid atau sudah digunakan.";
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
    <title>Reset Password — <?= $_site_name ?></title>
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
        <div class="brand-tagline">Buat Password<br>Baru</div>
        <div class="brand-desc">Pilih password baru yang kuat untuk melindungi akun Anda. Minimal 6 karakter.</div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> <?= $_site_name ?> · Semua hak dilindungi</div>
</div>

<!-- Center Card -->
<div class="auth-center">
    <div class="auth-card">
        <?php if (!$valid_token && empty($message_type)): ?>
        <!-- Invalid / expired token -->
        <div class="auth-icon-wrap" style="background:#fff5f5;color:#c92a2a;"><i class="ph ph-x-circle"></i></div>
        <div class="auth-card-title">Link Tidak Valid</div>
        <div class="auth-card-sub"><?= htmlspecialchars($message_text) ?></div>
        <a href="<?= base_url('auth/forgot') ?>" class="btn-submit" style="text-decoration:none;">
            <i class="ph ph-arrow-counter-clockwise"></i> Minta Link Baru
        </a>

        <?php elseif ($message_type === 'success'): ?>
        <!-- Success -->
        <div class="auth-icon-wrap" style="background:#f0fdf4;color:#166534;"><i class="ph ph-check-circle"></i></div>
        <div class="auth-card-title">Password Diperbarui!</div>
        <div class="auth-card-sub">Password Anda berhasil diubah. Silakan masuk dengan password baru.</div>
        <a href="<?= base_url('auth/login') ?>" class="btn-submit" style="text-decoration:none;">
            <i class="ph ph-sign-in"></i> Masuk Sekarang
        </a>

        <?php else: ?>
        <!-- Reset form -->
        <div class="auth-icon-wrap"><i class="ph ph-lock-key"></i></div>
        <div class="auth-card-title">Buat Password Baru</div>
        <div class="auth-card-sub">Halo <?= htmlspecialchars($user['nama'] ?? '') ?>! Masukkan password baru Anda di bawah ini.</div>

        <?php if($message_type === 'error'): ?>
        <div class="alert-box alert-err"><i class="ph-fill ph-warning-circle"></i><?= htmlspecialchars($message_text) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="field-group">
                <label class="field-label">Password Baru <span class="req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-lock field-icon"></i>
                    <input type="password" name="password" id="newPass" class="form-input" placeholder="Minimal 6 karakter" required minlength="6">
                    <button type="button" class="pass-toggle" onclick="togglePass('newPass','newIco')">
                        <i class="ph ph-eye" id="newIco"></i>
                    </button>
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Konfirmasi Password <span class="req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-lock-simple field-icon"></i>
                    <input type="password" name="password_confirm" id="confPass" class="form-input" placeholder="Ulangi password" required minlength="6">
                    <button type="button" class="pass-toggle" onclick="togglePass('confPass','confIco')">
                        <i class="ph ph-eye" id="confIco"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="reset" class="btn-submit">
                <i class="ph ph-floppy-disk"></i> Simpan Password Baru
            </button>
        </form>
        <?php endif; ?>

        <p class="bottom-note" style="margin-top:20px;">
            <a href="<?= base_url('auth/login') ?>"><i class="ph ph-arrow-left"></i> Kembali ke Login</a>
        </p>
    </div>
</div>

<script>
function togglePass(id, ico) {
    const i = document.getElementById(id), ic = document.getElementById(ico);
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'password' ? 'ph ph-eye' : 'ph ph-eye-slash';
}
</script>
</body>
</html>
