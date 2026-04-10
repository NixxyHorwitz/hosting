<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/api_helper.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/../library/traffic.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header("Location: /hosting"); exit(); }

// Track traffic (anonymous)
track_traffic($conn);

// Generate captcha
if (!isset($_POST['register'])) {
    $_SESSION['captcha_num1']   = rand(2, 12);
    $_SESSION['captcha_num2']   = rand(1, 9);
    $_SESSION['captcha_result'] = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
}

$message_type = "";
$message_text = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $nama_depan   = mysqli_real_escape_string($conn, trim($_POST['nama_depan'] ?? ''));
    $nama_belakang= mysqli_real_escape_string($conn, trim($_POST['nama_belakang'] ?? ''));
    $nama         = trim("$nama_depan $nama_belakang") ?: $nama_depan;
    $email        = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $kode_tel     = mysqli_real_escape_string($conn, $_POST['kode_telepon'] ?? '+62');
    $no_hp        = mysqli_real_escape_string($conn, preg_replace('/\D/', '', $_POST['no_hp'] ?? ''));
    $whatsapp     = $kode_tel . $no_hp;
    $alamat       = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));
    $negara       = mysqli_real_escape_string($conn, trim($_POST['negara'] ?? 'Indonesia'));
    $provinsi     = mysqli_real_escape_string($conn, trim($_POST['provinsi'] ?? ''));
    $kota         = mysqli_real_escape_string($conn, trim($_POST['kota'] ?? ''));
    $kode_pos     = mysqli_real_escape_string($conn, trim($_POST['kode_pos'] ?? ''));
    $password     = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $captcha      = (int)($_POST['captcha'] ?? 0);

    // Get real IP
    $ip_headers = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    $reg_ip = '0.0.0.0';
    foreach ($ip_headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) { $reg_ip = $ip; break; }
        }
    }
    $reg_ip_e = mysqli_real_escape_string($conn, $reg_ip);

    if ($captcha !== $_SESSION['captcha_result']) {
        $message_type = "error";
        $message_text = "Jawaban captcha salah!";
        $_SESSION['captcha_num1']   = rand(2,12);
        $_SESSION['captcha_num2']   = rand(1,9);
        $_SESSION['captcha_result'] = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
    } elseif (empty($nama_depan) || empty($email) || empty($_POST['password'])) {
        $message_type = "error";
        $message_text = "Lengkapi semua field yang wajib diisi.";
    } else {
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
        if (mysqli_num_rows($cek) > 0) {
            $message_type = "error";
            $message_text = "Email sudah terdaftar. Silakan login.";
        } else {
            $otp        = sprintf("%06d", mt_rand(1, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $ins = "INSERT INTO users (nama, nama_depan, nama_belakang, email, no_whatsapp, password, role, status,
                        otp_code, otp_expiry, alamat, negara, provinsi, kota, kode_pos, reg_ip, kode_telepon)
                    VALUES ('$nama','$nama_depan','$nama_belakang','$email','$whatsapp','$password','client','pending',
                        '$otp','$otp_expiry','$alamat','$negara','$provinsi','$kota','$kode_pos','$reg_ip_e','$kode_tel')";
            if (mysqli_query($conn, $ins)) {
                $user_id = mysqli_insert_id($conn);
                sendEmailTemplate($email, $nama, 'register_otp', ['nama'=>$nama, 'otp'=>$otp]);
                $_SESSION['otp_user_id'] = $user_id;
                $otpsession = md5($user_id . time());
                $_SESSION['otp_session'] = $otpsession;
                $message_type = "success_register";
            } else {
                $message_type = "error";
                $message_text = "Gagal mendaftar: " . mysqli_error($conn);
            }
        }
    }
}

// Site settings for logo/name + Google SSO
$site_name = 'SobatHosting';
$site_logo = '';
$res = @mysqli_query($conn, "SELECT site_name, site_logo, google_client_id FROM settings LIMIT 1");
if ($res && $row_set = mysqli_fetch_assoc($res)) {
    if (!empty($row_set['site_name'])) $site_name = $row_set['site_name'];
    if (!empty($row_set['site_logo'])) $site_logo  = $row_set['site_logo'];
}

// Build Google OAuth URL
$_g_client_id_r  = trim($row_set['google_client_id'] ?? '');
$_sso_enabled_r  = !empty($_g_client_id_r);
$_google_url_r   = '';
if ($_sso_enabled_r) {
    $_google_url_r = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $_g_client_id_r,
        'redirect_uri'  => base_url('auth/google'),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);
}

// Flash messages from OAuth callback
if (!empty($_SESSION['auth_error'])) {
    $message_type = 'error';
    $message_text = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}
if (!empty($_SESSION['auth_success'])) {
    $message_type = 'success';
    $message_text = $_SESSION['auth_success'];
    unset($_SESSION['auth_success']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar — <?= htmlspecialchars($site_name) ?></title>
<?php if(!empty($site_logo)): ?>
<link rel="icon" href="/uploads/<?= htmlspecialchars($site_logo) ?>" type="image/x-icon">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
    --blue: #3b5bdb;
    --blue-dark: #2f4ac4;
    --blue-light: #4c6ef5;
    --bg: #f1f3f9;
    --card: #ffffff;
    --border: #e0e5f0;
    --text: #1a1d2e;
    --sub: #6c757d;
    --input-bg: #f8faff;
}
body { font-family: 'Inter', sans-serif; background: var(--bg); min-height: 100vh; margin: 0; display: flex; }

/* ── Left Brand Panel ── */
.brand-panel {
    width: 360px;
    min-height: 100vh;
    background: linear-gradient(160deg, #3b5bdb 0%, #1a3070 100%);
    position: fixed;
    left: 0; top: 0; bottom: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px 36px;
    z-index: 1;
}
.brand-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.brand-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 22px;
    font-weight: 800;
    color: white;
    text-decoration: none;
    position: relative;
}
.brand-logo-icon {
    width: 42px;
    height: 42px;
    background: rgba(255,255,255,.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.brand-tagline {
    font-size: 26px;
    font-weight: 800;
    color: white;
    line-height: 1.3;
    position: relative;
    margin-top: auto;
}
.brand-desc {
    font-size: 13px;
    color: rgba(255,255,255,.7);
    line-height: 1.7;
    position: relative;
    margin-top: 14px;
}
.brand-footer {
    font-size: 11px;
    color: rgba(255,255,255,.4);
    position: relative;
}

/* ── Right Form Panel ── */
.form-panel {
    margin-left: 360px;
    flex: 1;
    min-height: 100vh;
    padding: 40px 60px;
    overflow-y: auto;
}
.form-topbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 40px;
    font-size: 13px;
    color: var(--sub);
}
.form-topbar a { color: var(--blue); font-weight: 600; text-decoration: none; }
.form-topbar a:hover { text-decoration: underline; }

.form-title { font-size: 28px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.form-subtitle { font-size: 13px; color: var(--sub); margin-bottom: 32px; }

/* Section separator */
.section-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--blue);
    text-transform: uppercase;
    letter-spacing: .6px;
    margin: 28px 0 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}

/* Form controls */
.field-label { font-size: 12px; font-weight: 600; color: var(--sub); margin-bottom: 5px; display: block; }
.field-req { color: #e03131; margin-left: 2px; }
.field-wrap { position: relative; }
.field-wrap .field-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--sub); font-size: 16px; pointer-events: none; }
.form-input {
    width: 100%;
    height: 44px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: var(--input-bg);
    font-size: 13px;
    color: var(--text);
    padding: 0 14px 0 38px;
    transition: border-color .15s, box-shadow .15s;
    outline: none;
}
.form-input.no-icon { padding-left: 14px; }
.form-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,91,219,.1); background: white; }
.form-input::placeholder { color: #adb5bd; }
.form-select { appearance: none; cursor: pointer; }
textarea.form-input { height: 80px; padding: 10px 14px; resize: vertical; }

/* Phone input combined */
.phone-wrap { display: flex; gap: 8px; }
.phone-code { width: 90px; flex-shrink: 0; }

/* Password toggle */
.pass-wrap { position: relative; }
.pass-wrap .form-input { padding-right: 44px; }
.pass-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--sub); cursor: pointer; font-size: 18px; padding: 0; }

/* Captcha box */
.captcha-eq {
    height: 44px;
    background: #e9ecef;
    border-radius: 10px;
    border: 1.5px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 3px;
    color: var(--text);
    user-select: none;
}

/* ToS */
.tos-box {
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 10px;
    padding: 14px 16px;
    margin-top: 20px;
}
.tos-box label { font-size: 13px; color: var(--text); cursor: pointer; user-select: none; }
.tos-box a { color: var(--blue); font-weight: 600; }

/* Submit button */
.btn-register {
    width: 100%;
    height: 48px;
    background: linear-gradient(135deg, var(--blue-light) 0%, var(--blue-dark) 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: .5px;
    cursor: pointer;
    transition: all .2s;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-register:hover { box-shadow: 0 6px 20px rgba(59,91,219,.35); transform: translateY(-1px); }
.btn-register:active { transform: translateY(0); }

/* Alert */
.alert-err { background: #fff5f5; border: 1px solid #ffa8a8; border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #c92a2a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

/* Google SSO button */
.btn-google-r {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    height: 46px;
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text);
    text-decoration: none;
    cursor: pointer;
    transition: all .15s;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.btn-google-r:hover { border-color: #4285F4; box-shadow: 0 3px 12px rgba(66,133,244,.2); color: var(--text); }
.btn-google-r.disabled-sso { opacity: .5; cursor: not-allowed; }

@media (max-width: 768px) {
    .brand-panel { display: none; }
    .form-panel { margin-left: 0; padding: 30px 24px; }
}
</style>
</head>
<body>

<!-- Left Panel -->
<div class="brand-panel">
    <a href="/" class="brand-logo">
        <div class="brand-logo-icon"><i class="ph ph-cloud-arrow-up"></i></div>
        <?= htmlspecialchars($site_name) ?>
    </a>
    <div>
        <div class="brand-tagline">Selamat Datang<br>di <?= htmlspecialchars($site_name) ?>!</div>
        <div class="brand-desc">Platform hosting terpercaya dengan uptime 99.9%, dukungan 24/7, dan panel cPanel yang mudah digunakan untuk bisnis Anda.</div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> <?= htmlspecialchars($site_name) ?> · Semua hak dilindungi</div>
</div>

<!-- Right Panel -->
<div class="form-panel">
    <div class="form-topbar">
        Sudah punya akun? <a href="/auth/login" class="ms-2">Masuk &rarr;</a>
    </div>

    <div class="form-title">Buat Akun Baru</div>
    <div class="form-subtitle">Daftar gratis dan nikmati layanan hosting premium.</div>

    <?php if($message_type === 'error'): ?>
    <div class="alert-err"><i class="ph-fill ph-warning-circle" style="font-size:20px;"></i><?= htmlspecialchars($message_text) ?></div>
    <?php elseif($message_type === 'success'): ?>
    <div style="background:#d3f9d8;border:1px solid #8ce99a;border-radius:8px;padding:10px 14px;font-size:13px;color:#2b8a3e;display:flex;align-items:center;gap:8px;margin-bottom:16px;">
        <i class="ph-fill ph-check-circle" style="font-size:18px;"></i><?= htmlspecialchars($message_text) ?>
    </div>
    <?php endif; ?>

    <!-- Google SSO Button -->
    <div style="margin-bottom: 20px;">
        <?php if($_sso_enabled_r): ?>
        <a href="<?= htmlspecialchars($_google_url_r) ?>" class="btn-google-r">
        <?php else: ?>
        <a href="#" class="btn-google-r disabled-sso" onclick="alert('SSO Google belum dikonfigurasi.');return false;">
        <?php endif; ?>
            <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                <g><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></g>
            </svg>
            <span>Daftar dengan Google<?= !$_sso_enabled_r ? ' (Tidak Aktif)' : '' ?></span>
        </a>

        <div style="display:flex;align-items:center;gap:12px;margin:16px 0;">
            <div style="flex:1;height:1px;background:var(--border);"></div>
            <span style="font-size:12px;color:var(--sub);white-space:nowrap;">atau daftar manual</span>
            <div style="flex:1;height:1px;background:var(--border);"></div>
        </div>
    </div>

    <form method="POST" id="regForm">

        <!-- Informasi Pribadi -->
        <div class="section-title">Informasi Pribadi</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="field-label">Nama Depan <span class="field-req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-user field-icon"></i>
                    <input type="text" name="nama_depan" class="form-input" placeholder="John" required
                           value="<?= htmlspecialchars($_POST['nama_depan'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Nama Belakang</label>
                <div class="field-wrap">
                    <i class="ph ph-user field-icon"></i>
                    <input type="text" name="nama_belakang" class="form-input" placeholder="Doe"
                           value="<?= htmlspecialchars($_POST['nama_belakang'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Alamat Email <span class="field-req">*</span></label>
                <div class="field-wrap">
                    <i class="ph ph-envelope field-icon"></i>
                    <input type="email" name="email" class="form-input" placeholder="johndoe@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Nomor Handphone <span class="field-req">*</span></label>
                <div class="phone-wrap">
                    <select name="kode_telepon" class="form-input form-select no-icon phone-code">
                        <option value="+62" <?= ($_POST['kode_telepon']??'+62')=='+62'?'selected':'' ?>>🇮🇩 +62</option>
                        <option value="+65">🇸🇬 +65</option>
                        <option value="+60">🇲🇾 +60</option>
                        <option value="+63">🇵🇭 +63</option>
                        <option value="+1">🇺🇸 +1</option>
                    </select>
                    <div class="field-wrap flex-grow-1">
                        <i class="ph ph-phone field-icon"></i>
                        <input type="tel" name="no_hp" class="form-input" placeholder="81234567890" required
                               value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
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
                    <textarea name="alamat" class="form-input" style="padding-left:38px;" placeholder="Jl. Pahlawan No 1945, Kelurahan X, Kecamatan Y"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Negara</label>
                <div class="field-wrap">
                    <i class="ph ph-globe field-icon"></i>
                    <select name="negara" class="form-input form-select">
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
                    <input type="text" name="provinsi" class="form-input" placeholder="Jawa Tengah"
                           value="<?= htmlspecialchars($_POST['provinsi'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Kota / Kabupaten</label>
                <div class="field-wrap">
                    <i class="ph ph-buildings field-icon"></i>
                    <input type="text" name="kota" class="form-input" placeholder="Semarang"
                           value="<?= htmlspecialchars($_POST['kota'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Kode Pos</label>
                <div class="field-wrap">
                    <i class="ph ph-mailbox field-icon"></i>
                    <input type="text" name="kode_pos" class="form-input" placeholder="50234"
                           value="<?= htmlspecialchars($_POST['kode_pos'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- Keamanan Akun -->
        <div class="section-title">Keamanan Akun</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="field-label">Password <span class="field-req">*</span></label>
                <div class="pass-wrap">
                    <input type="password" name="password" id="regPass" class="form-input no-icon" placeholder="••••••••••" required minlength="6">
                    <button type="button" class="pass-toggle" onclick="togglePass('regPass',this)"><i class="ph ph-eye"></i></button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="field-label">Konfirmasi Password <span class="field-req">*</span></label>
                <div class="pass-wrap">
                    <input type="password" id="regPass2" class="form-input no-icon" placeholder="••••••••••" required>
                    <button type="button" class="pass-toggle" onclick="togglePass('regPass2',this)"><i class="ph ph-eye"></i></button>
                </div>
            </div>

            <!-- Captcha -->
            <div class="col-md-6">
                <label class="field-label">Verifikasi Keamanan <span class="field-req">*</span></label>
                <div class="row g-2 align-items-center">
                    <div class="col-5">
                        <div class="captcha-eq"><?= $_SESSION['captcha_num1'] ?> + <?= $_SESSION['captcha_num2'] ?> = ?</div>
                    </div>
                    <div class="col-7">
                        <div class="field-wrap">
                            <i class="ph ph-equals field-icon"></i>
                            <input type="number" name="captcha" class="form-input" placeholder="Jawaban" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ToS -->
        <div class="tos-box">
            <div class="d-flex align-items-start gap-2">
                <input type="checkbox" id="tos" name="tos" required style="margin-top:2px;width:16px;height:16px;flex-shrink:0;cursor:pointer;">
                <label for="tos">Saya telah membaca dan menyetujui <a href="#" target="_blank">Ketentuan Layanan</a> dan <a href="#" target="_blank">Kebijakan Privasi</a> yang berlaku.</label>
            </div>
        </div>

        <button type="submit" name="register" class="btn-register">
            <i class="ph ph-user-plus"></i> Daftar Sekarang
        </button>

        <p style="text-align:center;font-size:12px;color:var(--sub);margin-top:16px;">
            Dengan mendaftar, Anda menyetujui penggunaan cookie untuk keamanan sesi.
        </p>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'ph ph-eye-slash';
    } else {
        inp.type   = 'password';
        ico.className = 'ph ph-eye';
    }
}

// Password match validation
document.getElementById('regForm').addEventListener('submit', function(e) {
    const p1 = document.getElementById('regPass').value;
    const p2 = document.getElementById('regPass2').value;
    if (p1 !== p2) {
        e.preventDefault();
        Swal.fire({ icon:'error', title:'Password tidak cocok!', text:'Konfirmasi password harus sama.', confirmButtonColor:'#3b5bdb' });
    }
});

<?php if($message_type === 'success_register'): ?>
Swal.fire({
    icon: 'success',
    title: '🎉 Kode OTP Terkirim!',
    text: 'Silakan cek email Anda untuk mendapatkan kode OTP verifikasi.',
    confirmButtonColor: '#3b5bdb',
    timer: 3000,
    showConfirmButton: false
}).then(() => { window.location.href = '/auth/otp/<?= $otpsession ?? '' ?>'; });
<?php elseif($message_type === 'error'): ?>
Swal.fire({ icon:'error', title:'Opps!', text:'<?= addslashes($message_text) ?>', confirmButtonColor:'#3b5bdb' });
<?php endif; ?>
</script>
</body>
</html>
