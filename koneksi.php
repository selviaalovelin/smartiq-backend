<?php
// Koneksi ke database lokal XAMPP
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'db_pemerintahan';

$koneksi = mysqli_connect($host, $user, $password, $database);
if (!$koneksi) {
    die('Koneksi Database Gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($koneksi, 'utf8');
