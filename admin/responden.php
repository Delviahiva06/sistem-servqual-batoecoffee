<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth_check.php';

if (isset($_GET['hapus'])) {
    $idHapus = (int) $_GET['hapus'];
    if ($idHapus > 0) {
        mysqli_begin_transaction($conn);
        try {
            $stmtJawaban = mysqli_prepare($conn, "DELETE FROM tbl_jawaban WHERE id_responden = ?");
            if (!$stmtJawaban) throw new Exception('Gagal menyiapkan hapus jawaban.');
            mysqli_stmt_bind_param($stmtJawaban, 'i', $idHapus);
            mysqli_stmt_execute($stmtJawaban);
            mysqli_stmt_close($stmtJawaban);

            $stmtResponden = mysqli_prepare($conn, "DELETE FROM tbl_responden WHERE id_responden = ?");
            if (!$stmtResponden) throw new Exception('Gagal menyiapkan hapus responden.');
            mysqli_stmt_bind_param($stmtResponden, 'i', $idHapus);
            mysqli_stmt_execute($stmtResponden);
            mysqli_stmt_close($stmtResponden);

            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
        }
    }
    header('Location: responden.php');
    exit;
}

$respondenList = array();
$kolomJenisKelaminAda = false;
$cekKolomJk = mysqli_query($conn, "SHOW COLUMNS FROM tbl_responden LIKE 'jenis_kelamin'");
if ($cekKolomJk && mysqli_num_rows($cekKolomJk) > 0) $kolomJenisKelaminAda = true;

$selectJenisKelamin = $kolomJenisKelaminAda ? "jenis_kelamin" : "'-' AS jenis_kelamin";
$qResponden = mysqli_query($conn, "SELECT id_responden, nama, usia, {$selectJenisKelamin}, tanggal FROM tbl_responden ORDER BY id_responden DESC");
if ($qResponden) {
    while ($row = mysqli_fetch_assoc($qResponden)) $respondenList[] = $row;
}

$jawabanByResponden = array();
$qJawaban = mysqli_query(
    $conn,
    "SELECT j.id_responden, p.kode, p.pertanyaan, d.nama_dimensi,
            j.skor_harapan, j.skor_kenyataan
     FROM tbl_jawaban j
     INNER JOIN tbl_pertanyaan p ON p.id_pertanyaan = j.id_pertanyaan
     INNER JOIN tbl_dimensi d ON d.id_dimensi = p.id_dimensi
     ORDER BY j.id_responden DESC, p.id_pertanyaan ASC"
);
if ($qJawaban) {
    while ($rowJawaban = mysqli_fetch_assoc($qJawaban)) {
        $idResponden = (int) $rowJawaban['id_responden'];
        if (!isset($jawabanByResponden[$idResponden])) $jawabanByResponden[$idResponden] = array();
        $jawabanByResponden[$idResponden][] = $rowJawaban;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Responden</title>
<link rel="stylesheet" href="assets/css/admin.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.responden-page .btn-detail{display:inline-block;background:#6b3a2a;color:#fff;border:none;border-radius:6px;padding:7px 12px;font-size:.8rem;text-decoration:none;cursor:pointer;margin-right:6px}
.responden-page .btn-detail:hover{background:#532c20;color:#fff}
.responden-page .detail-modal .modal-header{background:linear-gradient(135deg,#3d2b1f 0%,#6b3a2a 100%);color:#f8f3ea}
.responden-page .detail-modal .btn-close{filter:invert(1) grayscale(1)}
.responden-page .detail-info{font-size:.9rem;color:#6b7280;margin-bottom:1rem}
.responden-page .detail-table{width:100%;border-collapse:collapse}
.responden-page .detail-table th,.responden-page .detail-table td{padding:10px 12px;border-bottom:1px solid #ead9ca;text-align:left;vertical-align:top}
.responden-page .detail-table th{background:#f3e4d8;color:#4b2c1c;font-weight:600}
.responden-page .detail-table-wrap{border:1px solid #ead9ca;border-radius:8px;overflow:auto}
.responden-page .aksi-wrap{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
</style>
</head>
<body class="admin-body">
<div class="admin-shell">
<?php $activeMenu = 'responden'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
<?php $pageTitle = 'Data Responden'; ?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-content responden-page">
<section class="card">
<h3 class="section-heading">Daftar Responden</h3>
<?php if (count($respondenList) === 0) : ?>
<p>Belum ada data responden.</p>
<?php else : ?>
<div class="table-wrapper">
<table class="admin-table">
<thead>
<tr>
<th>No</th><th>Nama</th><th>Usia</th><th>Jenis Kelamin</th><th>Tanggal Mengisi</th><th>Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($respondenList as $index => $row) : ?>
<?php $idResponden = (int) $row['id_responden']; ?>
<tr>
<td><?php echo $index + 1; ?></td>
<td><?php echo htmlspecialchars($row['nama']); ?></td>
<td><?php echo (int) $row['usia']; ?></td>
<td><?php echo htmlspecialchars($row['jenis_kelamin']); ?></td>
<td><?php echo htmlspecialchars($row['tanggal']); ?></td>
<td>
<div class="aksi-wrap">
<button type="button" class="btn-detail" data-bs-toggle="modal" data-bs-target="#detailJawabanModal"
data-responden-id="<?php echo $idResponden; ?>"
data-responden-nama="<?php echo htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?>">DETAIL</button>
<a href="responden.php?hapus=<?php echo $idResponden; ?>" class="btn-danger"
onclick="return confirm('Apakah yakin ingin menghapus data ini?');">HAPUS</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php foreach ($respondenList as $row) : ?>
<?php $idResponden = (int) $row['id_responden']; ?>
<div id="detail-content-<?php echo $idResponden; ?>" class="d-none">
<p class="detail-info">Menampilkan skor Harapan dan Kenyataan untuk seluruh atribut pelayanan yang dijawab oleh responden.</p>
<?php if (empty($jawabanByResponden[$idResponden])) : ?>
<p class="text-muted mb-0">Responden ini belum memiliki jawaban.</p>
<?php else : ?>
<div class="detail-table-wrap">
<table class="detail-table">
<thead><tr><th>Kode</th><th>Pertanyaan</th><th>Dimensi</th><th>Harapan</th><th>Kenyataan</th></tr></thead>
<tbody>
<?php foreach ($jawabanByResponden[$idResponden] as $jawaban) : ?>
<tr>
<td><?php echo htmlspecialchars($jawaban['kode']); ?></td>
<td><?php echo htmlspecialchars($jawaban['pertanyaan']); ?></td>
<td><?php echo htmlspecialchars($jawaban['nama_dimensi']); ?></td>
<td><?php echo (int) $jawaban['skor_harapan']; ?></td>
<td><?php echo (int) $jawaban['skor_kenyataan']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</section>

<div class="modal fade detail-modal" id="detailJawabanModal" tabindex="-1" aria-labelledby="detailJawabanModalLabel" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="detailJawabanModalLabel">Detail Jawaban Responden</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
</div>
<div class="modal-body" id="detailJawabanModalBody"></div>
</div>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    var modalEl=document.getElementById('detailJawabanModal');
    if(!modalEl)return;
    modalEl.addEventListener('show.bs.modal',function(event){
        var button=event.relatedTarget;
        if(!button)return;
        var respondenId=button.getAttribute('data-responden-id');
        var respondenNama=button.getAttribute('data-responden-nama')||'';
        var source=document.getElementById('detail-content-'+respondenId);
        var titleEl=document.getElementById('detailJawabanModalLabel');
        var bodyEl=document.getElementById('detailJawabanModalBody');
        if(titleEl)titleEl.textContent='Detail Jawaban: '+respondenNama;
        if(bodyEl)bodyEl.innerHTML=source?source.innerHTML:'<p class="text-muted mb-0">Detail jawaban tidak ditemukan.</p>';
    });
})();
</script>
</body>
</html>
