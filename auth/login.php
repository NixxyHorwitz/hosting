<?php
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

$message_type = "";
$message_text = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    if (mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);
        if (password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $message_type = "error";
                $message_text = "Akun belum aktif. Silakan verifikasi email Anda.";
            } else {
                // Check 2FA
                if (isset($user['is_2fa_enabled']) && $user['is_2fa_enabled'] == 1) {
                    $_SESSION['pending_2fa_id'] = $user['id'];
                    $require_2fa = true;
                } else {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_nama'] = $user['nama'];
                    mysqli_query($conn, "UPDATE users SET last_login=NOW(), last_ip='" . mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']) . "' WHERE id='{$user['id']}'");
                    $message_type = "success_login";
                }
            }
        } else {
            $message_type = "error";
            $message_text = "Password yang Anda masukkan salah.";
        }
    } else {
        $message_type = "error";
        $message_text = "Email tidak ditemukan di sistem kami.";
    }
}

// Handle OTP Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_2fa'])) {
    if(!isset($_SESSION['pending_2fa_id'])) {
        header("Location: login");
        exit;
    }
    $uid = (int)$_SESSION['pending_2fa_id'];
    $code = trim($_POST['otp_code'] ?? '');
    
    $q_u = mysqli_query($conn, "SELECT * FROM users WHERE id='$uid'");
    $u_2fa = mysqli_fetch_assoc($q_u);
    
    require_once __DIR__ . '/../core/GoogleAuthenticator.php';
    $ga = new PHPGangsta_GoogleAuthenticator();
    
    if ($ga->verifyCode($u_2fa['gauth_secret'], $code, 2)) {
        // Log in user
        $_SESSION['user_id']   = $u_2fa['id'];
        $_SESSION['user_nama'] = $u_2fa['nama'];
        mysqli_query($conn, "UPDATE users SET last_login=NOW(), last_ip='" . mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']) . "' WHERE id='{$u_2fa['id']}'");
        unset($_SESSION['pending_2fa_id']);
        $message_type = "success_login";
    } else {
        $require_2fa = true;
        $message_type = "error";
        $message_text = "Kode OTP tidak valid atau kadaluarsa.";
    }
}

// Redirect if already sent to 2fa page
if (isset($_SESSION['pending_2fa_id']) && !isset($require_2fa) && !isset($_POST['submit_2fa'])) {
    $require_2fa = true;
}

// Load site settings + Google SSO URL
$_ls   = @mysqli_query($conn, "SELECT site_name, site_logo, google_client_id FROM settings LIMIT 1");
$_lset = ($_ls ? mysqli_fetch_assoc($_ls) : []) ?? [];
$_site_name  = htmlspecialchars($_lset['site_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'SobatHosting'));
$_site_logo  = $_lset['site_logo'] ?? '';
$_g_id       = trim($_lset['google_client_id'] ?? '');
$_sso        = !empty($_g_id);
$_google_url = '';
if ($_sso) {
    $_google_url = base_url('auth/google');
}
// Flash messages from OAuth callback
if (!empty($_SESSION['auth_error']))   { $message_type = 'error'; $message_text = $_SESSION['auth_error']; unset($_SESSION['auth_error']); }
if (!empty($_SESSION['auth_success'])) { $message_type = 'success_login'; unset($_SESSION['auth_success']); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — <?= $_site_name ?></title>
    <?php if(!empty($_site_logo)): ?>
    <link rel="icon" href="/uploads/<?= htmlspecialchars($_site_logo) ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
    <?php include __DIR__ . '/auth_shared.css.php'; ?>
    </style>
</head>
<body>

<div class="brand-panel">
    <a href="/" class="brand-logo">
        <div class="brand-logo-icon"><i class="ph ph-cloud-arrow-up"></i></div>
        <?= $_site_name ?>
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
        <?php if(isset($require_2fa) && $require_2fa === true): ?>
            <div class="form-title" style="text-align:center;"><i class="ph-fill ph-shield-check" style="color:var(--accent);font-size:32px;"></i><br>Keamanan 2FA</div>
            <div class="form-subtitle" style="text-align:center;">Akun Anda dilindungi Autentikasi Dua Faktor. Silakan masukkan 6 digit kode OTP dari Authenticator Anda.</div>

            <?php if($message_type === 'error'): ?>
            <div class="alert-box alert-err">
                <i class="ph-fill ph-warning-circle"></i>
                <?= htmlspecialchars($message_text) ?>
            </div>
            <?php endif; ?>

            <form action="" method="POST" style="margin-top:20px;">
                <div class="field-group">
                    <input type="text" style="letter-spacing:6px; font-size:1.5rem; text-align:center;" class="form-input fw-bold" name="otp_code" placeholder="123456" maxlength="6" required autocomplete="one-time-code">
                </div>
                <button type="submit" name="submit_2fa" class="btn-submit mt-2">
                    <i class="ph ph-lock-key"></i> Verifikasi Kode
                </button>
            </form>
            
            <div style="text-align:center; margin-top: 20px;">
                <a href="login?cancel_2fa=1" class="forgot-link">Batalkan Login & Kembali</a>
            </div>
            <?php if (isset($_GET['cancel_2fa'])) { unset($_SESSION['pending_2fa_id']); header("Location: login"); exit; } ?>
            
        <?php else: ?>
            <div class="form-title">Masuk Akun</div>
            <div class="form-subtitle">Selamat datang kembali! Masukkan detail akun Anda.</div>

            <?php if($message_type === 'error'): ?>
            <div class="alert-box alert-err">
                <i class="ph-fill ph-warning-circle"></i>
                <?= htmlspecialchars($message_text) ?>
            </div>
            <?php endif; ?>

        <!-- Google SSO -->
        <?php if($_sso): ?>
        <a href="<?= htmlspecialchars($_google_url) ?>" class="btn-google">
        <?php else: ?>
        <a href="#" class="btn-google disabled" onclick="alert('SSO Google belum dikonfigurasi.');return false;">
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

        <form action="" method="POST">
            <div class="field-group">
                <label class="field-label">Alamat Email <span class="req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-envelope field-icon"></i>
                    <input type="email" name="email" class="form-input" placeholder="contoh@email.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="field-group">
                <label class="field-label">Password <span class="req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-lock field-icon"></i>
                    <input type="password" name="password" id="loginPass" class="form-input" placeholder="••••••••" required>
                    <button type="button" class="pass-toggle" onclick="togglePass('loginPass','passIco')">
                        <i class="ph ph-eye" id="passIco"></i>
                    </button>
                </div>
                <a href="<?= base_url('auth/forgot') ?>" class="forgot-link">Lupa password?</a>
            </div>

            <button type="submit" name="login" class="btn-submit">
                <i class="ph ph-sign-in"></i> Masuk
            </button>
        </form>

        <p class="bottom-note">
            Belum punya akun? <a href="<?= base_url('auth/register') ?>">Daftar gratis</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function togglePass(id, ico) {
    const i = document.getElementById(id), ic = document.getElementById(ico);
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'password' ? 'ph ph-eye' : 'ph ph-eye-slash';
}
<?php if($message_type === 'success_login'): ?>
Swal.fire({ icon:'success', title:'Berhasil Masuk!',
    text:'Selamat datang kembali, <?= addslashes($_SESSION['user_nama'] ?? '') ?>!',
    timer:1400, showConfirmButton:false, timerProgressBar:true
}).then(()=>{ window.location.href='<?= base_url('hosting') ?>'; });
<?php endif; ?>
</script>
</body>
</html>
