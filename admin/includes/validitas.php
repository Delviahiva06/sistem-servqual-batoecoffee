<?php

/**
 * Menghitung korelasi Pearson Product Moment antara dua deret data.
 *
 * Rumus:
 * r = (n·ΣXY − ΣX·ΣY) /
 * √[(n·ΣX² − (ΣX)²)(n·ΣY² − (ΣY)²)]
 *
 * @param array $x Skor item pertanyaan
 * @param array $y Skor total responden
 * @return float|null
 */
function pearsonCorrelation(array $x, array $y)
{
    $n = count($x);

    if ($n !== count($y) || $n < 2) {
        return null;
    }

    $sumX = 0.0;
    $sumY = 0.0;
    $sumXY = 0.0;
    $sumX2 = 0.0;
    $sumY2 = 0.0;

    for ($i = 0; $i < $n; $i++) {
        $xi = (float) $x[$i];
        $yi = (float) $y[$i];

        $sumX += $xi;
        $sumY += $yi;
        $sumXY += $xi * $yi;
        $sumX2 += $xi * $xi;
        $sumY2 += $yi * $yi;
    }

    $pembilang = ($n * $sumXY) - ($sumX * $sumY);

    $penyebutX = ($n * $sumX2) - ($sumX * $sumX);
    $penyebutY = ($n * $sumY2) - ($sumY * $sumY);

    if ($penyebutX <= 0 || $penyebutY <= 0) {
        return null;
    }

    $penyebut = sqrt($penyebutX * $penyebutY);

    if ($penyebut == 0.0) {
        return null;
    }

    return $pembilang / $penyebut;
}



function ambilMatriksSkorValiditas($conn, $jenisSkor)
{
    $matriks = array();

    if ($jenisSkor === 'harapan') {
        $kolom = 'skor_harapan';
    } elseif ($jenisSkor === 'kenyataan') {
        $kolom = 'skor_kenyataan';
    } else {
        return $matriks;
    }

    $query = mysqli_query(
        $conn,
        "SELECT id_responden, id_pertanyaan, {$kolom}
         FROM tbl_jawaban
         ORDER BY id_responden ASC, id_pertanyaan ASC"
    );

    if (!$query) {
        return $matriks;
    }

    while ($row = mysqli_fetch_assoc($query)) {
        $idResponden = (int) $row['id_responden'];
        $idPertanyaan = (int) $row['id_pertanyaan'];

        if (!isset($matriks[$idResponden])) {
            $matriks[$idResponden] = array();
        }

        $matriks[$idResponden][$idPertanyaan] =
            (int) $row[$kolom];
    }

    return $matriks;
}
{
    $matriks = array();

    $query = mysqli_query(
        $conn,
        "SELECT id_responden, id_pertanyaan, skor_kenyataan
         FROM tbl_jawaban
         ORDER BY id_responden ASC, id_pertanyaan ASC"
    );

    if (!$query) {
        return $matriks;
    }

    while ($row = mysqli_fetch_assoc($query)) {
        $idResponden = (int) $row['id_responden'];
        $idPertanyaan = (int) $row['id_pertanyaan'];

        if (!isset($matriks[$idResponden])) {
            $matriks[$idResponden] = array();
        }

        $matriks[$idResponden][$idPertanyaan]
            = (int) $row['skor_kenyataan'];
    }

    return $matriks;
}


/**
 * Menghitung total skor kenyataan setiap responden.
 */
function hitungTotalSkorPerResponden(array $matriks)
{
    $totalSkor = array();

    foreach ($matriks as $idResponden => $skorPertanyaan) {
        $totalSkor[$idResponden] = array_sum($skorPertanyaan);
    }

    return $totalSkor;
}


/**
 * Menentukan status validitas.
 *
 * Valid jika r hitung >= r tabel.
 */
function tentukanStatusValiditas($rHitung, $rTabel)
{
    if ($rHitung === null) {
        return 'Tidak Valid';
    }

    return $rHitung >= $rTabel
        ? 'Valid'
        : 'Tidak Valid';
}


/**
 * Mengambil daftar pertanyaan dari database.
 */
function ambilDaftarPertanyaan($conn)
{
    $pertanyaanList = array();

    $query = mysqli_query(
        $conn,
        "SELECT id_pertanyaan, kode, pertanyaan
         FROM tbl_pertanyaan
         ORDER BY id_pertanyaan ASC"
    );

    if (!$query) {
        return $pertanyaanList;
    }

    while ($row = mysqli_fetch_assoc($query)) {
        $pertanyaanList[] = $row;
    }

    return $pertanyaanList;
}



function hitungUjiValiditas(
    $conn,
    $jenisSkor = 'kenyataan',
    $rTabel = 0.1966
) {
    $pertanyaanList = ambilDaftarPertanyaan($conn);

    $matriks = ambilMatriksSkorValiditas(
        $conn,
        $jenisSkor
    );

    $totalSkor = hitungTotalSkorPerResponden($matriks);

    $hasil = array();

    foreach ($pertanyaanList as $pertanyaan) {
        $idPertanyaan = (int) $pertanyaan['id_pertanyaan'];

        $skorItem = array();
        $skorTotal = array();

        foreach ($matriks as $idResponden => $skorPertanyaan) {
            if (!isset($skorPertanyaan[$idPertanyaan])) {
                continue;
            }

            $skorItem[] = (float) $skorPertanyaan[$idPertanyaan];
            $skorTotal[] = (float) $totalSkor[$idResponden];
        }

        $rHitung = pearsonCorrelation(
            $skorItem,
            $skorTotal
        );

        $hasil[] = array(
            'kode' => $pertanyaan['kode'],
            'pertanyaan' => $pertanyaan['pertanyaan'],
            'r_hitung' => $rHitung,
            'r_tabel' => (float) $rTabel,
            'status' => tentukanStatusValiditas(
                $rHitung,
                $rTabel
            ),
            'n_responden' => count($skorItem),
        );
    }

    return $hasil;
}
{
    $pertanyaanList = ambilDaftarPertanyaan($conn);
    $matriks = ambilMatriksSkorKenyataan($conn);
    $totalSkor = hitungTotalSkorPerResponden($matriks);

    $hasil = array();

    foreach ($pertanyaanList as $pertanyaan) {
        $idPertanyaan = (int) $pertanyaan['id_pertanyaan'];

        $skorItem = array();
        $skorTotal = array();

        foreach ($matriks as $idResponden => $skorPertanyaan) {
            if (!isset($skorPertanyaan[$idPertanyaan])) {
                continue;
            }

            $skorItem[] =
                (float) $skorPertanyaan[$idPertanyaan];

            $skorTotal[] =
                (float) $totalSkor[$idResponden];
        }

        $rHitung = pearsonCorrelation(
            $skorItem,
            $skorTotal
        );

        $hasil[] = array(
            'kode' => $pertanyaan['kode'],
            'pertanyaan' => $pertanyaan['pertanyaan'],
            'r_hitung' => $rHitung,
            'r_tabel' => (float) $rTabel,
            'status' => tentukanStatusValiditas(
                $rHitung,
                $rTabel
            ),
            'n_responden' => count($skorItem),
        );
    }

    return $hasil;
}