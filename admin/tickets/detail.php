<?php
require_once __DIR__ . '/../library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../../config/database.php';

$admin_id  = $_SESSION['admin_id'];
$ticket_id = isset($params[0]) ? (int)$params[0] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

$ticket_query = mysqli_query($conn, "SELECT t.*, u.nama as user_name, u.email as user_email, u.no_whatsapp
                                     FROM tickets t JOIN users u ON t.user_id = u.id
                                     WHERE t.id = '$ticket_id'");
$ticket = mysqli_fetch_assoc($ticket_query);
if (!$ticket) { header("Location: " . base_url('admin/tickets')); exit(); }

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply_ticket'])) {
        $message    = mysqli_real_escape_string($conn, $_POST['message']);
        $new_status = mysqli_real_escape_string($conn, $_POST['status_update']);
        mysqli_query($conn, "INSERT INTO ticket_replies (ticket_id, admin_id, message) VALUES ('$ticket_id', '$admin_id', '$message')");
        $reply_id = mysqli_insert_id($conn);
        mysqli_query($conn, "UPDATE tickets SET status = '$new_status' WHERE id = '$ticket_id'");

        if (!empty($_FILES['attachments']['name'][0])) {
            $base_dir  = dirname(__DIR__, 2);
            $upload_dir = $base_dir . '/uploads/tickets/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $allowed_exts = ['jpg','jpeg','png','pdf','zip','rar','txt'];
            foreach ($_FILES['attachments']['name'] as $k => $name) {
                if ($_FILES['attachments']['error'][$k] === UPLOAD_ERR_OK) {
                    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $size = $_FILES['attachments']['size'][$k];
                    if (in_array($ext, $allowed_exts) && $size <= 5242880) {
                        $new_name = uniqid('t_').'_'.time().'.'.$ext;
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$k], $upload_dir.$new_name)) {
                            $sn  = mysqli_real_escape_string($conn, $name);
                            $sp  = mysqli_real_escape_string($conn, 'uploads/tickets/'.$new_name);
                            mysqli_query($conn, "INSERT INTO ticket_attachments (ticket_id,reply_id,file_name,file_path,file_size) VALUES ('$ticket_id','$reply_id','$sn','$sp','$size')");
                        }
                    }
                }
            }
        }
        header("Location: " . base_url("admin/tickets/detail/$ticket_id")); exit();
    }
    if (isset($_POST['close_ticket'])) {
        mysqli_query($conn, "UPDATE tickets SET status='Closed' WHERE id='$ticket_id'");
        header("Location: " . base_url("admin/tickets/detail/$ticket_id")); exit();
    }
    if (isset($_POST['reopen_ticket'])) {
        mysqli_query($conn, "UPDATE tickets SET status='Open' WHERE id='$ticket_id'");
        header("Location: " . base_url("admin/tickets/detail/$ticket_id")); exit();
    }
}

$replies_query = mysqli_query($conn, "SELECT tr.*, u.nama as user_name, a.username as admin_name
                                    FROM ticket_replies tr
                                    LEFT JOIN users u ON tr.user_id = u.id
                                    LEFT JOIN admins a ON tr.admin_id = a.id
                                    WHERE tr.ticket_id = '$ticket_id' ORDER BY tr.created_at ASC");

$reply_count = mysqli_num_rows($replies_query);

$page_title = "Tiket " . $ticket['ticket_number'];
include __DIR__ . '/../library/header.php';
?>

<style>
.quick-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; border:1px solid; white-space:nowrap; }
.qb-ok   { color:var(--ok);   background:var(--oks); border-color:var(--ob); }
.qb-err  { color:var(--err);  background:var(--es);  border-color:#3d1a1a; }
.qb-warn { color:var(--warn); background:var(--ws);  border-color:#3d2e0a; }
.qb-muted{ color:var(--sub);  background:var(--surface); border-color:var(--border); }
.qb-pur  { color:#a78bfa; background:rgba(167,139,250,.15); border-color:rgba(167,139,250,.3); }

/* Timeline thread */
.msg-thread { display:flex; flex-direction:column; gap:0; }
.msg-wrap { display:flex; gap:12px; padding:16px 20px; border-bottom:1px solid var(--border); }
.msg-wrap:last-child { border-bottom:none; }
.msg-wrap.admin-msg { background:rgba(71,122,238,.04); }
.msg-avatar { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0; margin-top:2px; }
.msg-body { flex:1; min-width:0; }
.msg-header { display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap; }
.msg-name { font-weight:700; font-size:13px; }
.msg-time { font-size:11px; color:var(--mut); }
.msg-content { font-size:13.5px; line-height:1.7; color:var(--text); }
.msg-content p { margin-bottom:.4rem; }
.msg-content p:last-child { margin:0; }

/* Sidebar info rows */
.info-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--border); font-size:12px; }
.info-row:last-child { border-bottom:none; }
.info-lbl { color:var(--mut); font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.5px; }

/* Reply editor */
.editor-toolbar { display:flex; gap:3px; padding:8px 10px; border-bottom:1px solid var(--border); background:rgba(255,255,255,.04); border-radius:8px 8px 0 0; flex-wrap:wrap; align-items:center; }
.tb-btn { width:30px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:5px; border:1px solid transparent; background:transparent; color:rgba(255,255,255,.7); cursor:pointer; font-size:14px; font-weight:700; transition:all .15s; line-height:1; }
.tb-btn:hover { background:rgba(255,255,255,.1); color:#fff; border-color:var(--border); }
.tb-sep { width:1px; height:18px; background:var(--border); margin:0 3px; }
#editorBody { min-height:140px; max-height:300px; overflow-y:auto; padding:12px 14px; background:transparent; outline:none; font-size:13.5px; line-height:1.7; color:var(--text); border-radius:0 0 8px 8px; }
#editorBody:empty::before { content:attr(data-placeholder); color:var(--mut); pointer-events:none; display:block; }

.att-chip { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px 10px; font-size:11.5px; }
.att-chip-icon { width:28px; height:28px; border-radius:6px; background:var(--as); display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:14px; flex-shrink:0; }
</style>

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <div class="d-flex align-items-center gap-3 mb-1">
            <a href="<?= base_url('admin/tickets') ?>" style="color:var(--mut);font-size:12px;text-decoration:none;">
                <i class="ph ph-arrow-left me-1"></i> Semua Tiket
            </a>
            <span style="color:var(--border);">·</span>
            <span style="font-family:monospace;font-size:12px;color:var(--accent);"><?= $ticket['ticket_number'] ?></span>
        </div>
        <h1 style="font-size:18px;font-weight:800;"><?= htmlspecialchars($ticket['title']) ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc m-0">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('admin/tickets') ?>">Tickets</a></li>
            <li class="breadcrumb-item active"><?= $ticket['ticket_number'] ?></li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php
        $s = $ticket['status'];
        if($s==='Open')            echo '<span class="quick-badge qb-pur" style="padding:5px 12px;font-size:11px;"><i class="ph-fill ph-circle" style="font-size:7px;"></i> OPEN</span>';
        elseif($s==='Customer-Reply') echo '<span class="quick-badge qb-warn" style="padding:5px 12px;font-size:11px;"><i class="ph-fill ph-arrow-fat-up"></i> CUSTOMER REPLY</span>';
        elseif($s==='Answered')    echo '<span class="quick-badge qb-ok" style="padding:5px 12px;font-size:11px;"><i class="ph-fill ph-check-circle"></i> ANSWERED</span>';
        else                       echo '<span class="quick-badge qb-muted" style="padding:5px 12px;font-size:11px;"><i class="ph ph-archive"></i> CLOSED</span>';
        ?>
    </div>
</div>

<div class="row g-3">
    <!-- Left: Thread -->
    <div class="col-lg-8">
        <div class="card-c mb-3">
            <div class="ch d-flex align-items-center justify-content-between">
                <h3 class="ct">
                    <i class="ph-fill ph-chat-circle me-2 text-primary"></i> Thread Percakapan
                </h3>
                <span style="font-size:11px;color:var(--mut);"><?= $reply_count ?> balasan</span>
            </div>
            <div class="msg-thread">
            <?php while($reply = mysqli_fetch_assoc($replies_query)):
                $is_admin = !empty($reply['admin_id']);
                $sender_name = $is_admin ? htmlspecialchars($reply['admin_name'] ?? 'Support') : htmlspecialchars($reply['user_name'] ?? 'User');
                $initial = strtoupper(substr($is_admin ? ($reply['admin_name'] ?? 'S') : ($reply['user_name'] ?? 'U'), 0, 1));
                $time_str = date('d M Y, H:i', strtotime($reply['created_at']));
            ?>
            <div class="msg-wrap <?= $is_admin ? 'admin-msg' : '' ?>">
                <div class="msg-avatar" style="background:<?= $is_admin ? 'var(--as)' : 'var(--surface)' ?>;color:<?= $is_admin ? 'var(--accent)' : 'var(--sub)' ?>;">
                    <?= $is_admin ? '<i class="ph-fill ph-headset"></i>' : $initial ?>
                </div>
                <div class="msg-body">
                    <div class="msg-header">
                        <span class="msg-name" style="color:<?= $is_admin ? 'var(--accent)' : 'var(--text)' ?>;"><?= $sender_name ?></span>
                        <?php if($is_admin): ?>
                        <span class="quick-badge qb-pur" style="font-size:9px;"><i class="ph-fill ph-headset"></i> SUPPORT</span>
                        <?php endif; ?>
                        <span class="msg-time"><i class="ph ph-clock me-1"></i><?= $time_str ?></span>
                    </div>
                    <div class="msg-content ticket-content"><?= $reply['message'] ?></div>

                    <?php
                    $reply_id  = $reply['id'];
                    $atts_q = mysqli_query($conn, "SELECT * FROM ticket_attachments WHERE reply_id = '$reply_id'");
                    if(mysqli_num_rows($atts_q) > 0):
                    ?>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php while($att = mysqli_fetch_assoc($atts_q)):
                            $ext  = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                            $icon = in_array($ext,['jpg','jpeg','png','gif']) ? 'ph-image' : ($ext==='pdf' ? 'ph-file-pdf' : (in_array($ext,['zip','rar']) ? 'ph-file-archive' : 'ph-file-text'));
                            $sz   = number_format($att['file_size']/1024, 0).' KB';
                        ?>
                        <div class="att-chip">
                            <div class="att-chip-icon"><i class="ph-fill <?= $icon ?>"></i></div>
                            <div>
                                <div style="font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($att['file_name']) ?>"><?= htmlspecialchars($att['file_name']) ?></div>
                                <div style="font-size:10px;color:var(--mut);"><?= strtoupper($ext) ?> · <?= $sz ?></div>
                            </div>
                            <div class="d-flex gap-1 ms-2">
                                <?php if(in_array($ext,['jpg','jpeg','png','gif','pdf'])): ?>
                                <button type="button" onclick="previewAtt('<?= base_url($att['file_path']) ?>','<?= $ext ?>','<?= htmlspecialchars(addslashes($att['file_name'])) ?>')" class="ab py-1 px-2" title="Preview"><i class="ph ph-eye"></i></button>
                                <?php endif; ?>
                                <a href="<?= base_url($att['file_path']) ?>" download="<?= htmlspecialchars($att['file_name']) ?>" class="ab py-1 px-2" title="Download"><i class="ph ph-download-simple"></i></a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
            </div>

            <!-- Reply Form -->
            <?php if($ticket['status'] !== 'Closed'): ?>
            <div class="cb" style="border-top:2px solid var(--border);">
                <div style="font-size:12px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">
                    <i class="ph-fill ph-paper-plane-tilt me-2" style="color:var(--accent);"></i> Tulis Balasan
                </div>
                <form action="" method="POST" id="replyForm" enctype="multipart/form-data">
                    <input type="hidden" name="message" id="msgInput">

                    <!-- Custom editor toolbar -->
                    <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;">
                        <div class="editor-toolbar">
                            <button type="button" class="tb-btn" onclick="execCmd('bold')"        title="Bold"><b>B</b></button>
                            <button type="button" class="tb-btn" onclick="execCmd('italic')"      title="Italic"><i>I</i></button>
                            <button type="button" class="tb-btn" onclick="execCmd('underline')"   title="Underline"><u>U</u></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" onclick="execCmd('insertUnorderedList')" title="List"><i class="ph ph-list-bullets"></i></button>
                            <button type="button" class="tb-btn" onclick="execCmd('insertOrderedList')"   title="Ordered"><i class="ph ph-list-numbers"></i></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" onclick="execCmd('removeFormat')" title="Clear format"><i class="ph ph-eraser"></i></button>
                        </div>
                        <div id="editorBody" contenteditable="true" data-placeholder="Ketik balasan di sini..."></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="file" name="attachments[]" id="fileInput" multiple class="d-none" accept=".jpg,.jpeg,.png,.pdf,.zip,.rar,.txt">
                            <button type="button" onclick="document.getElementById('fileInput').click()"
                                style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:var(--surface);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:12px;font-weight:600;cursor:pointer;transition:background .15s;"
                                onmouseover="this.style.background='var(--hover)'" onmouseout="this.style.background='var(--surface)'">
                                <i class="ph ph-paperclip" style="font-size:15px;"></i> Lampiran
                            </button>
                            <span id="fileInfo" style="font-size:11px;color:var(--mut);">Maks 5MB</span>

                            <div style="width:1px;height:18px;background:var(--border);"></div>
                            <span style="font-size:11px;color:var(--mut);">Status:</span>
                            <select name="status_update" class="fc" style="font-size:12px;padding:4px 8px;width:auto;">
                                <option value="Answered" selected>Answered</option>
                                <option value="Closed">Closed</option>
                                <option value="Open">Biarkan Open</option>
                            </select>
                        </div>
                        <button type="submit" name="reply_ticket" class="btn btn-primary fw-bold" style="background:var(--accent);border:none;font-size:13px;padding:8px 20px;border-radius:8px;">
                            <i class="ph-fill ph-paper-plane-tilt me-1"></i> Kirim Balasan
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Sidebar -->
    <div class="col-lg-4">
        <div class="card-c p-0 sticky-top" style="top:80px;">
            <!-- User info header -->
            <div class="cb d-flex align-items-center gap-3 view-user-detail" data-userid="<?= $ticket['user_id'] ?>" style="cursor:pointer;border-bottom:1px solid var(--border);">
                <div style="width:42px;height:42px;border-radius:12px;background:var(--as);display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:800;font-size:18px;flex-shrink:0;">
                    <?= strtoupper(substr($ticket['user_name']??'U',0,1)) ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($ticket['user_name']) ?></div>
                    <div style="font-size:11px;color:var(--mut);"><i class="ph-fill ph-envelope me-1" style="color:var(--accent);"></i><?= htmlspecialchars($ticket['user_email']) ?></div>
                    <?php if($ticket['no_whatsapp']): ?>
                    <div style="font-size:11px;color:#25d366;margin-top:2px;"><i class="ph-fill ph-whatsapp-logo me-1"></i><?= htmlspecialchars($ticket['no_whatsapp']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ticket details -->
            <div class="cb">
                <div style="font-size:10px;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">Info Tiket</div>
                <div class="info-row">
                    <span class="info-lbl">No. Tiket</span>
                    <span style="font-family:monospace;font-size:12px;color:var(--accent);"><?= $ticket['ticket_number'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">Departemen</span>
                    <span style="font-size:12px;"><?= htmlspecialchars($ticket['department']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">Prioritas</span>
                    <span style="font-size:12px;"><?= htmlspecialchars($ticket['priority'] ?? 'Normal') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">Balasan</span>
                    <span style="font-size:12px;"><?= $reply_count ?> pesan</span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">Dibuat</span>
                    <span style="font-size:12px;"><?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="cb" style="border-top:1px solid var(--border);">
                <?php if($ticket['status'] !== 'Closed'): ?>
                <form method="POST" onsubmit="return confirm('Tutup tiket ini?')">
                    <button type="submit" name="close_ticket" class="btn w-100 fw-bold" style="background:var(--es);color:var(--err);border:1px solid var(--err);border-radius:8px;font-size:13px;padding:8px;">
                        <i class="ph-fill ph-lock-key me-1"></i> Tutup Tiket
                    </button>
                </form>
                <?php else: ?>
                <form method="POST">
                    <button type="submit" name="reopen_ticket" class="btn w-100 fw-bold" style="background:var(--oks);color:var(--ok);border:1px solid var(--ok);border-radius:8px;font-size:13px;padding:8px;">
                        <i class="ph-fill ph-arrow-counter-clockwise me-1"></i> Buka Kembali
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Attachment Preview Modal -->
<div class="modal fade" id="prevModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h6 class="modal-title fw-bold" id="prevTitle">Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 text-center" style="min-height:200px;max-height:75vh;overflow:auto;background:#0a0a0f;">
                <div id="prevBody" class="d-flex align-items-center justify-content-center" style="min-height:200px;"></div>
            </div>
        </div>
    </div>
</div>

<style>
.ticket-content p { margin-bottom:.4rem; }
.ticket-content p:last-child { margin:0; }
</style>

<script>
function execCmd(cmd) { document.execCommand(cmd, false, null); document.getElementById('editorBody').focus(); }

document.getElementById('replyForm')?.addEventListener('submit', function(e) {
    const html = document.getElementById('editorBody').innerHTML.trim();
    const fi   = document.getElementById('fileInput');
    if ((html === '' || html === '<br>') && fi.files.length === 0) {
        e.preventDefault();
        alert('Pesan atau lampiran tidak boleh kosong!');
        return;
    }
    document.getElementById('msgInput').value = html;
});

document.getElementById('fileInput')?.addEventListener('change', function() {
    const n = this.files.length;
    document.getElementById('fileInfo').innerHTML = n
        ? `<span style="color:var(--ok);"><i class="ph-fill ph-check-circle"></i> ${n} file dipilih</span>`
        : 'Maks 5MB';
});

function previewAtt(url, ext, title) {
    document.getElementById('prevTitle').innerText = title;
    const b = document.getElementById('prevBody');
    b.innerHTML = '<div class="spinner-border text-primary m-5"></div>';
    new bootstrap.Modal(document.getElementById('prevModal')).show();
    setTimeout(() => {
        if(['jpg','jpeg','png','gif'].includes(ext))
            b.innerHTML = `<img src="${url}" class="img-fluid" style="max-height:72vh;object-fit:contain;">`;
        else if(ext === 'pdf')
            b.innerHTML = `<iframe src="${url}" width="100%" height="500" style="border:none;"></iframe>`;
        else
            b.innerHTML = '<div class="p-4" style="color:var(--sub);">Format tidak bisa di-preview.</div>';
    }, 300);
}
</script>

<?php include __DIR__ . '/../library/footer.php'; ?>
