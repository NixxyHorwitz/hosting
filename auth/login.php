<?php
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

// ─── AJAX HANDLER ────────────────────────────────────────────────────────────
if (!empty($_POST['_ajax'])) {
    header('Content-Type: application/json');
    $act = $_POST['_ajax'];

    // ── Login
    if ($act === 'login') {
        $email    = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $q = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' LIMIT 1");
        if (!$q || mysqli_num_rows($q) === 0) {
            echo json_encode(['ok'=>false,'msg'=>'Email tidak ditemukan di sistem kami.']); exit();
        }
        $user = mysqli_fetch_assoc($q);
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['ok'=>false,'msg'=>'Password yang Anda masukkan salah.']); exit();
        }
        if ($user['status'] !== 'active') {
            echo json_encode(['ok'=>false,'msg'=>'Akun belum aktif. Silakan verifikasi email Anda.']); exit();
        }
        if (!empty($user['is_2fa_enabled']) && $user['is_2fa_enabled'] == 1) {
            $_SESSION['pending_2fa_id'] = $user['id'];
            echo json_encode(['ok'=>true,'action'=>'show_2fa']); exit();
        }
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_nama'] = $user['nama'];
        mysqli_query($conn, "UPDATE users SET last_login=NOW(), last_ip='" . mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']) . "' WHERE id='{$user['id']}'");
        echo json_encode(['ok'=>true,'action'=>'redirect','url'=>'/hosting','nama'=>htmlspecialchars($user['nama'])]); exit();
    }

    // ── Verify 2FA OTP
    if ($act === 'verify_2fa') {
        if (!isset($_SESSION['pending_2fa_id'])) {
            echo json_encode(['ok'=>false,'msg'=>'Sesi 2FA tidak valid.']); exit();
        }
        $uid  = (int)$_SESSION['pending_2fa_id'];
        $code = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');
        $q    = mysqli_query($conn, "SELECT * FROM users WHERE id='$uid' LIMIT 1");
        $u    = mysqli_fetch_assoc($q);
        require_once __DIR__ . '/../core/GoogleAuthenticator.php';
        $ga = new PHPGangsta_GoogleAuthenticator();
        if (!$ga->verifyCode($u['gauth_secret'], $code, 2)) {
            echo json_encode(['ok'=>false,'msg'=>'Kode OTP tidak valid atau kadaluarsa.']); exit();
        }
        $_SESSION['user_id']   = $u['id'];
        $_SESSION['user_nama'] = $u['nama'];
        mysqli_query($conn, "UPDATE users SET last_login=NOW(), last_ip='" . mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']) . "' WHERE id='{$u['id']}'");
        unset($_SESSION['pending_2fa_id']);
        echo json_encode(['ok'=>true,'action'=>'redirect','url'=>'/hosting','nama'=>htmlspecialchars($u['nama'])]); exit();
    }

    echo json_encode(['ok'=>false,'msg'=>'Aksi tidak dikenal.']); exit();
}

// Cancel 2FA
if (isset($_GET['cancel_2fa'])) { unset($_SESSION['pending_2fa_id']); header("Location: " . base_url('auth/login')); exit(); }

// Load settings
$_ls   = @mysqli_query($conn, "SELECT site_name, site_logo, site_favicon, google_client_id FROM settings LIMIT 1");
$_lset = ($_ls ? mysqli_fetch_assoc($_ls) : []) ?? [];
$_site_name    = htmlspecialchars($_lset['site_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'SobatHosting'));
$_site_logo    = $_lset['site_logo'] ?? '';
$_site_favicon = $_lset['site_favicon'] ?? '';
$_g_id         = trim($_lset['google_client_id'] ?? '');
$_sso          = !empty($_g_id);
$_google_url   = $_sso ? base_url('auth/google') : '';

$_require_2fa = isset($_SESSION['pending_2fa_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — <?= $_site_name ?></title>
    <?php $_fav = !empty($_site_favicon) ? $_site_favicon : $_site_logo; if (!empty($_fav)): ?>
    <link rel="icon" href="/uploads/<?= htmlspecialchars($_fav) ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <style>
    <?php include __DIR__ . '/auth_shared.css.php'; ?>

    /* ── Custom Modal Alert ── */
    .auth-modal-overlay {
        display: none; position: fixed; inset: 0; z-index: 9999;
        background: rgba(0,0,0,.55); backdrop-filter: blur(4px);
        align-items: center; justify-content: center;
    }
    .auth-modal-overlay.show { display: flex; animation: fadeIn .15s ease; }
    .auth-modal {
        background: #fff; border-radius: 18px; padding: 32px 28px 24px;
        max-width: 360px; width: 90%; text-align: center;
        box-shadow: 0 24px 60px rgba(0,0,0,.18);
        animation: slideUp .2s ease;
    }
    .auth-modal-icon { font-size: 40px; margin-bottom: 12px; }
    .auth-modal-title { font-size: 17px; font-weight: 800; color: #1a1a2e; margin-bottom: 8px; }
    .auth-modal-text  { font-size: 13.5px; color: #555; line-height: 1.6; margin-bottom: 20px; }
    .auth-modal-btn {
        display: inline-block; padding: 10px 28px; border-radius: 10px;
        font-size: 13px; font-weight: 700; border: none; cursor: pointer;
        background: var(--blue); color: #fff; transition: opacity .15s;
    }
    .auth-modal-btn:hover { opacity: .85; }
    .auth-modal-btn.danger { background: #e53e3e; }
    @keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    /* ── Button loader ── */
    .btn-submit .spinner { display:none; width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle; }
    .btn-submit.loading .spinner { display:inline-block; }
    .btn-submit.loading .btn-text { opacity: .7; }
    @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- Custom Alert Modal -->
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
        <?php if(!empty($_site_logo)): ?>
        <img src="/uploads/<?= htmlspecialchars($_site_logo) ?>" alt="<?= $_site_name ?>" style="max-height:38px;max-width:140px;object-fit:contain;">
        <?php else: ?>
        <div class="brand-logo-icon"><i class="ph ph-cloud-arrow-up"></i></div>
        <?= $_site_name ?>
        <?php endif; ?>
    </a>
    <div>
        <div class="brand-tagline">Selamat Datang<br>Kembali!</div>
        <div class="brand-desc">Platform hosting terpercaya dengan uptime 99.9%, dukungan 24/7, dan panel cPanel yang mudah digunakan.</div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> <?= $_site_name ?> · Semua hak dilindungi</div>
</div>

<div class="form-panel">
    <div class="form-topbar">
        Belum punya akun? <a href="<?= base_url('auth/register') ?>">Daftar &rarr;</a>
    </div>

    <div class="form-wrap">

        <?php if($_require_2fa): ?>
        <!-- ── 2FA Panel ── -->
        <div class="form-title" style="text-align:center;"><i class="ph-fill ph-shield-check" style="color:var(--accent);font-size:32px;"></i><br>Keamanan 2FA</div>
        <div class="form-subtitle" style="text-align:center;">Masukkan 6 digit kode OTP dari Authenticator Anda.</div>
        <div id="alert2fa" class="alert-box alert-err" style="display:none;"></div>
        <div class="field-group" style="margin-top:20px;">
            <input type="text" id="otpInput2fa" class="form-input" style="letter-spacing:6px;font-size:1.5rem;text-align:center;" placeholder="123456" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
        </div>
        <button type="button" class="btn-submit mt-2" id="btn2fa" onclick="submit2fa()">
            <span class="spinner"></span>
            <span class="btn-text"><i class="ph ph-lock-key"></i> Verifikasi Kode</span>
        </button>
        <div style="text-align:center;margin-top:20px;">
            <a href="<?= base_url('auth/login') ?>?cancel_2fa=1" class="forgot-link">Batalkan Login &amp; Kembali</a>
        </div>

        <?php else: ?>
        <!-- ── Login Panel ── -->
        <div class="form-title">Masuk Akun</div>
        <div class="form-subtitle">Selamat datang kembali! Masukkan detail akun Anda.</div>

        <?php if($_sso): ?>
        <a href="<?= htmlspecialchars($_google_url) ?>" class="btn-google">
        <?php else: ?>
        <a href="#" class="btn-google disabled" onclick="showModal('info','Google SSO','Login Google belum dikonfigurasi oleh admin.');return false;">
        <?php endif; ?>
            <svg width="18" height="18" viewBox="0 0 48 48"><g>
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </g></svg>
            <span>Masuk dengan Google<?= !$_sso ? ' (Tidak Aktif)' : '' ?></span>
        </a>

        <div class="or-divider"><div></div><span>atau masuk dengan email</span><div></div></div>

        <div class="field-group">
            <label class="field-label">Alamat Email <span class="req">*</span></label>
            <div class="field-wrap">
                <i class="ph ph-envelope field-icon"></i>
                <input type="email" id="loginEmail" class="form-input" placeholder="contoh@email.com" autocomplete="email">
            </div>
        </div>

        <div class="field-group">
            <label class="field-label">Password <span class="req">*</span></label>
            <div class="field-wrap">
                <i class="ph ph-lock field-icon"></i>
                <input type="password" id="loginPass" class="form-input" placeholder="••••••••" autocomplete="current-password">
                <button type="button" class="pass-toggle" onclick="togglePass('loginPass','passIco')">
                    <i class="ph ph-eye" id="passIco"></i>
                </button>
            </div>
            <a href="<?= base_url('auth/forgot') ?>" class="forgot-link">Lupa password?</a>
        </div>

        <button type="button" class="btn-submit" id="btnLogin" onclick="doLogin()">
            <span class="spinner"></span>
            <span class="btn-text"><i class="ph ph-sign-in"></i> Masuk</span>
        </button>

        <p class="bottom-note">Belum punya akun? <a href="<?= base_url('auth/register') ?>">Daftar gratis</a></p>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
const LOGIN_URL = '<?= base_url('auth/login') ?>';

// ── Modal helpers
function showModal(type, title, text, onok) {
    const icons = { success:'✅', error:'❌', info:'ℹ️', warning:'⚠️' };
    const colors = { success:'#2f9e44', error:'#e03131', info:'#1971c2', warning:'#e67700' };
    $('#modalIcon').text(icons[type] || 'ℹ️');
    $('#modalTitle').text(title);
    $('#modalText').text(text);
    $('#modalBtn').removeClass('danger').css('background', colors[type] || '#1971c2');
    if (type === 'error') $('#modalBtn').addClass('danger').css('background','#e03131');
    $('#authModal').data('onok', onok || null).addClass('show');
}
function closeModal() {
    const cb = $('#authModal').data('onok');
    $('#authModal').removeClass('show');
    if (typeof cb === 'function') cb();
}
$('#authModal').on('click', function(e) { if ($(e.target).is('#authModal')) closeModal(); });

// ── Button loader
function setLoading(btnId, loading) {
    const btn = $('#' + btnId);
    btn.prop('disabled', loading).toggleClass('loading', loading);
}

function togglePass(id, ico) {
    const i = document.getElementById(id), ic = document.getElementById(ico);
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'password' ? 'ph ph-eye' : 'ph ph-eye-slash';
}

// ── Login AJAX
function doLogin() {
    const email = $.trim($('#loginEmail').val());
    const pass  = $('#loginPass').val();
    if (!email || !pass) { showModal('error','Field Kosong','Harap isi email dan password.'); return; }
    setLoading('btnLogin', true);
    $.post(LOGIN_URL, { _ajax: 'login', email, password: pass }, 'json')
        .done(data => {
            if (data.ok && data.action === 'redirect') {
                showModal('success', 'Berhasil Masuk!', 'Selamat datang, ' + data.nama + '!', () => {
                    window.location.href = data.url;
                });
            } else if (data.ok && data.action === 'show_2fa') {
                window.location.reload();
            } else {
                showModal('error', 'Login Gagal', data.msg || 'Terjadi kesalahan.');
                setLoading('btnLogin', false);
            }
        })
        .fail(() => { showModal('error','Koneksi Gagal','Periksa jaringan Anda dan coba lagi.'); setLoading('btnLogin', false); });
}

// ── 2FA Submit
function submit2fa() {
    const code = $('#otpInput2fa').val().replace(/\D/g,'');
    if (code.length !== 6) { $('#alert2fa').text('Masukkan 6 digit kode OTP.').show(); return; }
    setLoading('btn2fa', true);
    $.post(LOGIN_URL, { _ajax: 'verify_2fa', otp_code: code }, 'json')
        .done(data => {
            if (data.ok) {
                showModal('success','Verifikasi Berhasil!','Selamat datang kembali!', () => { window.location.href = data.url; });
            } else {
                $('#alert2fa').text(data.msg).show();
                setLoading('btn2fa', false);
            }
        })
        .fail(() => { $('#alert2fa').text('Koneksi gagal.').show(); setLoading('btn2fa', false); });
}

// Submit on Enter
$(document).on('keydown', function(e) {
    if (e.key === 'Enter') {
        <?php if($_require_2fa): ?>submit2fa();<?php else: ?>doLogin();<?php endif; ?>
    }
});
</script>
</body>
</html>
