<?php
require 'db.php';
echo "<h1>🔧 Майстер виправлення бази даних (Версія 2.0)</h1>";

try {
    // 1. ВИМИКАЄМО захист зв'язків (Foreign Keys), щоб видалити таблицю
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // 2. Видаляємо стару таблицю
    $pdo->exec("DROP TABLE IF EXISTS users");
    echo "✅ Стара таблиця 'users' успішно видалена (захист обійдено).<br>";

    // 3. ВМИКАЄМО захист назад (дуже важливо!)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 4. Створюємо нову ПРАВИЛЬНУ таблицю
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
        full_name VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✅ Нова таблиця 'users' створена.<br>";

    // 5. Додаємо користувачів
    $users = [
        ['admin', '1234567', 'admin', 'Головний Адміністратор'],
        ['markian', '99120399', 'user', 'Маркіян (Клієнт)']
    ];

    $insert = $pdo->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");

    foreach ($users as $u) {
        $hash = password_hash($u[1], PASSWORD_DEFAULT);
        $insert->execute([$u[0], $hash, $u[2], $u[3]]);
        echo "✅ Користувач <b>{$u[0]}</b> доданий.<br>";
    }

    echo "<hr><h3>🔍 Фінальна перевірка входу:</h3>";

    // 6. Тестуємо вхід
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user && password_verify('1234567', $user['password'])) {
        echo "<h2 style='color:green'>🎉 УСПІХ! Вхід працює!</h2>";
        echo "<h3>Тепер поверни нормальний код у <b>login.php</b> і заходь.</h3>";
    } else {
        echo "<h2 style='color:red'>❌ ПОМИЛКА!</h2>";
    }

} catch (PDOException $e) {
    die("Помилка: " . $e->getMessage());
}
?>