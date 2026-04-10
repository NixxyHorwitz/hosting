<?php
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

$otpsession = $_GET['params'][0] ?? $_GET['id'] ?? '';
if (empty($otpsession) || !isset($_SESSION['otp_session']) || $_SESSION['otp_session'] !== $otpsession) {
    header("Location: " . base_url('auth/login')); exit();
}

$user_id = $_SESSION['otp_user_id'] ?? 0;

// ─── AJAX HANDLER ─────────────────────────────────────────────────────────────
if (!empty($_POST['_ajax']) && $_POST['_ajax'] === 'verify_otp') {
    header('Content-Type: application/json');
    $otp_input = '';
    if (isset($_POST['otp'])) {
        $otp_input = preg_replace('/\D/', '', $_POST['otp']);
    } elseif (isset($_POST['d1'])) {
        for ($i = 1; $i <= 6; $i++) $otp_input .= preg_replace('/\D/', '', $_POST["d$i"] ?? '');
    }
    $otp_input = mysqli_real_escape_string($conn, $otp_input);

    $q = mysqli_query($conn, "SELECT id, otp_code, otp_expiry FROM users WHERE id='$user_id' LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) {
        echo json_encode(['ok'=>false,'msg'=>'User tidak ditemukan.']); exit();
    }
    $user = mysqli_fetch_assoc($q);
    if ($user['otp_code'] !== $otp_input) {
        echo json_encode(['ok'=>false,'msg'=>'Kode OTP tidak valid. Periksa kembali email Anda.']); exit();
    }
    if (strtotime($user['otp_expiry']) <= time()) {
        echo json_encode(['ok'=>false,'msg'=>'Kode OTP sudah kedaluwarsa. Silakan daftar ulang.']); exit();
    }
    mysqli_query($conn, "UPDATE users SET status='active', otp_code=NULL, otp_expiry=NULL WHERE id='$user_id'");
    unset($_SESSION['otp_session'], $_SESSION['otp_user_id']);
    echo json_encode(['ok'=>true,'msg'=>'Verifikasi berhasil. Akun Anda telah diaktifkan!']); exit();
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
    <style>
    <?php include __DIR__ . '/auth_shared.css.php'; ?>
    .otp-boxes { display:flex; gap:10px; justify-content:center; margin:8px 0 20px; }
    .otp-box {
        width:52px; height:58px;
        border:2px solid var(--border); border-radius:12px;
        text-align:center; font-size:24px; font-weight:800;
        color:var(--text); background:var(--input-bg); outline:none;
        transition:border-color .15s, box-shadow .15s; font-family:inherit;
    }
    .otp-box:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(59,91,219,.12); background:#fff; }
    .resend-link { font-size:12px; color:var(--sub); text-align:center; margin-top:16px; }
    .resend-link a { color:var(--blue); font-weight:600; text-decoration:none; }
    /* Custom modal */
    .auth-modal-overlay {
        display:none; position:fixed; inset:0; z-index:9999;
        background:rgba(0,0,0,.55); backdrop-filter:blur(4px);
        align-items:center; justify-content:center;
    }
    .auth-modal-overlay.show { display:flex; animation:fadeIn .15s ease; }
    .auth-modal {
        background:#fff; border-radius:18px; padding:32px 28px 24px;
        max-width:360px; width:90%; text-align:center;
        box-shadow:0 24px 60px rgba(0,0,0,.18); animation:slideUp .2s ease;
    }
    .auth-modal-icon { font-size:40px; margin-bottom:12px; }
    .auth-modal-title { font-size:17px; font-weight:800; color:#1a1a2e; margin-bottom:8px; }
    .auth-modal-text  { font-size:13.5px; color:#555; line-height:1.6; margin-bottom:20px; }
    .auth-modal-btn {
        display:inline-block; padding:10px 28px; border-radius:10px;
        font-size:13px; font-weight:700; border:none; cursor:pointer;
        background:#1971c2; color:#fff; transition:opacity .15s;
    }
    .auth-modal-btn:hover { opacity:.85; }
    .btn-submit .spinner { display:none; width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle; }
    .btn-submit.loading .spinner { display:inline-block; }
    .btn-submit.loading .btn-text { opacity:.7; }
    @keyframes fadeIn  { from{opacity:0} to{opacity:1} }
    @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
    @keyframes spin    { to{transform:rotate(360deg)} }
    </style>
</head>
<body>

<div class="auth-modal-overlay" id="authModal">
    <div class="auth-modal">
        <div class="auth-modal-icon" id="modalIcon"></div>
        <div class="auth-modal-title" id="modalTitle"></div>
        <div class="auth-modal-text" id="modalText"></div>
        <button class="auth-modal-btn" id="modalBtn" onclick="closeModal()">OK</button>
    </div>
</div>

<div class="brand-panel">
    <a href="/" class="brand-logo">
        <div class="brand-logo-icon"><i class="ph ph-cloud-arrow-up"></i></div>
        <?= $_site_name ?>
    </a>
    <div>
        <div class="brand-tagline">Verifikasi<br>Email Anda</div>
        <div class="brand-desc">Kami mengirimkan kode 6 digit ke email Anda. Kode berlaku selama 15 menit.</div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> <?= $_site_name ?> · Semua hak dilindungi</div>
</div>

<div class="auth-center">
    <div class="auth-card">
        <div class="auth-icon-wrap"><i class="ph ph-shield-check"></i></div>
        <div class="auth-card-title">Masukkan Kode OTP</div>
        <div class="auth-card-sub">Kami telah mengirimkan kode verifikasi 6 digit ke email Anda.</div>

        <div id="otpAlert" class="alert-box alert-err" style="display:none;"></div>

        <div class="otp-boxes" id="otpBoxes">
            <?php for($i=1;$i<=6;$i++): ?>
            <input type="text" id="d<?= $i ?>" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="<?= $i===1?'one-time-code':'off' ?>">
            <?php endfor; ?>
        </div>

        <button type="button" id="btnVerify" class="btn-submit" onclick="doVerifyOtp()">
            <span class="spinner"></span>
            <span class="btn-text"><i class="ph ph-check-circle"></i> Verifikasi</span>
        </button>

        <div class="resend-link">Tidak menerima kode? <a href="<?= base_url('auth/register') ?>">Daftar ulang</a></div>
        <p class="bottom-note" style="margin-top:16px;">
            <a href="<?= base_url('auth/login') ?>"><i class="ph ph-arrow-left"></i> Kembali ke Login</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
const OTP_URL = '<?= base_url('auth/otp/' . $otpsession) ?>';
const LOGIN_URL = '<?= base_url('auth/login') ?>';

// ── Modal helpers
function showModal(type, title, text, onok) {
    const icons  = { success:'✅', error:'❌', info:'ℹ️' };
    const colors = { success:'#2f9e44', error:'#e03131', info:'#1971c2' };
    $('#modalIcon').text(icons[type]||'ℹ️');
    $('#modalTitle').text(title);
    $('#modalText').text(text);
    $('#modalBtn').css('background', colors[type]||'#1971c2');
    $('#authModal').data('onok', onok||null).addClass('show');
}
function closeModal() {
    const cb = $('#authModal').data('onok');
    $('#authModal').removeClass('show');
    if (typeof cb === 'function') cb();
}

// ── OTP box navigation
const boxes = document.querySelectorAll('.otp-box');
boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        const v = e.target.value.replace(/\D/g,'');
        e.target.value = v ? v[0] : '';
        if (v && i < boxes.length - 1) boxes[i+1].focus();
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) boxes[i-1].focus();
        if (e.key === 'Enter') doVerifyOtp();
    });
});
boxes[0].addEventListener('paste', e => {
    e.preventDefault();
    const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
    boxes.forEach((b, i) => { b.value = data[i] || ''; });
    if (data.length >= 6) boxes[5].focus();
});

function getOtpValue() {
    return Array.from(boxes).map(b => b.value).join('');
}

function doVerifyOtp() {
    const otp = getOtpValue();
    if (otp.length < 6) {
        $('#otpAlert').text('Harap isi semua 6 digit kode OTP.').show();
        return;
    }
    $('#otpAlert').hide();
    const btn = $('#btnVerify');
    btn.prop('disabled', true).addClass('loading');

    $.post(OTP_URL, { _ajax: 'verify_otp', otp: otp }, 'json')
        .done(data => {
            if (data.ok) {
                showModal('success', 'Akun Aktif!', data.msg, () => {
                    window.location.href = LOGIN_URL;
                });
            } else {
                $('#otpAlert').text(data.msg).show();
                btn.prop('disabled', false).removeClass('loading');
            }
        })
        .fail(() => {
            $('#otpAlert').text('Koneksi gagal. Coba lagi.').show();
            btn.prop('disabled', false).removeClass('loading');
        });
}

document.getElementById('d1').focus();
</script>
</body>
</html>
