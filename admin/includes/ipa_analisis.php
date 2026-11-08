<?php

/**
 * Modul Analisis Importance Performance Analysis (IPA)
 *
 * Metode:
 * - Titik potong sumbu Y = rata-rata seluruh nilai Harapan (per pertanyaan)
 * - Titik potong sumbu X = rata-rata seluruh nilai Kenyataan (per pertanyaan)
 * - GAP = Kenyataan − Harapan
 */

/**
 * Menentukan kuadran IPA berdasarkan koordinat dan titik potong.
 *
 * Kuadran A: X < rataX, Y > rataY (Prioritas Utama)
 * Kuadran B: X > rataX, Y > rataY (Pertahankan Prestasi)
 * Kuadran C: X < rataX, Y < rataY (Prioritas Rendah)
 * Kuadran D: X > rataX, Y < rataY (Berlebihan)
 *
 * @param float $x Rata-rata Kenyataan
 * @param float $y Rata-rata Harapan
 * @param float $rataX Titik potong sumbu X
 * @param float $rataY Titik potong sumbu Y
 * @return array
 */
function tentukanKuadranIpa($x, $y, $rataX, $rataY)
{
    // Kuadran utama berdasarkan aturan strict (< dan >)
    if ($x < $rataX && $y > $rataY) {
        return array(
            'kode' => 'A',
            'nama' => 'Kuadran A',
            'keterangan' => 'Prioritas Utama',
        );
    }
    if ($x > $rataX && $y > $rataY) {
        return array(
            'kode' => 'B',
            'nama' => 'Kuadran B',
            'keterangan' => 'Pertahankan Prestasi',
        );
    }
    if ($x < $rataX && $y < $rataY) {
        return array(
            'kode' => 'C',
            'nama' => 'Kuadran C',
            'keterangan' => 'Prioritas Rendah',
        );
    }
    if ($x > $rataX && $y < $rataY) {
        return array(
            'kode' => 'D',
            'nama' => 'Kuadran D',
            'keterangan' => 'Berlebihan',
        );
    }

    // Titik pada garis batas — klasifikasi ke kuadran terdekat
    if ($y >= $rataY && $x <= $rataX) {
        return array(
            'kode' => 'A',
            'nama' => 'Kuadran A',
            'keterangan' => 'Prioritas Utama',
        );
    }
    if ($y >= $rataY) {
        return array(
            'kode' => 'B',
            'nama' => 'Kuadran B',
            'keterangan' => 'Pertahankan Prestasi',
        );
    }
    if ($x <= $rataX) {
        return array(
            'kode' => 'C',
            'nama' => 'Kuadran C',
            'keterangan' => 'Prioritas Rendah',
        );
    }

    return array(
        'kode' => 'D',
        'nama' => 'Kuadran D',
        'keterangan' => 'Berlebihan',
    );
}

/**
 * Menghitung seluruh analisis IPA dari hasil SERVQUAL per pertanyaan.
 *
 * @param array $hasilServqualPertanyaan
 * @return array
 */
function hitungAnalisisIpa(array $hasilServqualPertanyaan)
{
    $hasilKosong = array(
        'ada_data' => false,
        'titik_potong' => array(
            'rata_harapan' => null,
            'rata_kenyataan' => null,
        ),
        'baris' => array(),
        'ringkasan' => array(
            'A' => 0,
            'B' => 0,
            'C' => 0,
            'D' => 0,
        ),
    );

    // Ambil hanya pertanyaan yang memiliki data jawaban responden
    $pertanyaanValid = array();
    foreach ($hasilServqualPertanyaan as $baris) {
        if ($baris['jumlah_jawaban'] > 0
            && $baris['rata_harapan'] !== null
            && $baris['rata_kenyataan'] !== null) {
            $pertanyaanValid[] = $baris;
        }
    }

    if (count($pertanyaanValid) === 0) {
        return $hasilKosong;
    }

    // Kumpulkan nilai harapan & kenyataan untuk titik potong
    $daftarHarapan = array();
    $daftarKenyataan = array();
    foreach ($pertanyaanValid as $baris) {
        $daftarHarapan[] = (float) $baris['rata_harapan'];
        $daftarKenyataan[] = (float) $baris['rata_kenyataan'];
    }

    // Titik potong IPA
    $rataHarapan = hitungRataRataServqual($daftarHarapan);
    $rataKenyataan = hitungRataRataServqual($daftarKenyataan);

    if ($rataHarapan === null || $rataKenyataan === null) {
        return $hasilKosong;
    }

    // Tentukan koordinat dan kuadran setiap pertanyaan
    $barisIpa = array();
    $ringkasan = array('A' => 0, 'B' => 0, 'C' => 0, 'D' => 0);

    foreach ($pertanyaanValid as $baris) {
        $x = (float) $baris['rata_kenyataan'];
        $y = (float) $baris['rata_harapan'];
        $gap = (float) $baris['gap'];
        $kuadran = tentukanKuadranIpa($x, $y, $rataKenyataan, $rataHarapan);

        $ringkasan[$kuadran['kode']]++;

        $barisIpa[] = array(
            'kode' => $baris['kode'],
            'pertanyaan' => $baris['pertanyaan'],
            'dimensi' => $baris['dimensi'],
            'nilai_harapan' => $y,
            'nilai_kenyataan' => $x,
            'gap' => $gap,
            'kuadran_kode' => $kuadran['kode'],
            'kuadran' => $kuadran['nama'] . ' (' . $kuadran['keterangan'] . ')',
            'kuadran_keterangan' => $kuadran['keterangan'],
        );
    }

    return array(
        'ada_data' => true,
        'titik_potong' => array(
            'rata_harapan' => $rataHarapan,
            'rata_kenyataan' => $rataKenyataan,
        ),
        'baris' => $barisIpa,
        'ringkasan' => $ringkasan,
    );
}

/**
 * Menyiapkan data grafik IPA untuk Chart.js.
 *
 * @param array $hasilIpa
 * @return array
 */
function siapkanDataGrafikIpa(array $hasilIpa)
{
    if (!$hasilIpa['ada_data']) {
        return array(
            'points' => array(),
            'rata_x' => null,
            'rata_y' => null,
        );
    }

    $points = array();
    foreach ($hasilIpa['baris'] as $baris) {
        $points[] = array(
            'kode' => $baris['kode'],
            'x' => round($baris['nilai_kenyataan'], 3),
            'y' => round($baris['nilai_harapan'], 3),
            'kuadran' => $baris['kuadran_kode'],
        );
    }

    return array(
        'points' => $points,
        'rata_x' => round($hasilIpa['titik_potong']['rata_kenyataan'], 3),
        'rata_y' => round($hasilIpa['titik_potong']['rata_harapan'], 3),
    );
}
