<?php
// admin_dashboard.php
session_start();
require 'db.php';

// Перевірка на адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Функція для отримання замовлень (використовується і при звичайному завантаженні, і при AJAX)
function getOrders($pdo, $search = '', $date = '', $orderBy = 'o.id', $direction = 'DESC') {
    $sql = "
        SELECT 
            o.*, 
            e.full_name AS master_fullname, 
            COALESCE(o.contact_name, c.full_name, u.full_name) AS final_client_name,
            COALESCE(o.contact_phone, c.phone) AS final_client_phone
        FROM orders o 
        LEFT JOIN employees e ON o.employee_id = e.id 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN users u ON o.client_id = u.id
        WHERE 1=1
    ";

    $params = [];

    // Фільтр пошуку
    if (!empty($search)) {
        $term = "%$search%";
        $sql .= " AND (
            o.id LIKE ? OR 
            o.device_model LIKE ? OR 
            COALESCE(o.contact_name, c.full_name, u.full_name) LIKE ? OR
            COALESCE(o.contact_phone, c.phone) LIKE ? OR
            e.full_name LIKE ?
        )";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }

    // Фільтр дати
    if (!empty($date)) {
        $sql .= " AND DATE(o.created_at) = ?";
        $params[] = $date;
    }

    $sql .= " ORDER BY $orderBy $direction";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- AJAX ОБРОБКА ---
if (isset($_GET['ajax_search'])) {
    $search = $_GET['search'] ?? '';
    $date = $_GET['date'] ?? '';
    $sort = $_GET['sort'] ?? 'id';
    $dir = $_GET['dir'] ?? 'desc';

    // Карта полів
    $cols = ['id'=>'o.id', 'device'=>'o.device_model', 'client'=>'final_client_name', 'master'=>'master_fullname', 'status'=>'o.status', 'price'=>'o.final_price', 'date'=>'o.created_at'];
    $orderBy = $cols[$sort] ?? 'o.id';
    $direction = ($dir === 'asc') ? 'ASC' : 'DESC';

    $orders = getOrders($pdo, $search, $date, $orderBy, $direction);

    // Вивід рядків таблиці (HTML)
    if (count($orders) > 0) {
        foreach ($orders as $row) {
            ?>
            <tr>
                <td class="ps-3 fw-bold text-secondary">#<?= $row['id'] ?></td>
                <td class="fw-bold">
                    <span class="text-dark"><?= htmlspecialchars($row['device_model']) ?></span>
                    <div class="small text-muted fw-normal text-truncate" style="max-width: 200px;">
                        <?= htmlspecialchars($row['problem_description']) ?>
                    </div>
                </td>
                <td>
                    <?php if (!empty($row['final_client_name'])): ?>
                        <div class="d-flex align-items-center">
                            <i class="fa-regular fa-user text-muted me-2 small"></i>
                            <div>
                                <div class="text-dark fw-bold small"><?= htmlspecialchars($row['final_client_name']) ?></div>
                                <?php if(!empty($row['final_client_phone'])): ?>
                                    <div class="small text-muted" style="font-size: 0.8em;"><?= htmlspecialchars($row['final_client_phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-danger small">ID: <?= $row['client_id'] ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($row['master_fullname'])): ?>
                        <span class="text-dark small fw-bold"><?= htmlspecialchars($row['master_fullname']) ?></span>
                    <?php else: ?>
                        <span class="text-muted small">-- Не призначено --</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php 
                    $s = $row['status'];
                    if($s=='new') echo '<span class="badge bg-primary">Новий</span>';
                    elseif($s=='in_progress') echo '<span class="badge bg-warning text-dark">В роботі</span>';
                    elseif($s=='done') echo '<span class="badge bg-success">Готово</span>';
                    else echo '<span class="badge bg-secondary">'.$s.'</span>';
                    ?>
                </td>
                <td class="fw-bold text-dark"><?= number_format($row['final_price'], 0, ' ', ' ') ?> ₴</td>
                <td class="small text-muted"><?= date('d.m H:i', strtotime($row['created_at'])) ?></td>
                <td class="text-end pe-3">
                    <div class="d-flex justify-content-end gap-1">
                        <a href="print_receipt.php?id=<?= $row['id'] ?>&type=order" target="_blank" class="btn btn-outline-secondary btn-action" title="Друкувати чек"><i class="fa-solid fa-print"></i></a>
                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-action" title="Редагувати"><i class="fa-solid fa-pencil"></i></a>
                        <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-action" onclick="return confirm('Видалити?')" title="Видалити"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fa-solid fa-magnifying-glass fa-3x mb-3 opacity-25"></i><br>Нічого не знайдено</td></tr>';
    }
    exit; // Зупиняємо скрипт після AJAX відповіді
}

include 'header.php';

// --- ЗВИЧАЙНЕ ЗАВАНТАЖЕННЯ ---
$sort = $_GET['sort'] ?? 'id'; 
$dir = $_GET['dir'] ?? 'desc'; 
$cols = ['id'=>'o.id', 'device'=>'o.device_model', 'client'=>'final_client_name', 'master'=>'master_fullname', 'status'=>'o.status', 'price'=>'o.final_price', 'date'=>'o.created_at'];
$orderBy = $cols[$sort] ?? 'o.id';
$direction = ($dir === 'asc') ? 'ASC' : 'DESC';

$orders = getOrders($pdo, '', '', $orderBy, $direction);

function sortHeader($key, $title, $currentSort, $currentDir) {
    $newDir = ($currentSort == $key && $currentDir == 'desc') ? 'asc' : 'desc';
    $icon = ($currentSort == $key) 
        ? ($currentDir == 'asc' ? ' <i class="fa-solid fa-arrow-up-long text-primary small"></i>' : ' <i class="fa-solid fa-arrow-down-long text-primary small"></i>') 
        : ' <i class="fa-solid fa-sort text-muted opacity-25 small"></i>';
    return '<a href="?sort=' . $key . '&dir=' . $newDir . '" class="text-decoration-none fw-bold text-dark">' . $title . $icon . '</a>';
}
?>

<style>
    .btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; padding: 0; font-size: 0.85rem; }
    .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .search-container { background: #fff; padding: 5px; border-radius: 12px; border: 1px solid #e5e7eb; display: flex; align-items: center; }
</style>

<div class="container py-4">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-0" style="color: #6366f1;"><i class="fa-solid "></i>Панель керування</h2>
        </div>
        
        <div class="d-flex gap-2 align-items-center flex-grow-1 justify-content-end" style="max-width: 600px;">
            <div class="search-container flex-grow-1 shadow-sm">
                <i class="fa-solid fa-magnifying-glass text-muted ms-3 me-2"></i>
                <input type="text" id="liveSearchInput" class="form-control border-0 shadow-none bg-transparent" placeholder="Пошук (ID, клієнт, телефон, пристрій)...">
            </div>
            
            <div class="search-container shadow-sm" style="width: 180px;">
                <input type="date" id="dateFilter" class="form-control border-0 shadow-none bg-transparent text-secondary fw-bold" style="font-size: 0.9rem;">
            </div>

            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 fw-bold ms-2">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 overflow-hidden" style="border-radius: 16px;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="py-3 ps-3"><?= sortHeader('id', 'ID', $sort, $dir) ?></th>
                        <th><?= sortHeader('device', 'Назва / Пристрій', $sort, $dir) ?></th>
                        <th><?= sortHeader('client', 'Клієнт', $sort, $dir) ?></th>
                        <th><?= sortHeader('master', 'Майстер', $sort, $dir) ?></th>
                        <th class="text-center"><?= sortHeader('status', 'Статус', $sort, $dir) ?></th>
                        <th><?= sortHeader('price', 'Ціна', $sort, $dir) ?></th>
                        <th><?= sortHeader('date', 'Дата', $sort, $dir) ?></th>
                        <th class="text-end pe-3" style="min-width: 140px;">Дії</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php 
                    // Початкове завантаження (дублюємо логіку виводу для першого разу)
                    // Але щоб не дублювати код, ми можемо викликати AJAX логіку через include, 
                    // або просто продублювати цикл один раз (найпростіший варіант для розуміння).
                    if (count($orders) > 0) {
                        foreach ($orders as $row) { 
                            // ... (Код рядка таблиці такий самий як в AJAX частині) ...
                            // Для скорочення тут просто виводимо, але в реальному файлі код рядка має бути ідентичний
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold text-secondary">#<?= $row['id'] ?></td>
                                <td class="fw-bold">
                                    <span class="text-dark"><?= htmlspecialchars($row['device_model']) ?></span>
                                    <div class="small text-muted fw-normal text-truncate" style="max-width: 200px;"><?= htmlspecialchars($row['problem_description']) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($row['final_client_name'])): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="fa-regular fa-user text-muted me-2 small"></i>
                                            <div>
                                                <div class="text-dark fw-bold small"><?= htmlspecialchars($row['final_client_name']) ?></div>
                                                <?php if(!empty($row['final_client_phone'])): ?><div class="small text-muted" style="font-size: 0.8em;"><?= htmlspecialchars($row['final_client_phone']) ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?><span class="text-danger small">ID: <?= $row['client_id'] ?></span><?php endif; ?>
                                </td>
                                <td><?= !empty($row['master_fullname']) ? '<span class="text-dark small fw-bold">'.htmlspecialchars($row['master_fullname']).'</span>' : '<span class="text-muted small">-- Не призначено --</span>' ?></td>
                                <td class="text-center">
                                    <?php 
                                    $s = $row['status'];
                                    if($s=='new') echo '<span class="badge bg-primary">Новий</span>';
                                    elseif($s=='in_progress') echo '<span class="badge bg-warning text-dark">В роботі</span>';
                                    elseif($s=='done') echo '<span class="badge bg-success">Готово</span>';
                                    else echo '<span class="badge bg-secondary">'.$s.'</span>';
                                    ?>
                                </td>
                                <td class="fw-bold text-dark"><?= number_format($row['final_price'], 0, ' ', ' ') ?> ₴</td>
                                <td class="small text-muted"><?= date('d.m H:i', strtotime($row['created_at'])) ?></td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="print_receipt.php?id=<?= $row['id'] ?>&type=order" target="_blank" class="btn btn-outline-secondary btn-action"><i class="fa-solid fa-print"></i></a>
                                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-action"><i class="fa-solid fa-pencil"></i></a>
                                        <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-action" onclick="return confirm('Видалити?')"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                        }
                    } else { echo '<tr><td colspan="8" class="text-center py-5 text-muted">Замовлень немає</td></tr>'; }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let searchTimeout;
    const searchInput = document.getElementById('liveSearchInput');
    const dateInput = document.getElementById('dateFilter');
    const tableBody = document.getElementById('ordersTableBody');

    function updateTable() {
        const search = searchInput.value;
        const date = dateInput.value;
        const sort = '<?= $sort ?>';
        const dir = '<?= $dir ?>';

        fetch(`admin_dashboard.php?ajax_search=1&search=${encodeURIComponent(search)}&date=${date}&sort=${sort}&dir=${dir}`)
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
            });
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300);
    });

    dateInput.addEventListener('change', updateTable);
</script>

<?php include 'footer.php'; ?>