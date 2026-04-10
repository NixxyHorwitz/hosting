<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';

if (!isset($_SESSION['user_id'])) {
    die("❌ Silakan login dulu");
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ ID tidak valid");
}

$id = (int) $_GET['id'];
$user_id = (int) $_SESSION['user_id'];

$query = mysqli_query($conn, "SELECT orders.*, hosting_plans.nama_paket FROM orders LEFT JOIN hosting_plans ON orders.hosting_plan_id = hosting_plans.id WHERE orders.id = '$id' AND orders.user_id = '$user_id' LIMIT 1");
$row_order = mysqli_fetch_assoc($query);

if (!$row_order) {
    die("❌ Data hosting tidak ditemukan.");
}

// --- AMBIL STATISTIK REAL-TIME DARI WHM ---
$whm_id = (int)$row_order['whm_id'];
$whmQuery = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id'");
$whm_server = mysqli_fetch_assoc($whmQuery);

if(!$whm_server) {
    die("❌ Server WHM tidak ditemukan, mohon hubungi admin.");
}

$whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);
try {

    $stats = $whm->getClientStats($row_order['username']);
     
    $disk_used = $stats['disk']['used'];
    $disk_limit = $stats['disk']['limit'];
    $disk_percent = $stats['disk']['percent'];

    $bw_used = $stats['bandwidth']['used'];
    $bw_limit = $stats['bandwidth']['limit'];
    $bw_percent = $stats['bandwidth']['percent'];
} catch (Exception $e) {
    // Jika API gagal (misal server down), gunakan nilai default agar page tidak error
    $disk_used = 0; $disk_limit = "N/A"; $disk_percent = 0;
    $bw_used = 0; $bw_limit = "N/A"; $bw_percent = 0;
    $api_error = $e->getMessage();
}
include __DIR__ . '/../library/header.php';
?>

<style>
    .card-rw { 
        border: 1px solid var(--border-color); 
        border-radius: 4px; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        margin-bottom: 1.5rem;
        background: #fff;
    }
    
    .card-rw .card-header {
        background-color: #fff;
        border-bottom: 1px solid var(--border-color);
        padding: 1.25rem 1.5rem;
        font-weight: 700;
        color: #333;
    }

    /* Sidebar Styles */
    .list-group-rw .list-group-item {
        border: none;
        padding: 10px 0;
        font-size: 14px;
        color: #555;
    }
    .list-group-rw .list-group-item:hover { color: #007bff; background: transparent; }

    /* Stats Progress Bars */
    .usage-label { font-size: 12px; font-weight: 600; color: #666; margin-bottom: 5px; display: flex; justify-content: space-between; }
    .progress { height: 8px; border-radius: 10px; margin-bottom: 15px; }

    /* Shortcut Grid */
    .cpanel-shortcut {
        text-align: center;
        padding: 15px 10px;
        border-radius: 4px;
        transition: all 0.2s;
        text-decoration: none !important;
        display: block;
        color: #444 !important;
        font-size: 12px;
        border: 1px solid transparent;
        background: #fff;
    }
    .cpanel-shortcut:hover {
        background: #f8fbff;
        border-color: #bbd6f5;
        transform: translateY(-2px);
    }
    .cpanel-shortcut img {
        width: 38px;
        height: 38px;
        margin-bottom: 10px;
        filter: grayscale(10%) contrast(90%);
    }

    .btn-rw-primary { background: #007bff; color: #fff; border: none; padding: 8px 20px; border-radius: 4px; font-size: 14px; transition: 0.2s; }
    .btn-rw-outline { border: 1px solid var(--border-color); color: #555; padding: 8px 20px; border-radius: 4px; font-size: 14px; background: #fff; transition: 0.2s; }
    .btn-rw-primary:hover { background: #0056b3; color: #fff; }
    .btn-rw-outline:hover { background: #f8f9fa; border-color: #d1d8e0; }
    
    .status-badge {
        background: #e6f7eb;
        color: #20c997;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
</style>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0" style="font-size: 0.85rem;">
            <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>" style="color: #007bff;">Portal Home</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('hosting/layanan') ?>" style="color: #007bff;">Client Area</a></li>
            <li class="breadcrumb-item active text-muted">Hosting Details</li>
        </ol>
    </nav>
</div>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="card-rw p-4">
            <h6 class="fw-bold mb-3 text-dark">Actions</h6>
            <div class="list-group list-group-rw">
                <a href="<?= base_url('hosting/layanan') ?>" class="list-group-item"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
                <a href="<?= base_url('hosting/cpanel/' . $id) ?>" target="_blank" class="list-group-item text-primary fw-bold">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Login to cPanel
                </a>
                <a href="<?= base_url('hosting/webmail/' . $id) ?>" target="_blank" class="list-group-item">
                    <i class="bi bi-envelope me-2"></i> Login to Webmail
                </a>
            </div>
        </div>

        <div class="card-rw p-4">
            <h6 class="fw-bold mb-3 text-dark">Usage Statistics</h6>
            
            <div class="usage-item">
                <div class="usage-label">
                    <span>Disk Usage</span>
                    <span><?= $disk_percent ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-primary" style="width: <?= $disk_percent ?>%"></div>
                </div>
                <p class="text-muted" style="font-size: 11px;"><?= $disk_used ?> MB of <?= $disk_limit ?> MB</p>
            </div>

            <div class="usage-item mt-3">
                <div class="usage-label">
                    <span>Bandwidth</span>
                    <span><?= $bw_percent ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-success" style="width: <?= $bw_percent ?>%"></div>
                </div>
                <p class="text-muted" style="font-size: 11px;"><?= $bw_used ?> MB of <?= $bw_limit ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="card-rw p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="status-badge mb-2 d-inline-block">
                        <?= ucfirst($row_order['status']) ?>
                    </span>
                    <h3 class="fw-bold m-0 text-dark"><?= htmlspecialchars($row_order['nama_paket'] ?? 'Paket Hosting', ENT_QUOTES) ?></h3>
                    <p class="text-muted mb-3"><?= htmlspecialchars($row_order['domain']) ?></p>
                </div>
                <div class="text-end">
                    <a href="http://<?= $row_order['domain'] ?>" target="_blank" class="btn btn-rw-outline me-2"><i class="bi bi-box-arrow-up-right me-1"></i> Visit Website</a>
                    <a href="<?= base_url('hosting/cpanel_sso/' . $id) ?>" target="_blank" class="btn btn-rw-primary">
                        <i class="bi bi-gear-fill me-1"></i> Manage
                    </a>
                </div>
            </div>
        </div>

        <div class="card-rw">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-grid-fill me-2" style="color: #48cae4;"></i> Quick Shortcuts
            </div>
            <div class="card-body p-4 bg-light" style="background: #fdfdfd !important;">
                <div class="row g-3">
                    <?php 
                    $shortcuts = [
                        ['Email Accounts', 'email_accounts.png', 'Email_Accounts'],
                        ['Forwarders', 'forwarders.png', 'Email_Forwarders'],
                        ['File Manager', 'file_manager.png', 'FileManager_Home'],
                        ['Backup', 'backup.png', 'Backups_Home'],
                        ['Subdomains', 'subdomains.png', 'Domains_SubDomains'],
                        ['MySQL Databases', 'mysql_databases.png', 'Database_MySQL'],
                        ['phpMyAdmin', 'php_my_admin.png', 'Database_phpMyAdmin'],
                        ['Cron Jobs', 'cron_jobs.png', 'Cron_Home'],
                    ];
                    foreach ($shortcuts as $sh): ?>
                    <div class="col-md-3 col-6">
                        <a href="<?= base_url('hosting/cpanel_sso/' . $id . '?app=' . $sh[2]) ?>" target="_blank" class="cpanel-shortcut shadow-sm">
                            <img src="<?= base_url('assets/' . $sh[1]) ?>" alt="<?= $sh[0] ?>">
                            <div class="fw-medium text-dark"><?= $sh[0] ?></div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <p class="text-center text-muted mt-4" style="font-size: 12px;">
            <i class="bi bi-clock me-1"></i> Information last updated: <strong><?= date('d/m/Y H:i') ?></strong>
        </p>
    </div>
</div>

<?php include __DIR__ . '/../library/footer.php'; ?>
