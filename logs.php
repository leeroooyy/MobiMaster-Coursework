<?php
// logs.php

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

// --- ОТРИМАННЯ ПАРАМЕТРІВ ---
$filter_date = $_GET['date'] ?? ''; 
$sort = $_GET['sort'] ?? 'id';      
$dir = $_GET['dir'] ?? 'desc';      

// Карта дозволених полів для сортування
$allowed = [
    'id'      => 'l.id',
    'date'    => 'l.created_at',
    'user'    => 'u.username', // Сортуємо по імені користувача
    'action'  => 'l.action',
    'details' => 'l.details'
];

if (!array_key_exists($sort, $allowed)) {
    $sort = 'id';
}

$orderBy = $allowed[$sort];
$direction = ($dir === 'asc') ? 'ASC' : 'DESC';

// --- SQL ЗАПИТ ---
$sql = "SELECT l.*, u.username, u.full_name 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id";

$params = [];

if (!empty($filter_date)) {
    $sql .= " WHERE DATE(l.created_at) = ?";
    $params[] = $filter_date;
}

$sql .= " ORDER BY $orderBy $direction";

// Обмеження, якщо не вибрана дата (щоб не вантажити всю історію)
if (empty($filter_date)) {
    $sql .= " LIMIT 200";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Помилка: " . $e->getMessage());
}

// --- ФУНКЦІЯ ПОСИЛАНЬ (З КРАСИВИМИ СТРІЛОЧКАМИ) ---
function sortLink($key, $title, $currentSort, $currentDir, $currentDate) {
    $newDir = ($currentSort == $key && $currentDir == 'desc') ? 'asc' : 'desc';
    
    // Іконка за замовчуванням (сіра)
    $icon = '<i class="fa-solid fa-sort text-muted opacity-25 ms-1"></i>';
    $textClass = 'text-secondary fw-bold';
    
    // Якщо колонка активна - змінюємо колір і іконку
    if ($currentSort == $key) {
        $textClass = 'text-primary fw-bold';
        $icon = ($currentDir == 'asc') 
            ? '<i class="fa-solid fa-arrow-up-long text-primary ms-1"></i>' 
            : '<i class="fa-solid fa-arrow-down-long text-primary ms-1"></i>';
    }

    $url = "?sort=$key&dir=$newDir";
    if (!empty($currentDate)) {
        $url .= "&date=$currentDate";
    }

    return '<a href="' . $url . '" class="' . $textClass . ' text-decoration-none d-flex align-items-center" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">' . $title . $icon . '</a>';
}

function getActionBadge($action) {
    if (stripos($action, 'DELETE') !== false) return 'bg-danger bg-opacity-10 text-danger border border-danger';
    if (stripos($action, 'UPDATE') !== false) return 'bg-warning bg-opacity-10 text-warning border border-warning';
    if (stripos($action, 'INSERT') !== false) return 'bg-success bg-opacity-10 text-success border border-success';
    if (stripos($action, 'CREATE') !== false) return 'bg-success bg-opacity-10 text-success border border-success';
    if (stripos($action, 'LOGIN') !== false) return 'bg-primary bg-opacity-10 text-primary border border-primary';
    return 'bg-secondary bg-opacity-10 text-secondary border';
}
?>

<div class="container py-5">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1" style="color: #4b5563;">
                <i class="fa-solid fa-clock-rotate-left me-2"></i> Журнал подій
            </h2>
            <p class="text-secondary mb-0">Історія дій користувачів у системі</p>
        </div>
        
        <div class="d-flex gap-2">
            <form method="GET" action="logs.php" class="d-flex align-items-center gap-2 bg-white p-1 rounded-pill shadow-sm border px-3">
                <i class="fa-regular fa-calendar text-muted"></i>
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
                <input type="date" name="date" class="form-control form-control-sm border-0 bg-transparent" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()" style="outline: none; box-shadow: none;">
                
                <?php if (!empty($filter_date)): ?>
                    <a href="logs.php" class="text-danger ms-2" title="Скинути дату">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </form>

            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3 fw-bold">
                <i class="fa-solid fa-arrow-left me-1"></i> На головну
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 overflow-hidden" style="border-radius: 16px;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="ps-4 py-3" style="width: 90px;">
                            <?= sortLink('id', 'ID', $sort, $dir, $filter_date) ?>
                        </th>
                        <th style="width: 200px;">
                            <?= sortLink('date', 'Дата та час', $sort, $dir, $filter_date) ?>
                        </th>
                        <th style="width: 200px;">
                            <?= sortLink('user', 'Користувач', $sort, $dir, $filter_date) ?>
                        </th>
                        <th style="width: 150px;">
                            <?= sortLink('action', 'Дія', $sort, $dir, $filter_date) ?>
                        </th>
                        <th>
                            <?= sortLink('details', 'Деталі', $sort, $dir, $filter_date) ?>
                        </th>
                    </tr>
                </thead>
                
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted opacity-75">
                                    #<?= $log['id'] ?>
                                </td>
                                <td class="small text-secondary" style="font-family: monospace; font-size: 0.9rem;">
                                    <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['username'])): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2 text-primary fw-bold border" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <?= strtoupper(substr($log['username'], 0, 1)) ?>
                                            </div>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($log['username']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Система / Гість</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill <?= getActionBadge($log['action']) ?> px-3">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td class="py-3 text-secondary">
                                    <?= htmlspecialchars($log['details']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted opacity-25 mb-3"><i class="fa-solid fa-clock-rotate-left fa-3x"></i></div>
                                <h5 class="text-muted fw-normal">Записів у журналі не знайдено</h5>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>