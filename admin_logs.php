<?php
// Вмикаємо показ помилок
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

// Перевірка на адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// --- ОТРИМАННЯ ПАРАМЕТРІВ ---
$filter_date = $_GET['date'] ?? ''; // Дата для фільтрації
$sort = $_GET['sort'] ?? 'id';      // Поле сортування
$dir = $_GET['dir'] ?? 'desc';      // Напрямок

// Дозволені поля для сортування
$allowed = [
    'id' => 'l.id',
    'date' => 'l.created_at',
    'user' => 'u.full_name',
    'action' => 'l.action',
    'details' => 'l.details'
];

if (!array_key_exists($sort, $allowed)) {
    $sort = 'id';
}

// --- ФОРМУВАННЯ SQL ЗАПИТУ ---
$orderBy = $allowed[$sort];
$direction = ($dir === 'asc') ? 'ASC' : 'DESC';

// Базовий запит
$sql = "SELECT l.*, u.full_name, u.role 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id";

$params = [];

// Якщо вибрана дата — додаємо умову WHERE
if (!empty($filter_date)) {
    $sql .= " WHERE DATE(l.created_at) = ?";
    $params[] = $filter_date;
}

// Додаємо сортування
$sql .= " ORDER BY $orderBy $direction";

// Якщо дата не вибрана, обмежуємо кількістю (щоб не грузити базу)
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

// Функція для посилань (зберігає дату при сортуванні)
function sortLink($key, $title, $currentSort, $currentDir, $currentDate) {
    $newDir = ($currentSort == $key && $currentDir == 'desc') ? 'asc' : 'desc';
    $icon = '';
    
    if ($currentSort == $key) {
        $icon = ($currentDir == 'asc') ? ' ⬆' : ' ⬇';
    } else {
        $icon = ' <i class="fa-solid fa-sort opacity-25"></i>';
    }

    // Зберігаємо дату в посиланні, щоб фільтр не злітав
    $url = "?sort=$key&dir=$newDir";
    if (!empty($currentDate)) {
        $url .= "&date=$currentDate";
    }

    return '<a href="' . $url . '" class="text-white text-decoration-none fw-bold">' . $title . $icon . '</a>';
}

function getActionBadge($action) {
    if (stripos($action, 'DELETE') !== false) return 'bg-danger';
    if (stripos($action, 'UPDATE') !== false) return 'bg-warning text-dark';
    if (stripos($action, 'INSERT') !== false) return 'bg-success';
    if (stripos($action, 'LOGIN') !== false) return 'bg-info text-dark';
    return 'bg-secondary';
}
?>

<div class="container py-4">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-0" style="color: #6366f1;">
                <i class="fa-solid fa-clock-rotate-left me-2"></i> Журнал подій
            </h2>
        </div>
        
        <form method="GET" class="d-flex align-items-center gap-2 bg-white p-2 rounded shadow-sm border">
            <label class="fw-bold text-secondary small mb-0"><i class="fa-regular fa-calendar me-1"></i> Дата:</label>
            <input type="date" name="date" class="form-control form-control-sm border-0 bg-light" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()">
            
            <?php if (!empty($filter_date)): ?>
                <a href="admin_logs.php" class="btn btn-sm btn-outline-danger" title="Скинути фільтр">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </form>

        <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> На головну
        </a>
    </div>

    <div class="card shadow-lg border-0 overflow-hidden" style="border-radius: 15px;">
        
        <?php if (!empty($filter_date)): ?>
            <div class="card-header bg-light text-center border-0 py-2">
                <small class="text-muted">Показано записи за: <strong><?= date('d.m.Y', strtotime($filter_date)) ?></strong></small>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);">
                    <tr>
                        <th class="ps-4 py-3"><?= sortLink('id', 'ID', $sort, $dir, $filter_date) ?></th>
                        <th><?= sortLink('date', 'Час події', $sort, $dir, $filter_date) ?></th>
                        <th><?= sortLink('user', 'Хто зробив', $sort, $dir, $filter_date) ?></th>
                        <th><?= sortLink('action', 'Тип дії', $sort, $dir, $filter_date) ?></th>
                        <th><?= sortLink('details', 'Опис деталі', $sort, $dir, $filter_date) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 text-muted small fw-bold">#<?= $log['id'] ?></td>
                                
                                <td class="small">
                                    <div class="fw-bold text-dark"><?= date('d.m.Y', strtotime($log['created_at'])) ?></div>
                                    <div class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                </td>
                                
                                <td>
                                    <?php if ($log['full_name']): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2 text-primary" style="width: 30px; height: 30px;">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark lh-1 small"><?= htmlspecialchars($log['full_name']) ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;">
                                                    <?= htmlspecialchars($log['role']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Гість / ID: <?= $log['user_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span class="badge <?= getActionBadge($log['action']) ?> border shadow-sm px-2 rounded-pill">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                
                                <td class="text-secondary small py-3" style="max-width: 400px;">
                                    <?= htmlspecialchars($log['details']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted opacity-25 mb-2"><i class="fa-solid fa-calendar-xmark fa-3x"></i></div>
                                <h5 class="text-muted">Записів не знайдено</h5>
                                <?php if(!empty($filter_date)): ?>
                                    <p class="text-muted small">Спробуйте вибрати іншу дату</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>