<?php
// Вмикаємо показ помилок
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// Отримуємо параметри для детального перегляду
$view_type = $_GET['view'] ?? 'general'; // 'general', 'master', 'client'
$id = $_GET['id'] ?? null;
$name_key = $_GET['name'] ?? null;

?>

<div class="container py-5">

    <?php if ($view_type == 'master' && $id): 
        // Отримуємо дані майстра
        $master = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $master->execute([$id]);
        $emp = $master->fetch();

        // Отримуємо всі виконані замовлення цього майстра
        // Приєднуємо дані клієнта (пріоритет: contact_name > clients > users)
        $sql = "SELECT o.*, 
                       COALESCE(o.contact_name, c.full_name, u.full_name) AS client_name,
                       COALESCE(o.contact_phone, c.phone) AS client_phone
                FROM orders o
                LEFT JOIN clients c ON o.client_id = c.id
                LEFT JOIN users u ON o.client_id = u.id
                WHERE o.employee_id = ? AND o.status = 'done'
                ORDER BY o.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $orders = $stmt->fetchAll();

        // Рахуємо статистику
        $total_income = 0;
        foreach ($orders as $o) $total_income += $o['final_price'];
        $salary = $total_income * 0.40; // Наприклад, майстер отримує 40%
    ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-user-gear me-2"></i> Майстер: <?= htmlspecialchars($emp['full_name']) ?></h2>
                <p class="text-muted"><?= htmlspecialchars($emp['position']) ?></p>
            </div>
            <a href="analytics.php" class="btn btn-dark shadow-sm">← Назад до списку</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 text-center bg-light">
                    <h6 class="text-muted text-uppercase small fw-bold">Виконано робіт</h6>
                    <h2 class="fw-bold mb-0"><?= count($orders) ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 text-center bg-light">
                    <h6 class="text-muted text-uppercase small fw-bold">Приніс в касу</h6>
                    <h2 class="fw-bold mb-0 text-success"><?= number_format($total_income, 0, ' ', ' ') ?> ₴</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 text-center text-white" style="background: #6366f1;">
                    <h6 class="text-uppercase small fw-bold opacity-75">Зарплата (40%)</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($salary, 0, ' ', ' ') ?> ₴</h2>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3">Історія виконаних замовлень</h5>
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Пристрій / Робота</th>
                            <th>Клієнт</th>
                            <th>Дата</th>
                            <th class="text-end pe-3">Сума</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-secondary">#<?= $row['id'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['device_model']) ?></div>
                                    <div class="small text-muted"><?= mb_strimwidth(htmlspecialchars($row['problem_description']), 0, 50, '...') ?></div>
                                </td>
                                <td>
                                    <i class="fa-regular fa-user text-muted small"></i> <?= htmlspecialchars($row['client_name']) ?>
                                    <?php if($row['client_phone']): ?><div class="small text-muted"><?= $row['client_phone'] ?></div><?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y', strtotime($row['created_at'])) ?></td>
                                <td class="text-end pe-3 fw-bold text-success"><?= number_format($row['final_price'], 0, ' ', ' ') ?> ₴</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Немає виконаних замовлень</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


    <?php elseif ($view_type == 'client' && $name_key): 
        // Шукаємо всі замовлення цього клієнта (по імені contact_name або через таблиці)
        $sql = "SELECT * FROM orders WHERE 
                contact_name = ? 
                OR client_id IN (SELECT id FROM clients WHERE full_name = ?)
                OR client_id IN (SELECT id FROM users WHERE full_name = ?)
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name_key, $name_key, $name_key]);
        $client_orders = $stmt->fetchAll();

        // Статистика клієнта
        $total_spent = 0;
        $count = count($client_orders);
        foreach ($client_orders as $o) $total_spent += $o['final_price'];
        $avg_check = ($count > 0) ? $total_spent / $count : 0;
    ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-success"><i class="fa-solid fa-user-tag me-2"></i> Клієнт: <?= htmlspecialchars($name_key) ?></h2>
                <p class="text-muted">Історія звернень</p>
            </div>
            <a href="analytics.php" class="btn btn-dark shadow-sm">← Назад до списку</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 text-center bg-light">
                    <h6 class="text-muted text-uppercase small fw-bold">Всього замовлень</h6>
                    <h2 class="fw-bold mb-0"><?= $count ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 text-center bg-light">
                    <h6 class="text-muted text-uppercase small fw-bold">Всього витратив</h6>
                    <h2 class="fw-bold mb-0 text-success"><?= number_format($total_spent, 0, ' ', ' ') ?> ₴</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 text-center" style="background: #10b981; color: white;">
                    <h6 class="text-uppercase small fw-bold opacity-75">Середній чек</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($avg_check, 0, ' ', ' ') ?> ₴</h2>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3">Історія покупок та ремонтів</h5>
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Пристрій</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th class="text-end pe-3">Сума</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($client_orders as $row): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-secondary">#<?= $row['id'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['device_model']) ?></div>
                                    <div class="small text-muted"><?= mb_strimwidth(htmlspecialchars($row['problem_description']), 0, 50, '...') ?></div>
                                </td>
                                <td>
                                    <?php if($row['status']=='done'): ?><span class="badge bg-success">Готово</span>
                                    <?php else: ?><span class="badge bg-warning text-dark">В процесі</span><?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y', strtotime($row['created_at'])) ?></td>
                                <td class="text-end pe-3 fw-bold"><?= number_format($row['final_price'], 0, ' ', ' ') ?> ₴</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>


    <?php else: ?>
        
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-0" style="color: #6366f1;"><i class="fa-solid fa-chart-pie me-2"></i> Аналітика сервісу</h2>
                <p class="text-muted mb-0">Статистика по співробітниках та клієнтах</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fa-solid fa-arrow-left me-2"></i> На головну
            </a>
        </div>

        <div class="row">
            
            <div class="col-lg-6 mb-4">
                <div class="card shadow-lg border-0 h-100" style="border-radius: 15px; overflow: hidden;">
                    <div class="card-header text-white py-3 border-0" style="background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-screwdriver-wrench me-2"></i> Ефективність майстрів</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Майстер</th>
                                    <th class="text-center">Замовлень</th>
                                    <th class="text-end pe-3">Зарплата (40%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // SQL: Рахуємо замовлення і суму для кожного майстра
                                $sql_masters = "
                                    SELECT e.id, e.full_name, e.position,
                                           COUNT(o.id) as total_orders,
                                           SUM(o.final_price) as total_revenue
                                    FROM employees e
                                    LEFT JOIN orders o ON e.id = o.employee_id AND o.status = 'done'
                                    GROUP BY e.id
                                    ORDER BY total_revenue DESC";
                                $masters_stats = $pdo->query($sql_masters)->fetchAll();
                                
                                foreach ($masters_stats as $m): 
                                    $salary = $m['total_revenue'] * 0.40; // Формула ЗП
                                ?>
                                <tr onclick="window.location='?view=master&id=<?= $m['id'] ?>'" style="cursor: pointer;">
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($m['full_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($m['position']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= $m['total_orders'] ?> шт.</span>
                                    </td>
                                    <td class="text-end pe-3 fw-bold text-success">
                                        <?= number_format($salary, 0, ' ', ' ') ?> ₴
                                        <div class="small text-muted fw-normal">з <?= number_format($m['total_revenue'], 0, ' ', ' ') ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card shadow-lg border-0 h-100" style="border-radius: 15px; overflow: hidden;">
                    <div class="card-header text-white py-3 border-0" style="background: linear-gradient(135deg, #10b981 0%, #34d399 100%);">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-users me-2"></i> Топ клієнтів</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Клієнт</th>
                                    <th class="text-center">Звернень</th>
                                    <th class="text-end pe-3">Сер. чек</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // SQL: Групуємо замовлення по contact_name (або full_name)
                                // Беремо тих, хто щось замовляв і статус 'done'
                                $sql_clients = "
                                    SELECT 
                                        COALESCE(o.contact_name, c.full_name, u.full_name) as client_display_name,
                                        COUNT(o.id) as total_orders,
                                        SUM(o.final_price) as total_spent
                                    FROM orders o
                                    LEFT JOIN clients c ON o.client_id = c.id
                                    LEFT JOIN users u ON o.client_id = u.id
                                    WHERE o.status = 'done' AND COALESCE(o.contact_name, c.full_name, u.full_name) IS NOT NULL
                                    GROUP BY client_display_name
                                    ORDER BY total_spent DESC
                                    LIMIT 10"; // Топ 10
                                $clients_stats = $pdo->query($sql_clients)->fetchAll();

                                foreach ($clients_stats as $c):
                                    $avg = ($c['total_orders'] > 0) ? $c['total_spent'] / $c['total_orders'] : 0;
                                ?>
                                <tr onclick="window.location='?view=client&name=<?= urlencode($c['client_display_name']) ?>'" style="cursor: pointer;">
                                    <td class="ps-3 fw-bold text-dark">
                                        <?= htmlspecialchars($c['client_display_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= $c['total_orders'] ?></span>
                                    </td>
                                    <td class="text-end pe-3 fw-bold text-success">
                                        <?= number_format($avg, 0, ' ', ' ') ?> ₴
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>