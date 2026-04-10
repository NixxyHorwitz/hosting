<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if(!defined('NS1')) include __DIR__ . '/../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login"); 
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) {
    header("Location: whm_servers");
    exit();
}

// Get Server Info
$server_query = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$id'");
$server = mysqli_fetch_assoc($server_query);
if (!$server) {
    header("Location: whm_servers");
    exit();
}

$page_title = "Overview Node WHM #" . $id;
include __DIR__ . '/library/header.php';

// Query data orders pada server ini
$orders_query = mysqli_query($conn, "SELECT orders.*, users.nama as nama_user, users.email as email_user, hosting_plans.nama_paket 
                                     FROM orders 
                                     LEFT JOIN users ON orders.user_id = users.id 
                                     LEFT JOIN hosting_plans ON orders.hosting_plan_id = hosting_plans.id 
                                     WHERE orders.whm_id = '$id' 
                                     ORDER BY orders.created_at DESC");
$total_orders = mysqli_num_rows($orders_query);
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="fs-4">Overview Node WHM</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bc m-0">
                <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url('admin/whm_servers') ?>">WHM Servers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Overview Node #<?= $id ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-c px-3 py-3 d-flex align-items-center">
            <div class="si blue m-0 me-3" style="width: 42px; height: 42px; font-size: 20px; flex-shrink: 0;"><i class="ph-fill ph-hard-drives"></i></div>
            <div style="min-width: 0;">
                <div class="text-truncate" style="font-size: 11px; font-weight: 600; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Hostname (Node #<?= $server['id'] ?>)</div>
                <div class="text-white text-truncate fw-bold" style="font-size: 15px;"><?= htmlspecialchars($server['whm_host']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-c px-3 py-3 d-flex align-items-center">
            <div class="si orange m-0 me-3" style="width: 42px; height: 42px; font-size: 20px; flex-shrink: 0;"><i class="ph-fill ph-users"></i></div>
            <div style="min-width: 0;">
                <div class="text-truncate" style="font-size: 11px; font-weight: 600; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Kapasitas Akun & Limit</div>
                <div class="text-white fw-bold" style="font-size: 15px;"><?= $total_orders ?> / <?= $server['limit_cpanel'] ?> Limit</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-c px-3 py-3 d-flex align-items-center">
            <div class="si green m-0 me-3" style="width: 42px; height: 42px; font-size: 20px; flex-shrink: 0;"><i class="ph-fill ph-shield-check"></i></div>
            <div style="min-width: 0;">
                <div class="text-truncate" style="font-size: 11px; font-weight: 600; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Hak Akses Otorisasi</div>
                <div class="text-white fw-bold" style="font-size: 15px;"><?= htmlspecialchars($server['whm_username']) ?> Access</div>
            </div>
        </div>
    </div>
</div>

<div class="card-c">
    <div class="ch py-2">
        <h3 class="ct m-0 fs-6">Orders & Accounts Hosted on Node #<?= $server['id'] ?></h3>
    </div>
    <div class="cb p-0">
        <div class="table-responsive">
            <table class="tbl table-hover w-100 mb-0" id="tableNodeOverview" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th class="py-2">Order ID</th>
                        <th class="py-2">Pelanggan</th>
                        <th class="py-2">Domain</th>
                        <th class="py-2">Akun cPanel</th>
                        <th class="py-2">Paket Hosting</th>
                        <th class="py-2 text-center">Status Layanan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($total_orders > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($orders_query)): ?>
                        <tr>
                            <td class="text-white fw-bold py-2">#<?= $row['id'] ?></td>
                            <td class="py-2">
                                <div class="fw-medium" style="color: var(--text);"><?= htmlspecialchars($row['nama_user'] ?? 'N/A') ?></div>
                                <div style="font-size: 11px; color: rgba(255,255,255,0.5);"><i class="ph-fill ph-envelope-simple me-1"></i><?= htmlspecialchars($row['email_user'] ?? '') ?></div>
                            </td>
                            <td class="py-2">
                                <div class="text-primary"><i class="ph-fill ph-link me-1"></i><?= htmlspecialchars($row['domain'] ?? '-') ?></div>
                            </td>
                            <td class="py-2">
                                <div class="text-white py-1 px-2 rounded-1 d-inline-block" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); font-family: monospace; font-size: 12.5px;">
                                    <i class="ph-fill ph-user text-warning me-1"></i><?= htmlspecialchars($row['username'] ?? 'Menunggu') ?>
                                </div>
                            </td>
                            <td class="py-2">
                                <span class="fw-medium" style="color: var(--text); font-size: 13px;"><?= htmlspecialchars($row['nama_paket'] ?? 'Custom Plan') ?></span>
                            </td>
                            <td class="py-2 text-center">
                                <?php if($row['status'] == 'active'): ?>
                                    <span class="bd bd-ok" style="font-size: 10px;">ACTIVE</span>
                                <?php elseif($row['status'] == 'suspended'): ?>
                                    <span class="bd bd-err" style="font-size: 10px;">SUSPENDED</span>
                                <?php else: ?>
                                    <span class="bd bd-warn" style="font-size: 10px;"><?= strtoupper($row['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/library/footer.php'; ?>
