</div> <!-- end content-wrap -->
</main> <!-- end main-content -->

<!-- jQuery & DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sbOverlay').classList.toggle('show');
    }

    $(document).ready(function() {
        if ($('.tbl').length) {
            $('.tbl').DataTable({
                "pageLength": 10,
                "language": {
                    "search": "Pencarian:",
                    "lengthMenu": "Tampilkan _MENU_ baris",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                    "infoFiltered": "(disaring dari _MAX_ total data)",
                    "paginate": {
                        "first": "Awal",
                        "last": "Terakhir",
                        "next": "Lanjut",
                        "previous": "Balik"
                    }
                }
            });
        }
    });
</script>

<!-- Global Modal Container for dynamically loaded modals -->
<div id="globalModalContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('click', function(e) {
        let target = e.target.closest('.view-user-detail');
        if(target) {
            e.preventDefault();
            console.log("DEBUG: .view-user-detail diklik!");
            
            let userId = target.getAttribute('data-userid');
            if(!userId) {
                alert("DEBUG: attr data-userid tidak ditemukan pada elemen yang diklik.");
                return;
            }
            console.log("DEBUG: Mengambil data untuk User ID: " + userId);
            
            let mContainer = document.getElementById('globalModalContainer');
            if(!mContainer) {
                alert("DEBUG: Elemen globalModalContainer tidak ada di DOM!");
                return;
            }
            
            let fetchUrl = '<?= base_url("admin/api/user_detail/") ?>' + userId;
            console.log("DEBUG: Akan melakukan fetch ke URL: " + fetchUrl);
            
            fetch(fetchUrl)
                .then(res => {
                    console.log("DEBUG: Fetch status " + res.status);
                    return res.text();
                })
                .then(html => {
                    console.log("DEBUG: HTML berhasil ditarik. Panjang string: " + html.length);
                    mContainer.innerHTML = html;
                    
                    let modalEl = mContainer.querySelector('.modal');
                    if(!modalEl) {
                        alert("DEBUG: Hasil request sukses, tapi TIDAK ADA element .modal di dalamnya. Lihat console untuk output HTML-nya.");
                        console.log("OUTPUT HTML:", html);
                        return;
                    }
                    console.log("DEBUG: .modal ditemukan, akan menjalankan bootstrap.Modal...");
                    
                    if (typeof bootstrap === 'undefined') {
                        alert("DEBUG: Error! Variabel global 'bootstrap' tidak ditemukan di browser. Apakah Bootstrap JS gagal dimuat?");
                        return;
                    }
                    
                    try {
                        let dynamicModal = new bootstrap.Modal(modalEl);
                        dynamicModal.show();
                        console.log("DEBUG: Modal berhasil show().");
                        
                        modalEl.addEventListener('hidden.bs.modal', function() {
                            mContainer.innerHTML = '';
                        });
                    } catch (e) {
                        alert("DEBUG: Terjadi error saat inisiasi bootstrap.Modal: " + e.message);
                        console.error(e);
                    }
                })
                .catch(err => {
                    console.error("DEBUG: Gagal memuat data user", err);
                    alert("DEBUG CATCH ERROR: Gagal fetch detail user. " + err.message);
                });
        }
    });
});
</script>

</body>
</html>
