<?php
// admin_feedback.php

// 1. Налаштування
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Перевірка на адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 2. Логіка видалення
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$id]);
        header("Location: admin_feedback.php"); // Перезавантаження сторінки
        exit;
    } catch (PDOException $e) {
        die("Помилка видалення: " . $e->getMessage());
    }
}

// 3. ЛОГІКА СОРТУВАННЯ
$sort = $_GET['sort'] ?? 'created_at'; // За замовчуванням сортуємо за датою
$dir  = $_GET['dir'] ?? 'desc';        // За замовчуванням спочатку нові

// Дозволені колонки для сортування (захист)
$allowed_cols = ['name', 'created_at'];
if (!in_array($sort, $allowed_cols)) {
    $sort = 'created_at';
}

// Напрямок SQL
$sql_dir = ($dir === 'asc') ? 'ASC' : 'DESC';

// Отримання повідомлень з сортуванням
try {
    $sql = "SELECT * FROM feedback ORDER BY $sort $sql_dir";
    $messages = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    die("<div class='alert alert-danger m-4'>Помилка SQL: " . $e->getMessage() . "</div>");
}

// Функція для малювання стрілочок
if (!function_exists('getArrow')) {
    function getArrow($col, $currentSort, $currentDir) {
        if ($col !== $currentSort) return ' <i class="fa-solid fa-sort text-muted opacity-25"></i>';
        return $currentDir === 'asc' ? ' <i class="fa-solid fa-arrow-up-long"></i>' : ' <i class="fa-solid fa-arrow-down-long"></i>';
    }
}

include 'header.php';
?>

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="fa-solid"></i>Вхідні повідомлення</h2>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">← На головну</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">
                                <a href="?sort=name&dir=<?= ($sort == 'name' && $dir == 'desc') ? 'asc' : 'desc' ?>" class="text-decoration-none text-dark fw-bold">
                                    Від кого <?= getArrow('name', $sort, $dir) ?>
                                </a>
                            </th>
                            
                            <th>Контакти</th>
                            <th style="width: 40%;">Повідомлення</th>
                            
                            <th>
                                <a href="?sort=created_at&dir=<?= ($sort == 'created_at' && $dir == 'desc') ? 'asc' : 'desc' ?>" class="text-decoration-none text-dark fw-bold">
                                    Дата <?= getArrow('created_at', $sort, $dir) ?>
                                </a>
                            </th>
                            
                            <th class="text-end pe-4">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <div class="text-dark"><?= htmlspecialchars($msg['name']) ?></div>
                                    </td>
                                
                                <td>
                                    <?php 
                                        $phone = $msg['phone'] ?? '';
                                        $email = $msg['email'] ?? '';
                                    ?>
                                    
                                    <?php if(!empty($phone)): ?>
                                        <div class="small"><i class="fa-solid fa-phone text-success me-2"></i><?= htmlspecialchars($phone) ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($email)): ?>
                                        <div class="small"><i class="fa-solid fa-envelope text-primary me-2"></i><?= htmlspecialchars($email) ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if(empty($phone) && empty($email)): ?>
                                        <span class="text-muted small">--</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                </td>
                                
                                <td class="text-muted small">
                                    <?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?>
                                </td>
                                
                                <td class="text-end pe-4">
                                    <a href="?delete=<?= $msg['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Видалити повідомлення?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-regular fa-folder-open fa-2x mb-3"></i><br>
                                    Вхідних повідомлень немає.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>