<?php
session_start();
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Email dan kata sandi harus diisi.';
    header('Location: login.php');
    exit;
}

// Periksa apakah tabel tb_admin memiliki kolom email.
$hasEmail = false;
$result = mysqli_query($koneksi, "SHOW COLUMNS FROM tb_admin LIKE 'email'");
if ($result && mysqli_num_rows($result) > 0) {
    $hasEmail = true;
}

if ($hasEmail) {
    $sql = "SELECT id_admin, nama_lengkap, username, password FROM tb_admin WHERE email = ? OR username = ? LIMIT 1";
} else {
    $sql = "SELECT id_admin, nama_lengkap, username, password FROM tb_admin WHERE username = ? LIMIT 1";
}

$stmt = mysqli_prepare($koneksi, $sql);
if (!$stmt) {
    $_SESSION['login_error'] = 'Terjadi kesalahan pada koneksi database.';
    header('Location: login.php');
    exit;
}

if ($hasEmail) {
    mysqli_stmt_bind_param($stmt, 'ss', $email, $email);
} else {
    mysqli_stmt_bind_param($stmt, 's', $email);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['login_error'] = 'Email atau kata sandi salah.';
    header('Location: login.php');
    exit;
}

$storedPassword = $user['password'];
$isValid = false;

// Cek password hash modern jika disimpan dengan password_hash, atau fallback ke MD5.
if (password_verify($password, $storedPassword)) {
    $isValid = true;
} elseif (md5($password) === $storedPassword) {
    $isValid = true;
}

if (!$isValid) {
    $_SESSION['login_error'] = 'Email atau kata sandi salah.';
    header('Location: login.php');
    exit;
}

// Login berhasil
$_SESSION['admin'] = $user['id_admin'];
$_SESSION['nama'] = $user['nama_lengkap'];

header('Location: dashboard.php');
exit;
