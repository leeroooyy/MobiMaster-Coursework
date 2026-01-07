<?php
session_start();
require 'db.php';

// Перевірка на адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        // Оновлюємо статус товару: is_sold = 1 (Продано)
        $stmt = $pdo->prepare("UPDATE products_services SET is_sold = 1 WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            // --- ЛОГУВАННЯ ПОДІЇ ---
            // Записуємо в журнал, що товар продано
            if (function_exists('logEvent')) {
                logEvent($pdo, $_SESSION['user_id'], 'UPDATE (Магазин)', "Адмін позначив товар #$id як проданий");
            }
            // -----------------------
        }

    } catch (PDOException $e) {
        die("Помилка: " . $e->getMessage());
    }
}

// Повертаємось на вкладку Вітрини
header("Location: index.php?tab=store");
exit;