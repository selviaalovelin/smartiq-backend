<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #eef4fb;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dashboard-box {
            background: white;
            padding: 32px;
            border-radius: 22px;
            box-shadow: 0 18px 45px rgba(11, 41, 78, 0.12);
            width: min(700px, 90vw);
            text-align: center;
        }
        .dashboard-box h1 {
            margin: 0 0 14px;
            color: #11345d;
        }
        .dashboard-box p {
            margin: 0 0 24px;
            color: #4b5c71;
            line-height: 1.6;
        }
        .logout-btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 999px;
            background: #0f4c81;
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        .logout-btn:hover {
            background: #0a3d6a;
        }
    </style>
</head>
<body>
    <div class="dashboard-box">
        <h1>Selamat datang, SELVIAAAAAA <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h1>
        <p>Anda berhasil masuk ke dashboard. Gunakan tombol di bawah ini untuk keluar dari sesi.</p>
        <a class="logout-btn" href="logout.php">Logout</a>
    </div>
</body>
</html>
