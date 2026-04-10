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

// Ambil data settings
$query = mysqli_query($conn, "SELECT * FROM settings WHERE id = 1");
$set = mysqli_fetch_assoc($query);

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
                    <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3 fs-6">Manajemen Template Email Dinamis</h5>
                    <p class="text-muted small">Anda bisa menggunakan tag dinamis (contoh: :nama:, :domain:, :username:, :password:, :otp:)</p>
                    <div class="accordion accordion-flush bg-transparent" id="accordionTemplates">
                        <?php
                        $q_tpl = mysqli_query($conn, "SELECT * FROM email_templates ORDER BY id ASC");
                        while($tpl = mysqli_fetch_assoc($q_tpl)):
                        ?>
                        <div class="accordion-item bg-transparent" style="border-bottom: 1px solid var(--border);">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-transparent text-white" type="button" data-bs-toggle="collapse" data-bs-target="#tpl-<?= $tpl['id'] ?>">
                                    <?= htmlspecialchars($tpl['name']) ?> - <small class="text-muted ms-2 px-1 rounded bg-secondary"><?= htmlspecialchars($tpl['description']) ?></small>
                                </button>
                            </h2>
                            <div id="tpl-<?= $tpl['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordionTemplates">
                                <div class="accordion-body">
                                    <form action="" method="POST">
                                        <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                                        <div class="mb-3">
                                            <label class="fl">Subjek Email</label>
                                            <input type="text" name="template_subject" class="fc w-100 form-control-sm" value="<?= htmlspecialchars($tpl['subject']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="fl">Isi Email (Mendukung HTML)</label>
                                            <textarea name="template_body" class="fc w-100 form-control-sm" rows="6"><?= htmlspecialchars($tpl['body']) ?></textarea>
                                        </div>
                                        <button type="submit" name="update_template" class="btn btn-sm btn-primary">Simpan Template Ini</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
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
</script>

<?php include __DIR__ . '/library/footer.php'; ?>