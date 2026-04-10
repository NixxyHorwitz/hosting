<?php
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

$otpsession = $_GET['params'][0] ?? $_GET['id'] ?? '';
if (empty($otpsession) || !isset($_SESSION['otp_session']) || $_SESSION['otp_session'] !== $otpsession) {
    header("Location: " . base_url('auth/login'));
    exit();
}

$message_type = "";
$message_text = "";
$user_id      = $_SESSION['otp_user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    // Support both single input and 6 separate digit boxes
    $otp_input = '';
    if (isset($_POST['otp'])) {
        $otp_input = preg_replace('/\D/', '', $_POST['otp']);
    } elseif (isset($_POST['d1'])) {
        for ($i = 1; $i <= 6; $i++) {
            $otp_input .= preg_replace('/\D/', '', $_POST["d$i"] ?? '');
        }
    }
    $otp_input = mysqli_real_escape_string($conn, $otp_input);

    $q = mysqli_query($conn, "SELECT id, otp_code, otp_expiry FROM users WHERE id='$user_id'");
    if (mysqli_num_rows($q) > 0) {
        $user = mysqli_fetch_assoc($q);
        if ($user['otp_code'] === $otp_input) {
            if (strtotime($user['otp_expiry']) > time()) {
                mysqli_query($conn, "UPDATE users SET status='active', otp_code=NULL, otp_expiry=NULL WHERE id='$user_id'");
                $message_type = "success";
                unset($_SESSION['otp_session'], $_SESSION['otp_user_id']);
            } else {
                $message_type = "error";
                $message_text = "Kode OTP sudah kedaluwarsa. Silakan daftar ulang.";
            }
        } else {
            $message_type = "error";
            $message_text = "Kode OTP tidak valid. Periksa kembali email Anda.";
        }
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
    <title>Verifikasi OTP — <?= $_site_name ?></title>
    <?php if(!empty($_site_logo)): ?><link rel="icon" href="/uploads/<?= htmlspecialchars($_site_logo) ?>" type="image/x-icon"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
    <?php include __DIR__ . '/auth_shared.css.php'; ?>
    /* OTP digit boxes */
    .otp-boxes { display: flex; gap: 10px; justify-content: center; margin: 8px 0 20px; }
    .otp-box {
        width: 52px; height: 58px;
        border: 2px solid var(--border); border-radius: 12px;
        text-align: center; font-size: 24px; font-weight: 800;
        color: var(--text); background: var(--input-bg); outline: none;
        transition: border-color .15s, box-shadow .15s; font-family: inherit;
    }
    .otp-box:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,91,219,.12); background: #fff; }
    .resend-link { font-size: 12px; color: var(--sub); text-align: center; margin-top: 16px; }
    .resend-link a { color: var(--blue); font-weight: 600; text-decoration: none; }
    .resend-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- Brand Panel -->
<div class="brand-panel">
    <a href="/" class="brand-logo">
        <div class="brand-logo-icon"><i class="ph ph-cloud-arrow-up"></i></div>
        <?= $_site_name ?>
    </a>
    <div>
        <div class="brand-tagline">Verifikasi<br>Email Anda</div>
        <div class="brand-desc">Kami mengirimkan kode 6 digit ke email Anda. Kode berlaku selama 10 menit.</div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> <?= $_site_name ?> · Semua hak dilindungi</div>
</div>

<!-- Center Card -->
<div class="auth-center">
    <div class="auth-card">
        <div class="auth-icon-wrap"><i class="ph ph-shield-check"></i></div>
        <div class="auth-card-title">Masukkan Kode OTP</div>
        <div class="auth-card-sub">Kami telah mengirimkan kode verifikasi 6 digit ke email Anda. Masukkan kode di bawah ini.</div>

        <?php if($message_type === 'error'): ?>
        <div class="alert-box alert-err"><i class="ph-fill ph-warning-circle"></i><?= htmlspecialchars($message_text) ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="otpForm">
            <!-- 6 individual digit boxes -->
            <div class="otp-boxes">
                <?php for($i=1;$i<=6;$i++): ?>
                <input type="text" name="d<?= $i ?>" id="d<?= $i ?>" class="otp-box"
                       maxlength="1" inputmode="numeric" pattern="[0-9]"
                       autocomplete="one-time-code" required>
                <?php endfor; ?>
            </div>
            <!-- Hidden combined input for fallback -->
            <input type="hidden" name="otp" id="otpCombined">

            <button type="submit" name="verify_otp" class="btn-submit">
                <i class="ph ph-check-circle"></i> Verifikasi
            </button>
        </form>

        <div class="resend-link">
            Tidak menerima kode? <a href="<?= base_url('auth/register') ?>">Daftar ulang</a>
        </div>

        <p class="bottom-note" style="margin-top:16px;">
            <a href="<?= base_url('auth/login') ?>"><i class="ph ph-arrow-left"></i> Kembali ke Login</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Auto-focus next box & paste support
const boxes = document.querySelectorAll('.otp-box');
boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        const v = e.target.value.replace(/\D/g,'');
        e.target.value = v ? v[0] : '';
        if (v && i < boxes.length - 1) boxes[i+1].focus();
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) boxes[i-1].focus();
    });
});

// Handle paste on any box
boxes[0].addEventListener('paste', e => {
    e.preventDefault();
    const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
    boxes.forEach((b, i) => { b.value = data[i] || ''; });
    if (data.length >= 6) boxes[5].focus();
});

// Combine digits before submit
document.getElementById('otpForm').addEventListener('submit', () => {
    let val = '';
    boxes.forEach(b => val += b.value);
    document.getElementById('otpCombined').value = val;
});

<?php if($message_type === 'success'): ?>
Swal.fire({
    icon: 'success',
    title: 'Akun Aktif!',
    text: 'Verifikasi berhasil. Akun Anda telah diaktifkan, silakan login.',
    confirmButtonColor: '#3b5bdb',
    confirmButtonText: 'Masuk Sekarang'
}).then(() => { window.location.href = '<?= base_url('auth/login') ?>'; });
<?php endif; ?>
</script>
</body>
</html>
