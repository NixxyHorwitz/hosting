<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('NS1')) { 
    include __DIR__ . '/../config/database.php'; 
}

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login"); 
    exit;
}

    // AJAX Test SMTP
if (isset($_POST['ajax_test_smtp'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../core/mailer.php';
    $test_email = mysqli_real_escape_string($conn, trim($_POST['test_email'] ?? ''));
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Email yang Anda masukkan tidak valid.']);
        exit;
    }
    $GLOBALS['is_smtp_test'] = true;
    $cek_tpl = mysqli_query($conn, "SELECT id FROM email_templates WHERE name='test_connection'");
    if(mysqli_num_rows($cek_tpl) == 0) {
        mysqli_query($conn, "INSERT INTO email_templates (name, description, subject, body) VALUES ('test_connection', 'Template untuk memastikan koneksi SMTP', 'Test Koneksi SMTP Berhasil', '<h2>Halo :nama:!</h2><p>Jika Anda membaca email ini, maka konfigurasi SMTP Anda beroperasi tanpa kendala.</p>')");
    }
    $res = sendEmailTemplate($test_email, $test_email, 'test_connection', ['nama' => 'Konfigurasi Anda']);
    if ($res === true) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => "Email percobaan berhasil dikirim ke $test_email! Jika Anda tidak menerimanya, periksa folder Spam."]);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => "Gagal mengirim pesan percobaan. Error: " . (is_string($res) ? $res : 'Unknown error')]);
    }
    exit;
}

    // Logika Update Settings
if (isset($_POST['update_settings'])) {
    $site_name = mysqli_real_escape_string($conn, $_POST['site_name']);
    $site_title = mysqli_real_escape_string($conn, $_POST['site_title']);
    $site_desc = mysqli_real_escape_string($conn, $_POST['site_description']);
    $api_key = mysqli_real_escape_string($conn, $_POST['api_key']);
    $secret_key = mysqli_real_escape_string($conn, $_POST['secret_key']);
    
    // Ambil input Nameserver
    $ns1 = mysqli_real_escape_string($conn, $_POST['ns1']);
    $ns2 = mysqli_real_escape_string($conn, $_POST['ns2']);
    $ns3 = mysqli_real_escape_string($conn, $_POST['ns3']);
    $ns4 = mysqli_real_escape_string($conn, $_POST['ns4']);
    
    // Ambil input Kontak
    $contact_phone = mysqli_real_escape_string($conn, $_POST['contact_phone']);
    $contact_billing = mysqli_real_escape_string($conn, $_POST['contact_billing']);
    $contact_info = mysqli_real_escape_string($conn, $_POST['contact_info']);
    $contact_support = mysqli_real_escape_string($conn, $_POST['contact_support']);

    // Ambil input SMTP
    $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
    $smtp_port = (int)$_POST['smtp_port'];
    $smtp_user = mysqli_real_escape_string($conn, $_POST['smtp_user']);
    $smtp_pass = mysqli_real_escape_string($conn, $_POST['smtp_pass']);
    $smtp_from = mysqli_real_escape_string($conn, $_POST['smtp_from_name']);

    // Ambil input Google Auth
    $google_client_id = mysqli_real_escape_string($conn, $_POST['google_client_id']);
    $google_client_secret = mysqli_real_escape_string($conn, $_POST['google_client_secret']);

    $update = "UPDATE settings SET 
               site_name='$site_name', 
               site_title='$site_title', 
               site_description='$site_desc',
               payment_api_key='$api_key',
               payment_secret_key='$secret_key',
               ns1='$ns1',
               ns2='$ns2',
               ns3='$ns3',
               ns4='$ns4',
               contact_phone='$contact_phone',
               contact_billing='$contact_billing',
               contact_info='$contact_info',
               contact_support='$contact_support',
               smtp_host='$smtp_host',
               smtp_port='$smtp_port',
               smtp_user='$smtp_user',
               smtp_pass='$smtp_pass',
               smtp_from_name='$smtp_from',
               google_client_id='$google_client_id',
               google_client_secret='$google_client_secret'
               WHERE id=1";

    if (mysqli_query($conn, $update)) {
        header("Location: " . base_url('admin/website') . "?status=success");
    } else {
        header("Location: " . base_url('admin/website') . "?status=error");
    }
    exit();
}

// Logika Update Template Email
if (isset($_POST['update_template'])) {
    $tpl_id = (int)$_POST['template_id'];
    $subject = mysqli_real_escape_string($conn, $_POST['template_subject']);
    $body = mysqli_real_escape_string($conn, $_POST['template_body']);
    if (mysqli_query($conn, "UPDATE email_templates SET subject='$subject', body='$body' WHERE id=$tpl_id")) {
        header("Location: " . base_url('admin/website') . "?status=success_template");
    } else {
        header("Location: " . base_url('admin/website') . "?status=error");
    }
    exit();
}

// ─── Running Text Update ───────────────────────────────────────
if (isset($_POST['update_running_text'])) {
    $rt      = mysqli_real_escape_string($conn, $_POST['running_text']);
    $rt_en   = isset($_POST['running_text_enabled']) ? 1 : 0;
    mysqli_query($conn, "UPDATE settings SET running_text='$rt', running_text_enabled=$rt_en WHERE id=1");
    header("Location: " . base_url('admin/website') . "?status=success"); exit();
}

// ─── Banner CRUD ───────────────────────────────────────────────
// Auto-create banners table
$chk_ban = mysqli_query($conn, "SHOW TABLES LIKE 'banners'");
if(mysqli_num_rows($chk_ban) == 0) {
    mysqli_query($conn, "CREATE TABLE banners (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), subtitle VARCHAR(255), image VARCHAR(255), button_text VARCHAR(100) DEFAULT 'Pesan Sekarang', button_url VARCHAR(255) DEFAULT '/hosting/services', bg_color VARCHAR(100) DEFAULT 'linear-gradient(135deg,#0a1628,#1e3a6e)', is_active TINYINT(1) DEFAULT 1, sort_order INT DEFAULT 0)");
}
if (isset($_POST['save_banner'])) {
    $bid      = (int)($_POST['banner_id'] ?? 0);
    $btitle   = mysqli_real_escape_string($conn, $_POST['banner_title']);
    $bsub     = mysqli_real_escape_string($conn, $_POST['banner_subtitle']);
    $bbtn     = mysqli_real_escape_string($conn, $_POST['banner_button_text']);
    $burl     = mysqli_real_escape_string($conn, $_POST['banner_button_url']);
    $bbg      = mysqli_real_escape_string($conn, $_POST['banner_bg_color']);
    $bact     = isset($_POST['banner_is_active']) ? 1 : 0;
    $border   = (int)($_POST['banner_sort_order'] ?? 0);
    $bimg     = '';
    // Handle image upload
    if (!empty($_FILES['banner_image']['name'])) {
        $ext   = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
        $bimg  = 'banner_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['banner_image']['tmp_name'], __DIR__ . '/../uploads/' . $bimg);
    }
    if ($bid > 0) {
        $img_sql = !empty($bimg) ? ", image='$bimg'" : '';
        mysqli_query($conn, "UPDATE banners SET title='$btitle', subtitle='$bsub', button_text='$bbtn', button_url='$burl', bg_color='$bbg', is_active=$bact, sort_order=$border$img_sql WHERE id=$bid");
    } else {
        mysqli_query($conn, "INSERT INTO banners (title,subtitle,image,button_text,button_url,bg_color,is_active,sort_order) VALUES ('$btitle','$bsub','$bimg','$bbtn','$burl','$bbg',$bact,$border)");
    }
    header("Location: " . base_url('admin/website') . "?status=success#tab-konten"); exit();
}
if (isset($_GET['del_banner'])) {
    mysqli_query($conn, "DELETE FROM banners WHERE id=" . (int)$_GET['del_banner']);
    header("Location: " . base_url('admin/website') . "?status=success#tab-konten"); exit();
}

// ─── Announcement CRUD ─────────────────────────────────────────
$chk_ann = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if(mysqli_num_rows($chk_ann) == 0) {
    mysqli_query($conn, "CREATE TABLE announcements (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT, category ENUM('info','warning','promo','maintenance') DEFAULT 'info', is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
}
if (isset($_POST['save_announcement'])) {
    $aid    = (int)($_POST['ann_id'] ?? 0);
    $atitle = mysqli_real_escape_string($conn, $_POST['ann_title']);
    $acont  = mysqli_real_escape_string($conn, $_POST['ann_content']);
    $acat   = mysqli_real_escape_string($conn, $_POST['ann_category']);
    $aact   = isset($_POST['ann_is_active']) ? 1 : 0;
    if ($aid > 0) {
        mysqli_query($conn, "UPDATE announcements SET title='$atitle', content='$acont', category='$acat', is_active=$aact WHERE id=$aid");
    } else {
        mysqli_query($conn, "INSERT INTO announcements (title,content,category,is_active) VALUES ('$atitle','$acont','$acat',$aact)");
    }
    header("Location: " . base_url('admin/website') . "?status=success#tab-konten"); exit();
}
if (isset($_GET['del_ann'])) {
    mysqli_query($conn, "DELETE FROM announcements WHERE id=" . (int)$_GET['del_ann']);
    header("Location: " . base_url('admin/website') . "?status=success#tab-konten"); exit();
}

// ─── Ambil semua data ──────────────────────────────────────────
$query = mysqli_query($conn, "SELECT * FROM settings WHERE id = 1");
$set   = mysqli_fetch_assoc($query);

$banners_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM banners ORDER BY sort_order ASC"), MYSQLI_ASSOC);
$ann_list     = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM announcements ORDER BY created_at DESC"), MYSQLI_ASSOC);

$page_title = "Konfigurasi Sistem";
include __DIR__ . '/library/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Konfigurasi Sistem</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bc">
                <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Settings</li>
            </ol>
        </nav>
    </div>
</div>

<form action="" method="POST">
    <div class="row g-4">
        <!-- Sidebar Tabs -->
        <div class="col-md-3">
            <div class="card-c p-2 sticky-top" style="top: 80px;">
                <div class="nav flex-column nav-pills" id="settings-tabs" role="tablist" aria-orientation="vertical">
                    <button class="nav-link active text-start fw-medium d-flex align-items-center gap-2 mb-1 p-2" style="border-radius: 8px;" data-bs-toggle="pill" data-bs-target="#tab-general" type="button" role="tab">
                        <i class="ph ph-globe" style="font-size: 18px;"></i> Informasi Umum
                    </button>
                    <button class="nav-link text-start fw-medium d-flex align-items-center gap-2 mb-1 p-2" style="border-radius: 8px;" data-bs-toggle="pill" data-bs-target="#tab-contacts" type="button" role="tab">
                        <i class="ph ph-address-book" style="font-size: 18px;"></i> Kontak & Support
                    </button>
                    <button class="nav-link text-start fw-medium d-flex align-items-center gap-2 mb-1 p-2" style="border-radius: 8px;" data-bs-toggle="pill" data-bs-target="#tab-payment" type="button" role="tab">
                        <i class="ph ph-credit-card" style="font-size: 18px;"></i> Payment Gateway
                    </button>
                    <button class="nav-link text-start fw-medium d-flex align-items-center gap-2 mb-1 p-2" style="border-radius: 8px;" data-bs-toggle="pill" data-bs-target="#tab-smtp" type="button" role="tab">
                        <i class="ph ph-envelope" style="font-size: 18px;"></i> SMTP Email
                    </button>
                    <button class="nav-link text-start fw-medium d-flex align-items-center gap-2 mb-1 p-2" style="border-radius: 8px;" data-bs-toggle="pill" data-bs-target="#tab-templates" type="button" role="tab">
                        <i class="ph ph-files" style="font-size: 18px;"></i> Template Email
                    </button>
                    <button class="nav-link text-start fw-medium d-flex align-items-center gap-2 mb-1 p-2" style="border-radius: 8px;" data-bs-toggle="pill" data-bs-target="#tab-google" type="button" role="tab">
                        <i class="ph ph-google-logo" style="font-size: 18px;"></i> SSO Google
                    </button>
                    <button class="nav-link text-start fw-medium d-flex align-items-center gap-2 mb-1 p-2" id="tab-konten" style="border-radius: 8px;" data-bs-toggle="pill" data-bs-target="#tab-pane-konten" type="button" role="tab">
                        <i class="ph ph-images" style="font-size: 18px;"></i> Konten Dashboard
                    </button>
                </div>
                
                <style>
                    #settings-tabs .nav-link {
                        background-color: transparent;
                        border: 1px solid transparent;
                        color: rgba(255, 255, 255, 0.6) !important;
                    }
                    #settings-tabs .nav-link.active {
                        background-color: var(--accent) !important;
                        color: #fff !important;
                    }
                    #settings-tabs .nav-link:hover:not(.active) {
                        background-color: var(--hover) !important;
                        color: var(--text) !important;
                    }
                </style>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div class="col-md-9">
            <div class="card-c">
                <div class="cb py-3">
                    <div class="tab-content" id="v-pills-tabContent">
                        
                        <!-- TAB GENERAL -->
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                            <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3 fs-6">Informasi Merek & SEO</h5>
                            <div class="mb-3">
                                <label class="fl">Nama Website</label>
                                <input type="text" name="site_name" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['site_name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Tagline / Meta Title</label>
                                <input type="text" name="site_title" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['site_title']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Deskripsi SEO (Meta Description)</label>
                                <textarea name="site_description" class="fc w-100 form-control-sm" rows="3"><?php echo htmlspecialchars($set['site_description']); ?></textarea>
                            </div>

                            <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3 mt-4 fs-6">Default Nameservers</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="fl">Nameserver 1</label>
                                    <input type="text" name="ns1" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['ns1']); ?>" placeholder="ns1.example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="fl">Nameserver 2</label>
                                    <input type="text" name="ns2" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['ns2']); ?>" placeholder="ns2.example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="fl">Nameserver 3</label>
                                    <input type="text" name="ns3" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['ns3']); ?>" placeholder="ns3.example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="fl">Nameserver 4</label>
                                    <input type="text" name="ns4" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['ns4']); ?>" placeholder="ns4.example.com">
                                </div>
                            </div>
                        </div>

                        <!-- TAB CONTACTS -->
                        <div class="tab-pane fade" id="tab-contacts" role="tabpanel">
                            <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3 fs-6">Kontak Bantuan Perusahaan</h5>
                            <div class="mb-3">
                                <label class="fl">Nomor Telepon Support</label>
                                <input type="text" name="contact_phone" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['contact_phone'] ?? '0274-892257'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Email Billing / Penagihan</label>
                                <input type="email" name="contact_billing" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['contact_billing'] ?? 'billing@rumahweb.com'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Email Info / Umum</label>
                                <input type="email" name="contact_info" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['contact_info'] ?? 'info@rumahweb.com'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Email Support Teknis</label>
                                <input type="email" name="contact_support" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['contact_support'] ?? 'teknis@rumahweb.com'); ?>">
                            </div>
                        </div>

                        <!-- TAB PAYMENT -->
                        <div class="tab-pane fade" id="tab-payment" role="tabpanel">
                            <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3 fs-6">Payment Gateway</h5>
                            <div class="mb-3">
                                <label class="fl">API Client Key / Merchant Code</label>
                                <input type="text" name="api_key" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['payment_api_key']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Secret Key / Private Key</label>
                                <input type="password" name="secret_key" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['payment_secret_key']); ?>">
                            </div>
                        </div>

                        <!-- TAB SMTP -->
                        <div class="tab-pane fade" id="tab-smtp" role="tabpanel">
                            <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3 fs-6">Konfigurasi SMTP server</h5>
                            <div class="mb-3">
                                <label class="fl">SMTP Host (misal: smtp.gmail.com)</label>
                                <input type="text" name="smtp_host" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['smtp_host']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">SMTP Port (465 atau 587)</label>
                                <input type="number" name="smtp_port" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['smtp_port']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Alamat Email Pengirim (Sender/User)</label>
                                <input type="email" name="smtp_user" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['smtp_user']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">App Password / SMTP Password</label>
                                <input type="password" name="smtp_pass" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['smtp_pass']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Nama Pengirim (Sender Name)</label>
                                <input type="text" name="smtp_from_name" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['smtp_from_name']); ?>">
                            </div>
                            
                            <!-- SMTP Tester Inline -->
                            <div class="p-3 mt-4 rounded border" style="background:rgba(255,255,255,0.02);">
                                <h6 class="fw-bold mb-2"><i class="ph-fill ph-paper-plane-tilt me-1 text-primary"></i> Uji Koneksi SMTP</h6>
                                <p class="text-secondary" style="font-size:12.5px;">Pastikan Anda <b>menyimpan</b> pengaturan SMTP terlebih dahulu di bagian bawah layar sebelum melakukan pengujian.</p>
                                <div class="d-flex gap-2">
                                    <input type="email" id="testEmail" class="form-control form-control-sm fc w-50" placeholder="Masukkan email penerima test" style="max-width:250px;">
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-medium px-3" onclick="testSMTP(this)">Kirim Test</button>
                                </div>
                            </div>
                        </div>

                        <!-- TAB GOOGLE AUTH -->
                        <div class="tab-pane fade" id="tab-google" role="tabpanel">
                            <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3 fs-6">Konfigurasi Google Single Sign-On (SSO)</h5>

                            <div class="p-3 mb-4 rounded bg-light border" style="font-size:12.5px;">
                                <p class="text-dark fw-bold mb-2"><i class="ph ph-info me-1"></i> Cara Setup:</p>
                                <ol class="text-secondary mb-2 ps-3" style="line-height:2;">
                                    <li>Buka <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-primary">Google Cloud Console → Credentials</a></li>
                                    <li>Buat OAuth 2.0 Client ID → pilih <strong class="text-dark">Web application</strong></li>
                                    <li>Tambahkan <strong class="text-dark">Authorized redirect URI</strong> berikut:</li>
                                </ol>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <code id="cb-uri" class="bg-white border text-dark" style="padding:6px 12px;border-radius:6px;flex:1;font-size:12px;"><?= base_url('auth/google') ?></code>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size:11px;white-space:nowrap;" onclick="navigator.clipboard.writeText(document.getElementById('cb-uri').innerText).then(()=>{this.textContent='✓ Copied!';setTimeout(()=>{this.textContent='Copy'},1500)})">Copy</button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="fl">Google Client ID</label>
                                <input type="text" name="google_client_id" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['google_client_id'] ?? ''); ?>" placeholder="xxxxx.apps.googleusercontent.com">
                            </div>
                            <div class="mb-3">
                                <label class="fl">Google Client Secret</label>
                                <input type="password" name="google_client_secret" class="fc w-100 form-control-sm" value="<?php echo htmlspecialchars($set['google_client_secret'] ?? ''); ?>" placeholder="GOCSPX-xxxxxx">
                            </div>

                            <?php if(!empty($set['google_client_id'])): ?>
                            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);font-size:12px;color:#4ade80;">
                                <i class="ph-fill ph-check-circle" style="font-size:16px;"></i> SSO Google <strong>aktif</strong> — pengguna dapat login/daftar via Google.
                            </div>
                            <?php else: ?>
                            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(234,179,8,0.1);border:1px solid rgba(234,179,8,0.3);font-size:12px;color:#facc15;">
                                <i class="ph ph-warning" style="font-size:16px;"></i> SSO Google <strong>belum dikonfigurasi</strong> — tombol Google di halaman login/daftar akan nonaktif.
                            </div>
                            <?php endif; ?>
                        </div>


                    </div>
                    
                    <div class="mt-4 pt-3 border-top" style="border-color: var(--border) !important;">
                        <button type="submit" name="update_settings" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm ms-auto py-2 px-3" style="background: var(--accent); border: none; border-radius: 8px; font-weight: 600; font-size: 13px;">
                            <i class="ph-fill ph-check-circle" style="font-size: 18px;"></i> SIMPAN PERUBAHAN
                        </button>
                    </div>
                </div>
                </form>
            </div>
            
            <div class="card-c mt-4 pb-3" id="templates-container" style="display:none;">
                <div class="cb py-3">
                    <div class="d-flex align-items-start justify-content-between mb-3 pb-2 border-bottom border-secondary">
                        <div>
                            <h5 class="fw-bold mb-1 fs-6" style="color:var(--text);">Manajemen Template Email</h5>
                            <p class="mb-0" style="font-size:12px;color:var(--mut);">Edit subjek dan isi email untuk setiap event otomatis. Gunakan tag <code style="background:rgba(255,255,255,.08);padding:1px 5px;border-radius:4px;color:var(--accent);">:parameter:</code> untuk data dinamis.</p>
                        </div>
                    </div>

                    <!-- Legenda Parameter Global -->
                    <div class="p-3 mb-4 rounded" style="background:rgba(255,255,255,.04);border:1px solid var(--border);font-size:12px;">
                        <p class="fw-bold mb-2" style="color:var(--text);font-size:12px;"><i class="ph-fill ph-info me-1" style="color:var(--accent);"></i> Parameter Dinamis yang Tersedia:</p>
                        <div class="row g-2">
                            <?php
                            $params_info = [
                                ':nama:'        => ['Nama lengkap user','ph-user'],
                                ':email:'       => ['Alamat email user','ph-envelope'],
                                ':username:'    => ['Username cPanel','ph-identification-card'],
                                ':password:'    => ['Password cPanel (saat dibuat)','ph-lock-key'],
                                ':domain:'      => ['Domain hosting','ph-globe'],
                                ':otp:'         => ['Kode OTP verifikasi','ph-key'],
                                ':site_name:'   => ['Nama website/platform','ph-buildings'],
                                ':plan:'        => ['Nama paket hosting','ph-package'],
                                ':expiry_date:' => ['Tanggal habis masa aktif','ph-calendar-x'],
                                ':invoice_id:'  => ['Nomor invoice','ph-receipt'],
                                ':amount:'      => ['Jumlah tagihan','ph-currency-circle-dollar'],
                            ];
                            foreach($params_info as $tag => $info): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="d-flex align-items-start gap-2 p-2 rounded" style="background:rgba(255,255,255,.03);">
                                    <i class="ph-fill <?= $info[1] ?> mt-1 flex-shrink-0" style="color:var(--accent);font-size:14px;"></i>
                                    <div>
                                        <code style="font-size:11px;color:var(--ok);background:rgba(32,201,151,.1);padding:1px 5px;border-radius:3px;"><?= $tag ?></code>
                                        <div style="font-size:10.5px;color:var(--mut);margin-top:2px;"><?= $info[0] ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="accordion accordion-flush bg-transparent" id="accordionTemplates">
                        <?php
                        // Mapping parameter yang relevan per jenis template
                        $tpl_params = [
                            'register'            => [':nama:', ':email:', ':username:', ':password:', ':site_name:'],
                            'register_otp'        => [':nama:', ':email:', ':otp:', ':site_name:'],
                            'hosting_active'      => [':nama:', ':email:', ':domain:', ':username:', ':password:', ':plan:', ':expiry_date:'],
                            'hosting_suspended'   => [':nama:', ':email:', ':domain:', ':plan:'],
                            'hosting_unsuspended' => [':nama:', ':email:', ':domain:', ':plan:'],
                            'hosting_expiry'      => [':nama:', ':email:', ':domain:', ':plan:', ':expiry_date:'],
                            'payment_success'     => [':nama:', ':email:', ':invoice_id:', ':amount:', ':domain:'],
                            'forgot_password'     => [':nama:', ':email:', ':otp:', ':site_name:'],
                            'test_connection'     => [':nama:'],
                        ];

                        $q_tpl = mysqli_query($conn, "SELECT * FROM email_templates ORDER BY id ASC");
                        while($tpl = mysqli_fetch_assoc($q_tpl)):
                            $relevant_params = $tpl_params[$tpl['name']] ?? [':nama:', ':email:'];
                        ?>
                        <div class="accordion-item bg-transparent" style="border-bottom: 1px solid var(--border);">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-medium" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#tpl-<?= $tpl['id'] ?>"
                                    style="background:transparent;color:var(--text);font-size:13px;box-shadow:none;">
                                    <i class="ph-fill ph-envelope-simple me-2" style="color:var(--accent);font-size:15px;"></i>
                                    <?= htmlspecialchars($tpl['name']) ?>
                                    <span style="font-size:10px;background:rgba(255,255,255,.07);padding:2px 8px;border-radius:5px;margin-left:10px;color:var(--mut);">
                                        <?= htmlspecialchars($tpl['description']) ?>
                                    </span>
                                </button>
                            </h2>
                            <div id="tpl-<?= $tpl['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordionTemplates">
                                <div class="accordion-body pt-2 pb-3">
                                    <!-- Parameter yang relevan untuk template ini -->
                                    <div class="mb-3 p-2 rounded d-flex flex-wrap gap-1 align-items-center" style="background:rgba(255,255,255,.03);border:1px dashed var(--border);">
                                        <span style="font-size:10.5px;color:var(--mut);margin-right:4px;"><i class="ph ph-tag me-1"></i>Parameter tersedia:</span>
                                        <?php foreach($relevant_params as $p): ?>
                                        <code onclick="insertParam('body-<?= $tpl['id'] ?>','<?= $p ?>')" title="Klik untuk sisipkan" style="font-size:10.5px;color:var(--ok);background:rgba(32,201,151,.1);padding:2px 6px;border-radius:3px;cursor:pointer;border:1px solid rgba(32,201,151,.2);"><?= $p ?></code>
                                        <?php endforeach; ?>
                                        <span style="font-size:10px;color:var(--mut);margin-left:4px;">— klik untuk sisipkan ke body</span>
                                    </div>

                                    <form action="" method="POST">
                                        <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                                        <div class="mb-3">
                                            <label class="fl">Subjek Email</label>
                                            <input type="text" name="template_subject" class="fc w-100 form-control-sm" value="<?= htmlspecialchars($tpl['subject']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="fl">Isi Email (HTML didukung)</label>
                                            <textarea name="template_body" id="body-<?= $tpl['id'] ?>" class="fc w-100 form-control-sm" rows="8" style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($tpl['body']) ?></textarea>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span style="font-size:11px;color:var(--mut);">
                                                <i class="ph ph-clock me-1"></i> Terakhir diupdate: <?= !empty($tpl['updated_at']) ? date('d M Y H:i', strtotime($tpl['updated_at'])) : '—' ?>
                                            </span>
                                            <button type="submit" name="update_template" class="btn btn-sm btn-primary" style="background:var(--accent);border:none;font-size:13px;">
                                                <i class="ph ph-floppy-disk me-1"></i> Simpan Template
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                            $acat_labels = ['info'=>'ℹ️ Info','warning'=>'⚠️ Warning','promo'=>'🏷️ Promo','maintenance'=>'🔧 Maintenance'];
                            foreach($ann_list as $a):
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold" style="color:var(--text);"><?= htmlspecialchars($a['title']) ?></div>
                                    <div style="font-size:10px;color:var(--mut);"><?= htmlspecialchars(mb_substr($a['content'],0,50)) ?>...</div>
                                </td>
                                <td><span style="font-size:11px;"><?= $acat_labels[$a['category']] ?? $a['category'] ?></span></td>
                                <td style="font-size:11px;color:var(--mut);"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                                <td class="text-center">
                                    <?php if($a['is_active']): ?>
                                        <span style="font-size:10px;color:var(--ok);background:var(--oks);padding:2px 8px;border-radius:4px;">Aktif</span>
                                    <?php else: ?>
                                        <span style="font-size:10px;color:var(--mut);background:var(--surface);padding:2px 8px;border-radius:4px;">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="ab py-1 px-2 me-1" onclick="showAnnForm(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)"><i class="ph ph-pencil-simple"></i></button>
                                    <a href="website?del_ann=<?= $a['id'] ?>" class="ab py-1 px-2 red" onclick="return confirm('Hapus pengumuman ini?')"><i class="ph ph-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Form Pengumuman -->
                    <div id="ann-form-wrap" style="display:none;" class="p-3 border-top">
                        <form action="" method="POST">
                            <input type="hidden" name="ann_id" id="af_id" value="0">
                            <h6 class="fw-bold mb-3" style="color:var(--text);font-size:13px;" id="af_label">Tambah Pengumuman</h6>
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="fl mb-1">Judul *</label>
                                    <input type="text" name="ann_title" id="af_title" class="fc w-100" required placeholder="Judul pengumuman...">
                                </div>
                                <div class="col-md-2">
                                    <label class="fl mb-1">Kategori</label>
                                    <select name="ann_category" id="af_category" class="fc w-100">
                                        <option value="info">ℹ️ Info</option>
                                        <option value="warning">⚠️ Warning</option>
                                        <option value="promo">🏷️ Promo</option>
                                        <option value="maintenance">🔧 Maintenance</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <label class="d-flex align-items-center gap-1" style="cursor:pointer;color:var(--text);font-size:13px;padding-bottom:6px;">
                                        <input type="checkbox" name="ann_is_active" id="af_active" value="1" checked style="width:14px;height:14px;">
                                        <span>Aktif</span>
                                    </label>
                                </div>
                                <div class="col-12">
                                    <label class="fl mb-1">Konten</label>
                                    <textarea name="ann_content" id="af_content" class="fc w-100" rows="3" placeholder="Isi pengumuman..."></textarea>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" name="save_announcement" class="btn btn-sm btn-primary" style="background:var(--accent);border:none;font-size:13px;">
                                    <i class="ph ph-floppy-disk me-1"></i> Simpan Pengumuman
                                </button>
                                <button type="button" class="btn btn-sm" style="color:var(--mut);" onclick="document.getElementById('ann-form-wrap').style.display='none'">Batal</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div><!-- end tab-pane-konten -->

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const tabs = document.querySelectorAll('#settings-tabs button[data-bs-toggle="pill"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (event) {
                tabs.forEach(t => t.classList.add('text-muted'));
                tabs.forEach(t => t.classList.remove('text-white'));
                
                event.target.classList.remove('text-muted');
                event.target.classList.add('text-white');
                
                if(event.target.getAttribute('data-bs-target') === '#tab-templates') {
                    document.getElementById('templates-container').style.display = 'block';
                    document.querySelector('.col-md-9 .card-c').style.display = 'none'; // hide settings form card
                } else {
                    document.getElementById('templates-container').style.display = 'none';
                    document.querySelector('.col-md-9 .card-c').style.display = 'block';
                }
            })
        });
    });

    <?php if(isset($_GET['status'])): ?>
        <?php if($_GET['status'] == 'success'): ?>
        Swal.fire({
            icon: 'success',
            title: 'Tersimpan!',
            text: 'Konfigurasi sistem telah berhasil diperbarui.',
            background: 'var(--card)',
            color: 'var(--text)',
            confirmButtonColor: 'var(--accent)'
        });
        <?php elseif($_GET['status'] == 'error'): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Terjadi kesalahan saat menyimpan pembaruan ke database.',
            background: 'var(--card)',
            color: 'var(--text)',
            confirmButtonColor: 'var(--err)'
        });
        <?php endif; ?>
    <?php endif; ?>

    // Fungsi Test SMTP AJAX
    function testSMTP(btn) {
        const email = document.getElementById('testEmail').value;
        if(!email) {
            Swal.fire({icon:'warning', title:'Oops!', text:'Masukkan email untuk mendemonstrasikan pengiriman.', background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--accent)'});
            return;
        }

        const originText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner fa-spin"></i> Sending...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('ajax_test_smtp', '1');
        fd.append('test_email', email);

        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon:'success', title:'Terkirim!', text:data.message, background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--ok)'});
            } else {
                Swal.fire({ icon:'error', title:'Gagal SMTP!', text:data.message, background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--warn)'});
            }
        })
        .catch(err => {
            alert("Error: " + err);
        })
        .finally(() => {
            btn.innerHTML = originText;
            btn.disabled = false;
        });
    }

    // Insert parameter tag into textarea at cursor position
    function insertParam(textareaId, param) {
        const ta = document.getElementById(textareaId);
        if (!ta) return;
        const start = ta.selectionStart;
        const end   = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + param + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + param.length;
        ta.focus();
        // Visual feedback
        ta.style.borderColor = 'var(--ok)';
        setTimeout(() => { ta.style.borderColor = ''; }, 700);
    }
</script>

<?php include __DIR__ . '/library/footer.php'; ?>