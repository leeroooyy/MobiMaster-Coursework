<?php
// db.php

// 1. Запускаємо сесію тільки якщо вона ще не активна
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'phone_repair_shop';
$user = 'root';
$pass = ''; // Ваш пароль, зазвичай пустий
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}

// 2. Функції логування з захистом від повторного оголошення
if (!function_exists('writeLog')) {
    function writeLog($pdo, $action, $details) {
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $action, $details]);
            } catch (Exception $e) {}
        }
    }
}

if (!function_exists('logEvent')) {
    function logEvent($pdo, $user_id, $action, $details) {
        try {
            $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $action, $details]);
        } catch (PDOException $e) {}
    }
}
?>