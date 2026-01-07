<?php
require 'db.php';

try {
    // Видаляємо старі записи, щоб не було помилок
    $pdo->exec("DELETE FROM users");

    $users = [
        ['admin', '1234567', 'admin', 'Головний Адміністратор'],
        ['markian', '99120399', 'user', 'Маркіян (Клієнт)']
    ];

    $sql = "INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    foreach ($users as $u) {
        // ОСЬ ТУТ МАГІЯ: Ми шифруємо пароль
        $hashed = password_hash($u[1], PASSWORD_DEFAULT);
        
        $stmt->execute([$u[0], $hashed, $u[2], $u[3]]);
        echo "✅ Користувач <b>{$u[0]}</b> доданий (пароль зашифровано)!<br>";
    }

} catch (PDOException $e) {
    die("Помилка: " . $e->getMessage());
}
?>