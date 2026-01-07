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
    
    try {
        $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        die("Помилка видалення: " . $e->getMessage());
    }
}

// Повертаємось до списку повідомлень
header("Location: admin_feedback.php");
exit;