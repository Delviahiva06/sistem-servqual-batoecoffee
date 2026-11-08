<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/koneksi.php';
require_once 'auth_check.php';
require_once __DIR__ . '/includes/servqual_perhitungan.php';
require_once __DIR__ . '/includes/ipa_analisis.php';

$namaBulan = array(
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
);

$filterSemua = isset($_GET['periode']) && $_GET['periode'] === 'semua';
$filterBulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : 0;
$filterTahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;

if ($filterSemua) {
    $filterBulan = 0;
    $filterTahun = 0;
}

if ($filterBulan < 0 || $filterBulan > 12) {
    $filterBulan = 0;
}

$daftarTahun = array();
$qTahun = mysqli_query($conn, "SELECT DISTINCT YEAR(tanggal) AS tahun FROM tbl_responden WHERE tanggal IS NOT NULL ORDER BY tahun DESC");
if ($qTahun) {
    while ($rowTahun = mysqli_fetch_assoc($qTahun)) {
        $tahunItem = (int) $rowTahun['tahun'];
        if ($tahunItem > 0) {
            $daftarTahun[] = $tahunItem;
        }
    }
}

if ($filterTahun > 0 && !in_array($filterTahun, $daftarTahun, true)) {
    $filterTahun = 0;
}

$syaratPeriode = '1=1';
$labelPeriode = 'Semua Periode';
if ($filterTahun > 0 && $filterBulan > 0) {
    $syaratPeriode = 'YEAR(r.tanggal) = ' . $filterTahun . ' AND MONTH(r.tanggal) = ' . $filterBulan;
    $labelPeriode = $namaBulan[$filterBulan] . ' ' . $filterTahun;
} elseif ($filterTahun > 0) {
    $syaratPeriode = 'YEAR(r.tanggal) = ' . $filterTahun;
    $labelPeriode = 'Tahun ' . $filterTahun;
} elseif ($filterBulan > 0) {
    $syaratPeriode = 'MONTH(r.tanggal) = ' . $filterBulan;
    $labelPeriode = $namaBulan[$filterBulan] . ' (semua tahun)';
}

$totalResponden = 0;
$qTotalResponden = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM tbl_responden r
     WHERE {$syaratPeriode}"
);
if ($qTotalResponden) {
    $row = mysqli_fetch_assoc($qTotalResponden);
    $totalResponden = isset($row['total']) ? (int) $row['total'] : 0;
}

$adaDataPeriode = $totalResponden > 0;

$rataHarapan = null;
$rataKenyataan = null;
$rataGap = null;
$dimensiStat = array();
$hasilPerPertanyaan = array();
$prioritasIpa = array();
$dimensiPrioritas = '-';
$gapPrioritas = null;

if ($adaDataPeriode) {
    $qRingkasan = mysqli_query(
        $conn,
        "SELECT AVG(j.skor_harapan) AS rata_harapan,
                AVG(j.skor_kenyataan) AS rata_kenyataan
         FROM tbl_jawaban j
         INNER JOIN tbl_responden r ON r.id_responden = j.id_responden
         WHERE {$syaratPeriode}"
    );
    if ($qRingkasan) {
        $rowRingkasan = mysqli_fetch_assoc($qRingkasan);
        if ($rowRingkasan && $rowRingkasan['rata_harapan'] !== null && $rowRingkasan['rata_kenyataan'] !== null) {
            $rataHarapan = (float) $rowRingkasan['rata_harapan'];
            $rataKenyataan = (float) $rowRingkasan['rata_kenyataan'];
            $rataGap = $rataKenyataan - $rataHarapan;
        }
    }

    $qDimensi = mysqli_query(
        $conn,
        "SELECT d.id_dimensi,
                d.nama_dimensi,
                AVG(j.skor_harapan) AS rata_harapan,
                AVG(j.skor_kenyataan) AS rata_kenyataan
         FROM tbl_jawaban j
         INNER JOIN tbl_responden r ON r.id_responden = j.id_responden
         INNER JOIN tbl_pertanyaan p ON p.id_pertanyaan = j.id_pertanyaan
         INNER JOIN tbl_dimensi d ON d.id_dimensi = p.id_dimensi
         WHERE {$syaratPeriode}
         GROUP BY d.id_dimensi, d.nama_dimensi
         ORDER BY d.id_dimensi ASC"
    );
    if ($qDimensi) {
        while ($rowDim = mysqli_fetch_assoc($qDimensi)) {
            $h = (float) $rowDim['rata_harapan'];
            $k = (float) $rowDim['rata_kenyataan'];
            $gap = $k - $h;
            $dimensiStat[] = array(
                'nama' => $rowDim['nama_dimensi'],
                'harapan' => $h,
                'kenyataan' => $k,
                'gap' => $gap,
            );
            if ($gapPrioritas === null || $gap < $gapPrioritas) {
                $gapPrioritas = $gap;
                $dimensiPrioritas = $rowDim['nama_dimensi'];
            }
        }
    }

    $qPertanyaan = mysqli_query(
        $conn,
        "SELECT p.id_pertanyaan,
                p.kode,
                p.pertanyaan,
                d.nama_dimensi,
                AVG(j.skor_harapan) AS rata_harapan,
                AVG(j.skor_kenyataan) AS rata_kenyataan,
                COUNT(j.id_jawaban) AS jumlah_jawaban
         FROM tbl_jawaban j
         INNER JOIN tbl_responden r ON r.id_responden = j.id_responden
         INNER JOIN tbl_pertanyaan p ON p.id_pertanyaan = j.id_pertanyaan
         INNER JOIN tbl_dimensi d ON d.id_dimensi = p.id_dimensi
         WHERE {$syaratPeriode}
         GROUP BY p.id_pertanyaan, p.kode, p.pertanyaan, d.nama_dimensi
         ORDER BY p.id_pertanyaan ASC"
    );
    if ($qPertanyaan) {
        while ($rowP = mysqli_fetch_assoc($qPertanyaan)) {
            $h = (float) $rowP['rata_harapan'];
            $k = (float) $rowP['rata_kenyataan'];
            $hasilPerPertanyaan[] = array(
                'id_pertanyaan' => (int) $rowP['id_pertanyaan'],
                'kode' => $rowP['kode'],
                'pertanyaan' => $rowP['pertanyaan'],
                'dimensi' => $rowP['nama_dimensi'],
                'rata_harapan' => $h,
                'rata_kenyataan' => $k,
                'gap' => $k - $h,
                'jumlah_jawaban' => (int) $rowP['jumlah_jawaban'],
            );
        }
    }

    $hasilIpa = hitungAnalisisIpa($hasilPerPertanyaan);
    if ($hasilIpa['ada_data']) {
        foreach ($hasilIpa['baris'] as $barisIpa) {
            if ($barisIpa['kuadran_kode'] === 'A') {
                $prioritasIpa[] = $barisIpa;
            }
        }
    }
}
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
function keteranganDimensi($gap, $gapTerbesar, $gapTerkecil)
{
    if ($gap === null) {
        return '-';
    }

    if (abs($gap - $gapTerbesar) < 0.0005) {
        return 'Kesenjangan terbesar, perlu perhatian utama';
    }

    if (abs($gap - $gapTerkecil) < 0.0005) {
        return 'Kesenjangan paling kecil';
    }

    return 'Masih terdapat kesenjangan pelayanan';
}
function interpretasiGapDashboard($gap)
{
    if ($gap === null) {
        return array(
            'status' => '-',
            'penjelasan' => 'Belum ada penilaian pelanggan pada periode ini.',
        );
    }
    if (abs($gap) < 0.0005) {
        return array(
            'status' => 'Pelayanan Sesuai Harapan',
            'penjelasan' => 'Pelayanan yang dirasakan pelanggan sudah sesuai dengan harapan mereka.',
        );
    }
    if ($gap < 0) {
        return array(
            'status' => 'Pelayanan Belum Memenuhi Harapan',
            'penjelasan' => 'Penilaian pelayanan yang dirasakan pelanggan masih lebih rendah dibandingkan harapan pelanggan.',
        );
    }
    return array(
        'status' => 'Pelayanan Melebihi Harapan',
        'penjelasan' => 'Pelayanan yang dirasakan pelanggan sudah lebih tinggi dibandingkan harapan mereka.',
    );
}

$gapDimensiTerbesar = null;
$gapDimensiTerkecil = null;

if (!empty($dimensiStat)) {
    $daftarGapDimensi = array_column($dimensiStat, 'gap');

    // Nilai paling negatif = kesenjangan terbesar.
    $gapDimensiTerbesar = min($daftarGapDimensi);

    // Nilai paling mendekati nol = kesenjangan paling kecil.
    $gapDimensiTerkecil = max($daftarGapDimensi);
}
$interpretasi = interpretasiGapDashboard($rataGap);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .dash-filter {
            background: #ffffff;
            border-radius: 10px;
            padding: 14px 16px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            margin-bottom: 16px;
        }
        .dash-filter form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }
        .dash-filter label {
            display: block;
            font-size: 0.78rem;
            color: #6b3a2a;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .dash-filter select {
            min-width: 150px;
            padding: 7px 10px;
            border: 1px solid #ead9ca;
            border-radius: 6px;
            font-size: 0.88rem;
        }
        .dash-filter .btn-filter {
            background: #6b3a2a;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 14px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .dash-filter .btn-all {
            background: #fff;
            color: #6b3a2a;
            border: 1px solid #6b3a2a;
            border-radius: 6px;
            padding: 8px 14px;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .dash-periode {
            margin-top: 8px;
            font-size: 0.82rem;
            color: #6b7280;
        }
        .dash-interpret {
            margin-top: 6px;
            font-size: 0.82rem;
            color: #4b2c1c;
            line-height: 1.45;
        }
        .dash-empty {
            background: #fffaf5;
            border: 1px solid #ead9ca;
            border-radius: 10px;
            padding: 16px;
            color: #6b3a2a;
        }
        .cards-row-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-bottom: 16px;
        }
        @media (max-width: 900px) {
            .cards-row,
            .cards-row-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-shell">
    <?php $activeMenu = 'dashboard'; ?>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <?php $pageTitle = 'Dashboard Admin'; ?>
            <?php include __DIR__ . '/includes/header.php'; ?>
            <div class="admin-content">
                <section class="dash-filter">
                    <form method="get" action="index.php">
                        <div>
                            <label for="bulan">Bulan</label>
                            <select name="bulan" id="bulan">
                                <option value="0">Semua Bulan</option>
                                <?php foreach ($namaBulan as $nomor => $nama) : ?>
                                    <option value="<?php echo $nomor; ?>" <?php echo $filterBulan === $nomor ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nama); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="tahun">Tahun</label>
                            <select name="tahun" id="tahun">
                                <option value="0">Semua Tahun</option>
                                <?php foreach ($daftarTahun as $tahunOpsi) : ?>
                                    <option value="<?php echo $tahunOpsi; ?>" <?php echo $filterTahun === $tahunOpsi ? 'selected' : ''; ?>>
                                        <?php echo $tahunOpsi; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-filter">Terapkan Periode</button>
                        <a href="index.php?periode=semua" class="btn-all">Semua Periode</a>
                    </form>
                    <div class="dash-periode">Periode ditampilkan: <strong><?php echo htmlspecialchars($labelPeriode); ?></strong></div>
                </section>

                <?php if (!$adaDataPeriode) : ?>
                    <section class="dash-empty">
                        Belum ada penilaian pelanggan pada periode ini.
                    </section>
                <?php else : ?>
                    <div class="cards-row">
                        <article class="card">
                            <h3>Total Responden</h3>
                            <div class="card-value"><?php echo $totalResponden; ?></div>
                            <div class="card-sub">Jumlah pelanggan yang mengisi kuesioner pada periode ini</div>
                        </article>
                        <article class="card">
                            <h3>Rata-rata Harapan</h3>
                            <div class="card-value"><?php echo number_format($rataHarapan, 3); ?></div>
                            <div class="card-sub">Harapan pelanggan terhadap pelayanan</div>
                        </article>
                        <article class="card">
                            <h3>Rata-rata Kenyataan</h3>
                            <div class="card-value"><?php echo number_format($rataKenyataan, 3); ?></div>
                            <div class="card-sub">Pelayanan yang dirasakan pelanggan</div>
                        </article>
                        <article class="card">
                            <h3>Rata-rata GAP SERVQUAL</h3>
                            <div class="card-value"><?php echo number_format($rataGap, 3); ?></div>
                            <div class="card-sub">Selisih kenyataan dan harapan pelanggan</div>
                    </div>

                    <section class="card" style="margin-bottom:16px;">
                        <h3 class="section-heading">Interpretasi Hasil Periode Ini</h3>
                        <p class="dash-interpret">
    <strong>Hasil Evaluasi Pelayanan.</strong>
    Rata-rata pelayanan yang dirasakan pelanggan sebesar
    <strong><?php echo number_format($rataKenyataan, 3); ?></strong>,
    sedangkan rata-rata harapan pelanggan sebesar
    <strong><?php echo number_format($rataHarapan, 3); ?></strong>,
    sehingga diperoleh GAP sebesar
    <strong><?php echo number_format($rataGap, 3); ?></strong>.
    Dimensi dengan kesenjangan terbesar adalah
    <strong>
        <?php echo htmlspecialchars(
            namaDimensiManajemen($dimensiPrioritas)
        ); ?>
    </strong>
    dengan GAP
    <strong><?php echo number_format((float) $gapPrioritas, 3); ?></strong>,
    sehingga dimensi tersebut perlu mendapat perhatian dalam perbaikan pelayanan.
</p>
                    </section>

                    <div class="cards-row-2">
                        <section class="card">
                            <h3 class="section-heading">Hasil per Dimensi</h3>
                            <div class="table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Dimensi</th>
                                            <th>Harapan</th>
                                            <th>Kenyataan</th>
                                            <th>GAP</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dimensiStat as $dim) : ?>
                                            <tr>
    <td>
        <?php echo htmlspecialchars(
            namaDimensiManajemen($dim['nama'])
        ); ?>
    </td>

    <td><?php echo number_format($dim['harapan'], 3); ?></td>

    <td><?php echo number_format($dim['kenyataan'], 3); ?></td>

    <td><?php echo number_format($dim['gap'], 3); ?></td>

    <td>
        <?php echo htmlspecialchars(
            keteranganDimensi(
                $dim['gap'],
                $gapDimensiTerbesar,
                $gapDimensiTerkecil
            )
        ); ?>
    </td>
</tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="card">
                        <h3 class="section-heading">Prioritas Perbaikan Pelayanan</h3>
                            <?php if (count($prioritasIpa) === 0) : ?>
                                <p class="dash-interpret">Tidak ada atribut pada prioritas utama perbaikan (Kuadran A) untuk periode ini.</p>
                            <?php else : ?>
                                <div class="table-wrapper">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Atribut Pelayanan</th>
                                                <th>Dimensi</th>
                                                <th>GAP</th>
                                                <th>Rekomendasi untuk Manajemen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($prioritasIpa as $item) : ?>
                                            <tr>
    <td><?php echo htmlspecialchars($item['pertanyaan']); ?></td>

    <td>
        <?php echo htmlspecialchars(
            namaDimensiManajemen($item['dimensi'])
        ); ?>
    </td>

    <td><?php echo number_format($item['gap'], 3); ?></td>

    <td>
        <?php echo htmlspecialchars(
            rekomendasiManajemen($item['kode'])
        ); ?>
    </td>
</tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="dash-interpret">
    Atribut di atas merupakan prioritas perbaikan berdasarkan hasil analisis IPA.
    Rekomendasi yang diberikan dapat digunakan sebagai bahan pertimbangan pihak manajemen
    dalam menentukan tindakan perbaikan pelayanan kepada pelanggan.
</p>
                            <?php endif; ?>
                        </section>
                    </div>
                <?php endif; ?>

                <?php include __DIR__ . '/includes/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html>
