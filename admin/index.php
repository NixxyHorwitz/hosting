<?php
require_once __DIR__ . '/library/admin_session.php';
if(!defined('NS1')) require_once __DIR__ . '/../config/database.php';

// ── Aggregate Stats ───────────────────────────────────────────
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    (SELECT COUNT(*) FROM users WHERE role='client') total_clients,
    (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) new_users,
    (SELECT COUNT(*) FROM orders WHERE status='active') active_hosting,
    (SELECT COUNT(*) FROM orders WHERE status='suspended') suspended_hosting,
    (SELECT COUNT(*) FROM orders WHERE status='pending') pending_orders,
    (SELECT COUNT(*) FROM orders WHERE status='processing') processing_orders,
    (SELECT COUNT(*) FROM invoices WHERE status='unpaid') unpaid_inv,
    (SELECT COUNT(*) FROM invoices WHERE status='unpaid' AND date_due < NOW()) overdue_inv,
    (SELECT IFNULL(SUM(total_harga),0) FROM orders WHERE status_pembayaran='success') total_revenue,
    (SELECT IFNULL(SUM(total_harga),0) FROM orders WHERE status_pembayaran='success' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) monthly_revenue,
    (SELECT COUNT(*) FROM tickets WHERE status='Open') tickets_open,
    (SELECT COUNT(*) FROM tickets WHERE status='Customer-Reply') tickets_reply,
    (SELECT COUNT(*) FROM whm_servers) whm_nodes,
    (SELECT SUM(limit_cpanel) FROM whm_servers) whm_capacity,
    (SELECT COUNT(*) FROM orders WHERE status IN ('active','suspended')) whm_used,
    (SELECT COUNT(*) FROM site_traffic WHERE DATE(created_at)=CURDATE() AND device_type!='bot') traffic_today,
    (SELECT COUNT(DISTINCT ip) FROM site_traffic WHERE DATE(created_at)=CURDATE()) unique_today
"));

// ── Recent Orders (last 8) ─────────────────────────────────────
$recent_orders = mysqli_fetch_all(mysqli_query($conn,
    "SELECT o.*, u.nama as nama_user, h.nama_paket
     FROM orders o LEFT JOIN users u ON o.user_id=u.id LEFT JOIN hosting_plans h ON o.hosting_plan_id=h.id
     ORDER BY o.created_at DESC LIMIT 8"), MYSQLI_ASSOC);

// ── Recent Invoices (last 6) ───────────────────────────────────
$recent_invoices = mysqli_fetch_all(mysqli_query($conn,
    "SELECT i.*, u.nama as nama_user FROM invoices i LEFT JOIN users u ON i.user_id=u.id
     ORDER BY i.date_created DESC LIMIT 6"), MYSQLI_ASSOC);

// ── Urgent Tickets (open/reply, last 5) ───────────────────────
$urgent_tickets = mysqli_fetch_all(mysqli_query($conn,
    "SELECT t.*, u.nama as nama_user FROM tickets t LEFT JOIN users u ON t.user_id=u.id
     WHERE t.status IN ('Open','Customer-Reply')
     ORDER BY CASE t.status WHEN 'Customer-Reply' THEN 0 ELSE 1 END, t.created_at DESC LIMIT 5"), MYSQLI_ASSOC);

// ── WHM Nodes ─────────────────────────────────────────────────
$whm_nodes = mysqli_fetch_all(mysqli_query($conn,
    "SELECT w.*,
     (SELECT COUNT(*) FROM orders WHERE whm_id=w.id AND status='active') active_acc,
     (SELECT COUNT(*) FROM orders WHERE whm_id=w.id AND status='suspended') susp_acc
     FROM whm_servers w ORDER BY w.id ASC"), MYSQLI_ASSOC);

// ── Revenue last 7 days spark ──────────────────────────────────
$spark_data = mysqli_fetch_all(mysqli_query($conn,
    "SELECT DATE(created_at) d, COUNT(*) orders, IFNULL(SUM(CASE WHEN status_pembayaran='success' THEN total_harga ELSE 0 END),0) rev
     FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at) ORDER BY d"), MYSQLI_ASSOC);
$spark_days = [];
for($i=6;$i>=0;$i--) { $d=date('Y-m-d',strtotime("-$i days")); $spark_days[$d]=['orders'=>0,'rev'=>0]; }
foreach($spark_data as $s) $spark_days[$s['d']] = ['orders'=>(int)$s['orders'],'rev'=>(int)$s['rev']];

$spark_rev_max = max(1, max(array_column($spark_days,'rev')));

// ── New users today ────────────────────────────────────────────
$new_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE DATE(created_at)=CURDATE()"))['c'] ?? 0;

// ── Expiring soon (next 7 days) ────────────────────────────────
$expiring_soon = mysqli_fetch_all(mysqli_query($conn,
    "SELECT o.*, u.nama as nama_user FROM orders o LEFT JOIN users u ON o.user_id=u.id
     WHERE o.status='active' AND o.expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
     ORDER BY o.expiry_date ASC LIMIT 5"), MYSQLI_ASSOC);

$page_title = "Dashboard";
include __DIR__ . '/library/header.php';
?>

<style>
/* ─── Compact stat cards ─── */
.sm-card { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px 16px; display:flex; align-items:center; gap:12px; }
.sm-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:19px; flex-shrink:0; }
.sm-val { font-size:21px; font-weight:800; line-height:1.1; }
.sm-lbl { font-size:10px; color:var(--mut); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.sm-sub { font-size:10px; color:var(--mut); margin-top:3px; }

/* ─── Quick badges ─── */
.qb { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; border:1px solid; white-space:nowrap; }
.qb-ok   { color:var(--ok);   background:var(--oks); border-color:var(--ob); }
.qb-err  { color:var(--err);  background:var(--es);  border-color:#3d1a1a; }
.qb-warn { color:var(--warn); background:var(--ws);  border-color:#3d2e0a; }
.qb-muted{ color:var(--sub);  background:var(--surface); border-color:var(--border); }
.qb-acc  { color:var(--accent); background:var(--as); border-color:var(--ba); }
.qb-pur  { color:#a78bfa; background:rgba(167,139,250,.15); border-color:rgba(167,139,250,.3); }

/* ─── Data rows ─── */
.drow { display:flex; align-items:center; gap:10px; padding:9px 16px; border-bottom:1px solid var(--border); font-size:12px; transition:background .12s; }
.drow:last-child { border-bottom:none; }
.drow:hover { background:var(--hover); }
.drow-main { flex:1; min-width:0; }
.drow-sub { font-size:10px; color:var(--mut); margin-top:1px; }
.chip-domain { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--accent); background:var(--as); border:1px solid var(--ba); border-radius:4px; padding:1px 6px; display:inline-block; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* ─── Spark bar ─── */
.spark-wrap { display:flex; align-items:flex-end; gap:2px; height:32px; }
.spark-b { flex:1; border-radius:2px 2px 0 0; background:var(--accent); opacity:.55; min-width:6px; }
.spark-b:hover { opacity:1; }

/* ─── WHM bar ─── */
.whm-bar-bg { height:5px; border-radius:99px; background:var(--surface); border:1px solid var(--border); overflow:hidden; margin-top:5px; }
.whm-bar-fill { height:100%; border-radius:99px; }

/* ─── Alert strip ─── */
.alert-strip { display:flex; align-items:center; gap:10px; padding:9px 14px; border-radius:8px; font-size:12px; font-weight:600; margin-bottom:12px; }
.alert-strip i { font-size:16px; flex-shrink:0; }

/* section title */
.sec-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--sub); margin-bottom:10px; display:flex; align-items:center; gap:6px; }
</style>

<!-- ── Header ── -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-squares-four me-2" style="color:var(--accent);"></i> Dashboard</h1>
        <div style="font-size:12px;color:var(--mut);">Selamat datang, <strong style="color:var(--text);"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong> — <?= date('l, d F Y') ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if($stats['tickets_reply'] > 0): ?>
        <a href="<?= base_url('admin/tickets') ?>" class="qb qb-warn" style="padding:6px 12px;font-size:11px;">
            <i class="ph-fill ph-warning"></i> <?= $stats['tickets_reply'] ?> Balas Tiket
        </a>
        <?php endif; ?>
        <?php if($stats['overdue_inv'] > 0): ?>
        <a href="<?= base_url('admin/invoices') ?>" class="qb qb-err" style="padding:6px 12px;font-size:11px;">
            <i class="ph-fill ph-receipt"></i> <?= $stats['overdue_inv'] ?> Invoice Overdue
        </a>
        <?php endif; ?>
        <?php if($stats['pending_orders'] > 0): ?>
        <a href="<?= base_url('admin/orders') ?>" class="qb qb-warn" style="padding:6px 12px;font-size:11px;">
            <i class="ph-fill ph-clock"></i> <?= $stats['pending_orders'] ?> Pending
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Row 1: Main Stats ── -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="sm-card">
            <div class="sm-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-money"></i></div>
            <div>
                <div class="sm-val" style="color:var(--ok);font-size:15px;">Rp <?= number_format($stats['total_revenue'],0,',','.') ?></div>
                <div class="sm-lbl">Total Revenue</div>
                <div class="sm-sub">+Rp <?= number_format($stats['monthly_revenue'],0,',','.') ?> /30 hari</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="sm-card">
            <div class="sm-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-users"></i></div>
            <div>
                <div class="sm-val"><?= $stats['total_clients'] ?></div>
                <div class="sm-lbl">Pelanggan</div>
                <div class="sm-sub">+<?= $stats['new_users'] ?> minggu ini</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="sm-card">
            <div class="sm-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-hard-drives"></i></div>
            <div>
                <div class="sm-val" style="color:var(--ok);"><?= $stats['active_hosting'] ?></div>
                <div class="sm-lbl">Hosting Aktif</div>
                <div class="sm-sub"><?= $stats['suspended_hosting'] ?> suspended</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="sm-card">
            <div class="sm-icon" style="background:var(--ws);color:var(--warn);"><i class="ph-fill ph-receipt"></i></div>
            <div>
                <div class="sm-val" style="color:var(--warn);"><?= $stats['unpaid_inv'] ?></div>
                <div class="sm-lbl">Invoice Unpaid</div>
                <div class="sm-sub" style="color:<?= $stats['overdue_inv']>0?'var(--err)':'var(--mut)' ?>;"><?= $stats['overdue_inv'] ?> overdue</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="sm-card">
            <div class="sm-icon" style="background:rgba(167,139,250,.15);color:#a78bfa;"><i class="ph-fill ph-headset"></i></div>
            <div>
                <div class="sm-val" style="color:#a78bfa;"><?= $stats['tickets_open'] + $stats['tickets_reply'] ?></div>
                <div class="sm-lbl">Tiket Aktif</div>
                <div class="sm-sub"><?= $stats['tickets_reply'] ?> butuh balas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="sm-card">
            <div class="sm-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-eye"></i></div>
            <div>
                <div class="sm-val"><?= number_format($stats['traffic_today']) ?></div>
                <div class="sm-lbl">Traffic Hari Ini</div>
                <div class="sm-sub"><?= $stats['unique_today'] ?> unique IP</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 2: Charts + WHM ── -->
<div class="row g-3 mb-3">
    <!-- Sparkline chart -->
    <div class="col-lg-5">
        <div class="card-c h-100">
            <div class="ch py-2 d-flex align-items-center justify-content-between">
                <div class="sec-title m-0"><i class="ph ph-chart-bar"></i> Order 7 Hari</div>
                <span style="font-size:11px;color:var(--mut);">Revenue per hari</span>
            </div>
            <div class="cb pt-2 pb-2">
                <div class="spark-wrap mb-1">
                    <?php foreach($spark_days as $d => $v):
                        $h = max(4, round(($v['rev']/$spark_rev_max)*32));
                        $col = $v['orders'] > 0 ? 'var(--accent)' : 'var(--border)';
                    ?>
                    <div class="spark-b" style="height:<?= $h ?>px;background:<?= $col ?>;" title="<?= $d ?> · <?= $v['orders'] ?> order · Rp <?= number_format($v['rev'],0,',','.') ?>"></div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex justify-content-between" style="font-size:9px;color:var(--mut);">
                    <?php foreach(array_keys($spark_days) as $d): ?><span><?= date('d/m',$d===date('Y-m-d')?time():strtotime($d)) ?></span><?php endforeach; ?>
                </div>
                <!-- Mini order list below chart -->
                <div class="mt-3" style="border-top:1px solid var(--border);padding-top:10px;">
                    <?php foreach(array_reverse($spark_days) as $d => $v): if($v['orders']==0) continue; ?>
                    <div class="d-flex justify-content-between" style="font-size:11px;padding:3px 0;border-bottom:1px solid var(--border);">
                        <span style="color:var(--sub);"><?= date('d M',$d===date('Y-m-d')?time():strtotime($d)) ?></span>
                        <span><strong style="color:var(--text);"><?= $v['orders'] ?></strong> order</span>
                        <span style="color:var(--ok);">Rp <?= number_format($v['rev'],0,',','.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- WHM Nodes -->
    <div class="col-lg-7">
        <div class="card-c h-100">
            <div class="ch py-2 d-flex align-items-center justify-content-between">
                <div class="sec-title m-0"><i class="ph-fill ph-hard-drives" style="color:var(--accent);"></i> WHM Server Nodes</div>
                <div class="d-flex gap-2">
                    <span class="qb qb-muted"><?= $stats['whm_nodes'] ?> node</span>
                    <span class="qb qb-ok"><?= $stats['whm_used'] ?>/<?= $stats['whm_capacity'] ?> akun</span>
                </div>
            </div>
            <div class="cb p-0">
                <?php if(empty($whm_nodes)): ?>
                <div class="text-center py-4" style="color:var(--mut);font-size:12px;">
                    <i class="ph ph-hard-drives d-block mb-2" style="font-size:28px;"></i> Belum ada WHM Server
                </div>
                <?php else: ?>
                <?php foreach($whm_nodes as $w):
                    $used = $w['active_acc'] + $w['susp_acc'];
                    $lim  = (int)$w['limit_cpanel'];
                    $pct  = $lim > 0 ? round($used/$lim*100) : 0;
                    $col  = $pct >= 90 ? 'var(--err)' : ($pct >= 70 ? 'var(--warn)' : 'var(--ok)');
                ?>
                <div class="drow">
                    <div style="width:30px;height:30px;border-radius:8px;background:var(--as);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--accent);font-size:16px;">
                        <i class="ph-fill ph-hard-drives"></i>
                    </div>
                    <div class="drow-main">
                        <div style="font-weight:700;font-size:12px;font-family:monospace;"><?= htmlspecialchars($w['whm_host']) ?></div>
                        <div class="drow-sub">Node #<?= $w['id'] ?> · <?= htmlspecialchars($w['whm_username']) ?></div>
                        <div class="whm-bar-bg">
                            <div class="whm-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                        </div>
                    </div>
                    <div class="text-end" style="flex-shrink:0;">
                        <div style="font-size:12px;font-weight:700;color:<?= $col ?>;"><?= $pct ?>%</div>
                        <div class="drow-sub"><?= $used ?>/<?= $lim ?></div>
                        <?php if($w['susp_acc']>0): ?>
                        <span class="qb qb-warn" style="font-size:9px;"><i class="ph-fill ph-pause"></i> <?= $w['susp_acc'] ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= base_url('admin/whm_overview/'.$w['id']) ?>" class="ab py-1 px-2 ms-2"><i class="ph ph-eye"></i></a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 3: Orders + Invoices + Tickets ── -->
<div class="row g-3 mb-3">
    <!-- Recent Orders -->
    <div class="col-lg-5">
        <div class="card-c h-100">
            <div class="ch py-2 d-flex align-items-center justify-content-between">
                <div class="sec-title m-0"><i class="ph-fill ph-shopping-cart" style="color:var(--accent);"></i> Order Terbaru</div>
                <a href="<?= base_url('admin/orders') ?>" style="font-size:11px;color:var(--accent);text-decoration:none;">Lihat semua →</a>
            </div>
            <div class="cb p-0">
                <?php foreach($recent_orders as $o):
                    $s = $o['status'];
                    $badge = match($s) {
                        'active'     => '<span class="qb qb-ok"><i class="ph-fill ph-check"></i> active</span>',
                        'suspended'  => '<span class="qb qb-err">suspend</span>',
                        'processing' => '<span class="qb qb-acc">process</span>',
                        default      => '<span class="qb qb-warn">pending</span>',
                    };
                    $pay = $o['status_pembayaran'] === 'success'
                        ? '<span class="qb qb-ok" style="font-size:9px;">lunas</span>'
                        : '<span class="qb qb-warn" style="font-size:9px;">'.$o['status_pembayaran'].'</span>';
                ?>
                <div class="drow">
                    <div class="drow-main">
                        <?php if($o['domain']): ?><span class="chip-domain"><?= htmlspecialchars($o['domain']) ?></span><?php endif; ?>
                        <div class="drow-sub mt-1">
                            <span class="view-user-detail" data-userid="<?= $o['user_id'] ?>" style="cursor:pointer;color:var(--sub);"><?= htmlspecialchars($o['nama_user'] ?? '—') ?></span>
                            · <?= htmlspecialchars($o['nama_paket'] ?? '—') ?> · <?= $o['durasi'] ?>bl
                        </div>
                    </div>
                    <div class="text-end d-flex flex-column align-items-end gap-1">
                        <?= $badge ?>
                        <?= $pay ?>
                        <div style="font-size:9px;color:var(--mut);"><?= date('d M', strtotime($o['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($recent_orders)): ?>
                <div class="text-center py-4" style="color:var(--mut);font-size:12px;">Belum ada pesanan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Invoices -->
    <div class="col-lg-3">
        <div class="card-c h-100">
            <div class="ch py-2 d-flex align-items-center justify-content-between">
                <div class="sec-title m-0"><i class="ph-fill ph-receipt" style="color:var(--warn);"></i> Invoice</div>
                <a href="<?= base_url('admin/invoices') ?>" style="font-size:11px;color:var(--accent);text-decoration:none;">Lihat →</a>
            </div>
            <div class="cb p-0">
                <?php foreach($recent_invoices as $inv):
                    $is_overdue = ($inv['status']==='unpaid' && $inv['date_due'] && time()>strtotime($inv['date_due']));
                    $ibadge = match($inv['status']) {
                        'paid'      => '<span class="qb qb-ok">PAID</span>',
                        'cancelled' => '<span class="qb qb-err">BATAL</span>',
                        default     => '<span class="qb '.($is_overdue?'qb-err':'qb-warn').'">UNPAID</span>',
                    };
                ?>
                <div class="drow">
                    <div class="drow-main">
                        <div style="font-family:monospace;font-size:11px;color:var(--accent);">INV-<?= str_pad($inv['id'],5,'0',STR_PAD_LEFT) ?></div>
                        <div class="drow-sub"><?= htmlspecialchars($inv['nama_user'] ?? '—') ?></div>
                        <div style="font-size:11px;font-weight:700;color:var(--ok);margin-top:2px;">Rp <?= number_format($inv['total_tagihan'],0,',','.') ?></div>
                    </div>
                    <div class="text-end">
                        <?= $ibadge ?>
                        <div style="font-size:9px;color:var(--mut);margin-top:3px;"><?= date('d M',strtotime($inv['date_created'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($recent_invoices)): ?>
                <div class="text-center py-4" style="color:var(--mut);font-size:12px;">Belum ada invoice.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Urgent Tickets -->
    <div class="col-lg-4">
        <div class="card-c h-100">
            <div class="ch py-2 d-flex align-items-center justify-content-between">
                <div class="sec-title m-0"><i class="ph-fill ph-headset" style="color:#a78bfa;"></i> Tiket Butuh Balas</div>
                <a href="<?= base_url('admin/tickets') ?>" style="font-size:11px;color:var(--accent);text-decoration:none;">Lihat →</a>
            </div>
            <div class="cb p-0">
                <?php foreach($urgent_tickets as $t):
                    $is_reply = ($t['status']==='Customer-Reply');
                ?>
                <a href="<?= base_url('admin/tickets/detail/'.$t['id']) ?>" class="drow" style="text-decoration:none;<?= $is_reply?'border-left:2px solid var(--warn);':'' ?>">
                    <div class="drow-main">
                        <div style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;"><?= htmlspecialchars($t['title']) ?></div>
                        <div class="drow-sub"><?= htmlspecialchars($t['nama_user']??'—') ?> · <?= $t['department'] ?></div>
                        <div class="drow-sub" style="font-family:monospace;"><?= $t['ticket_number'] ?></div>
                    </div>
                    <div>
                        <?php if($is_reply): ?>
                        <span class="qb qb-warn"><i class="ph-fill ph-arrow-fat-up" style="font-size:8px;"></i> REPLY</span>
                        <?php else: ?>
                        <span class="qb qb-pur">OPEN</span>
                        <?php endif; ?>
                        <div style="font-size:9px;color:var(--mut);margin-top:3px;text-align:right;"><?= date('d M',strtotime($t['created_at'])) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if(empty($urgent_tickets)): ?>
                <div class="text-center py-4">
                    <i class="ph-fill ph-check-circle" style="font-size:28px;color:var(--ok);opacity:.6;"></i>
                    <div style="font-size:12px;color:var(--mut);margin-top:6px;">Semua tiket tertangani!</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 4: Expiring Soon ── -->
<?php if(!empty($expiring_soon)): ?>
<div class="card-c mb-3">
    <div class="ch py-2 d-flex align-items-center justify-content-between">
        <div class="sec-title m-0"><i class="ph-fill ph-warning" style="color:var(--warn);"></i> Hosting Hampir Expired (7 Hari)</div>
        <span class="qb qb-warn"><?= count($expiring_soon) ?> akun</span>
    </div>
    <div class="cb p-0">
        <div class="d-flex flex-wrap gap-0">
        <?php foreach($expiring_soon as $e):
            $days_left = max(0, ceil((strtotime($e['expiry_date']) - time()) / 86400));
            $col = $days_left <= 1 ? 'var(--err)' : ($days_left <= 3 ? 'var(--warn)' : 'var(--ok)');
        ?>
        <div class="drow" style="width:100%;">
            <div style="width:30px;height:30px;border-radius:8px;background:var(--ws);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ph-fill ph-clock" style="color:var(--warn);font-size:15px;"></i>
            </div>
            <div class="drow-main">
                <span class="chip-domain"><?= htmlspecialchars($e['domain']) ?></span>
                <div class="drow-sub"><?= htmlspecialchars($e['nama_user']??'—') ?> · Exp: <?= date('d M Y', strtotime($e['expiry_date'])) ?></div>
            </div>
            <div style="font-weight:800;font-size:13px;color:<?= $col ?>;"><?= $days_left ?>h lagi</div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/library/footer.php'; ?>