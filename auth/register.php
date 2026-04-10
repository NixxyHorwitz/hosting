<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/api_helper.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/../library/traffic.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }
track_traffic($conn);

// Google registration session
$action = $_GET['params'][0] ?? null;
$sesid  = $_GET['params'][1] ?? null;
$g_data = null;

if ($action === 'google' && !empty($sesid)) {
    if (isset($_SESSION['google_reg'][$sesid])) {
        $g_data = $_SESSION['google_reg'][$sesid];
        if ($g_data['expires'] < time()) { unset($_SESSION['google_reg'][$sesid]); $g_data = null; }
    }
}

// Generate captcha on fresh load
if (empty($_SESSION['captcha_num1'])) {
    $_SESSION['captcha_num1']   = rand(2, 12);
    $_SESSION['captcha_num2']   = rand(1, 9);
    $_SESSION['captcha_result'] = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
}

// ─── AJAX HANDLER ─────────────────────────────────────────────────────────────
if (!empty($_POST['_ajax']) && $_POST['_ajax'] === 'register') {
    header('Content-Type: application/json');

    $nama_depan    = trim($_POST['nama_depan'] ?? '');
    $nama_belakang = trim($_POST['nama_belakang'] ?? '');
    $nama          = trim("$nama_depan $nama_belakang") ?: $nama_depan;
    $email         = trim($_POST['email'] ?? '');
    $kode_tel      = $_POST['kode_telepon'] ?? '+62';
    $no_hp         = preg_replace('/\D/', '', $_POST['no_hp'] ?? '');
    $whatsapp      = $kode_tel . $no_hp;
    $alamat        = trim($_POST['alamat'] ?? '');
    $negara        = trim($_POST['negara'] ?? 'Indonesia');
    $provinsi      = trim($_POST['provinsi'] ?? '');
    $kota          = trim($_POST['kota'] ?? '');
    $kode_pos      = trim($_POST['kode_pos'] ?? '');
    $password_raw  = $_POST['password'] ?? '';
    $captcha       = (int)($_POST['captcha'] ?? 0);
    $is_google     = !empty($_POST['_google_sesid']);
    $g_sesid       = $_POST['_google_sesid'] ?? '';

    // Google session lookup
    $gdat = null;
    if ($is_google && isset($_SESSION['google_reg'][$g_sesid])) {
        $gdat = $_SESSION['google_reg'][$g_sesid];
        if ($gdat['expires'] < time()) { unset($_SESSION['google_reg'][$g_sesid]); $gdat = null; }
    }

    // Validations
    if (!$is_google && $captcha !== $_SESSION['captcha_result']) {
        $_SESSION['captcha_num1']   = rand(2,12);
        $_SESSION['captcha_num2']   = rand(1,9);
        $_SESSION['captcha_result'] = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
        echo json_encode(['ok'=>false,'msg'=>'Jawaban captcha salah!','refresh_captcha'=>true,
            'c1'=>$_SESSION['captcha_num1'],'c2'=>$_SESSION['captcha_num2']]); exit();
    }
    if (empty($nama_depan) || empty($email)) {
        echo json_encode(['ok'=>false,'msg'=>'Lengkapi semua field yang wajib diisi.']); exit();
    }
    if (!$is_google && (empty($password_raw) || strlen($password_raw) < 6)) {
        echo json_encode(['ok'=>false,'msg'=>'Password minimal 6 karakter.']); exit();
    }

    $email_e     = mysqli_real_escape_string($conn, $email);
    $nama_e      = mysqli_real_escape_string($conn, $nama);
    $nama_dep_e  = mysqli_real_escape_string($conn, $nama_depan);
    $nama_bel_e  = mysqli_real_escape_string($conn, $nama_belakang);
    $whatsapp_e  = mysqli_real_escape_string($conn, $whatsapp);
    $alamat_e    = mysqli_real_escape_string($conn, $alamat);
    $negara_e    = mysqli_real_escape_string($conn, $negara);
    $provinsi_e  = mysqli_real_escape_string($conn, $provinsi);
    $kota_e      = mysqli_real_escape_string($conn, $kota);
    $kode_pos_e  = mysqli_real_escape_string($conn, $kode_pos);
    $kode_tel_e  = mysqli_real_escape_string($conn, $kode_tel);
    $password    = password_hash($password_raw, PASSWORD_DEFAULT);

    // Get real IP
    $reg_ip = '0.0.0.0';
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) { $reg_ip = $ip; break; }
        }
    }
    $reg_ip_e = mysqli_real_escape_string($conn, $reg_ip);

    $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email_e' LIMIT 1");
    if (mysqli_num_rows($cek) > 0) {
        echo json_encode(['ok'=>false,'msg'=>'Email sudah terdaftar. Silakan login.']); exit();
    }

    if ($gdat) {
        $email_e    = mysqli_real_escape_string($conn, $gdat['email']);
        $google_id  = mysqli_real_escape_string($conn, $gdat['google_id']);
        $avatar_url = mysqli_real_escape_string($conn, $gdat['avatar_url']);
        $status     = 'active'; $otp = null; $otp_expiry = null;
    } else {
        $google_id = null; $avatar_url = null;
        $status    = 'pending';
        $otp       = sprintf("%06d", mt_rand(1, 999999));
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    }

    $ins = "INSERT INTO users (nama, nama_depan, nama_belakang, email, no_whatsapp, password, role, status,
                otp_code, otp_expiry, alamat, negara, provinsi, kota, kode_pos, reg_ip, kode_telepon, google_id, avatar_url)
            VALUES ('$nama_e','$nama_dep_e','$nama_bel_e','$email_e','$whatsapp_e','$password','client','$status',
                " . ($otp ? "'$otp'" : "NULL") . "," . ($otp_expiry ? "'$otp_expiry'" : "NULL") . ",'$alamat_e','$negara_e','$provinsi_e','$kota_e','$kode_pos_e','$reg_ip_e','$kode_tel_e'," . ($google_id ? "'$google_id'" : "NULL") . "," . ($avatar_url ? "'$avatar_url'" : "NULL") . ")";

    if (!mysqli_query($conn, $ins)) {
        echo json_encode(['ok'=>false,'msg'=>'Gagal mendaftar: ' . mysqli_error($conn)]); exit();
    }
    $user_id = mysqli_insert_id($conn);

    if ($gdat) {
        unset($_SESSION['google_reg'][$g_sesid]);
        $_SESSION['user_id']   = $user_id;
        $_SESSION['user_nama'] = $nama_depan;
        echo json_encode(['ok'=>true,'action'=>'redirect','url'=>'/hosting']); exit();
    } else {
        sendEmailTemplate($email, $nama, 'register_otp', ['nama'=>$nama, 'otp'=>$otp]);
        $_SESSION['otp_user_id'] = $user_id;
        $otpsession = md5($user_id . time());
        $_SESSION['otp_session'] = $otpsession;
        echo json_encode(['ok'=>true,'action'=>'otp','otp_url'=>base_url('auth/otp/' . $otpsession)]); exit();
    }
}

// Site settings
$site_name    = 'SobatHosting';
$site_logo    = '';
$site_favicon = '';
$res = @mysqli_query($conn, "SELECT site_name, site_logo, site_favicon, google_client_id FROM settings LIMIT 1");
$row_set = ($res ? mysqli_fetch_assoc($res) : []) ?? [];
if (!empty($row_set['site_name']))    $site_name    = $row_set['site_name'];
if (!empty($row_set['site_logo']))    $site_logo    = $row_set['site_logo'];
if (!empty($row_set['site_favicon'])) $site_favicon = $row_set['site_favicon'];
$_g_client_id_r = trim($row_set['google_client_id'] ?? '');
$_sso_enabled_r = !empty($_g_client_id_r);
$_google_url_r  = $_sso_enabled_r ? base_url('auth/google') : '';

if (!empty($_SESSION['auth_error'])) {
    $flash_type = 'error'; $flash_msg = $_SESSION['auth_error']; unset($_SESSION['auth_error']);
} elseif (!empty($_SESSION['auth_success'])) {
    $flash_type = 'success'; $flash_msg = $_SESSION['auth_success']; unset($_SESSION['auth_success']);
} else { $flash_type = ''; $flash_msg = ''; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar — <?= htmlspecialchars($site_name) ?></title>
<?php $_fav = !empty($site_favicon) ? $site_favicon : $site_logo; if (!empty($_fav)): ?>
<link rel="icon" href="/uploads/<?= htmlspecialchars($_fav) ?>" type="image/x-icon">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<style>
*,*::before,*::after{box-sizing:border-box}
:root{--blue:#3b5bdb;--blue-dark:#2f4ac4;--blue-light:#4c6ef5;--bg:#f1f3f9;--card:#fff;--border:#e0e5f0;--text:#1a1d2e;--sub:#6c757d;--input-bg:#f8faff;}
body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;margin:0;display:flex;}
.brand-panel{width:360px;min-height:100vh;background:linear-gradient(160deg,#3b5bdb 0%,#1a3070 100%);position:fixed;left:0;top:0;bottom:0;display:flex;flex-direction:column;justify-content:space-between;padding:40px 36px;z-index:1;}
.brand-panel::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");}
.brand-logo{display:flex;align-items:center;gap:10px;font-size:22px;font-weight:800;color:white;text-decoration:none;position:relative;}
.brand-logo-icon{width:42px;height:42px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;}
.brand-tagline{font-size:26px;font-weight:800;color:white;line-height:1.3;position:relative;margin-top:auto;}
.brand-desc{font-size:13px;color:rgba(255,255,255,.7);line-height:1.7;position:relative;margin-top:14px;}
.brand-footer{font-size:11px;color:rgba(255,255,255,.4);position:relative;}
.form-panel{margin-left:360px;flex:1;min-height:100vh;padding:40px 60px;overflow-y:auto;}
.form-topbar{display:flex;justify-content:flex-end;align-items:center;margin-bottom:40px;font-size:13px;color:var(--sub);}
.form-topbar a{color:var(--blue);font-weight:600;text-decoration:none;}
.form-title{font-size:28px;font-weight:800;color:var(--text);margin-bottom:8px;}
.form-subtitle{font-size:13px;color:var(--sub);margin-bottom:32px;}
.section-title{font-size:13px;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:.6px;margin:28px 0 16px;padding-bottom:8px;border-bottom:1px solid var(--border);}
.field-label{font-size:12px;font-weight:600;color:var(--sub);margin-bottom:5px;display:block;}
.field-req{color:#e03131;margin-left:2px;}
.field-wrap{position:relative;}
.field-wrap .field-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--sub);font-size:16px;pointer-events:none;}
.form-input{width:100%;height:44px;border:1.5px solid var(--border);border-radius:10px;background:var(--input-bg);font-size:13px;color:var(--text);padding:0 14px 0 38px;transition:border-color .15s,box-shadow .15s;outline:none;}
.form-input.no-icon{padding-left:14px;}
.form-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(59,91,219,.1);background:white;}
.form-input::placeholder{color:#adb5bd;}
.form-select{appearance:none;cursor:pointer;}
textarea.form-input{height:80px;padding:10px 14px;resize:vertical;}
.phone-wrap{display:flex;gap:8px;}
.phone-code{width:90px;flex-shrink:0;}
.pass-wrap{position:relative;}
.pass-wrap .form-input{padding-right:44px;}
.pass-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--sub);cursor:pointer;font-size:18px;padding:0;}
.captcha-eq{height:44px;background:#e9ecef;border-radius:10px;border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;letter-spacing:3px;color:var(--text);user-select:none;}
.tos-box{background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:14px 16px;margin-top:20px;}
.tos-box label{font-size:13px;color:var(--text);cursor:pointer;user-select:none;}
.tos-box a{color:var(--blue);font-weight:600;}
.btn-register{width:100%;height:48px;background:linear-gradient(135deg,var(--blue-light) 0%,var(--blue-dark) 100%);color:white;border:none;border-radius:12px;font-size:14px;font-weight:700;letter-spacing:.5px;cursor:pointer;transition:all .2s;margin-top:20px;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-register:hover{box-shadow:0 6px 20px rgba(59,91,219,.35);transform:translateY(-1px);}
.btn-register:disabled{opacity:.65;cursor:not-allowed;transform:none;}
.alert-box-reg{border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.alert-err-r{background:#fff5f5;border:1px solid #ffa8a8;color:#c92a2a;}
.alert-ok-r{background:#d3f9d8;border:1px solid #8ce99a;color:#2b8a3e;}
.alert-info-r{background:#e7f5ff;border:1px solid #74c0fc;color:#1864ab;}
.btn-google-r{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;height:46px;background:#fff;border:1.5px solid var(--border);border-radius:12px;font-size:13.5px;font-weight:600;color:var(--text);text-decoration:none;cursor:pointer;transition:all .15s;box-shadow:0 1px 3px rgba(0,0,0,.06);}
.btn-google-r:hover{border-color:#4285F4;box-shadow:0 3px 12px rgba(66,133,244,.2);color:var(--text);}
.btn-google-r.disabled-sso{opacity:.5;cursor:not-allowed;}
/* Custom modal */
.auth-modal-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
.auth-modal-overlay.show{display:flex;animation:fadeIn .15s ease;}
.auth-modal{background:#fff;border-radius:18px;padding:32px 28px 24px;max-width:380px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.18);animation:slideUp .2s ease;}
.auth-modal-icon{font-size:40px;margin-bottom:12px;}
.auth-modal-title{font-size:17px;font-weight:800;color:#1a1a2e;margin-bottom:8px;}
.auth-modal-text{font-size:13.5px;color:#555;line-height:1.6;margin-bottom:20px;}
.auth-modal-btn{display:inline-block;padding:10px 28px;border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;background:#1971c2;color:#fff;transition:opacity .15s;}
.auth-modal-btn:hover{opacity:.85;}
/* Spinner */
.btn-spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;}
.btn-register.loading .btn-spinner{display:inline-block;}
.btn-register.loading .btn-label{opacity:.7;}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:768px){.brand-panel{display:none;}.form-panel{margin-left:0;padding:30px 24px;}}
</style>
</head>
<body>

<!-- Custom Modal -->
<div class="auth-modal-overlay" id="authModal">
    <div class="auth-modal">
        <div class="auth-modal-icon" id="modalIcon"></div>
        <div class="auth-modal-title" id="modalTitle"></div>
        <div class="auth-modal-text" id="modalText"></div>
        <button class="auth-modal-btn" id="modalBtn" onclick="closeModal()">OK</button>
    </div>
</div>

<!-- Brand Panel -->
<div class="brand-panel">
    <a href="/" class="brand-logo">
        <?php if(!empty($site_logo)): ?>
        <img src="/uploads/<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_name) ?>" style="max-height:38px;max-width:140px;object-fit:contain;">
        <?php else: ?>
        <div class="brand-logo-icon"><i class="ph ph-cloud-arrow-up"></i></div>
        <?= htmlspecialchars($site_name) ?>
        <?php endif; ?>
    </a>
    <div>
        <div class="brand-tagline">Selamat Datang<br>di <?= htmlspecialchars($site_name) ?>!</div>
        <div class="brand-desc">Platform hosting terpercaya dengan uptime 99.9%, dukungan 24/7, dan panel cPanel yang mudah digunakan.</div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> <?= htmlspecialchars($site_name) ?> · Semua hak dilindungi</div>
</div>

<!-- Form Panel -->
<div class="form-panel">
    <div class="form-topbar">
        Sudah punya akun? <a href="<?= base_url('auth/login') ?>" class="ms-2">Masuk &rarr;</a>
    </div>

    <div class="form-title">Buat Akun Baru</div>
    <div class="form-subtitle">Daftar gratis dan nikmati layanan hosting premium.</div>

    <div id="flashAlert" class="alert-box-reg <?php
        if ($flash_type === 'error') echo 'alert-err-r';
        elseif ($flash_type === 'success') echo 'alert-ok-r';
        elseif ($flash_type === 'info') echo 'alert-info-r';
        else echo 'alert-err-r';
    ?>" style="display:<?= $flash_msg ? 'flex' : 'none' ?>;">
        <i class="ph-fill ph-<?= $flash_type === 'success' ? 'check-circle' : ($flash_type === 'info' ? 'info' : 'warning-circle') ?>"></i>
        <span><?= htmlspecialchars($flash_msg) ?></span>
    </div>
    <div id="regAlert" class="alert-box-reg alert-err-r" style="display:none;">
        <i class="ph-fill ph-warning-circle"></i>
        <span id="regAlertText"></span>
    </div>

    <?php if(!$g_data): ?>
    <div style="margin-bottom:20px;">
        <?php if($_sso_enabled_r): ?>
        <a href="<?= htmlspecialchars($_google_url_r) ?>" class="btn-google-r">
        <?php else: ?>
        <a href="#" class="btn-google-r disabled-sso" onclick="showModal('info','Google SSO','Fitur login Google belum dikonfigurasi.');return false;">
        <?php endif; ?>
            <svg width="18" height="18" viewBox="0 0 48 48"><g>
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </g></svg>
            <span>Daftar dengan Google<?= !$_sso_enabled_r ? ' (Tidak Aktif)' : '' ?></span>
        </a>
        <div style="display:flex;align-items:center;gap:12px;margin:16px 0;">
            <div style="flex:1;height:1px;background:var(--border);"></div>
            <span style="font-size:12px;color:var(--sub);white-space:nowrap;">atau daftar manual</span>
            <div style="flex:1;height:1px;background:var(--border);"></div>
        </div>
    </div>
    <?php endif; ?>

    <div id="regForm">
        <!-- Informasi Pribadi -->
        <div class="section-title">Informasi Pribadi</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="field-label">Nama Depan <span class="field-req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-user field-icon"></i>
                    <input type="text" id="f_nama_depan" class="form-input" placeholder="John"
                           value="<?= htmlspecialchars($g_data ? $g_data['nama'] : '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Nama Belakang</label>
                <div class="field-wrap">
                    <i class="ph ph-user field-icon"></i>
                    <input type="text" id="f_nama_belakang" class="form-input" placeholder="Doe">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Alamat Email <span class="field-req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-envelope field-icon"></i>
                    <input type="email" id="f_email" class="form-input" placeholder="johndoe@example.com"
                           <?= $g_data ? 'readonly style="background:#e9ecef;cursor:not-allowed;"' : '' ?>
                           value="<?= htmlspecialchars($g_data ? $g_data['email'] : '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Nomor Handphone <span class="field-req">*</span></label>
                <div class="phone-wrap">
                    <select id="f_kode_tel" class="form-input form-select no-icon phone-code">
                        <option value="+62">🇮🇩 +62</option>
                        <option value="+65">🇸🇬 +65</option>
                        <option value="+60">🇲🇾 +60</option>
                        <option value="+63">🇵🇭 +63</option>
                        <option value="+1">🇺🇸 +1</option>
                    </select>
                    <div class="field-wrap flex-grow-1">
                        <i class="ph ph-phone field-icon"></i>
                        <input type="tel" id="f_no_hp" class="form-input" placeholder="81234567890">
                    </div>
                </div>
            </div>
        </div>

        <!-- Alamat Invoice -->
        <div class="section-title">Alamat Invoice</div>
        <div class="row g-3">
            <div class="col-12">
                <label class="field-label">Alamat Lengkap</label>
                <div class="field-wrap">
                    <i class="ph ph-map-pin field-icon" style="top:14px;transform:none;"></i>
                    <textarea id="f_alamat" class="form-input" style="padding-left:38px;" placeholder="Jl. Pahlawan No 1945, Kelurahan X"></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Negara</label>
                <div class="field-wrap">
                    <i class="ph ph-globe field-icon"></i>
                    <select id="f_negara" class="form-input form-select">
                        <option value="Indonesia" selected>Indonesia</option>
                        <option value="Malaysia">Malaysia</option>
                        <option value="Singapore">Singapore</option>
                        <option value="Philippines">Philippines</option>
                        <option value="Other">Lainnya</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Provinsi</label>
                <div class="field-wrap">
                    <i class="ph ph-map-trifold field-icon"></i>
                    <input type="text" id="f_provinsi" class="form-input" placeholder="Jawa Tengah">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Kota / Kabupaten</label>
                <div class="field-wrap">
                    <i class="ph ph-buildings field-icon"></i>
                    <input type="text" id="f_kota" class="form-input" placeholder="Semarang">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Kode Pos</label>
                <div class="field-wrap">
                    <i class="ph ph-mailbox field-icon"></i>
                    <input type="text" id="f_kode_pos" class="form-input" placeholder="50234">
                </div>
            </div>
        </div>

        <!-- Keamanan Akun -->
        <?php if(!$g_data): ?>
        <div class="section-title">Keamanan Akun</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="field-label">Password <span class="field-req">*</span></label>
                <div class="pass-wrap">
                    <input type="password" id="f_password" class="form-input no-icon" placeholder="••••••••••" minlength="6">
                    <button type="button" class="pass-toggle" onclick="togglePass('f_password',this)"><i class="ph ph-eye"></i></button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Konfirmasi Password <span class="field-req">*</span></label>
                <div class="pass-wrap">
                    <input type="password" id="f_password2" class="form-input no-icon" placeholder="••••••••••">
                    <button type="button" class="pass-toggle" onclick="togglePass('f_password2',this)"><i class="ph ph-eye"></i></button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Verifikasi Keamanan <span class="field-req">*</span></label>
                <div class="row g-2 align-items-center">
                    <div class="col-5">
                        <div class="captcha-eq" id="captchaEq"><?= $_SESSION['captcha_num1'] ?> + <?= $_SESSION['captcha_num2'] ?> = ?</div>
                    </div>
                    <div class="col-7">
                        <div class="field-wrap">
                            <i class="ph ph-equals field-icon"></i>
                            <input type="number" id="f_captcha" class="form-input" placeholder="Jawaban">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ToS -->
        <div class="tos-box">
            <div class="d-flex align-items-start gap-2">
                <input type="checkbox" id="f_tos" style="margin-top:2px;width:16px;height:16px;flex-shrink:0;cursor:pointer;">
                <label for="f_tos">Saya telah membaca dan menyetujui <a href="#" target="_blank">Ketentuan Layanan</a> dan <a href="#" target="_blank">Kebijakan Privasi</a>.</label>
            </div>
        </div>

        <button type="button" class="btn-register" id="btnRegister" onclick="doRegister()">
            <span class="btn-spinner"></span>
            <span class="btn-label"><i class="ph ph-user-plus"></i> Daftar Sekarang</span>
        </button>
        <p style="text-align:center;font-size:12px;color:var(--sub);margin-top:16px;">
            Dengan mendaftar, Anda menyetujui penggunaan cookie untuk keamanan sesi.
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const REG_URL   = '<?= base_url('auth/register' . ($action && $sesid ? '/' . $action . '/' . $sesid : '')) ?>';
const IS_GOOGLE = <?= $g_data ? 'true' : 'false' ?>;
const G_SESID   = '<?= htmlspecialchars($sesid ?? '') ?>';

function showModal(type, title, text, onok) {
    const icons  = { success:'✅', error:'❌', info:'ℹ️', warning:'⚠️' };
    const colors = { success:'#2f9e44', error:'#e03131', info:'#1971c2', warning:'#e67700' };
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
$('#authModal').on('click', function(e) { if ($(e.target).is('#authModal')) closeModal(); });

function showRegError(msg) { $('#regAlertText').text(msg); $('#regAlert').show(); window.scrollTo({top:0,behavior:'smooth'}); }
function hideRegError()    { $('#regAlert').hide(); }

function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'password' ? 'ph ph-eye' : 'ph ph-eye-slash';
}

function doRegister() {
    hideRegError();
    const nama_depan = $.trim($('#f_nama_depan').val());
    const email      = $.trim($('#f_email').val());
    const no_hp      = $.trim($('#f_no_hp').val());
    const tos        = $('#f_tos').is(':checked');
    const password   = $('#f_password').val();
    const password2  = $('#f_password2').val();

    if (!nama_depan || !email || !no_hp) { showRegError('Lengkapi semua field yang wajib diisi.'); return; }
    if (!tos)                             { showRegError('Anda harus menyetujui Ketentuan Layanan.'); return; }
    if (!IS_GOOGLE) {
        if (!password || password.length < 6) { showRegError('Password minimal 6 karakter.'); return; }
        if (password !== password2)           { showModal('error','Password Tidak Cocok','Konfirmasi password harus sama dengan password yang dimasukkan.'); return; }
    }

    const data = {
        _ajax:         'register',
        nama_depan,
        nama_belakang: $('#f_nama_belakang').val(),
        email,
        kode_telepon:  $('#f_kode_tel').val(),
        no_hp,
        alamat:        $('#f_alamat').val(),
        negara:        $('#f_negara').val(),
        provinsi:      $('#f_provinsi').val(),
        kota:          $('#f_kota').val(),
        kode_pos:      $('#f_kode_pos').val(),
        password,
        captcha:       $('#f_captcha').val(),
        _google_sesid: IS_GOOGLE ? G_SESID : '',
    };

    const btn = $('#btnRegister').prop('disabled', true).addClass('loading');
    $.post(REG_URL, data, 'json')
        .done(res => {
            if (res.ok && res.action === 'otp') {
                showModal('success','🎉 Kode OTP Terkirim!',
                    'Silakan cek email Anda untuk mendapatkan kode OTP verifikasi.',
                    () => { window.location.href = res.otp_url; });
            } else if (res.ok && res.action === 'redirect') {
                showModal('success','Pendaftaran Berhasil!','Akun Anda telah aktif. Mengalihkan...',
                    () => { window.location.href = res.url; });
            } else {
                if (res.refresh_captcha) {
                    $('#captchaEq').text(res.c1 + ' + ' + res.c2 + ' = ?');
                    $('#f_captcha').val('');
                }
                showRegError(res.msg || 'Terjadi kesalahan.');
                btn.prop('disabled', false).removeClass('loading');
            }
        })
        .fail(() => {
            showRegError('Koneksi gagal. Periksa jaringan Anda dan coba lagi.');
            btn.prop('disabled', false).removeClass('loading');
        });
}
</script>
</body>
</html>
