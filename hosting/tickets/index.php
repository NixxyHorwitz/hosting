<?php
require_once __DIR__ . '/../../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../library/session.php';

$user_id = $_SESSION['user_id'];

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='Open' THEN 1 ELSE 0 END) as open_c,
        SUM(CASE WHEN status='Answered' THEN 1 ELSE 0 END) as answered_c,
        SUM(CASE WHEN status='Closed' THEN 1 ELSE 0 END) as closed_c,
        SUM(CASE WHEN status='Customer-Reply' THEN 1 ELSE 0 END) as reply_c
    FROM tickets WHERE user_id='$user_id'
"));

$filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$where_f = '';
if ($filter === 'open')     $where_f = "AND t.status IN ('Open','Customer-Reply')";
elseif ($filter === 'done') $where_f = "AND t.status IN ('Answered','Closed')";

$tickets_query = mysqli_query($conn, "SELECT t.*,
    (SELECT created_at FROM ticket_replies tr WHERE tr.ticket_id=t.id ORDER BY id DESC LIMIT 1) as last_reply_time,
    (SELECT message  FROM ticket_replies tr WHERE tr.ticket_id=t.id ORDER BY id DESC LIMIT 1) as last_reply_text,
    (SELECT admin_id FROM ticket_replies tr WHERE tr.ticket_id=t.id ORDER BY id DESC LIMIT 1) as last_admin_id
    FROM tickets t WHERE user_id='$user_id' $where_f
    ORDER BY CASE t.status WHEN 'Customer-Reply' THEN 0 WHEN 'Open' THEN 1 ELSE 2 END, t.id DESC");

// Contact info from settings
$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT contact_phone, contact_email, contact_billing, contact_info, contact_support FROM settings LIMIT 1")) ?? [];

$page_title = "Trouble Ticket";
include __DIR__ . '/../../library/header.php';
?>

<style>
/* ─── Stat bar ─── */
.tstat-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:16px; }
@media(max-width:576px){ .tstat-bar { grid-template-columns:repeat(2,1fr); } }
.tstat { background:#fff; border:1px solid var(--border-color); border-radius:10px; padding:10px 14px; display:flex; align-items:center; gap:10px; }
.tstat-ico { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.tstat-val { font-size:18px; font-weight:800; line-height:1.1; }
.tstat-lbl { font-size:10px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; }

/* ─── Filter pills ─── */
.t-pills { display:flex; gap:6px; overflow-x:auto; padding-bottom:2px; margin-bottom:14px; }
.t-pills::-webkit-scrollbar { height:3px; }
.tpill { display:inline-flex; align-items:center; gap:4px; white-space:nowrap; padding:5px 14px; border-radius:20px; border:1px solid var(--border-color); font-size:11.5px; font-weight:600; color:var(--text-muted); text-decoration:none; transition:all .15s; background:#fff; }
.tpill:hover { border-color:#007bff44; color:#007bff; }
.tpill.active { background:#ebf5ff; border-color:#007bff; color:#007bff; }

/* ─── Ticket row ─── */
.tck-row { display:flex; align-items:flex-start; gap:12px; padding:12px 16px; border-bottom:1px solid var(--border-color); text-decoration:none; color:inherit; transition:background .12s; }
.tck-row:last-child { border-bottom:none; }
.tck-row:hover { background:#f8f9fb; }
.tck-row.needs-reply { border-left:3px solid #f39c12; }
.tck-num { font-family:monospace; font-size:10.5px; color:#007bff; background:#ebf5ff; padding:1px 6px; border-radius:4px; white-space:nowrap; }
.tck-title { font-size:13px; font-weight:700; color:#333; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:300px; }
.tck-preview { font-size:11px; color:var(--text-muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:300px; }
.tck-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:700; white-space:nowrap; }
.tb-open     { background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; }
.tb-reply    { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
.tb-answered { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.tb-closed   { background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; }
.tck-dot { width:8px; height:8px; border-radius:50%; background:#f39c12; flex-shrink:0; animation:pulse 1.2s ease-in-out infinite; margin-top:5px; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }
</style>

<!-- Alert banner customer-reply -->
<?php if(($stats['reply_c'] ?? 0) > 0): ?>
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:10px;margin-bottom:14px;font-size:12.5px;color:#c2410c;font-weight:600;">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:16px;"></i>
    <div>Anda memiliki <strong><?= $stats['reply_c'] ?></strong> tiket yang sudah dibalas tim support & menunggu respons Anda.</div>
</div>
<?php endif; ?>

<!-- Stat cards -->
<div class="tstat-bar">
    <div class="tstat">
        <div class="tstat-ico" style="background:#eff6ff;color:#3b82f6;"><i class="bi bi-ticket-perforated"></i></div>
        <div><div class="tstat-val"><?= $stats['total'] ?></div><div class="tstat-lbl">Total</div></div>
    </div>
    <div class="tstat">
        <div class="tstat-ico" style="background:#fef2f2;color:#ef4444;"><i class="bi bi-clock-history"></i></div>
        <div><div class="tstat-val" style="color:<?= (($stats['open_c']+$stats['reply_c'])>0)?'#ef4444':'inherit' ?>;"><?= $stats['open_c']+$stats['reply_c'] ?></div><div class="tstat-lbl">Aktif</div></div>
    </div>
    <div class="tstat">
        <div class="tstat-ico" style="background:#f0fdf4;color:#22c55e;"><i class="bi bi-check-circle"></i></div>
        <div><div class="tstat-val" style="color:#22c55e;"><?= $stats['answered_c'] ?></div><div class="tstat-lbl">Dijawab</div></div>
    </div>
    <div class="tstat">
        <div class="tstat-ico" style="background:#f3f4f6;color:#9ca3af;"><i class="bi bi-archive"></i></div>
        <div><div class="tstat-val" style="color:#9ca3af;"><?= $stats['closed_c'] ?></div><div class="tstat-lbl">Ditutup</div></div>
    </div>
</div>

<!-- Main card -->
<div class="inv-card" style="background:#fff;border:1px solid var(--border-color);border-radius:12px;overflow:hidden;">
    <!-- Header -->
    <div style="padding:12px 16px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <div style="font-weight:700;font-size:14px;color:#333;display:flex;align-items:center;gap:8px;">
            <i class="bi bi-headset" style="color:#007bff;"></i> Tiket Support
        </div>
        <a href="<?= base_url('hosting/tickets/create') ?>" class="btn btn-sm text-white fw-medium" style="background:#20c997;border-radius:6px;font-size:12px;padding:6px 16px;">
            <i class="bi bi-plus-circle me-1"></i> Buat Tiket Baru
        </a>
    </div>

    <!-- Filter pills + search -->
    <div style="padding:10px 16px 0;">
        <div class="t-pills">
            <a href="?status=all"  class="tpill <?= $filter==='all'?'active':'' ?>">Semua <span style="background:#e2e8f0;color:#4a5568;padding:1px 6px;border-radius:10px;font-size:10px;"><?= $stats['total'] ?></span></a>
            <a href="?status=open" class="tpill <?= $filter==='open'?'active':'' ?>">Aktif  <span style="background:#e2e8f0;color:#4a5568;padding:1px 6px;border-radius:10px;font-size:10px;"><?= $stats['open_c']+$stats['reply_c'] ?></span></a>
            <a href="?status=done" class="tpill <?= $filter==='done'?'active':'' ?>">Selesai</a>
        </div>
    </div>

    <!-- Ticket rows -->
    <?php if(mysqli_num_rows($tickets_query) > 0): ?>
    <?php while($row = mysqli_fetch_assoc($tickets_query)):
        $is_reply = ($row['status'] === 'Customer-Reply');
        $by_admin = !empty($row['last_admin_id']);
        $preview  = strip_tags($row['last_reply_text'] ?? '');
        $preview  = mb_substr($preview, 0, 70) . (mb_strlen($preview) > 70 ? '…' : '');
        $ago_sec  = $row['last_reply_time'] ? abs(time() - strtotime($row['last_reply_time'])) : 0;
        $ago_str  = $ago_sec ? ($ago_sec < 3600 ? round($ago_sec/60).'m lalu' : ($ago_sec < 86400 ? round($ago_sec/3600).'j lalu' : date('d M', strtotime($row['last_reply_time'])))) : date('d M', strtotime($row['created_at']));
        $badge = match($row['status']) {
            'Customer-Reply' => '<span class="tck-badge tb-reply"><i class="bi bi-arrow-up-circle-fill"></i> Balas Kamu</span>',
            'Open'           => '<span class="tck-badge tb-open"><i class="bi bi-circle-fill" style="font-size:6px;"></i> Open</span>',
            'Answered'       => '<span class="tck-badge tb-answered"><i class="bi bi-check-circle-fill"></i> Dijawab</span>',
            default          => '<span class="tck-badge tb-closed">Ditutup</span>',
        };
    ?>
    <a href="<?= base_url('hosting/tickets/detail/'.$row['id']) ?>" class="tck-row <?= $is_reply ? 'needs-reply' : '' ?>">
        <?php if($is_reply): ?><div class="tck-dot"></div><?php endif; ?>
        <div style="flex:1;min-width:0;">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <span class="tck-num"><?= $row['ticket_number'] ?></span>
                <span style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($row['department']) ?></span>
            </div>
            <div class="tck-title"><?= htmlspecialchars($row['title']) ?></div>
            <?php if($preview): ?>
            <div class="tck-preview">
                <?= $by_admin ? '<i class="bi bi-headset me-1" style="color:#007bff;"></i>' : '<i class="bi bi-person-fill me-1"></i>' ?>
                <?= htmlspecialchars($preview) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
            <?= $badge ?>
            <span style="font-size:10px;color:var(--text-muted);"><?= $ago_str ?></span>
        </div>
        <i class="bi bi-chevron-right" style="color:var(--text-muted);font-size:11px;align-self:center;flex-shrink:0;"></i>
    </a>
    <?php endwhile; ?>
    <?php else: ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text-muted);">
        <i class="bi bi-ticket-perforated d-block mb-2" style="font-size:36px;opacity:.3;"></i>
        Belum ada tiket <?= $filter !== 'all' ? 'dengan filter ini' : 'support' ?>.
        <div style="margin-top:10px;">
            <a href="<?= base_url('hosting/tickets/create') ?>" class="btn btn-sm btn-primary" style="border-radius:6px;">Buat Tiket Pertama</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Contact Info -->
<div class="row g-3 mt-1">
    <?php if(!empty($settings['contact_phone'])): ?>
    <div class="col-md-6">
        <div class="card border-0 p-3 shadow-sm" style="border-radius:10px;">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-telephone-fill text-primary me-2"></i>
                <h6 class="fw-bold m-0" style="font-size:13px;">Telepon</h6>
            </div>
            <div class="text-muted" style="font-size:12.5px;"><?= htmlspecialchars($settings['contact_phone']) ?></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if(!empty($settings['contact_email']) || !empty($settings['contact_billing'])): ?>
    <div class="col-md-6">
        <div class="card border-0 p-3 shadow-sm" style="border-radius:10px;">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-envelope-fill text-primary me-2"></i>
                <h6 class="fw-bold m-0" style="font-size:13px;">Email</h6>
            </div>
            <?php if($settings['contact_billing']): ?>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;"><strong style="color:#333;width:80px;display:inline-block;">Billing:</strong> <?= htmlspecialchars($settings['contact_billing']) ?></div>
            <?php endif; ?>
            <?php if($settings['contact_info']): ?>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;"><strong style="color:#333;width:80px;display:inline-block;">Info:</strong> <?= htmlspecialchars($settings['contact_info']) ?></div>
            <?php endif; ?>
            <?php if($settings['contact_support']): ?>
            <div style="font-size:12px;color:var(--text-muted);"><strong style="color:#333;width:80px;display:inline-block;">Teknis:</strong> <?= htmlspecialchars($settings['contact_support']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../library/footer.php'; ?>
