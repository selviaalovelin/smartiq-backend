<?php
session_start();
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Backend</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f4c81 0%, #194f91 50%, #0f4074 100%);
            color: #222;
        }
        .login-card {
            width: min(420px, 90vw);
            background: rgba(255, 255, 255, 0.98);
            border-radius: 22px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.18);
            overflow: hidden;
        }
        .login-card .card-header {
            padding: 36px 30px;
            background: #f2f4f7;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .login-card .card-header h2 {
            margin: 0;
            font-size: 1.55rem;
            letter-spacing: 0.5px;
            color: #0f3b5d;
        }
        .login-card .card-body {
            padding: 32px 30px 36px;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1d3045;
        }
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #c8d2dd;
            border-radius: 14px;
            background: #ffffff;
            font-size: 0.95rem;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #0f4c81;
            box-shadow: 0 0 0 5px rgba(15, 76, 129, 0.12);
        }
        .btn-submit {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 14px 18px;
            background: #7eb7d4;
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .btn-submit:hover {
            background: #6aa8cb;
            transform: translateY(-1px);
        }
        .message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #ffe5e5;
            color: #8a1c1c;
            border: 1px solid #f1c2c2;
        }
        .footer-text {
            margin-top: 18px;
            text-align: center;
            font-size: 0.85rem;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-header">
            <h2>Masuk ke Sistem</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="proses_login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Masukkan Email Anda" required>
                </div>
                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan Kata Sandi Anda" required>
                </div>
                <button type="submit" class="btn-submit">Masuk</button>
            </form>
            <div class="footer-text">Belum memiliki akun? <a href="#" style="color:#0f4c81; text-decoration:none;">Daftar</a></div>
        </div>
    </div>
</body>
</html>