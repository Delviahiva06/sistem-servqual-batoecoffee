<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/validitas.php';
require_once __DIR__ . '/includes/servqual_perhitungan.php';
require_once __DIR__ . '/includes/ipa_analisis.php';

$rTabelValiditas = 0.1966;

$hasilValiditasHarapan = hitungUjiValiditas(
    $conn,
    'harapan',
    $rTabelValiditas
);

$hasilValiditasKenyataan = hitungUjiValiditas(
    $conn,
    'kenyataan',
    $rTabelValiditas
);
$jenisValiditas = isset($_GET['jenis_validitas'])
    ? $_GET['jenis_validitas']
    : 'kenyataan';

if ($jenisValiditas === 'harapan') {
    $hasilValiditas = $hasilValiditasHarapan;
} else {
    $hasilValiditas = $hasilValiditasKenyataan;
}

// =========================================================================
// UJI RELIABILITAS — CRONBACH'S ALPHA
// Rumus: α = (k/(k−1)) × (1 − (Σ varians item / varians total))
// =========================================================================

/**
 * Menghitung varians sampel dari sekumpulan nilai.
 * Digunakan untuk varians tiap item maupun varians skor total.
 *
 * @param array $nilai
 * @return float|null null jika data kurang dari 2
 */
function hitungVariansSampel(array $nilai)
{
    $n = count($nilai);
    if ($n < 2) {
        return null;
    }

    $rataRata = array_sum($nilai) / $n;
    $jumlahKuadrat = 0.0;

    foreach ($nilai as $nilaiItem) {
        $selisih = (float) $nilaiItem - $rataRata;
        $jumlahKuadrat += $selisih * $selisih;
    }

    return $jumlahKuadrat / ($n - 1);
}

/**
 * Menghitung nilai Cronbach's Alpha dari matriks skor responden.
 *
 * @param array $matriksSkor [id_responden => [id_pertanyaan => skor]]
 * @param array $daftarIdPertanyaan daftar id_pertanyaan yang dihitung
 * @return float|null null jika data tidak cukup untuk dihitung
 */
function hitungCronbachAlpha(array $matriksSkor, array $daftarIdPertanyaan)
{
    // k = jumlah pertanyaan (item)
    $k = count($daftarIdPertanyaan);
    if ($k < 2) {
        return null;
    }

    // Hanya gunakan responden yang memiliki jawaban lengkap untuk semua item
    $matriksLengkap = array();
    foreach ($matriksSkor as $idResponden => $skorPerItem) {
        $lengkap = true;
        foreach ($daftarIdPertanyaan as $idPertanyaan) {
            if (!isset($skorPerItem[$idPertanyaan])) {
                $lengkap = false;
                break;
            }
        }
        if ($lengkap) {
            $matriksLengkap[$idResponden] = $skorPerItem;
        }
    }

    // Minimal 2 responden agar varians dapat dihitung
    if (count($matriksLengkap) < 2) {
        return null;
    }

    // Σ varians item — jumlah varians skor tiap pertanyaan dari seluruh responden
    $jumlahVariansItem = 0.0;
    foreach ($daftarIdPertanyaan as $idPertanyaan) {
        $skorItem = array();
        foreach ($matriksLengkap as $skorPerItem) {
            $skorItem[] = (float) $skorPerItem[$idPertanyaan];
        }

        $variansItem = hitungVariansSampel($skorItem);
        if ($variansItem === null) {
            return null;
        }
        $jumlahVariansItem += $variansItem;
    }

    // Varians total — varians dari total skor setiap responden
    $skorTotalResponden = array();
    foreach ($matriksLengkap as $skorPerItem) {
        $total = 0.0;
        foreach ($daftarIdPertanyaan as $idPertanyaan) {
            $total += (float) $skorPerItem[$idPertanyaan];
        }
        $skorTotalResponden[] = $total;
    }

    $variansTotal = hitungVariansSampel($skorTotalResponden);
    if ($variansTotal === null || $variansTotal == 0.0) {
        return null;
    }

    // Terapkan rumus Cronbach's Alpha
    return ($k / ($k - 1)) * (1 - ($jumlahVariansItem / $variansTotal));
}

/**
 * Menentukan status reliabilitas berdasarkan nilai alpha dan batas minimum.
 *
 * @param float|null $alpha
 * @param float $batasMinimum
 * @return string
 */
function tentukanStatusReliabilitas($alpha, $batasMinimum = 0.70)
{
    if ($alpha === null) {
        return 'Tidak Reliabel';
    }
    return $alpha >= $batasMinimum ? 'Reliabel' : 'Tidak Reliabel';
}

// Ambil daftar id pertanyaan dari database sebagai dasar perhitungan k
$daftarIdPertanyaan = array();
$qDaftarPertanyaan = mysqli_query(
    $conn,
    "SELECT id_pertanyaan FROM tbl_pertanyaan ORDER BY id_pertanyaan ASC"
);
if ($qDaftarPertanyaan) {
    while ($barisPertanyaan = mysqli_fetch_assoc($qDaftarPertanyaan)) {
        $daftarIdPertanyaan[] = (int) $barisPertanyaan['id_pertanyaan'];
    }
}

// Bangun matriks skor harapan dan skor kenyataan per responden
$matriksSkorHarapan = array();
$matriksSkorKenyataan = array();
$qSkorReliabilitas = mysqli_query(
    $conn,
    "SELECT id_responden, id_pertanyaan, skor_harapan, skor_kenyataan
     FROM tbl_jawaban
     ORDER BY id_responden ASC, id_pertanyaan ASC"
);
if ($qSkorReliabilitas) {
    while ($barisSkor = mysqli_fetch_assoc($qSkorReliabilitas)) {
        $idResponden = (int) $barisSkor['id_responden'];
        $idPertanyaan = (int) $barisSkor['id_pertanyaan'];

        if (!isset($matriksSkorHarapan[$idResponden])) {
            $matriksSkorHarapan[$idResponden] = array();
            $matriksSkorKenyataan[$idResponden] = array();
        }

        $matriksSkorHarapan[$idResponden][$idPertanyaan] = (int) $barisSkor['skor_harapan'];
        $matriksSkorKenyataan[$idResponden][$idPertanyaan] = (int) $barisSkor['skor_kenyataan'];
    }
}

// Hitung Cronbach's Alpha terpisah untuk skor harapan dan skor kenyataan
$batasAlphaReliabel = 0.70;
$alphaHarapan = hitungCronbachAlpha($matriksSkorHarapan, $daftarIdPertanyaan);
$alphaKenyataan = hitungCronbachAlpha($matriksSkorKenyataan, $daftarIdPertanyaan);

// Siapkan baris tabel hasil reliabilitas
$hasilReliabilitas = array(
    array(
        'jenis' => 'Harapan',
        'alpha' => $alphaHarapan,
        'status' => tentukanStatusReliabilitas($alphaHarapan, $batasAlphaReliabel),
    ),
    array(
        'jenis' => 'Kenyataan',
        'alpha' => $alphaKenyataan,
        'status' => tentukanStatusReliabilitas($alphaKenyataan, $batasAlphaReliabel),
    ),
);

// =========================================================================
// FILTER PERIODE EVALUASI
// =========================================================================
$namaBulan=array(1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember');
$periodeDipilih=isset($_GET['periode'])?trim($_GET['periode']):'semua';
$daftarPeriode=array();
$qPeriode=mysqli_query($conn,"SELECT YEAR(tanggal) tahun, MONTH(tanggal) bulan, COUNT(*) jumlah_responden FROM tbl_responden WHERE tanggal IS NOT NULL GROUP BY YEAR(tanggal),MONTH(tanggal) ORDER BY YEAR(tanggal) DESC,MONTH(tanggal) DESC");
if($qPeriode){while($p=mysqli_fetch_assoc($qPeriode)){
    $th=(int)$p['tahun']; $bl=(int)$p['bulan'];
    if($th>0&&$bl>=1&&$bl<=12)$daftarPeriode[]=array('value'=>sprintf('%04d-%02d',$th,$bl),'label'=>$namaBulan[$bl].' '.$th,'jumlah'=>(int)$p['jumlah_responden']);
}}
$periodeValid=array_column($daftarPeriode,'value');
if($periodeDipilih!=='semua'&&!in_array($periodeDipilih,$periodeValid,true))$periodeDipilih='semua';
$labelPeriode='Semua Periode';
$totalRespondenPeriode=0;
$qTotalSemua=mysqli_query($conn,"SELECT COUNT(*) total FROM tbl_responden");
if($qTotalSemua&&($rTotalSemua=mysqli_fetch_assoc($qTotalSemua)))$totalRespondenPeriode=(int)$rTotalSemua['total'];
if($periodeDipilih!=='semua'){
    list($tahunFilter,$bulanFilter)=array_map('intval',explode('-',$periodeDipilih));
    $labelPeriode=$namaBulan[$bulanFilter].' '.$tahunFilter;
    $qt=mysqli_query($conn,"SELECT COUNT(*) total FROM tbl_responden WHERE YEAR(tanggal)=$tahunFilter AND MONTH(tanggal)=$bulanFilter");
    if($qt&&($rt=mysqli_fetch_assoc($qt)))$totalRespondenPeriode=(int)$rt['total'];
}

// =========================================================================
// PERHITUNGAN SERVQUAL
// GAP = Rata-rata Kenyataan − Rata-rata Harapan
// =========================================================================
$hasilServqual = hitungServqual($conn, $periodeDipilih);
$hasilServqualPertanyaan = $hasilServqual['pertanyaan'];
$hasilServqualDimensi = $hasilServqual['dimensi'];
$servqualAdaData = servqualMemilikiDataJawaban($hasilServqualPertanyaan);

// =========================================================================
// ANALISIS IPA (Importance Performance Analysis)
// =========================================================================
$hasilIpa = hitungAnalisisIpa($hasilServqualPertanyaan);

$dataGrafikIpa = siapkanDataGrafikIpa($hasilIpa);

// Ringkasan hasil evaluasi periode terpilih
$nilaiHarapanRingkas = array();
$nilaiKenyataanRingkas = array();
$nilaiGapRingkas = array();
$prioritasUtamaIpa = array();

foreach ($hasilServqualPertanyaan as $barisRingkas) {
    if ((int)$barisRingkas['jumlah_jawaban'] > 0
        && $barisRingkas['rata_harapan'] !== null
        && $barisRingkas['rata_kenyataan'] !== null) {
        $nilaiHarapanRingkas[] = (float)$barisRingkas['rata_harapan'];
        $nilaiKenyataanRingkas[] = (float)$barisRingkas['rata_kenyataan'];
        $nilaiGapRingkas[] = (float)$barisRingkas['gap'];
    }
}

$rataHarapanRingkas = hitungRataRataServqual($nilaiHarapanRingkas);
$rataKenyataanRingkas = hitungRataRataServqual($nilaiKenyataanRingkas);
$rataGapRingkas = hitungRataRataServqual($nilaiGapRingkas);

$dimensiGapTerbesar = null;
foreach ($hasilServqualDimensi as $dimensiRingkas) {
    if ($dimensiRingkas['gap'] === null) continue;
    if ($dimensiGapTerbesar === null || $dimensiRingkas['gap'] < $dimensiGapTerbesar['gap']) {
        $dimensiGapTerbesar = $dimensiRingkas;
    }
}

if (!empty($hasilIpa['ada_data'])) {
    foreach ($hasilIpa['baris'] as $barisPrioritas) {
        if ($barisPrioritas['kuadran_kode'] === 'A') {
            $prioritasUtamaIpa[] = $barisPrioritas;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluasi Pelayanan</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .servqual-page .breadcrumb {
            --bs-breadcrumb-divider: '›';
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
        }

        .servqual-page .breadcrumb-item a {
            color: #6b3a2a;
            text-decoration: none;
        }

        .servqual-page .breadcrumb-item a:hover {
            text-decoration: underline;
        }

        .servqual-page .breadcrumb-item.active {
            color: #6b7280;
        }

        .servqual-page .sq-accordion {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(61, 43, 31, 0.08);
        }

        .servqual-page .sq-accordion .accordion-item {
            border: none;
            border-bottom: 1px solid #ead9ca;
        }

        .servqual-page .sq-accordion .accordion-item:last-child {
            border-bottom: none;
        }

        .servqual-page .sq-accordion .accordion-button {
            background: linear-gradient(135deg, #3d2b1f 0%, #6b3a2a 100%);
            color: #f8f3ea;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.875rem 1.25rem;
            box-shadow: none;
        }

        .servqual-page .sq-accordion .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, #3d2b1f 0%, #6b3a2a 100%);
            color: #f8f3ea;
        }

        .servqual-page .sq-accordion .accordion-button::after {
            filter: brightness(0) invert(1);
        }

        .servqual-page .sq-accordion .accordion-button:focus {
            box-shadow: none;
            border-color: transparent;
        }

        .servqual-page .sq-accordion .accordion-body {
            padding: 1.25rem;
            background: #ffffff;
        }

        .servqual-page .table {
            margin-bottom: 0;
            font-size: 0.875rem;
        }

        .servqual-page .table thead th {
            background: #f3e4d8;
            color: #4b2c1c;
            font-weight: 600;
            border-bottom: 1px solid #e5d7cc;
            white-space: nowrap;
        }

        .servqual-page .table tbody td {
            color: #374151;
            vertical-align: middle;
        }

        .servqual-page .table-responsive {
            border: 1px solid #ead9ca;
            border-radius: 8px;
            overflow: hidden;
        }

        .servqual-page .reliability-stat {
            background: #fffaf5;
            border: 1px solid #ead9ca;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            height: 100%;
        }

        .servqual-page .reliability-stat .label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .servqual-page .reliability-stat .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3d2b1f;
            line-height: 1.2;
        }

        .servqual-page .reliability-stat .value.placeholder {
            color: #9ca3af;
            font-weight: 500;
        }

        .servqual-page .ipa-chart-placeholder {
            background: #fffaf5;
            border: 2px dashed #d4b8a5;
            border-radius: 10px;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #6b7280;
            padding: 2rem 1rem;
        }

        .servqual-page .ipa-chart-placeholder .icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
            opacity: 0.6;
        }

        .servqual-page .section-subtitle {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b3a2a;
            margin-bottom: 0.75rem;
        }

        .servqual-page .ipa-inner-card {
            background: #fffaf5;
            border: 1px solid #ead9ca;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        .servqual-page .ipa-inner-card .ipa-card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #6b3a2a;
            margin-bottom: 0.75rem;
        }

        .servqual-page .ipa-cutpoint-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .servqual-page .ipa-cutpoint-item {
            background: #ffffff;
            border: 1px solid #ead9ca;
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }

        .servqual-page .ipa-cutpoint-item .label {
            font-size: 0.78rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .servqual-page .ipa-cutpoint-item .value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #3d2b1f;
        }

        .servqual-page .ipa-summary-list {
            margin: 0;
            padding-left: 1.1rem;
        }

        .servqual-page .ipa-summary-list li {
            margin-bottom: 0.5rem;
            color: #374151;
            font-size: 0.875rem;
        }

        .servqual-page .ipa-summary-count {
            font-weight: 700;
            color: #3d2b1f;
        }

        .servqual-page .badge-kuadran-a {
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
        }

        .servqual-page .badge-kuadran-b {
            background: #dcfce7;
            color: #166534;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
        }

        .servqual-page .badge-kuadran-c {
            background: #e0e7ff;
            color: #3730a3;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
        }

        .servqual-page .badge-kuadran-d {
            background: #fef3c7;
            color: #92400e;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
        }

        .servqual-page .ipa-chart-wrap {
            background: #ffffff;
            border: 1px solid #ead9ca;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.25rem;
            min-height: 420px;
        }

        .servqual-page .ipa-chart-wrap canvas {
            width: 100% !important;
            height: 380px !important;
        }

        @media (max-width: 576px) {
            .servqual-page .ipa-cutpoint-grid {
                grid-template-columns: 1fr;
            }
        }

        .servqual-page .btn-detail {
            background: #6b3a2a;
            border-color: #6b3a2a;
            color: #fff;
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
        }

        .servqual-page .btn-detail:hover {
            background: #532c20;
            border-color: #532c20;
            color: #fff;
        }

        .servqual-page .detail-modal .modal-header {
            background: linear-gradient(135deg, #3d2b1f 0%, #6b3a2a 100%);
            color: #f8f3ea;
        }

        .servqual-page .detail-modal .btn-close {
            filter: invert(1) grayscale(1);
        }

        .servqual-page .badge-valid {
            background: #dcfce7;
            color: #166534;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
        }

        .servqual-page .badge-invalid {
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
        }

        .servqual-page .validitas-info {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }

        @media (max-width: 576px) {
            .servqual-page .reliability-stat .value {
                font-size: 1.25rem;
            }

            .servqual-page .ipa-chart-placeholder {
                min-height: 240px;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-shell">
        <?php $activeMenu = 'servqual'; ?>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <?php $pageTitle = 'Evaluasi Pelayanan'; ?>
            <?php include __DIR__ . '/includes/header.php'; ?>
            <div class="admin-content servqual-page">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Evaluasi Pelayanan</li>
                    </ol>
                </nav>

                <section class="card mb-4">
                    <div class="card-body">
                        <form method="get" action="servqual.php" class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label for="periode" class="form-label mb-1"><strong>Periode Evaluasi</strong></label>
                                <select name="periode" id="periode" class="form-select" style="min-width:220px;">
                                    <option value="semua" <?php echo $periodeDipilih==='semua'?'selected':''; ?>>Semua Periode</option>
                                    <?php foreach($daftarPeriode as $periode): ?>
                                        <option value="<?php echo htmlspecialchars($periode['value']); ?>" <?php echo $periodeDipilih===$periode['value']?'selected':''; ?>>
                                            <?php echo htmlspecialchars($periode['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-dark">Tampilkan</button>
                            <div class="ms-md-auto text-muted small pb-2">
                                Periode: <strong><?php echo htmlspecialchars($labelPeriode); ?></strong>
                                &middot; <?php echo (int)$totalRespondenPeriode; ?> responden
                            </div>
                        </form>
                    </div>
                </section>

                <?php if (!$servqualAdaData) : ?>
                    <div class="alert alert-light border mb-4">Belum ada penilaian pelanggan pada periode ini.</div>
                <?php else : ?>

                <section class="mb-4">
                    <p class="section-subtitle">Ringkasan Evaluasi</p>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="reliability-stat">
                                <div class="label">Total Responden</div>
                                <div class="value"><?php echo (int)$totalRespondenPeriode; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="reliability-stat">
                                <div class="label">Rata-rata Harapan</div>
                                <div class="value"><?php echo formatServqualAngka($rataHarapanRingkas); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="reliability-stat">
                                <div class="label">Rata-rata Kenyataan</div>
                                <div class="value"><?php echo formatServqualAngka($rataKenyataanRingkas); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="reliability-stat">
                                <div class="label">Rata-rata GAP</div>
                                <div class="value"><?php echo formatServqualAngka($rataGapRingkas); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php if ($dimensiGapTerbesar !== null) : ?>
                        <p class="validitas-info mt-3 mb-0">
                            Selisih terbesar pada periode ini terdapat pada dimensi
                            <strong><?php echo htmlspecialchars($dimensiGapTerbesar['dimensi']); ?></strong>
                            dengan GAP <?php echo formatServqualAngka($dimensiGapTerbesar['gap']); ?>.
                        </p>
                    <?php endif; ?>
                </section>

                <section class="ipa-inner-card">
                    <div class="ipa-card-title">Hasil SERVQUAL per Dimensi</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr><th>Dimensi</th><th>Harapan</th><th>Kenyataan</th><th>GAP</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hasilServqualDimensi as $barisDimensi) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($barisDimensi['dimensi']); ?></td>
                                        <td><?php echo formatServqualAngka($barisDimensi['rata_harapan']); ?></td>
                                        <td><?php echo formatServqualAngka($barisDimensi['rata_kenyataan']); ?></td>
                                        <td><?php echo formatServqualAngka($barisDimensi['gap']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="ipa-inner-card">
                    <div class="ipa-card-title">Prioritas Perbaikan Pelayanan</div>
                    <p class="validitas-info">
                        Atribut pada Kuadran A menjadi prioritas utama karena harapan pelanggan relatif tinggi,
                        sedangkan nilai kenyataannya berada di bawah rata-rata kinerja periode yang dipilih.
                    </p>
                    <?php if (count($prioritasUtamaIpa) === 0) : ?>
                        <p class="text-muted mb-0">Tidak ada atribut yang berada pada Kuadran A pada periode ini.</p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr><th>Atribut Pelayanan</th><th>Dimensi</th><th>Harapan</th><th>Kenyataan</th><th>GAP</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prioritasUtamaIpa as $barisPrioritas) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($barisPrioritas['pertanyaan']); ?></td>
                                            <td><?php echo htmlspecialchars($barisPrioritas['dimensi']); ?></td>
                                            <td><?php echo formatServqualAngka($barisPrioritas['nilai_harapan']); ?></td>
                                            <td><?php echo formatServqualAngka($barisPrioritas['nilai_kenyataan']); ?></td>
                                            <td><?php echo formatServqualAngka($barisPrioritas['gap']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($hasilIpa['ada_data']) : ?>
                <section class="ipa-inner-card">
                    <div class="ipa-card-title">Diagram Kartesius IPA</div>
                    <div class="ipa-cutpoint-grid mb-3">
                        <div class="ipa-cutpoint-item">
                            <div class="label">Rata-rata Kenyataan (X)</div>
                            <div class="value"><?php echo formatServqualAngka($hasilIpa['titik_potong']['rata_kenyataan']); ?></div>
                        </div>
                        <div class="ipa-cutpoint-item">
                            <div class="label">Rata-rata Harapan (Y)</div>
                            <div class="value"><?php echo formatServqualAngka($hasilIpa['titik_potong']['rata_harapan']); ?></div>
                        </div>
                    </div>
                    <div class="ipa-chart-wrap"><canvas id="ipaChart"></canvas></div>
                    <ul class="ipa-summary-list">
                        <li><span class="badge-kuadran-a">Kuadran A</span> &mdash; <span class="ipa-summary-count"><?php echo (int)$hasilIpa['ringkasan']['A']; ?></span> atribut. Prioritas utama.</li>
                        <li><span class="badge-kuadran-b">Kuadran B</span> &mdash; <span class="ipa-summary-count"><?php echo (int)$hasilIpa['ringkasan']['B']; ?></span> atribut. Pertahankan kinerja.</li>
                        <li><span class="badge-kuadran-c">Kuadran C</span> &mdash; <span class="ipa-summary-count"><?php echo (int)$hasilIpa['ringkasan']['C']; ?></span> atribut. Prioritas rendah.</li>
                        <li><span class="badge-kuadran-d">Kuadran D</span> &mdash; <span class="ipa-summary-count"><?php echo (int)$hasilIpa['ringkasan']['D']; ?></span> atribut. Kinerja relatif berlebih.</li>
                    </ul>
                </section>

                <section class="ipa-inner-card">
                    <div class="ipa-card-title">Detail Hasil per Atribut</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr><th>Kode</th><th>Atribut Pelayanan</th><th>Dimensi</th><th>Harapan</th><th>Kenyataan</th><th>GAP</th><th>Kuadran</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hasilIpa['baris'] as $barisIpa) : ?>
                                    <?php
                                    $badgeKuadran = 'badge-kuadran-a';
                                    if ($barisIpa['kuadran_kode'] === 'B') $badgeKuadran = 'badge-kuadran-b';
                                    elseif ($barisIpa['kuadran_kode'] === 'C') $badgeKuadran = 'badge-kuadran-c';
                                    elseif ($barisIpa['kuadran_kode'] === 'D') $badgeKuadran = 'badge-kuadran-d';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($barisIpa['kode']); ?></td>
                                        <td><?php echo htmlspecialchars($barisIpa['pertanyaan']); ?></td>
                                        <td><?php echo htmlspecialchars($barisIpa['dimensi']); ?></td>
                                        <td><?php echo formatServqualAngka($barisIpa['nilai_harapan']); ?></td>
                                        <td><?php echo formatServqualAngka($barisIpa['nilai_kenyataan']); ?></td>
                                        <td><?php echo formatServqualAngka($barisIpa['gap']); ?></td>
                                        <td><span class="<?php echo $badgeKuadran; ?>"><?php echo htmlspecialchars($barisIpa['kuadran_kode']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

                <?php endif; ?>

                <div class="accordion sq-accordion mb-4" id="instrumenAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingInstrumen">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInstrumen">
                                Pengujian Instrumen Penelitian
                            </button>
                        </h2>
                        <div id="collapseInstrumen" class="accordion-collapse collapse <?php echo isset($_GET['jenis_validitas']) ? 'show' : ''; ?>" data-bs-parent="#instrumenAccordion">
                            <div class="accordion-body">
                                <p class="validitas-info">
                                    Uji validitas dan reliabilitas merupakan pengujian instrumen penelitian dan menggunakan seluruh data penelitian.
                                    Filter periode di atas digunakan untuk evaluasi SERVQUAL dan IPA.
                                </p>

                                <p class="section-subtitle">Uji Validitas</p>
                                <form method="GET" action="servqual.php#collapseInstrumen" class="mb-3">
                                    <input type="hidden" name="periode" value="<?php echo htmlspecialchars($periodeDipilih); ?>">
                                    <label for="jenis_validitas" class="form-label"><strong>Jenis Uji Validitas</strong></label>
                                    <select name="jenis_validitas" id="jenis_validitas" class="form-select" style="max-width:250px;" onchange="this.form.submit()">
                                        <option value="kenyataan" <?php echo $jenisValiditas==='kenyataan'?'selected':''; ?>>Kenyataan</option>
                                        <option value="harapan" <?php echo $jenisValiditas==='harapan'?'selected':''; ?>>Harapan</option>
                                    </select>
                                </form>
                                <p class="validitas-info">Pearson Product Moment &middot; r tabel = <?php echo number_format($rTabelValiditas,4); ?> (n ≈ 100).</p>
                                <div class="table-responsive mb-4">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr><th>Kode</th><th>Pertanyaan</th><th>r Hitung</th><th>r Tabel</th><th>Status</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($hasilValiditas as $baris) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($baris['kode']); ?></td>
                                                <td><?php echo htmlspecialchars($baris['pertanyaan']); ?></td>
                                                <td><?php echo $baris['r_hitung'] !== null ? number_format($baris['r_hitung'],4) : '&mdash;'; ?></td>
                                                <td><?php echo number_format($baris['r_tabel'],4); ?></td>
                                                <td><?php if($baris['status']==='Valid'): ?><span class="badge-valid">Valid</span><?php else: ?><span class="badge-invalid">Tidak Valid</span><?php endif; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <p class="section-subtitle">Uji Reliabilitas</p>
                                <p class="validitas-info">Cronbach's Alpha &middot; standar reliabilitas &alpha; &ge; <?php echo number_format($batasAlphaReliabel,2); ?>.</p>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr><th>Jenis</th><th>Cronbach Alpha</th><th>Status</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($hasilReliabilitas as $barisReliabilitas) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($barisReliabilitas['jenis']); ?></td>
                                                <td><?php echo $barisReliabilitas['alpha'] !== null ? number_format($barisReliabilitas['alpha'],3) : '&mdash;'; ?></td>
                                                <td><?php if($barisReliabilitas['status']==='Reliabel'): ?><span class="badge-valid">Reliabel</span><?php else: ?><span class="badge-invalid">Tidak Reliabel</span><?php endif; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include __DIR__ . '/includes/footer.php'; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation"></script>
    <script>
        (function () {
            var chartCanvas = document.getElementById('ipaChart');
            if (!chartCanvas) return;

            var chartData = <?php echo json_encode($dataGrafikIpa, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            if (!chartData.points || chartData.points.length === 0) return;

            var quadrantColors = {
                A: 'rgba(220, 38, 38, 0.85)',
                B: 'rgba(22, 163, 74, 0.85)',
                C: 'rgba(79, 70, 229, 0.85)',
                D: 'rgba(217, 119, 6, 0.85)'
            };

            var quadrantBg = {
                A: 'rgba(254, 226, 226, 0.35)',
                B: 'rgba(220, 252, 231, 0.35)',
                C: 'rgba(224, 231, 255, 0.35)',
                D: 'rgba(254, 243, 199, 0.35)'
            };

            var scatterPoints = chartData.points.map(function (point) {
                return {
                    x: point.x,
                    y: point.y,
                    kode: point.kode,
                    kuadran: point.kuadran
                };
            });

            var xValues = scatterPoints.map(function (p) { return p.x; });
            var yValues = scatterPoints.map(function (p) { return p.y; });

            var xMin = Math.min.apply(null, xValues.concat([1]));
            var xMax = Math.max.apply(null, xValues.concat([5]));
            var yMin = Math.min.apply(null, yValues.concat([1]));
            var yMax = Math.max.apply(null, yValues.concat([5]));
            var xPad = 0.25;
            var yPad = 0.25;

            var rataX = chartData.rata_x;
            var rataY = chartData.rata_y;

            // Plugin kustom: tampilkan label kode pertanyaan (P1, P2, ...) pada setiap titik
            var ipaPointLabelsPlugin = {
                id: 'ipaPointLabels',
                afterDatasetsDraw: function (chart) {
                    var ctx = chart.ctx;
                    chart.data.datasets.forEach(function (dataset, datasetIndex) {
                        var meta = chart.getDatasetMeta(datasetIndex);
                        if (meta.hidden) return;
                        meta.data.forEach(function (element, index) {
                            var raw = dataset.data[index];
                            if (!raw || !raw.kode) return;
                            ctx.save();
                            ctx.font = 'bold 10px sans-serif';
                            ctx.fillStyle = '#3d2b1f';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(raw.kode, element.x, element.y - 8);
                            ctx.restore();
                        });
                    });
                }
            };

            Chart.register(ipaPointLabelsPlugin);

            new Chart(chartCanvas, {
                type: 'scatter',
                data: {
                    datasets: [
                        {
                            label: 'Kuadran A (Prioritas Utama)',
                            data: scatterPoints.filter(function (p) { return p.kuadran === 'A'; }),
                            backgroundColor: quadrantColors.A,
                            borderColor: quadrantColors.A,
                            pointRadius: 7,
                            pointHoverRadius: 9
                        },
                        {
                            label: 'Kuadran B (Pertahankan Prestasi)',
                            data: scatterPoints.filter(function (p) { return p.kuadran === 'B'; }),
                            backgroundColor: quadrantColors.B,
                            borderColor: quadrantColors.B,
                            pointRadius: 7,
                            pointHoverRadius: 9
                        },
                        {
                            label: 'Kuadran C (Prioritas Rendah)',
                            data: scatterPoints.filter(function (p) { return p.kuadran === 'C'; }),
                            backgroundColor: quadrantColors.C,
                            borderColor: quadrantColors.C,
                            pointRadius: 7,
                            pointHoverRadius: 9
                        },
                        {
                            label: 'Kuadran D (Berlebihan)',
                            data: scatterPoints.filter(function (p) { return p.kuadran === 'D'; }),
                            backgroundColor: quadrantColors.D,
                            borderColor: quadrantColors.D,
                            pointRadius: 7,
                            pointHoverRadius: 9
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    var raw = context.raw;
                                    return raw.kode + ' | X: ' + raw.x.toFixed(2) + ', Y: ' + raw.y.toFixed(2) + ' (' + raw.kuadran + ')';
                                }
                            }
                        },
                        annotation: {
                            annotations: {
                                quadrantA: {
                                    type: 'box',
                                    xMin: xMin - xPad,
                                    xMax: rataX,
                                    yMin: rataY,
                                    yMax: yMax + yPad,
                                    backgroundColor: quadrantBg.A,
                                    borderWidth: 0
                                },
                                quadrantB: {
                                    type: 'box',
                                    xMin: rataX,
                                    xMax: xMax + xPad,
                                    yMin: rataY,
                                    yMax: yMax + yPad,
                                    backgroundColor: quadrantBg.B,
                                    borderWidth: 0
                                },
                                quadrantC: {
                                    type: 'box',
                                    xMin: xMin - xPad,
                                    xMax: rataX,
                                    yMin: yMin - yPad,
                                    yMax: rataY,
                                    backgroundColor: quadrantBg.C,
                                    borderWidth: 0
                                },
                                quadrantD: {
                                    type: 'box',
                                    xMin: rataX,
                                    xMax: xMax + xPad,
                                    yMin: yMin - yPad,
                                    yMax: rataY,
                                    backgroundColor: quadrantBg.D,
                                    borderWidth: 0
                                },
                                garisVertikal: {
                                    type: 'line',
                                    xMin: rataX,
                                    xMax: rataX,
                                    yMin: yMin - yPad,
                                    yMax: yMax + yPad,
                                    borderColor: 'rgba(61, 43, 31, 0.75)',
                                    borderWidth: 2,
                                    borderDash: [6, 4],
                                    label: {
                                        display: true,
                                        content: 'Rata X = ' + rataX.toFixed(2),
                                        position: 'start'
                                    }
                                },
                                garisHorizontal: {
                                    type: 'line',
                                    xMin: xMin - xPad,
                                    xMax: xMax + xPad,
                                    yMin: rataY,
                                    yMax: rataY,
                                    borderColor: 'rgba(107, 58, 42, 0.75)',
                                    borderWidth: 2,
                                    borderDash: [6, 4],
                                    label: {
                                        display: true,
                                        content: 'Rata Y = ' + rataY.toFixed(2),
                                        position: 'end'
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Nilai Kenyataan (X)',
                                font: { weight: 'bold' }
                            },
                            min: xMin - xPad,
                            max: xMax + xPad,
                            ticks: {
                                callback: function (value) {
                                    return Number(value).toFixed(2);
                                }
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Nilai Harapan (Y)',
                                font: { weight: 'bold' }
                            },
                            min: yMin - yPad,
                            max: yMax + yPad,
                            ticks: {
                                callback: function (value) {
                                    return Number(value).toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        })();
    </script>
</body>
</html>
