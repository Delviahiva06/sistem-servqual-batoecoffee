<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<?php
require_once __DIR__ . '/config/koneksi.php';

// Hanya reset session jika kuesioner sudah selesai dikirim
if (isset($_SESSION['kuesioner_selesai']) && $_SESSION['kuesioner_selesai'] === true) {
    unset($_SESSION['id_responden'], $_SESSION['nama_responden'], $_SESSION['usia_responden'], $_SESSION['jenis_kelamin_responden'], $_SESSION['kuesioner_selesai']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identitas Responden - Batoe Coffee</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <main class="container questionnaire-page">
        <section class="hero-panel">
            <h1>Kuesioner Kepuasan Pelanggan Batoe Coffee and Space</h1>
            <p>Isi identitas responden sebelum memulai pengisian kuesioner SERVQUAL.</p>
        </section>
        <form action="kuesioner.php" method="post" class="card form-card">
            <label for="nama">Nama</label>
            <input id="nama" name="nama" type="text" required>

            <label for="usia">Usia</label>
            <input id="usia" name="usia" type="number" min="1" required>

            <label for="jenis_kelamin">Jenis Kelamin</label>
            <select id="jenis_kelamin" name="jenis_kelamin" required>
                <option value="">-- Pilih --</option>
                <option value="Laki-laki">Laki-laki</option>
                <option value="Perempuan">Perempuan</option>
            </select>

            <button type="submit">MULAI KUESIONER</button>
        </form>
    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
</body>
</html>
