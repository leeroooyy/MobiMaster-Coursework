<?php
session_start();
require 'db.php';

// Перевірка адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 1. Спочатку отримуємо назву картинки, щоб видалити її з папки
    $stmt = $pdo->prepare("SELECT image FROM products_services WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product) {
        // Якщо є картинка, видаляємо файл
        if (!empty($product['image'])) {
            $file_path = 'uploads/' . $product['image'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // 2. Тепер видаляємо запис з бази
        $del = $pdo->prepare("DELETE FROM products_services WHERE id = ?");
        $del->execute([$id]);
    }
}

// Повертаємось туди, звідки прийшли (або на вітрину)
$redirect_tab = $_GET['return'] ?? 'store'; 
header("Location: index.php?tab=" . $redirect_tab);
exit;