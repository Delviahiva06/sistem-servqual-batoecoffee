<?php
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "db_batoecoffee"
);

mysqli_set_charset($conn, "utf8mb4");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
?>