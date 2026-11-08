<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/koneksi.php';

// Kalau sudah login, langsung ke dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: /batoecoffee/admin/index.php');
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $error = 'Username atau password tidak boleh kosong!';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id_admin, username, password, nama FROM tbl_admin WHERE username = ? LIMIT 1");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            $loginValid = false;

            if ($admin) {
                if (password_verify($password, $admin['password'])) {
                    $loginValid = true;
                } elseif (md5($password) === $admin['password']) {
                    $loginValid = true;
                }
            }

            if ($loginValid) {
                $_SESSION['admin_id']       = (int)$admin['id_admin'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_nama']     = $admin['nama'];

                header('Location: /batoecoffee/admin/index.php');
                exit;
            } else {
                $error = 'Username atau password salah!';
            }
        } else {
            $error = 'Terjadi kesalahan pada sistem. Coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container questionnaire-page">

    <section class="hero-panel">
        <h1>Login Admin</h1>
        <p>Masuk ke panel admin SERVQUAL Batoe Coffee and Space.</p>
    </section>

    <form method="post" class="card form-card">

        <?php if (!empty($error)) : ?>
            <p class="alert"><?php echo $error; ?></p>
        <?php endif; ?>

        <label for="username">Username</label>
        <input id="username" type="text" name="username" required>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>

        <button name="login" type="submit">Login</button>

    </form>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>