<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login");
    exit;
}

$user_id = $_SESSION['user_id'];

// Stats
$counts = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_any,
        SUM(CASE WHEN status='paid'      THEN 1 ELSE 0 END) as total_paid,
        SUM(CASE WHEN status='unpaid'    THEN 1 ELSE 0 END) as total_unpaid,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as total_cancelled
    FROM invoices WHERE user_id='$user_id'
"));

$due = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count_due, IFNULL(SUM(total_tagihan),0) as sum_due 
    FROM invoices WHERE user_id='$user_id' AND status='unpaid'
"));

$filter = isset($_GET['status']) ? $_GET['status'] : 'any';
$where_status = '';
$title_filter = 'Semua';
if ($filter === 'paid')      { $where_status = "AND status='paid'";      $title_filter = 'Lunas'; }
if ($filter === 'unpaid')    { $where_status = "AND status='unpaid'";    $title_filter = 'Belum Lunas'; }
if ($filter === 'cancelled') { $where_status = "AND status='cancelled'"; $title_filter = 'Dibatalkan'; }

// Pagination
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total_inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM invoices WHERE user_id='$user_id' $where_status"))['c'];
$total_pages = max(1, ceil($total_inv / $per_page));

$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';
$search_where = $search ? "AND id LIKE '%$search%'" : '';

$invoices = mysqli_query($conn, "SELECT * FROM invoices WHERE user_id='$user_id' $where_status $search_where ORDER BY date_created DESC LIMIT $per_page OFFSET $offset");

$page_title = "Invoices";
include __DIR__ . '/../library/header.php';
?>

<style>
/* ─── Summary Cards ─── */
.bill-cards { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:16px; }
@media(min-width:576px){ .bill-cards { grid-template-columns:repeat(4,1fr); } }

.bill-card {
    background:#fff;
    border:1px solid var(--border-color);
    border-radius:10px;
    padding:12px 14px;
    display:flex;
    align-items:center;
    gap:10px;
    cursor:pointer;
    transition:all .2s;
    text-decoration:none;
    color:inherit;
}
.bill-card:hover { border-color:#007bff22; box-shadow:0 2px 10px rgba(0,120,255,.07); }
.bill-card.active-filter { border-color:#007bff; background:#ebf5ff; }
.bill-card-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.bill-card-val { font-size:18px; font-weight:800; line-height:1.1; }
.bill-card-lbl { font-size:10px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-top:1px; }

/* ─── Alert bar ─── */
.due-alert {
    background:#fff5f5; border:1px solid #fecaca; border-radius:10px;
    padding:12px 16px; display:flex; align-items:center; gap:10px;
    margin-bottom:16px; font-size:12.5px; color:#b91c1c; font-weight:600;
}
.due-alert i { font-size:18px; }

/* ─── Invoice Table Card ─── */
.inv-card { background:#fff; border:1px solid var(--border-color); border-radius:12px; overflow:hidden; }
.inv-card-head { padding:12px 16px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.inv-card-title { font-weight:700; font-size:14px; color:#333; display:flex; align-items:center; gap:8px; }

/* ─── Filter pills (mobile) ─── */
.filter-pills { display:flex; gap:6px; overflow-x:auto; padding-bottom:2px; margin-bottom:14px; }
.filter-pills::-webkit-scrollbar { height:3px; }
.filter-pills::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:9px; }
.fpill {
    display:inline-flex; align-items:center; gap:4px; white-space:nowrap;
    padding:5px 12px; border-radius:20px; border:1px solid var(--border-color);
    font-size:11.5px; font-weight:600; color:var(--text-muted); cursor:pointer;
    text-decoration:none; transition:all .15s; background:#fff;
}
.fpill:hover { border-color:#007bff44; color:#007bff; }
.fpill.active { background:#ebf5ff; border-color:#007bff; color:#007bff; }
.fpill-count { background:#e2e8f0; color:#4a5568; padding:1px 6px; border-radius:10px; font-size:10px; }
.fpill.active .fpill-count { background:#bbd6f5; color:#007bff; }

/* ─── Invoice list rows ─── */
.inv-row {
    display:flex; align-items:center; gap:12px;
    padding:11px 16px; border-bottom:1px solid var(--border-color);
    cursor:pointer; transition:background .12s; text-decoration:none; color:inherit;
}
.inv-row:last-child { border-bottom:none; }
.inv-row:hover { background:#f8f9fb; }
.inv-id { font-family:monospace; font-size:12px; font-weight:700; color:#007bff; background:#ebf5ff; padding:2px 7px; border-radius:5px; white-space:nowrap; }
.inv-date-col { min-width:80px; }
.inv-date { font-size:12px; color:#333; font-weight:600; }
.inv-sub { font-size:10.5px; color:var(--text-muted); margin-top:1px; }
.inv-amount { font-weight:700; font-size:13px; color:#333; white-space:nowrap; }

/* ─── Status badges ─── */
.ibadge { display:inline-flex; align-items:center; gap:3px; padding:3px 9px; border-radius:5px; font-size:10px; font-weight:700; white-space:nowrap; }
.ibadge-paid      { background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0; }
.ibadge-unpaid    { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.ibadge-cancelled { background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; }
.ibadge-overdue   { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }

/* ─── Empty state ─── */
.inv-empty { text-align:center; padding:40px 20px; color:var(--text-muted); }
.inv-empty i { font-size:36px; opacity:.4; display:block; margin-bottom:10px; }

/* ─── Search + toolbar ─── */
.inv-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.inv-search { flex:1; min-width:140px; max-width:240px; position:relative; }
.inv-search input { padding:6px 10px 6px 30px; border:1px solid var(--border-color); border-radius:7px; font-size:12.5px; width:100%; background:#f8f9fa; outline:none; transition:border .15s; }
.inv-search input:focus { border-color:#007bff77; background:#fff; }
.inv-search i { position:absolute; left:9px; top:50%; transform:translateY(-50%); font-size:13px; color:var(--text-muted); pointer-events:none; }

/* ─── Pagination ─── */
.inv-page { display:flex; align-items:center; gap:4px; justify-content:center; padding:12px; border-top:1px solid var(--border-color); }
.ppage { width:30px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:12px; font-weight:600; border:1px solid var(--border-color); color:var(--text-muted); text-decoration:none; transition:all .15s; }
.ppage:hover { border-color:#007bff44; color:#007bff; }
.ppage.active { background:#007bff; border-color:#007bff; color:#fff; }
.ppage.disabled { opacity:.4; pointer-events:none; }

@media(max-width:575px) {
    .inv-row { gap:8px; padding:10px 12px; }
    .inv-date-col { display:none; }
    .inv-amount { font-size:12px; }
}
</style>

<!-- ─── Due Alert ─── -->
<?php if ($due['count_due'] > 0): ?>
<div class="due-alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        Anda memiliki <strong><?= $due['count_due'] ?></strong> invoice belum lunas, total
        <strong>Rp <?= number_format($due['sum_due'], 0, ',', '.') ?></strong>.
        <a href="?status=unpaid" style="color:#b91c1c;text-decoration:underline;margin-left:6px;">Bayar sekarang →</a>
    </div>
</div>
<?php endif; ?>

<!-- ─── Summary Cards ─── -->
<div class="bill-cards">
    <a href="?status=any" class="bill-card <?= $filter==='any'?'active-filter':'' ?>">
        <div class="bill-card-icon" style="background:#eff6ff;color:#3b82f6;"><i class="bi bi-receipt"></i></div>
        <div>
            <div class="bill-card-val"><?= (int)$counts['total_any'] ?></div>
            <div class="bill-card-lbl">Semua</div>
        </div>
    </a>
    <a href="?status=unpaid" class="bill-card <?= $filter==='unpaid'?'active-filter':'' ?>">
        <div class="bill-card-icon" style="background:#fef2f2;color:#ef4444;"><i class="bi bi-clock-history"></i></div>
        <div>
            <div class="bill-card-val" style="color:<?= $counts['total_unpaid']>0?'#ef4444':'inherit' ?>;"><?= (int)$counts['total_unpaid'] ?></div>
            <div class="bill-card-lbl">Belum Lunas</div>
        </div>
    </a>
    <a href="?status=paid" class="bill-card <?= $filter==='paid'?'active-filter':'' ?>">
        <div class="bill-card-icon" style="background:#f0fdf4;color:#22c55e;"><i class="bi bi-check-circle"></i></div>
        <div>
            <div class="bill-card-val" style="color:#22c55e;"><?= (int)$counts['total_paid'] ?></div>
            <div class="bill-card-lbl">Lunas</div>
        </div>
    </a>
    <a href="?status=cancelled" class="bill-card <?= $filter==='cancelled'?'active-filter':'' ?>">
        <div class="bill-card-icon" style="background:#f9fafb;color:#9ca3af;"><i class="bi bi-x-circle"></i></div>
        <div>
            <div class="bill-card-val" style="color:#9ca3af;"><?= (int)$counts['total_cancelled'] ?></div>
            <div class="bill-card-lbl">Dibatalkan</div>
        </div>
    </a>
</div>

<!-- ─── Invoice List Card ─── -->
<div class="inv-card">
    <div class="inv-card-head">
        <div class="inv-card-title">
            <i class="bi bi-receipt" style="color:#007bff;"></i>
            Daftar Invoice &mdash; <span style="color:#007bff;"><?= $title_filter ?></span>
            <span style="background:#e2e8f0;color:#4a5568;padding:2px 8px;border-radius:8px;font-size:11px;"><?= $total_inv ?></span>
        </div>
        <!-- Search -->
        <div class="inv-toolbar">
            <form method="GET" class="inv-search" style="position:relative;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Cari ID invoice..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            </form>
        </div>
    </div>

    <!-- Filter pills: compact on mobile -->
    <div style="padding:10px 16px 2px;">
        <div class="filter-pills">
            <a href="?status=any"       class="fpill <?= $filter==='any'?'active':''?>">Semua        <span class="fpill-count"><?= (int)$counts['total_any'] ?></span></a>
            <a href="?status=unpaid"    class="fpill <?= $filter==='unpaid'?'active':''?>">Belum Lunas  <span class="fpill-count"><?= (int)$counts['total_unpaid'] ?></span></a>
            <a href="?status=paid"      class="fpill <?= $filter==='paid'?'active':''?>">Lunas        <span class="fpill-count"><?= (int)$counts['total_paid'] ?></span></a>
            <a href="?status=cancelled" class="fpill <?= $filter==='cancelled'?'active':''?>">Dibatalkan   <span class="fpill-count"><?= (int)$counts['total_cancelled'] ?></span></a>
        </div>
    </div>

    <!-- Rows -->
    <?php if ($invoices && mysqli_num_rows($invoices) > 0): ?>
    <?php while($row = mysqli_fetch_assoc($invoices)):
        $inv_id   = $row['id'];
        $d_create = $row['date_created'] ? date('d M Y', strtotime($row['date_created'])) : '—';
        $d_due    = $row['date_due']     ? date('d M Y', strtotime($row['date_due']))    : '—';
        $is_overdue = ($row['status'] === 'unpaid' && $row['date_due'] && time() > strtotime($row['date_due']));

        if ($row['status'] === 'paid'):
            $badge = '<span class="ibadge ibadge-paid"><i class="bi bi-check-circle-fill"></i> Lunas</span>';
        elseif ($is_overdue):
            $badge = '<span class="ibadge ibadge-overdue"><i class="bi bi-clock-fill"></i> Overdue</span>';
        elseif ($row['status'] === 'unpaid'):
            $badge = '<span class="ibadge ibadge-unpaid"><i class="bi bi-clock"></i> Belum Lunas</span>';
        else:
            $badge = '<span class="ibadge ibadge-cancelled"><i class="bi bi-x-circle"></i> Dibatalkan</span>';
        endif;
    ?>
    <a href="<?= base_url("hosting/invoice/$inv_id") ?>" class="inv-row">
        <span class="inv-id">#<?= str_pad($inv_id, 5, '0', STR_PAD_LEFT) ?></span>
        <div class="inv-date-col">
            <div class="inv-date"><?= $d_create ?></div>
            <div class="inv-sub" style="color:<?= $is_overdue?'#dc2626':'var(--text-muted)' ?>;">
                Due: <?= $d_due ?>
            </div>
        </div>
        <div style="flex:1;"></div>
        <div class="inv-amount">Rp <?= number_format($row['total_tagihan'], 0, ',', '.') ?></div>
        <?= $badge ?>
        <i class="bi bi-chevron-right" style="color:var(--text-muted);font-size:11px;flex-shrink:0;"></i>
    </a>
    <?php endwhile; ?>
    <?php else: ?>
    <div class="inv-empty">
        <i class="bi bi-receipt"></i>
        Tidak ada invoice <?= $filter !== 'any' ? "berstatus \"$title_filter\"" : '' ?>.
        <?php if($search): ?>
        <div style="font-size:12px;margin-top:6px;">Tidak ditemukan untuk "<strong><?= htmlspecialchars($search) ?></strong>"</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="inv-page">
        <a href="?status=<?= $filter ?>&page=1" class="ppage <?= $page<=1?'disabled':'' ?>" title="Pertama">«</a>
        <a href="?status=<?= $filter ?>&page=<?= max(1,$page-1) ?>" class="ppage <?= $page<=1?'disabled':'' ?>">‹</a>
        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($total_pages, $page + $range);
        if($start > 1) echo '<span class="ppage disabled">…</span>';
        for($p=$start; $p<=$end; $p++):
        ?>
        <a href="?status=<?= $filter ?>&page=<?= $p ?>" class="ppage <?= $p==$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor;
        if($end < $total_pages) echo '<span class="ppage disabled">…</span>';
        ?>
        <a href="?status=<?= $filter ?>&page=<?= min($total_pages,$page+1) ?>" class="ppage <?= $page>=$total_pages?'disabled':'' ?>">›</a>
        <a href="?status=<?= $filter ?>&page=<?= $total_pages ?>" class="ppage <?= $page>=$total_pages?'disabled':'' ?>" title="Terakhir">»</a>
        <span style="font-size:11px;color:var(--text-muted);margin-left:6px;">Hal <?= $page ?>/<?= $total_pages ?></span>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../library/footer.php'; ?>
