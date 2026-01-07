<?php
session_start(); // Важливо: запускаємо сесію на самому початку
require 'db.php';

// Якщо користувач вже увійшов — перекидаємо на головну
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Успішний вхід
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['username'];

        // --- ЛОГУВАННЯ ПОДІЇ ---
        // Записуємо в журнал, що хтось увійшов
        if (function_exists('logEvent')) {
            logEvent($pdo, $user['id'], 'LOGIN', 'Користувач увійшов у систему');
        }
        // -----------------------

        header("Location: index.php");
        exit;
    } else {
        $error = "Невірний логін або пароль";
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Вхід в систему</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6; /* Світло-сірий фон */
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        .login-icon { font-size: 40px; margin-bottom: 20px; }
        .form-control {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 15px;
            font-size: 15px;
        }
        .form-control:focus {
            border-color: #6366f1; /* Фіолетова рамка */
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        /* КНОПКА ТЕПЕР ФІОЛЕТОВА */
        .btn-login {
            background-color: #6366f1; /* ВАШ ФІРМОВИЙ КОЛІР */
            border: none;
            color: white;
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
            transition: all 0.2s;
        }
        .btn-login:hover { 
            background-color: #4f46e5; /* Трохи темніший при наведенні */
            transform: translateY(-1px);
        }
        
        .error-msg { color: #ef4444; font-size: 14px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-icon">🔐 <span style="font-weight: 800; color: #111;">Вхід</span></div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div style="text-align: left; margin-bottom: 5px; color: #6b7280; font-size: 13px; margin-left: 5px;">Логін</div>
            <input type="text" name="username" class="form-control" placeholder="" required>
            
            <div style="text-align: left; margin-bottom: 5px; color: #6b7280; font-size: 13px; margin-left: 5px;">Пароль</div>
            <input type="password" name="password" class="form-control" placeholder="" required>
            
            <button type="submit" class="btn-login">Увійти</button>
        </form>
    </div>

</body>
</html>