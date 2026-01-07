<?php
session_start();
require 'db.php';

// Перевірка, чи це адмін
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        // 1. Спочатку отримуємо інформацію про замовлення (для логу)
        // Бо після видалення ми вже не дізнаємось, що це було
        $stmtInfo = $pdo->prepare("SELECT device_model, final_price FROM orders WHERE id = ?");
        $stmtInfo->execute([$id]);
        $order = $stmtInfo->fetch();

        // 2. Виконуємо видалення
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            
            // --- ЛОГУВАННЯ ПОДІЇ ---
            if (function_exists('logEvent')) {
                $details = "Видалено замовлення #$id";
                
                // Якщо вдалося отримати деталі перед видаленням, додаємо їх
                if ($order) {
                    $details .= " (" . $order['device_model'] . "). Сума була: " . $order['final_price'] . " грн.";
                }

                logEvent($pdo, $_SESSION['user_id'], 'DELETE', $details);
            }
            // -----------------------
        }

    } catch (PDOException $e) {
        die("Помилка видалення: " . $e->getMessage());
    }
}

// Повертаємось назад на вкладку ремонтів
header("Location: index.php?tab=repair");
exit;