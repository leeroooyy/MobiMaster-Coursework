<?php
// clients.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// --- ЛОГІКА СОРТУВАННЯ ---
$sort = $_GET['sort'] ?? 'id';
$dir = $_GET['dir'] ?? 'desc';
$search = $_GET['search'] ?? '';

// Дозволені поля для сортування (захист від SQL ін'єкцій)
$allowed_cols = ['id', 'full_name', 'orders_count', 'total_spent'];
if (!in_array($sort, $allowed_cols)) {
    $sort = 'id';
}
$dirSql = ($dir === 'asc') ? 'ASC' : 'DESC';

// --- SQL ЗАПИТ ---
$params = [];
$sql = "
    SELECT c.*, 
           COUNT(o.id) as orders_count, 
           COALESCE(SUM(o.final_price), 0) as total_spent 
    FROM clients c 
    LEFT JOIN orders o ON c.id = o.client_id 
";

if ($search) {
    $sql .= " WHERE c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql .= " GROUP BY c.id ORDER BY $sort $dirSql";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Функція для генерації посилань сортування
function sortLink($col, $label, $currentSort, $currentDir, $search) {
    $newDir = ($currentSort == $col && $currentDir == 'desc') ? 'asc' : 'desc';
    $icon = '';
    
    // Якщо колонка активна - малюємо стрілочку
    if ($currentSort == $col) {
        $icon = ($currentDir == 'asc') 
            ? ' <i class="fa-solid fa-arrow-up-long text-primary"></i>' 
            : ' <i class="fa-solid fa-arrow-down-long text-primary"></i>';
    } else {
        $icon = ' <i class="fa-solid fa-sort text-muted opacity-25"></i>';
    }

    // Зберігаємо пошук у посиланні
    $url = "?sort=$col&dir=$newDir";
    if (!empty($search)) {
        $url .= "&search=" . urlencode($search);
    }

    return '<a href="' . $url . '" class="text-decoration-none text-dark fw-bold">' . $label . $icon . '</a>';
}
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fa-solid"></i>База клієнтів</h2>
            <p class="text-secondary mb-0">Всього знайдено: <?= count($clients) ?></p>
        </div>
        
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
            
            <input type="text" name="search" class="form-control" placeholder="Пошук (Ім'я, телефон...)" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary shadow-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if($search): ?><a href="clients.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary small text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">
                            <?= sortLink('id', 'ID', $sort, $dir, $search) ?>
                        </th>
                        <th>
                            <?= sortLink('full_name', 'Клієнт', $sort, $dir, $search) ?>
                        </th>
                        <th>Контакти</th>
                        <th class="text-center">
                            <?= sortLink('orders_count', 'Замовлень', $sort, $dir, $search) ?>
                        </th>
                        <th class="text-end">
                            <?= sortLink('total_spent', 'Всього витрачено', $sort, $dir, $search) ?>
                        </th>
                        <th class="text-end pe-4">Дії</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clients) > 0): ?>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td class="ps-4 text-muted small fw-bold">#<?= $client['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($client['full_name']) ?></div>
                                <div class="small text-muted">
                                    Реєстрація: <?= $client['registration_date'] ? date('d.m.Y', strtotime($client['registration_date'])) : '<span class="fst-italic">Невідомо</span>' ?>
                                </div>
                            </td>
                            <td>
                                <div><i class="fa-solid fa-phone text-success me-2 small"></i><?= htmlspecialchars($client['phone']) ?></div>
                                <?php if($client['email']): ?>
                                    <div class="small text-primary"><i class="fa-solid fa-envelope me-2"></i><?= htmlspecialchars($client['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-3"><?= $client['orders_count'] ?></span>
                            </td>
                            <td class="text-end fw-bold text-success fs-6">
                                <?= number_format($client['total_spent'], 0, ' ', ' ') ?> ₴
                            </td>
                            <td class="text-end pe-4">
                                <a href="client_details.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm">
                                    Деталі <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-user-slash fa-3x mb-3 opacity-25"></i><br>
                                Клієнтів не знайдено
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>