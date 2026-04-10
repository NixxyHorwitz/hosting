<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) {
    header("Location: login");
    exit;
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    mysqli_query($conn, "DELETE FROM hosting_plans WHERE id = '$id'");
    header("Location: hosting_plans?status=deleted");
    exit();
}

// --- ADD ---
if (isset($_POST['add_plan'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_paket']);
    $disk = mysqli_real_escape_string($conn, $_POST['disk_limit']);
    $bw = mysqli_real_escape_string($conn, $_POST['bandwidth_limit']);
    $whm = mysqli_real_escape_string($conn, $_POST['whm_name']);
    $harga = (float)$_POST['harga'];

    mysqli_query($conn, "INSERT INTO hosting_plans (nama_paket, disk_limit, bandwidth_limit, whm_package_name, harga_per_bulan) 
                         VALUES ('$nama', '$disk', '$bw', '$whm', '$harga')");
    header("Location: hosting_plans?status=added");
    exit();
}

// --- EDIT ---
if (isset($_POST['edit_plan'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_paket']);
    $disk = mysqli_real_escape_string($conn, $_POST['disk_limit']);
    $bw = mysqli_real_escape_string($conn, $_POST['bandwidth_limit']);
    $whm = mysqli_real_escape_string($conn, $_POST['whm_name']);
    $harga = (float)$_POST['harga'];

    mysqli_query($conn, "UPDATE hosting_plans SET 
                        nama_paket = '$nama', 
                        disk_limit = '$disk', 
                        bandwidth_limit = '$bw', 
                        whm_package_name = '$whm', 
                        harga_per_bulan = '$harga' 
                        WHERE id = '$id'");
    header("Location: hosting_plans?status=edited");
    exit();
}


$query = mysqli_query($conn, "SELECT * FROM hosting_plans ORDER BY id DESC");

$page_title = "Hosting Plans";
include __DIR__ . '/library/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Kelola Paket Hosting</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bc">
                <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Hosting Plans</li>
            </ol>
        </nav>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal" style="background: var(--accent); border: none; font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 8px;">
        <i class="ph ph-plus-circle me-1" style="font-size: 16px; vertical-align: text-bottom;"></i> Tambah Paket
    </button>
</div>

<div class="card-c">
    <div class="ch">
        <h3 class="ct">Daftar Paket Sistem</h3>
    </div>
    <div class="cb p-0 mt-3">
        <div class="table-responsive">
            <table class="tbl table-hover w-100" id="tableHosting" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Nama Paket</th>
                        <th>Disk Limit</th>
                        <th>Bandwidth</th>
                        <th>WHM Name</th>
                        <th>Harga /Bulan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($query)): ?>
                    <tr>
                        <td>
                            <div class="fw-bold" style="font-size: 14px;"><?= htmlspecialchars($row['nama_paket'] ?? '') ?></div>
                        </td>
                        <td><span class="bd bd-acc"><?= htmlspecialchars($row['disk_limit'] ?? '') ?></span></td>
                        <td><?= htmlspecialchars($row['bandwidth_limit'] ?? '') ?></td>
                        <td><span class="text-muted" style="font-size: 12px;"><i class="ph-fill ph-server me-1 text-primary"></i> <?= htmlspecialchars($row['whm_package_name'] ?? '') ?></span></td>
                        <td><div class="fw-bold" style="color: var(--ok);">Rp <?= number_format((float)($row['harga_per_bulan'] ?? 0), 0, ',', '.') ?></div></td>
                        <td class="text-center">
                            <button type="button" class="ab me-1" title="Edit" 
                                onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_paket'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($row['disk_limit'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($row['bandwidth_limit'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($row['whm_package_name'] ?? '')) ?>', <?= (float)($row['harga_per_bulan'] ?? 0) ?>)">
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <button class="ab red" onclick="confirmDelete(<?= $row['id'] ?>)" title="Hapus"><i class="ph ph-trash"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold">Tambah Paket Hosting</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body py-3">
                    <div class="alert alert-primary py-2 px-3 mb-3 d-flex align-items-center" style="font-size: 11px; background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3); color: #8ab4f8; border-radius: 6px;">
                        <i class="ph-fill ph-info me-2 fs-5"></i> 
                        Pastikan WHM Package Name persis dengan yang ada di panel WHM server Anda.
                    </div>
                    <div class="mb-2">
                        <label class="fl m-0 mb-1" style="font-size: 11px;">Nama Paket</label>
                        <input type="text" name="nama_paket" class="fc form-control-sm w-100" placeholder="Contoh: Paket Hemat" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="fl m-0 mb-1" style="font-size: 11px;">Disk Limit (MB/GB)</label>
                            <input type="text" name="disk_limit" class="fc form-control-sm w-100" placeholder="Contoh: 10 GB" required>
                        </div>
                        <div class="col-md-6">
                            <label class="fl m-0 mb-1" style="font-size: 11px;">Bandwidth</label>
                            <input type="text" name="bandwidth_limit" class="fc form-control-sm w-100" placeholder="Contoh: Unlimited" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="fl m-0 mb-1" style="font-size: 11px;">WHM Package Name</label>
                        <input type="text" name="whm_name" class="fc form-control-sm w-100" placeholder="sh_hemat" required>
                    </div>
                    <div class="mb-2">
                        <label class="fl m-0 mb-1" style="font-size: 11px;">Harga Per Bulan (Rp)</label>
                        <input type="number" name="harga" class="fc form-control-sm w-100" placeholder="50000" min="0" required>
                    </div>
                </div>
                <div class="modal-footer mf py-2">
                    <button type="button" class="btn py-1 px-2 text-muted fw-medium" data-bs-dismiss="modal" style="font-size: 12px;">Batal</button>
                    <button type="submit" name="add_plan" class="btn py-1 px-3 btn-primary shadow-sm" style="background: var(--accent); border: none; border-radius: 6px; font-size: 12px; font-weight: 600;">Simpan Paket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold">Edit Paket Hosting</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body py-3">
                    <div class="mb-2">
                        <label class="fl m-0 mb-1" style="font-size: 11px;">Nama Paket</label>
                        <input type="text" name="nama_paket" id="edit_nama" class="fc form-control-sm w-100" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="fl m-0 mb-1" style="font-size: 11px;">Disk Limit (MB/GB)</label>
                            <input type="text" name="disk_limit" id="edit_disk" class="fc form-control-sm w-100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="fl m-0 mb-1" style="font-size: 11px;">Bandwidth</label>
                            <input type="text" name="bandwidth_limit" id="edit_bw" class="fc form-control-sm w-100" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="fl m-0 mb-1" style="font-size: 11px;">WHM Package Name</label>
                        <input type="text" name="whm_name" id="edit_whm" class="fc form-control-sm w-100" required>
                    </div>
                    <div class="mb-2">
                        <label class="fl m-0 mb-1" style="font-size: 11px;">Harga Per Bulan (Rp)</label>
                        <input type="number" name="harga" id="edit_harga" class="fc form-control-sm w-100" min="0" required>
                    </div>
                </div>
                <div class="modal-footer mf py-2">
                    <button type="button" class="btn py-1 px-2 text-muted fw-medium" data-bs-dismiss="modal" style="font-size: 12px;">Batal</button>
                    <button type="submit" name="edit_plan" class="btn py-1 px-3 btn-primary shadow-sm" style="background: var(--accent); border: none; border-radius: 6px; font-size: 12px; font-weight: 600;">Update Paket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openEditModal(id, nama, disk, bw, whm, harga) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_disk').value = disk;
        document.getElementById('edit_bw').value = bw;
        document.getElementById('edit_whm').value = whm;
        document.getElementById('edit_harga').value = harga;
        
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Paket?',
            text: "Data paket hosting ini akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--err)',
            cancelButtonColor: 'var(--hover)',
            confirmButtonText: 'Ya, Hapus!',
            background: 'var(--card)',
            color: 'var(--text)'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'hosting_plans?delete=' + id;
            }
        })
    }

    <?php if(isset($_GET['status'])): ?>
        <?php
            $icon = 'success';
            $title = 'Berhasil!';
            $text = '';
            
            if ($_GET['status'] == 'deleted') $text = 'Paket hosting telah berhasil dihapus.';
            if ($_GET['status'] == 'added') $text = 'Paket hosting baru telah berhasil ditambahkan.';
            if ($_GET['status'] == 'edited') $text = 'Detail paket hosting berhasil diperbarui.';
        ?>
        <?php if($text != ''): ?>
        Swal.fire({
            icon: '<?= $icon ?>',
            title: '<?= $title ?>',
            text: '<?= $text ?>',
            timer: 2000,
            showConfirmButton: false,
            background: 'var(--card)',
            color: 'var(--text)'
        });
        <?php endif; ?>
    <?php endif; ?>
</script>

<?php include __DIR__ . '/library/footer.php'; ?>