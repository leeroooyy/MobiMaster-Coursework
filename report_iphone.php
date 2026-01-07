<?php
session_start();
require 'db.php';

// Перевірка адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// --- СКЛАДНИЙ SQL ЗАПИТ (ВИБІРКА З УСІХ ТАБЛИЦЬ) ---
// Ми шукаємо всі записи про 'iPhone 11' і підтягуємо імена людей
$sql = "
    SELECT 
        o.id, 
        o.device_model, 
        o.problem_description, 
        o.status,
        o.final_price, 
        o.created_at,
        c.full_name AS client_name, 
        c.phone AS client_phone,
        m.full_name AS master_name
    FROM orders o
    LEFT JOIN users c ON o.client_id = c.id      -- Зв'язок з клієнтом
    LEFT JOIN users m ON o.employee_id = m.id    -- Зв'язок з майстром
    WHERE o.device_model LIKE '%iPhone 11%'      -- Фільтр по назві
    ORDER BY o.id DESC
";

try {
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Помилка запиту: " . $e->getMessage());
}
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>📊 Спеціальний звіт: iPhone 11</h3>
        <a href="index.php" class="btn btn-secondary">На головну</a>
    </div>

    <div class="alert alert-info">
        <strong>SQL запит, що виконується:</strong><br>
        <code>SELECT * FROM orders JOIN users (client) JOIN users (master) WHERE device LIKE '%iPhone 11%'</code>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>ID</th>
                        <th>Пристрій</th>
                        <th>Опис / Проблема</th>
                        <th>Клієнт (з табл. users)</th>
                        <th>Майстер (з табл. users)</th>
                        <th>Ціна</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($results) > 0): ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td>#<?= $row['id'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['device_model']) ?></td>
                                <td><?= htmlspecialchars($row['problem_description']) ?></td>
                                <td>
                                    <?= htmlspecialchars($row['client_name'] ?? 'Невідомий') ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['client_phone'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['master_name'] ?? 'Не призначено') ?>
                                </td>
                                <td class="fw-bold text-success"><?= number_format($row['final_price'], 0, ' ', ' ') ?> ₴</td>
                                <td><?= $row['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">Продажів або ремонтів iPhone 11 не знайдено.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>