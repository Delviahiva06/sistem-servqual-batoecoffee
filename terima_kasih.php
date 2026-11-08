<?php
require_once __DIR__ . '/config/koneksi.php';

$namaResponden = isset($_SESSION['nama_responden']) ? $_SESSION['nama_responden'] : '';
if ($namaResponden === '') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terima Kasih - Batoe Coffee</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <main class="container questionnaire-page">
        <section class="hero-panel">
            <h1>Terima Kasih</h1>
            <p>Terima kasih sudah mengisi kuesioner!</p>
        </section>
        <section class="card form-card thanks-card">
            <p class="success-icon">✅</p>
            <p><strong>Nama Responden:</strong> <?php echo htmlspecialchars($namaResponden); ?></p>
            <a class="button-link" href="index.php">Kembali ke Beranda</a>
        </section>
    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
