<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/servqual_perhitungan.php';
require_once __DIR__ . '/includes/ipa_analisis.php';

/* =========================================================
   FILTER PERIODE
   ========================================================= */
$namaBulan = array(
    1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
    5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
    9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
);

$periodeDipilih = isset($_GET['periode']) ? trim($_GET['periode']) : 'semua';
$daftarPeriode = array();

$qPeriode = mysqli_query(
    $conn,
    "SELECT YEAR(tanggal) AS tahun,
            MONTH(tanggal) AS bulan,
            COUNT(*) AS jumlah_responden
     FROM tbl_responden
     WHERE tanggal IS NOT NULL
     GROUP BY YEAR(tanggal), MONTH(tanggal)
     ORDER BY YEAR(tanggal) DESC, MONTH(tanggal) DESC"
);

if ($qPeriode) {
    while ($p = mysqli_fetch_assoc($qPeriode)) {
        $tahun = (int)$p['tahun'];
        $bulan = (int)$p['bulan'];

        if ($tahun > 0 && $bulan >= 1 && $bulan <= 12) {
            $daftarPeriode[] = array(
                'value' => sprintf('%04d-%02d', $tahun, $bulan),
                'label' => $namaBulan[$bulan] . ' ' . $tahun,
                'jumlah' => (int)$p['jumlah_responden']
            );
        }
    }
}

$periodeValid = array_column($daftarPeriode, 'value');
if ($periodeDipilih !== 'semua' && !in_array($periodeDipilih, $periodeValid, true)) {
    $periodeDipilih = 'semua';
}

$labelPeriode = 'Semua Periode';
$jumlahResponden = 0;

if ($periodeDipilih === 'semua') {
    $qTotal = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_responden");
} else {
    list($tahunFilter, $bulanFilter) = array_map('intval', explode('-', $periodeDipilih));
    $labelPeriode = $namaBulan[$bulanFilter] . ' ' . $tahunFilter;

    $qTotal = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM tbl_responden
         WHERE YEAR(tanggal) = {$tahunFilter}
           AND MONTH(tanggal) = {$bulanFilter}"
    );
}

if ($qTotal && ($rowTotal = mysqli_fetch_assoc($qTotal))) {
    $jumlahResponden = (int)$rowTotal['total'];
}

/* =========================================================
   SERVQUAL + IPA SESUAI PERIODE
   ========================================================= */
$hasilServqual = hitungServqual($conn, $periodeDipilih);
$hasilPertanyaan = $hasilServqual['pertanyaan'];
$hasilDimensi = $hasilServqual['dimensi'];
$adaData = servqualMemilikiDataJawaban($hasilPertanyaan);

$hasilIpa = hitungAnalisisIpa($hasilPertanyaan);

$nilaiHarapan = array();
$nilaiKenyataan = array();
$nilaiGap = array();

foreach ($hasilPertanyaan as $baris) {
    if (
        isset($baris['jumlah_jawaban']) &&
        (int)$baris['jumlah_jawaban'] > 0 &&
        $baris['rata_harapan'] !== null &&
        $baris['rata_kenyataan'] !== null
    ) {
        $nilaiHarapan[] = (float)$baris['rata_harapan'];
        $nilaiKenyataan[] = (float)$baris['rata_kenyataan'];
        $nilaiGap[] = (float)$baris['gap'];
    }
}

$rataHarapan = count($nilaiHarapan) ? array_sum($nilaiHarapan) / count($nilaiHarapan) : null;
$rataKenyataan = count($nilaiKenyataan) ? array_sum($nilaiKenyataan) / count($nilaiKenyataan) : null;
$rataGap = count($nilaiGap) ? array_sum($nilaiGap) / count($nilaiGap) : null;

/* Dimensi dengan GAP paling negatif */
$dimensiGapTerbesar = null;
foreach ($hasilDimensi as $dimensi) {
    if ($dimensi['gap'] === null) {
        continue;
    }

    if ($dimensiGapTerbesar === null || (float)$dimensi['gap'] < (float)$dimensiGapTerbesar['gap']) {
        $dimensiGapTerbesar = $dimensi;
    }
}

/* Kuadran A = prioritas perbaikan menurut IPA */
$prioritasIpa = array();
if (!empty($hasilIpa['ada_data']) && isset($hasilIpa['baris'])) {
    foreach ($hasilIpa['baris'] as $barisIpa) {
        if (isset($barisIpa['kuadran_kode']) && $barisIpa['kuadran_kode'] === 'A') {
            $prioritasIpa[] = $barisIpa;
        }
    }
}

/* Data grafik per dimensi */
$chartLabels = array();
$chartHarapan = array();
$chartKenyataan = array();

foreach ($hasilDimensi as $dimensi) {
    if ($dimensi['rata_harapan'] === null || $dimensi['rata_kenyataan'] === null) {
        continue;
    }

    $chartLabels[] = $dimensi['dimensi'];
    $chartHarapan[] = round((float)$dimensi['rata_harapan'], 2);
    $chartKenyataan[] = round((float)$dimensi['rata_kenyataan'], 2);
}

$chartDimensi = array(
    'labels' => $chartLabels,
    'harapan' => $chartHarapan,
    'kenyataan' => $chartKenyataan
);

function namaDimensiManajemen($dimensi)
{
    $namaDimensi = array(
        'Tangible' => 'Tangible (Bukti Fisik)',
        'Reliability' => 'Reliability (Keandalan)',
        'Responsiveness' => 'Responsiveness (Daya Tanggap)',
        'Assurance' => 'Assurance (Jaminan)',
        'Empathy' => 'Empathy (Empati)'
    );

    return isset($namaDimensi[$dimensi])
        ? $namaDimensi[$dimensi]
        : $dimensi;
}
function rekomendasiManajemen($kode)
{
    $rekomendasi = array(
        'P01' => 'Meningkatkan pengecekan kebersihan dan kerapian fasilitas café secara berkala.',
        'P02' => 'Melakukan pemeriksaan kondisi fasilitas café secara berkala agar tetap nyaman digunakan pelanggan.',
        'P03' => 'Meningkatkan pengawasan terhadap standar kerapian penampilan karyawan.',
        'P04' => 'Memastikan kebersihan peralatan makan dan minum sebelum digunakan pelanggan.',
        'P05' => 'Mempertahankan ketepatan pelayanan sesuai dengan kebutuhan pelanggan.',
        'P06' => 'Meningkatkan konsistensi pelayanan agar pelanggan memperoleh pelayanan yang sesuai.',
        'P07' => 'Mengevaluasi proses penyajian agar keterlambatan pesanan dapat dikurangi.',
        'P08' => 'Meningkatkan ketelitian karyawan dalam memberikan pelayanan kepada pelanggan.',
        'P09' => 'Mengevaluasi alur pelayanan agar pelanggan dapat dilayani dengan lebih cepat dan responsif.',
        'P10' => 'Meningkatkan kesiapan karyawan dalam membantu pelanggan ketika membutuhkan pelayanan.',
        'P11' => 'Meningkatkan respons karyawan terhadap permintaan maupun kebutuhan pelanggan.',
        'P12' => 'Meningkatkan kesiapan karyawan dalam menangani keluhan pelanggan.',
        'P13' => 'Mempertahankan kemampuan karyawan dalam memberikan rasa aman dan nyaman kepada pelanggan.',
        'P14' => 'Meningkatkan pemahaman karyawan mengenai produk agar dapat memberikan informasi yang dibutuhkan pelanggan.',
        'P15' => 'Mempertahankan sikap sopan dan ramah dalam memberikan pelayanan kepada pelanggan.',
        'P16' => 'Meningkatkan kepercayaan pelanggan melalui pelayanan yang konsisten dan meyakinkan.',
        'P17' => 'Meningkatkan perhatian karyawan terhadap kebutuhan masing-masing pelanggan.',
        'P18' => 'Mempertahankan keramahan dan perhatian personal dalam melayani pelanggan.',
        'P19' => 'Meningkatkan pemahaman terhadap kebutuhan dan keinginan pelanggan.',
        'P20' => 'Mempertahankan komunikasi yang baik antara karyawan dan pelanggan.'
    );

    return isset($rekomendasi[$kode])
        ? $rekomendasi[$kode]
        : 'Melakukan evaluasi pada atribut pelayanan ini berdasarkan hasil penilaian pelanggan.';
}
function formatHasilAngka($nilai)
{
    return $nilai === null ? '—' : number_format((float)$nilai, 2);
}

function statusGapRingkas($gap)
{
    if ($gap === null) {
        return 'Belum ada data';
    }
    if ($gap < 0) {
        return 'Kenyataan masih di bawah harapan';
    }
    if ($gap > 0) {
        return 'Kenyataan melebihi harapan';
    }
    return 'Kenyataan sesuai harapan';
}

$jumlahPrioritas = count($prioritasIpa);
$kesimpulan = '';

if ($adaData) {
    if ($periodeDipilih === 'semua') {
    $awalKesimpulan = 'Berdasarkan seluruh data penilaian, terdapat ';
} else {
    $awalKesimpulan = 'Pada periode ' . $labelPeriode . ', terdapat ';
}

$kesimpulan = $awalKesimpulan
    . $jumlahResponden
    . ' penilaian pelanggan. Rata-rata kenyataan pelayanan sebesar '
    . formatHasilAngka($rataKenyataan)
    . ' dibandingkan rata-rata harapan sebesar '
    . formatHasilAngka($rataHarapan)
    . ', dengan GAP '
    . formatHasilAngka($rataGap) . '. ';

    if ($dimensiGapTerbesar !== null) {
        $kesimpulan .= namaDimensiManajemen($dimensiGapTerbesar['dimensi'])
            . ' memiliki kesenjangan terbesar dengan GAP '
            . formatHasilAngka($dimensiGapTerbesar['gap']) . '. ';
    }

    if ($jumlahPrioritas > 0) {

    $daftarAtributPrioritas = array();

    foreach ($prioritasIpa as $itemPrioritas) {
        $daftarAtributPrioritas[] = $itemPrioritas['pertanyaan'];
    }

    $kesimpulan .= 'Berdasarkan analisis IPA, terdapat '
        . $jumlahPrioritas
        . ' atribut yang menjadi prioritas perbaikan, yaitu '
        . implode(', ', $daftarAtributPrioritas)
        . '. Hasil tersebut dapat digunakan pihak manajemen sebagai dasar dalam menentukan tindakan perbaikan pelayanan kepada pelanggan.';

} else {

    $kesimpulan .= 'Berdasarkan analisis IPA, tidak terdapat atribut pada Kuadran A pada periode ini.';
}

}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil & Grafik</title>

    <link rel="stylesheet" href="assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .hasil-page {
            padding-top: 8px !important;
            padding-bottom: 20px !important;
        }

        .hasil-page .page-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 16px;
        }

        .hasil-page .page-top h3 {
            margin: 0 0 4px;
            font-size: 1.15rem;
            color: #3d2b1f;
        }

        .hasil-page .page-top p {
            margin: 0;
            color: #6b7280;
            font-size: .84rem;
        }

        .hasil-page .export-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .hasil-page .filter-card,
        .hasil-page .panel,
        .hasil-page .stat-card {
            background: #fff;
            border: 1px solid #ead9ca;
            box-shadow: 0 2px 8px rgba(61,43,31,.04);
        }

        .hasil-page .filter-card {
            border-radius: 10px;
            padding: 13px 15px;
            margin-bottom: 18px;
        }

        .hasil-page .block-title {
            margin: 18px 0 9px;
            font-size: .82rem;
            font-weight: 700;
            color: #6b3a2a;
            text-transform: uppercase;
            letter-spacing: .035em;
        }

        .hasil-page .stat-card {
            border-radius: 10px;
            padding: 13px 14px;
            height: 100%;
        }

        .hasil-page .stat-card .label {
            color: #6b7280;
            font-size: .74rem;
            line-height: 1.25;
            margin-bottom: 5px;
        }

        .hasil-page .stat-card .value {
            color: #3d2b1f;
            font-size: 1.28rem;
            font-weight: 700;
        }

        .hasil-page .stat-card .sub {
            color: #6b7280;
            font-size: .72rem;
            margin-top: 3px;
        }

        .hasil-page .panel {
            border-radius: 12px;
            padding: 15px 16px;
        }

        .hasil-page .panel-title {
            color: #6b3a2a;
            font-size: .92rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .hasil-page .chart-wrap {
            position: relative;
            height: 330px;
        }

        .hasil-page .chart-wrap canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .hasil-page .result-table {
            font-size: .82rem;
            margin-bottom: 0;
        }

        .hasil-page .result-table th {
            background: #f3e4d8;
            color: #4b2c1c;
            white-space: nowrap;
            padding: 9px 10px;
        }

        .hasil-page .result-table td {
            padding: 9px 10px;
            vertical-align: top;
        }

        .hasil-page .priority-table .attr-col {
            min-width: 340px;
            white-space: normal;
        }

        .hasil-page .interpretasi {
            background: #fffaf5;
            border: 1px solid #ead9ca;
            border-left: 4px solid #6b3a2a;
            border-radius: 10px;
            padding: 14px 15px;
            color: #374151;
            font-size: .85rem;
            line-height: 1.65;
        }

        .hasil-page .priority-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            padding: 0 8px;
            border-radius: 999px;
            background: #f3e4d8;
            color: #6b3a2a;
            font-weight: 700;
            font-size: .8rem;
        }

        @media (max-width: 768px) {
            .hasil-page .page-top {
                flex-direction: column;
            }

            .hasil-page .chart-wrap {
                height: 280px;
            }
        }

        @media print {
            .admin-sidebar,
            .admin-header,
            .export-actions,
            .filter-card {
                display: none !important;
            }

            .admin-main,
            .admin-content,
            .admin-shell {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .hasil-page .panel,
            .hasil-page .stat-card {
                box-shadow: none;
            }
        }
    </style>
</head>

<body class="admin-body">
<div class="admin-shell">

    <?php $activeMenu = 'hasil'; ?>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="admin-main">

        <?php $pageTitle = 'Hasil & Grafik'; ?>
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="admin-content hasil-page">
            <div id="hasilDashboard">

                <div class="page-top">
                    <div>
                        <h3>Hasil & Grafik</h3>
                        <p>Ringkasan hasil evaluasi kualitas pelayanan berdasarkan periode yang dipilih.</p>
                    </div>

                    <div class="export-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportExcel">Export Excel</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportPdf">Export PDF</button>
                        <button type="button" class="btn btn-sm btn-print" onclick="window.print()">Print</button>
                    </div>
                </div>

                <div class="filter-card">
                    <form method="get" action="hasil.php" class="d-flex flex-wrap align-items-end gap-3">
                        <div>
                            <label for="periode" class="form-label mb-1"><strong>Periode Hasil</strong></label>
                            <select name="periode" id="periode" class="form-select" style="min-width:220px;">
                                <option value="semua" <?php echo $periodeDipilih === 'semua' ? 'selected' : ''; ?>>
                                    Semua Periode
                                </option>

                                <?php foreach ($daftarPeriode as $periode) : ?>
                                    <option
                                        value="<?php echo htmlspecialchars($periode['value']); ?>"
                                        <?php echo $periodeDipilih === $periode['value'] ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($periode['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-dark">Tampilkan</button>

                        <div class="ms-md-auto text-muted small pb-2">
                            Periode:
                            <strong><?php echo htmlspecialchars($labelPeriode); ?></strong>
                            &middot;
                            <?php echo (int)$jumlahResponden; ?> responden
                        </div>
                    </form>
                </div>

                <?php if (!$adaData) : ?>

                    <div class="alert alert-light border">
                        Belum ada hasil evaluasi pelanggan pada periode ini.
                    </div>

                <?php else : ?>

                    <div class="block-title">Ringkasan Hasil</div>

                    <div class="row g-2">
                        <div class="col-6 col-xl-3">
                            <div class="stat-card">
                                <div class="label">Total Responden</div>
                                <div class="value"><?php echo (int)$jumlahResponden; ?></div>
                                <div class="sub"><?php echo htmlspecialchars($labelPeriode); ?></div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="stat-card">
                                <div class="label">Rata-rata Harapan</div>
                                <div class="value"><?php echo formatHasilAngka($rataHarapan); ?></div>
                                <div class="sub">Nilai yang diharapkan pelanggan</div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="stat-card">
                                <div class="label">Rata-rata Kenyataan</div>
                                <div class="value"><?php echo formatHasilAngka($rataKenyataan); ?></div>
                                <div class="sub">Nilai pelayanan yang diterima</div>
                            </div>
                        </div>

                        <div class="col-6 col-xl-3">
                            <div class="stat-card">
                                <div class="label">Rata-rata GAP</div>
                                <div class="value"><?php echo formatHasilAngka($rataGap); ?></div>
                                <div class="sub"><?php echo htmlspecialchars(statusGapRingkas($rataGap)); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="block-title">Hasil SERVQUAL</div>

                    <div class="panel mb-3">
                        <div class="panel-title">Perbandingan Harapan dan Kenyataan per Dimensi</div>
                        <div class="chart-wrap">
                            <canvas id="chartHarapanKenyataan"></canvas>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">

                        <div class="col-xl-8">
                            <div class="panel h-100">
                                <div class="panel-title">Ringkasan SERVQUAL per Dimensi</div>

                                <div class="table-responsive">
                                    <table class="table table-sm result-table" id="tabelServqual">
                                        <thead>
                                            <tr>
                                                <th>Dimensi</th>
                                                <th>Harapan</th>
                                                <th>Kenyataan</th>
                                                <th>GAP</th>
                                            
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($hasilDimensi as $row) : ?>
                                                <tr>
                                                <td>
    <?php echo htmlspecialchars(
        namaDimensiManajemen($row['dimensi'])
    ); ?>
</td>
                                                    <td><?php echo formatHasilAngka($row['rata_harapan']); ?></td>
                                                    <td><?php echo formatHasilAngka($row['rata_kenyataan']); ?></td>
                                                    <td><?php echo formatHasilAngka($row['gap']); ?></td>
                                                    <td>
    
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="panel h-100">
                                <div class="panel-title">Ringkasan SERVQUAL</div>

                                <div class="interpretasi">
                                    <p class="mb-2">
                                        Rata-rata kenyataan pelayanan adalah
                                        <strong><?php echo formatHasilAngka($rataKenyataan); ?></strong>,
                                        sedangkan rata-rata harapan pelanggan adalah
                                        <strong><?php echo formatHasilAngka($rataHarapan); ?></strong>.
                                    </p>

                                    <p class="mb-2">
                                        Selisih keduanya menghasilkan GAP
                                        <strong><?php echo formatHasilAngka($rataGap); ?></strong>.
                                        <?php if ($rataGap < 0) : ?>
                                            Artinya, pelayanan yang diterima pelanggan masih berada di bawah harapan.
                                        <?php elseif ($rataGap > 0) : ?>
                                            Artinya, pelayanan yang diterima pelanggan telah melebihi harapan.
                                        <?php else : ?>
                                            Artinya, pelayanan yang diterima telah sesuai dengan harapan pelanggan.
                                        <?php endif; ?>
                                    </p>

                                    <?php if ($dimensiGapTerbesar !== null) : ?>
                                        <p class="mb-0">
                                            Kesenjangan terbesar terdapat pada dimensi
                                            <strong>
    <?php echo htmlspecialchars(
        namaDimensiManajemen($dimensiGapTerbesar['dimensi'])
    ); ?>
</strong>
                                            dengan GAP
                                            <strong><?php echo formatHasilAngka($dimensiGapTerbesar['gap']); ?></strong>.
                                            Dimensi ini perlu mendapat perhatian karena menunjukkan selisih terbesar antara pelayanan yang diharapkan dan yang dirasakan pelanggan.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="block-title">Prioritas Perbaikan Berdasarkan IPA</div>

                    <div class="panel mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <div class="panel-title mb-1">Atribut Prioritas Utama — Kuadran A</div>
                                <div class="text-muted small">
                                    Atribut berikut memiliki harapan relatif tinggi, tetapi kinerjanya masih berada di bawah rata-rata periode yang dipilih.
                                </div>
                            </div>

                            <div class="stat-card py-2 px-3" style="height:auto;">
                                <div class="label">Jumlah Prioritas</div>
                                <div class="value"><?php echo (int)$jumlahPrioritas; ?> atribut</div>
                            </div>
                        </div>

                        <?php if ($jumlahPrioritas === 0) : ?>

                            <div class="alert alert-light border mb-0">
                                Tidak terdapat atribut pelayanan pada Kuadran A pada periode ini.
                            </div>

                        <?php else : ?>

                            <div class="table-responsive">
                                <table class="table table-sm result-table priority-table" id="tabelPrioritasIpa">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th class="attr-col">Atribut Pelayanan</th>
                                            <th>Dimensi</th>
                                            <th>Harapan</th>
                                            <th>Kenyataan</th>
                                            <th>GAP</th>
                                            <th>Rekomendasi untuk Manajemen</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($prioritasIpa as $index => $row) : ?>
                                            <tr>
                                                <td>
                                                    <span class="priority-number"><?php echo $index + 1; ?></span>
                                                </td>

                                                <td class="attr-col">
                                                    <?php echo htmlspecialchars($row['pertanyaan']); ?>
                                                </td>

                                                <td>
    <?php echo htmlspecialchars(
        namaDimensiManajemen($row['dimensi'])
    ); ?>
</td>

                                                <td>
                                                    <?php echo formatHasilAngka($row['nilai_harapan']); ?>
                                                </td>

                                                <td>
                                                    <?php echo formatHasilAngka($row['nilai_kenyataan']); ?>
                                                </td>

                                                <td>
    <?php echo formatHasilAngka($row['gap']); ?>
</td>

<td>
    <?php echo htmlspecialchars(
        rekomendasiManajemen($row['kode'])
    ); ?>
</td>

</tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?>
                    </div>

                    <div class="block-title">Kesimpulan</div>

                    <div class="panel">
                        <div class="panel-title">Kesimpulan Hasil Evaluasi</div>
                        <div class="interpretasi">
                            <p class="mb-0"><?php echo htmlspecialchars($kesimpulan); ?></p>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
(function () {
    var chartDimensi = <?php echo json_encode($chartDimensi, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    var chartElement = document.getElementById('chartHarapanKenyataan');

    if (
        chartElement &&
        chartDimensi.labels &&
        chartDimensi.labels.length > 0
    ) {
        new Chart(chartElement, {
            type: 'bar',
            data: {
                labels: chartDimensi.labels,
                datasets: [
                    {
                        label: 'Harapan',
                        data: chartDimensi.harapan,
                        backgroundColor: '#16a34a',
                        borderRadius: 4
                    },
                    {
                        label: 'Kenyataan',
                        data: chartDimensi.kenyataan,
                        backgroundColor: '#dc2626',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 5,
                        title: {
                            display: true,
                            text: 'Nilai Rata-rata'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Dimensi SERVQUAL'
                        }
                    }
                }
            }
        });
    }

    function tableToCsv(tableIds) {
        var rows = [];

        tableIds.forEach(function (id) {
            var table = document.getElementById(id);
            if (!table) return;

            Array.from(table.querySelectorAll('tr')).forEach(function (tr) {
                var cols = Array.from(tr.querySelectorAll('th,td')).map(function (cell) {
                    return '"' + cell.innerText.replace(/"/g, '""') + '"';
                });

                rows.push(cols.join(','));
            });

            rows.push('');
        });

        return rows.join('\n');
    }

    var btnExcel = document.getElementById('btnExportExcel');

    if (btnExcel) {
        btnExcel.addEventListener('click', function () {
            var csv = tableToCsv(['tabelServqual', 'tabelPrioritasIpa']);
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');

            link.href = URL.createObjectURL(blob);
            link.download = 'hasil-servqual-ipa-<?php echo preg_replace('/[^A-Za-z0-9_-]/', '-', $periodeDipilih); ?>.csv';
            link.click();

            URL.revokeObjectURL(link.href);
        });
    }

    var btnPdf = document.getElementById('btnExportPdf');

    if (btnPdf && window.html2pdf) {
        btnPdf.addEventListener('click', function () {
            html2pdf()
                .set({
                    margin: .3,
                    filename: 'hasil-servqual-ipa-<?php echo preg_replace('/[^A-Za-z0-9_-]/', '-', $periodeDipilih); ?>.pdf',
                    html2canvas: {
                        scale: 2,
                        useCORS: true
                    },
                    jsPDF: {
                        unit: 'in',
                        format: 'a3',
                        orientation: 'landscape'
                    }
                })
                .from(document.getElementById('hasilDashboard'))
                .save();
        });
    }
})();
</script>
</body>
</html>
