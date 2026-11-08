<?php
require_once __DIR__ . '/config/koneksi.php';

$tabelPertanyaan = '';
$hasilTabel = mysqli_query($conn, "SHOW TABLES");
if ($hasilTabel) {
    while ($baris = mysqli_fetch_array($hasilTabel)) {
        $namaTabel = $baris[0];
        if (in_array($namaTabel, array('tbl_pertanyaan', 'pertanyaan', 'tbl_soal', 'soal'), true)) {
            $tabelPertanyaan = $namaTabel;
            break;
        }
    }
}

$daftarPertanyaan = array();
$error = '';
$pesanValidasi = '';
$labelSkala = array(
    1 => 'STM',
    2 => 'TM',
    3 => 'CM',
    4 => 'M',
    5 => 'SM',
);

if ($tabelPertanyaan !== '') {
    $query = "SELECT * FROM {$tabelPertanyaan}";
    $hasilPertanyaan = mysqli_query($conn, $query);

    if ($hasilPertanyaan) {
        while ($row = mysqli_fetch_assoc($hasilPertanyaan)) {
            $id = isset($row['id_pertanyaan']) ? $row['id_pertanyaan'] : (isset($row['id']) ? $row['id'] : null);
            $teks = isset($row['pertanyaan']) ? $row['pertanyaan'] : (isset($row['soal']) ? $row['soal'] : (isset($row['question']) ? $row['question'] : ''));
            if ($id !== null && $teks !== '') {
                $daftarPertanyaan[] = array(
                    'id' => $id,
                    'teks' => $teks,
                );
            }
        }
    } else {
        $error = 'Data pertanyaan tidak bisa dibaca dari database.';
    }
} else {
    $error = 'Tabel pertanyaan tidak ditemukan. Gunakan nama tabel: tbl_pertanyaan atau pertanyaan.';
}

$nama = '';
$usia = 0;
$jenis_kelamin = '';
$idResponden = isset($_SESSION['id_responden']) ? (int) $_SESSION['id_responden'] : 0;
$kolomJenisKelaminAda = false;
$sudahSubmit = isset($_SESSION['kuesioner_selesai']) && $_SESSION['kuesioner_selesai'] === true;

$cekKolomJk = mysqli_query($conn, "SHOW COLUMNS FROM tbl_responden LIKE 'jenis_kelamin'");
if ($cekKolomJk && mysqli_num_rows($cekKolomJk) > 0) {
    $kolomJenisKelaminAda = true;
}

if (isset($_POST['submit_kuesioner'])) {
    $nama = isset($_SESSION['nama_responden']) ? $_SESSION['nama_responden'] : '';
    $usia = isset($_SESSION['usia_responden']) ? (int) $_SESSION['usia_responden'] : 0;
    $jenis_kelamin = isset($_SESSION['jenis_kelamin_responden']) ? $_SESSION['jenis_kelamin_responden'] : '';

    $kenyataan = isset($_POST['kenyataan']) && is_array($_POST['kenyataan']) ? $_POST['kenyataan'] : array();
    $harapan = isset($_POST['harapan']) && is_array($_POST['harapan']) ? $_POST['harapan'] : array();

    $semuaTerisi = $idResponden > 0 && count($daftarPertanyaan) > 0;

    if ($sudahSubmit) {
        $pesanValidasi = 'Kuesioner sudah pernah dikirim pada sesi ini.';
        $semuaTerisi = false;
    }

    foreach ($daftarPertanyaan as $pertanyaan) {
        $idPertanyaan = (int) $pertanyaan['id'];

        if (!isset($kenyataan[$idPertanyaan], $harapan[$idPertanyaan])) {
            $semuaTerisi = false;
            break;
        }

        $nilaiKenyataan = (int) $kenyataan[$idPertanyaan];
        $nilaiHarapan = (int) $harapan[$idPertanyaan];

        if ($nilaiKenyataan < 1 || $nilaiKenyataan > 5 || $nilaiHarapan < 1 || $nilaiHarapan > 5) {
            $semuaTerisi = false;
            break;
        }
    }

    if (!$semuaTerisi) {
        $pesanValidasi = 'Harap isi semua pertanyaan!';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $idRespondenDb = mysqli_real_escape_string($conn, (string) $idResponden);
            mysqli_query($conn, "DELETE FROM tbl_jawaban WHERE id_responden = '{$idRespondenDb}'");

            foreach ($daftarPertanyaan as $pertanyaan) {
                $idPertanyaan = (int) $pertanyaan['id'];
                $nilaiKenyataan = (int) $kenyataan[$idPertanyaan];
                $nilaiHarapan = (int) $harapan[$idPertanyaan];

                $sqlInsertJawaban = "INSERT INTO tbl_jawaban (id_responden, id_pertanyaan, skor_kenyataan, skor_harapan)
                    VALUES ({$idResponden}, {$idPertanyaan}, {$nilaiKenyataan}, {$nilaiHarapan})";
                if (!mysqli_query($conn, $sqlInsertJawaban)) {
                    throw new Exception('Gagal menyimpan jawaban.');
                }
            }

            mysqli_commit($conn);
            $_SESSION['kuesioner_selesai'] = true;
            header('Location: terima_kasih.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $pesanValidasi = 'Terjadi kesalahan saat menyimpan jawaban.';
        }
    }
} else {
    $namaInput = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $usiaInput = isset($_POST['usia']) ? (int) $_POST['usia'] : 0;
    $jenisKelaminInput = isset($_POST['jenis_kelamin']) ? trim($_POST['jenis_kelamin']) : '';

    if ($namaInput === '' || $usiaInput <= 0 || $jenisKelaminInput === '') {
        if ($idResponden <= 0 || !isset($_SESSION['nama_responden'], $_SESSION['usia_responden'], $_SESSION['jenis_kelamin_responden'])) {
            header('Location: index.php');
            exit;
        }

        $nama = $_SESSION['nama_responden'];
        $usia = (int) $_SESSION['usia_responden'];
        $jenis_kelamin = $_SESSION['jenis_kelamin_responden'];

    // ✅ TAMBAHAN: kalau session sudah ada, skip INSERT
    } elseif ($idResponden > 0 && isset($_SESSION['nama_responden'])) {
        $nama = $_SESSION['nama_responden'];
        $usia = (int) $_SESSION['usia_responden'];
        $jenis_kelamin = $_SESSION['jenis_kelamin_responden'];

    } elseif (!$kolomJenisKelaminAda) {
        $error = "Kolom 'jenis_kelamin' belum ada di tbl_responden. Jalankan SQL perbaikan kolom terlebih dahulu.";
    } else {
        $namaEsc = mysqli_real_escape_string($conn, $namaInput);
        $jenisKelaminEsc = mysqli_real_escape_string($conn, $jenisKelaminInput);
        $tanggalSekarang = date('Y-m-d H:i:s');
        $tanggalEsc = mysqli_real_escape_string($conn, $tanggalSekarang);

        $sqlResponden = "INSERT INTO tbl_responden (nama, jenis_kelamin, usia, tanggal)
            VALUES ('{$namaEsc}', '{$jenisKelaminEsc}', {$usiaInput}, '{$tanggalEsc}')";

        if (!mysqli_query($conn, $sqlResponden)) {
            $error = 'Data responden gagal disimpan.';
        } else {
            $idResponden = (int) mysqli_insert_id($conn);
            $_SESSION['id_responden'] = $idResponden;
            $_SESSION['nama_responden'] = $namaInput;
            $_SESSION['usia_responden'] = $usiaInput;
            $_SESSION['jenis_kelamin_responden'] = $jenisKelaminInput;

            $nama = $namaInput;
            $usia = $usiaInput;
            $jenis_kelamin = $jenisKelaminInput;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuesioner - Batoe Coffee</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <main class="container questionnaire-page">
        <section class="hero-panel">
            <h1>Kuesioner Kepuasan Pelanggan</h1>
            <p>Batoe Coffee and Space - SERVQUAL</p>
        </section>

        <section class="respondent-chip">
            <span><strong>Nama:</strong> <?php echo htmlspecialchars($nama); ?></span>
            <span><strong>Usia:</strong> <?php echo htmlspecialchars((string) $usia); ?></span>
            <span><strong>Jenis Kelamin:</strong> <?php echo htmlspecialchars($jenis_kelamin); ?></span>
        </section>

        <?php if ($error !== '') : ?>
            <p class="alert"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (count($daftarPertanyaan) === 0) : ?>
            <p class="alert">Belum ada data soal di tabel pertanyaan.</p>
        <?php else : ?>
            <?php if ($pesanValidasi !== '') : ?>
                <p class="alert"><?php echo htmlspecialchars($pesanValidasi); ?></p>
            <?php endif; ?>
            <section class="progress-box">
                <div class="progress-head">
                    <strong>Progress Pengisian</strong>
                    <span id="progressText">Pertanyaan 0 dari <?php echo count($daftarPertanyaan); ?></span>
                </div>
                <div class="progress-track">
                    <div id="progressFill" class="progress-fill"></div>
                </div>
            </section>
            <section class="scale-box">
                <h3>Keterangan Skala Penilaian</h3>
                <div class="scale-list">
                    <span>1 = Sangat Tidak Memuaskan (STM)</span>
                    <span>2 = Tidak Memuaskan (TM)</span>
                    <span>3 = Cukup Memuaskan (CM)</span>
                    <span>4 = Memuaskan (M)</span>
                    <span>5 = Sangat Memuaskan (SM)</span>
                </div>
            </section>

            <form action="kuesioner.php" method="post" class="survey-form">

                <?php
                $dimensiAktif = '';
                $getDimensiByNomor = function ($nomor) {
                    if ($nomor >= 1 && $nomor <= 4) {
                        return 'TANGIBLE (BUKTI FISIK)';
                    }
                    if ($nomor >= 5 && $nomor <= 8) {
                        return 'RELIABILITY (KEANDALAN)';
                    }
                    if ($nomor >= 9 && $nomor <= 12) {
                        return 'RESPONSIVENESS (DAYA TANGGAP)';
                    }
                    if ($nomor >= 13 && $nomor <= 16) {
                        return 'ASSURANCE (JAMINAN)';
                    }
                    if ($nomor >= 17 && $nomor <= 20) {
                        return 'EMPATHY (EMPATI)';
                    }
                    return 'UMUM';
                };
                ?>
                <?php foreach ($daftarPertanyaan as $index => $pertanyaan) : ?>
                    <?php $nomorPertanyaan = $index + 1; ?>
                    <?php $dimensiSaatIni = $getDimensiByNomor($nomorPertanyaan); ?>
                    <?php if ($dimensiAktif !== $dimensiSaatIni) : ?>
                        <?php if ($dimensiAktif !== '') : ?>
                            </section>
                        <?php endif; ?>
                        <?php $dimensiAktif = $dimensiSaatIni; ?>
                        <section class="dimension-block">
                            <h2><?php echo htmlspecialchars($dimensiAktif); ?></h2>
                    <?php endif; ?>

                    <article class="question-item">
                        <div class="question-title">
                            <span class="number"><?php echo $index + 1; ?></span>
                            <p><?php echo htmlspecialchars($pertanyaan['teks']); ?></p>
                        </div>

                        <div class="rating-group">
                            <div class="rating-label reality">Kenyataan</div>
                            <div class="choices">
                                <?php for ($nilai = 1; $nilai <= 5; $nilai++) : ?>
                                    <label class="choice">
                                        <input type="radio" name="kenyataan[<?php echo (int) $pertanyaan['id']; ?>]" value="<?php echo $nilai; ?>" required>
                                        <span class="dot"></span>
                                        <small><?php echo $nilai; ?></small>
                                        <small><?php echo $labelSkala[$nilai]; ?></small>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="rating-group">
                            <div class="rating-label expectation">Harapan</div>
                            <div class="choices">
                                <?php for ($nilai = 1; $nilai <= 5; $nilai++) : ?>
                                    <label class="choice">
                                        <input type="radio" name="harapan[<?php echo (int) $pertanyaan['id']; ?>]" value="<?php echo $nilai; ?>" required>
                                        <span class="dot"></span>
                                        <small><?php echo $nilai; ?></small>
                                        <small><?php echo $labelSkala[$nilai]; ?></small>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </section>

                <button type="submit" name="submit_kuesioner" value="1" class="submit-btn">Kirim Kuesioner</button>
            </form>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        (function () {
            var total = <?php echo count($daftarPertanyaan); ?>;
            var textEl = document.getElementById('progressText');
            var fillEl = document.getElementById('progressFill');
            if (!total || !textEl || !fillEl) return;

            function updateProgress() {
                var done = 0;
                var kenyataanMap = {};
                var harapanMap = {};
                document.querySelectorAll('input[name^="kenyataan["]:checked').forEach(function (el) {
                    kenyataanMap[el.name] = true;
                });
                document.querySelectorAll('input[name^="harapan["]:checked').forEach(function (el) {
                    harapanMap[el.name.replace('harapan', 'kenyataan')] = true;
                });
                Object.keys(kenyataanMap).forEach(function (key) {
                    if (harapanMap[key]) done++;
                });

                var percent = Math.round((done / total) * 100);
                textEl.textContent = 'Pertanyaan ' + done + ' dari ' + total;
                fillEl.style.width = percent + '%';
            }

            document.addEventListener('change', function (e) {
                if (e.target.matches('input[type="radio"]')) {
                    updateProgress();
                }
            });
            updateProgress();
        })();
    </script>
</body>
</html>
