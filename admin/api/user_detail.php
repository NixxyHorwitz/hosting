<?php
include __DIR__ . '/../../config/database.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

function show_error_modal($msg) {
    echo '
    <div class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="background:#151821; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                <div class="modal-body text-center py-4">
                    <i class="ph-fill ph-warning-circle text-danger mb-3" style="font-size: 40px;"></i>
                    <h6 class="text-white">'.$msg.'</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>';
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    show_error_modal("Sesi kadaluarsa. Silakan muat ulang halaman.");
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($user_id <= 0) {
    show_error_modal("ID Pengguna tidak valid.");
}

$query = mysqli_query($conn, "SELECT id, nama, email, role, negara, provinsi, kota, kabupaten, kode_pos, no_whatsapp FROM users WHERE id = '$user_id'");
if (!$query) {
    show_error_modal("Database Error: " . mysqli_error($conn));
}
$user = mysqli_fetch_assoc($query);

if(!$user) {
    show_error_modal("Pengguna tidak ditemukan di database.");
}



// Stats Tiket
$t_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM tickets WHERE user_id = '$user_id'");
$user['total_tickets'] = ($t_query && $t_row = mysqli_fetch_assoc($t_query)) ? $t_row['total'] : 0;

// Daftar Layanan Hosting
$orders_query = mysqli_query($conn, "SELECT o.id, o.domain, o.status, h.nama_paket FROM orders o LEFT JOIN hosting_plans h ON o.hosting_plan_id = h.id WHERE o.user_id = '$user_id' ORDER BY o.id DESC LIMIT 4");

$active_services = 0;
$services_html = '';
if($orders_query && mysqli_num_rows($orders_query) > 0) {
    while($ord = mysqli_fetch_assoc($orders_query)) {
        if($ord['status'] == 'active') $active_services++;
        
        $sbd = 'text-warning';
        if($ord['status'] == 'active') $sbd = 'text-success';
        if($ord['status'] == 'suspended') $sbd = 'text-danger';

        $services_html .= '
        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
            <div style="line-height: 1.2;">
                <div class="text-white fw-bold" style="font-size: 13px;"><i class="ph-fill ph-link me-1 text-primary"></i> '.htmlspecialchars($ord['domain']).'</div>
                <div style="font-size: 10.5px; color: rgba(255,255,255,0.5); margin-left: 20px;">#'.$ord['id'].' • '.htmlspecialchars($ord['nama_paket'] ?? 'Custom').'</div>
            </div>
            <span class="'.$sbd.'" style="font-size: 10px; font-weight: 700; background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">'.strtoupper($ord['status']).'</span>
        </div>';
    }
} else {
    $services_html = '<div class="text-center py-4 text-muted"><i class="ph-fill ph-hard-drives d-block mb-2 text-secondary" style="font-size: 32px;"></i> <div style="font-size: 12px;">Belum memiliki layanan hosting</div></div>';
}

$initial = strtoupper(substr($user['nama'], 0, 1));
$role_text = ($user['role'] == 'admin') ? 'Administrator' : 'Client Reguler';

// Check if balance exists in users, assuming it didn't
$balance = 'Rp 0'; 
$loc = implode(', ', array_filter([$user['kota'] ?? '', $user['provinsi'] ?? ''])) ?: '-';
$email = $user['email'] ? htmlspecialchars($user['email']) : '-';
$phone = $user['no_whatsapp'] ? htmlspecialchars($user['no_whatsapp']) : '-';
?>

?>

<div class="modal fade" id="dynamicUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="background: var(--card, #1a1d27); color: var(--text, #fff); border-radius: 12px; overflow: hidden;">
            
            <div class="modal-header d-flex align-items-center justify-content-between p-4" style="background: var(--surface, #151821); border-bottom: 1px solid var(--border, rgba(255,255,255,0.05));">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold shadow-sm" style="width: 50px; height: 50px; font-size: 20px; background: #477aee;">
                        <?= $initial ?>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1 text-white" style="font-size: 18px; line-height: 1.2;"><?= htmlspecialchars($user['nama']) ?></h4>
                        <div style="font-size: 12px; color: var(--sub, rgba(255,255,255,0.6));"><i class="ph-bold ph-identification-badge text-primary pe-1"></i><?= $role_text ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 11px;"></button>
            </div>
            
            <div class="modal-body p-4 p-md-4">
                <div class="row g-4">
                    <!-- Left Kolom: Info Dasar -->
                    <div class="col-md-5">
                        <div class="card-c h-100 shadow-none m-0" style="background: var(--surface, #151821); border: 1px solid var(--border, rgba(255,255,255,0.05)); box-shadow: none !important;">
                            <div class="cb p-3">
                                <h6 class="fw-bold mb-3" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary, #477aee);">Identitas & Kontak</h6>
                                
                                <div class="d-flex flex-column gap-3">
                                    <div>
                                        <div style="font-size: 10px; color: var(--sub, rgba(255,255,255,0.5)); text-transform: uppercase; font-weight: 600;">Alamat Email</div>
                                        <div class="text-white fw-medium text-truncate mt-1" style="font-size: 13.5px;" title="<?= $email ?>"><i class="ph-fill ph-envelope-simple text-secondary me-2"></i><?= $email ?></div>
                                    </div>
                                    
                                    <div>
                                        <div style="font-size: 10px; color: var(--sub, rgba(255,255,255,0.5)); text-transform: uppercase; font-weight: 600;">WhatsApp</div>
                                        <div class="text-white fw-medium mt-1" style="font-size: 13.5px;"><i class="ph-fill ph-whatsapp-logo text-success me-2"></i><?= $phone ?></div>
                                    </div>

                                    <div>
                                        <div style="font-size: 10px; color: var(--sub, rgba(255,255,255,0.5)); text-transform: uppercase; font-weight: 600;">Wilayah</div>
                                        <div class="text-white fw-medium mt-1" style="font-size: 13.5px;"><i class="ph-fill ph-map-pin text-danger me-2"></i><?= htmlspecialchars($loc) ?></div>
                                    </div>
                                </div>
                                
                                <a href="<?= base_url('admin/users') ?>" class="btn btn-sm btn-outline-primary w-100 mt-4 rounded-3 fw-bold" style="font-size: 11.5px; border-color: rgba(71, 122, 238, 0.3);">
                                    <i class="ph-bold ph-pencil-simple me-1"></i> Edit Lengkap
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Kolom: Layanan -->
                    <div class="col-md-7">
                        <div class="card-c h-100 shadow-none m-0" style="background: var(--surface, #151821); border: 1px solid var(--border, rgba(255,255,255,0.05)); box-shadow: none !important;">
                            <div class="cb p-3 d-flex flex-column h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-2" style="border-bottom: 1px solid var(--border, rgba(255,255,255,0.05));">
                                    <h6 class="fw-bold m-0" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary, #477aee);">Ringkasan Layanan</h6>
                                    <span class="badge rounded-pill" style="background: rgba(71, 122, 238, 0.15); color: #477aee; font-size: 10.5px; font-weight: 600; padding: 4px 10px;"><?= $active_services ?> Hosting Aktif</span>
                                </div>
                                
                                <div class="flex-grow-1" style="max-height: 220px; overflow-y: auto; overflow-x: hidden; padding-right: 5px;">
                                    <?= $services_html ?>
                                </div>
                                
                                <div class="mt-auto pt-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center" style="font-size: 12px; color: var(--sub, rgba(255,255,255,0.6)); background: rgba(0,0,0,0.2); padding: 5px 12px; border-radius: 6px; border: 1px solid var(--border, rgba(255,255,255,0.05));">
                                        <i class="ph-fill ph-ticket text-warning me-2"></i> Total <strong class="text-white mx-1"><?= $user['total_tickets'] ?></strong> Tiket Support
                                    </div>
                                    <a href="<?= base_url('admin/tickets?user='.$user['id']) ?>" class="btn btn-sm btn-primary rounded-3 text-white shadow-sm fw-bold px-3 py-1" style="font-size: 11.5px;">
                                        Lihat Tiket <i class="ph-bold ph-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End Row -->
            </div>
            
        </div>
    </div>
</div>
<script>
// Prevent duplicate auto-init issues if bootstrap initializes it automatically, we handle it in footer.php
</script>
