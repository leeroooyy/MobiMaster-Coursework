<?php
// employee_orders.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Перевірка адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Перевірка ID
if (!isset($_GET['id'])) {
    header("Location: salary.php");
    exit;
}

$emp_id = $_GET['id'];

// 1. Отримуємо дані співробітника
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$emp_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo "<div class='container py-5'>Співробітника не знайдено. <a href='salary.php'>Назад</a></div>";
    exit;
}

include 'header.php';

// 2. Отримуємо всі замовлення цього майстра
$sql = "
    SELECT 
        o.*, 
        c.full_name as client_name, 
        c.phone as client_phone 
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.id
    WHERE o.employee_id = ?
    ORDER BY o.created_at DESC
";

$stmtOrders = $pdo->prepare($sql);
$stmtOrders->execute([$emp_id]);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

// Рахуємо статистику "на льоту"
$total_done_sum = 0;
$count_done = 0;
$count_active = 0;

foreach ($orders as $ord) {
    if ($ord['status'] == 'done') {
        $total_done_sum += $ord['final_price'];
        $count_done++;
    } elseif ($ord['status'] == 'new' || $ord['status'] == 'in_progress') {
        $count_active++;
    }
}

// Допоміжні функції для краси
function getStatusBadge($status) {
    switch ($status) {
        case 'new': return 'bg-primary';
        case 'in_progress': return 'bg-warning text-dark';
        case 'done': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getStatusLabel($status) {
    switch ($status) {
        case 'new': return 'Новий';
        case 'in_progress': return 'В роботі';
        case 'done': return 'Готово';
        default: return $status;
    }
}
?>

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="salary.php" class="text-decoration-none">Зарплата</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($employee['full_name']) ?></li>
                </ol>
            </nav>
            <h2 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-user-gear me-2 text-primary"></i>
                Історія робіт майстра
            </h2>
        </div>
        <a href="salary.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
            <i class="fa-solid fa-arrow-left me-2"></i> Назад
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4 bg-light">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-6 d-flex align-items-center mb-3 mb-md-0">
                    <div class="rounded-circle d-flex justify-content-center align-items-center fw-bold me-3 text-white shadow-sm" 
                         style="width: 60px; height: 60px; font-size: 1.5rem; background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                        <?= mb_substr($employee['full_name'], 0, 1) ?>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold"><?= htmlspecialchars($employee['full_name']) ?></h4>
                        <span class="badge bg-white text-secondary border"><?= htmlspecialchars($employee['position']) ?></span>
                        <div class="text-muted small mt-1"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($employee['phone']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row text-center g-2">
                        <div class="col-4">
                            <div class="bg-white p-2 rounded border">
                                <small class="text-muted d-block fw-bold text-uppercase">Виконано</small>
                                <span class="fs-5 fw-bold text-success"><?= $count_done ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white p-2 rounded border">
                                <small class="text-muted d-block fw-bold text-uppercase">В роботі</small>
                                <span class="fs-5 fw-bold text-warning"><?= $count_active ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white p-2 rounded border">
                                <small class="text-muted d-block fw-bold text-uppercase">Каса (Done)</small>
                                <span class="fs-5 fw-bold text-primary"><?= number_format($total_done_sum, 0, ' ', ' ') ?> ₴</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 12px;">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-list-ul me-2"></i> Список замовлень</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary small text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">ID</th>
                        <th>Пристрій / Опис</th>
                        <th>Клієнт</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th class="text-end pe-4">Сума</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($orders) > 0): ?>
                        <?php foreach($orders as $row): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= $row['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['device_model']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 250px;">
                                        <?= htmlspecialchars($row['problem_description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($row['client_name']): ?>
                                        <div class="fw-bold"><?= htmlspecialchars($row['client_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['client_phone']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= getStatusBadge($row['status']) ?> px-3 py-2 rounded-pill">
                                        <?= getStatusLabel($row['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-secondary">
                                    <?= date('d.m.Y H:i', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="text-end pe-4 fw-bold fs-6">
                                    <?= number_format($row['final_price'], 0, ' ', ' ') ?> ₴
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-list fa-3x mb-3 opacity-25"></i><br>
                                У цього майстра ще немає замовлень.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>