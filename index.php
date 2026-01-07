<?php
// index.php

// 1. Налаштування
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin');

// --- ЛОГІКА: ОТРИМАННЯ ДАНИХ (для адміна) ---
function getOrdersData($pdo, $tab, $search, $status_filter, $sort, $dir) {
    $rows = [];
    $term = "%$search%";
    
    if ($tab == 'repair') {
        $cols = ['id'=>'o.id', 'device'=>'o.device_model', 'client'=>'final_client_name', 'master'=>'master_name', 'status'=>'o.status', 'price'=>'o.final_price', 'date'=>'o.created_at'];
        $orderBy = $cols[$sort] ?? 'o.id';
        $direction = ($dir === 'asc') ? 'ASC' : 'DESC';

        $sql = "SELECT 
                    o.*, 
                    e.full_name AS master_name,
                    COALESCE(o.contact_name, c.full_name, u.full_name) AS final_client_name,
                    COALESCE(o.contact_phone, c.phone) AS final_client_phone
                FROM orders o 
                LEFT JOIN employees e ON o.employee_id = e.id 
                LEFT JOIN clients c ON o.client_id = c.id 
                LEFT JOIN users u ON o.client_id = u.id
                WHERE 1=1";
        
        $params = [];
        if (!empty($search)) {
            $sql .= " AND (o.contact_name LIKE ? OR c.full_name LIKE ? OR u.full_name LIKE ? OR o.device_model LIKE ?)";
            $params = array_fill(0, 4, $term);
        }
        if (!empty($status_filter)) {
            $sql .= " AND o.status = ?";
            $params[] = $status_filter;
        }
        $sql .= " ORDER BY $orderBy $direction";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }
    elseif ($tab == 'sales') {
        $cols = ['id'=>'p.id', 'name'=>'p.name', 'price'=>'p.price', 'client'=>'real_client_name'];
        $orderBy = $cols[$sort] ?? 'p.id';
        $direction = ($dir === 'asc') ? 'ASC' : 'DESC';

        $sql = "SELECT 
                    p.*, 
                    e.full_name AS master_name,
                    COALESCE(o.contact_name, c.full_name, u.full_name) AS real_client_name,
                    o.id AS order_id
                FROM products_services p
                LEFT JOIN orders o ON o.item_id = p.id
                LEFT JOIN clients c ON o.client_id = c.id
                LEFT JOIN users u ON o.client_id = u.id
                LEFT JOIN employees e ON o.employee_id = e.id
                WHERE p.type='product' AND p.is_sold=1";
        
        $params = [];
        if (!empty($search)) {
            $sql .= " AND (p.name LIKE ? OR o.contact_name LIKE ? OR c.full_name LIKE ?)";
            $params = [$term, $term, $term];
        }
        $sql .= " ORDER BY $orderBy $direction";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }
    elseif ($tab == 'store') {
        $cols = ['id'=>'id', 'name'=>'name', 'description'=>'description', 'price'=>'price'];
        $orderBy = $cols[$sort] ?? 'id';
        $direction = ($dir === 'asc') ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM products_services WHERE type='product' AND is_sold=0";
        $params = [];
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params = [$term, $term];
        }
        $sql .= " ORDER BY $orderBy $direction";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }
    return $rows;
}

// === AJAX ОБРОБНИК ===
if (isset($_GET['ajax_search'])) {
    $current_tab = $_GET['tab'] ?? 'repair';
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $sort = $_GET['sort'] ?? 'id';
    $dir = $_GET['dir'] ?? 'desc';

    $rows = getOrdersData($pdo, $current_tab, $search, $status_filter, $sort, $dir);

    if (count($rows) > 0) {
        foreach ($rows as $row) {
            echo "<tr>";
            if ($current_tab == 'repair') {
                echo '<td class="ps-3 fw-bold text-secondary">#' . $row['id'] . '</td>';
                echo '<td class="fw-bold">' . htmlspecialchars($row['device_model']) . '</td>';
                echo '<td>';
                if (!empty($row['final_client_name'])) {
                    echo htmlspecialchars($row['final_client_name']);
                    if(!empty($row['final_client_phone'])) echo '<div class="small text-muted">' . htmlspecialchars($row['final_client_phone']) . '</div>';
                } else {
                    echo '<span class="text-danger small">ID: ' . $row['client_id'] . '</span>';
                }
                echo '</td>';
                echo '<td>' . htmlspecialchars($row['master_name'] ?? '--') . '</td>';
                echo '<td>';
                if($row['status']=='new') echo '<span class="badge bg-primary">Новий</span>';
                elseif($row['status']=='in_progress') echo '<span class="badge bg-warning text-dark">В роботі</span>';
                elseif($row['status']=='done') echo '<span class="badge bg-success">Готово</span>';
                else echo '<span class="badge bg-secondary">'.$row['status'].'</span>';
                echo '</td>';
                echo '<td class="fw-bold">' . number_format($row['final_price'], 0, ' ', ' ') . ' ₴</td>';
                echo '<td class="small text-muted">' . date('d.m H:i', strtotime($row['created_at'])) . '</td>';
                echo '<td class="text-end pe-3">';
                echo '<a href="print_receipt.php?id=' . $row['id'] . '" target="_blank" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-print"></i></a>';
                echo '<a href="edit.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-pencil"></i></a>';
                echo '<a href="?tab=repair&delete_order_id=' . $row['id'] . '" class="btn btn-sm btn-outline-danger border" onclick="return confirm(\'Видалити?\')"><i class="fa-solid fa-trash"></i></a>';
                echo '</td>';
            } elseif ($current_tab == 'store') {
                echo '<td class="ps-3 fw-bold text-secondary">#' . $row['id'] . '</td>';
                echo '<td>';
                if($row['image']) echo '<img src="uploads/' . $row['image'] . '" width="40" style="border-radius:4px;">';
                echo '</td>';
                echo '<td class="fw-bold">' . htmlspecialchars($row['name']) . '</td>';
                echo '<td class="small text-muted">' . mb_strimwidth($row['description'], 0, 40) . '</td>';
                echo '<td class="fw-bold text-success">' . number_format($row['price'], 0, ' ', ' ') . ' ₴</td>';
                echo '<td class="text-end pe-3">';
                echo '<a href="mark_sold.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border text-success me-1" title="Продано"><i class="fa-solid fa-check"></i></a>';
                echo '<a href="edit_product.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border" title="Редагувати"><i class="fa-solid fa-pencil"></i></a>';
                echo '<a href="?tab=store&delete_product_id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border text-danger" onclick="return confirm(\'Видалити?\')"><i class="fa-solid fa-trash"></i></a>';
                echo '</td>';
            } elseif ($current_tab == 'sales') {
                echo '<td class="ps-3 fw-bold text-secondary">#' . $row['id'] . '</td>';
                echo '<td>';
                if($row['image']) echo '<img src="uploads/' . $row['image'] . '" width="40" style="border-radius:4px;">';
                echo '</td>';
                echo '<td class="fw-bold">' . htmlspecialchars($row['name']) . '</td>';
                echo '<td class="small text-muted">' . mb_strimwidth($row['description'], 0, 40) . '</td>';
                echo '<td>' . htmlspecialchars($row['real_client_name'] ?? 'Каса') . '</td>';
                echo '<td class="fw-bold text-success">' . number_format($row['price'], 0, ' ', ' ') . ' ₴</td>';
                echo '<td class="text-end pe-3">';
                // === НОВА КНОПКА (AJAX) ===
                echo '<a href="sale_details.php?id=' . $row['order_id'] . '" class="btn btn-sm btn-primary border me-1" title="Деталі продажу"><i class="fa-regular fa-eye"></i></a>';
                // ==========================
                echo '<a href="print_receipt.php?id=' . $row['order_id'] . '&type=product" target="_blank" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-print"></i></a>';
                echo '<a href="edit_product.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-pencil"></i></a>';
                echo '<a href="delete_product.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-danger border" onclick="return confirm(\'Видалити?\')"><i class="fa-solid fa-trash"></i></a>';
                echo '</td>';
            }
            echo "</tr>";
        }
    } else {
        echo '<tr><td colspan="8" class="text-center py-5 text-muted">Нічого не знайдено</td></tr>';
    }
    exit;
}

// --- ГОЛОВНА СТОРІНКА ---

if ($is_admin) {
    if (isset($_GET['delete_product_id'])) {
        $pdo->prepare("DELETE FROM products_services WHERE id = ?")->execute([$_GET['delete_product_id']]);
        header("Location: index.php?tab=store"); exit;
    }
    if (isset($_GET['delete_order_id'])) {
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$_GET['delete_order_id']]);
        header("Location: index.php?tab=repair"); exit;
    }
}

include 'header.php';

if ($is_admin) {
    $current_tab = $_GET['tab'] ?? 'repair'; 
    $sort = $_GET['sort'] ?? 'id';           
    $dir = $_GET['dir'] ?? 'desc';           
    $search = $_GET['search'] ?? '';         
    $status_filter = $_GET['status'] ?? '';  

    $rows = getOrdersData($pdo, $current_tab, $search, $status_filter, $sort, $dir);

    function sortLink($column, $title, $activeTab, $currentSort, $currentDir) {
        $params = $_GET;
        $params['tab'] = $activeTab;
        $params['sort'] = $column;
        $params['dir'] = ($currentSort == $column && $currentDir == 'desc') ? 'asc' : 'desc';
        $icon = function_exists('getArrow') ? getArrow($column, $currentSort, $currentDir) : '';
        return '<a href="?' . http_build_query($params) . '" class="text-dark text-decoration-none fw-bold">' . $title . $icon . '</a>';
    }
    function getTabStyle($tabName, $activeTab, $color) {
        return ($tabName === $activeTab) 
            ? "background-color: #eef2ff; color: #000000 !important; border: 2px solid $color;" 
            : "background-color: white; color: #000000 !important; border: 1px solid #e5e7eb;";
    }
}
?>

<?php if ($is_admin): ?>

    <?php $stats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) as new, SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) as work, SUM(final_price) as money FROM orders")->fetch(); ?>
    <div class="row mb-4">
        <div class="col-md-3"><div class="card h-100 border-0 shadow-sm p-3 text-center"><h6 class="text-muted small fw-bold">Всього</h6><h2 class="fw-bold mb-0"><?= $stats['total'] ?></h2></div></div>
        <div class="col-md-3"><div class="card h-100 border-0 shadow-sm p-3 text-center"><h6 class="text-muted small fw-bold">Нових</h6><h2 class="fw-bold mb-0"><?= $stats['new'] ?></h2></div></div>
        <div class="col-md-3"><div class="card h-100 border-0 shadow-sm p-3 text-center"><h6 class="text-muted small fw-bold">В роботі</h6><h2 class="fw-bold mb-0"><?= $stats['work'] ?></h2></div></div>
        <div class="col-md-3"><div class="card h-100 border-0 shadow-sm p-3 text-center"><h6 class="text-muted small fw-bold">Каса</h6><h2 class="fw-bold mb-0 text-success"><?= number_format($stats['money'], 0, ' ', ' ') ?> ₴</h2></div></div>
    </div>

    <div class="card p-3 mb-4 shadow-sm border-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex gap-2">
                <a href="create.php" class="btn btn-primary shadow-sm px-4 py-2 fw-bold" style="background-color: #6366f1; border: none; font-size: 1.1rem;">
                    <i class="fa-solid fa-screwdriver-wrench me-2"></i> Оформити ремонт
                </a>

                <a href="add_product.php" class="btn btn-primary shadow-sm px-4 py-2 fw-bold ms-2" style="background-color: #6366f1; border: none; font-size: 1.1rem;">
                    <i class="fa-solid fa-cart-plus me-2"></i> Додати товар
                </a>

                <a href="teacher_report.php" class="btn btn-primary shadow-sm px-4 py-2 fw-bold ms-2" style="background-color: #6366f1; border: none; font-size: 1.1rem;">
                    <i class="fa-solid fa-file-contract me-2"></i> Вибірка
                </a>
            </div>

            <div class="d-flex align-items-center gap-2" style="background: #fff; padding: 5px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <?php if ($current_tab == 'repair'): ?>
                    <select id="statusFilter" class="form-select border-0 bg-transparent py-2" onchange="liveSearch()">
                        <option value="">📌 Всі статуси</option>
                        <option value="new">🔵 Нові</option>
                        <option value="in_progress">🟡 В роботі</option>
                        <option value="done">🟢 Готові</option>
                    </select>
                    <div style="width:1px;height:24px;background:#e5e7eb;"></div>
                <?php endif; ?>
                <input id="liveSearchInput" class="form-control border-0 bg-transparent py-2" type="search" placeholder="Живий пошук..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a href="?tab=repair" class="nav-link active fw-bold px-4 shadow-sm" style="<?= getTabStyle('repair', $current_tab, '#6366f1') ?>">🛠 Ремонти + Продажі</a></li>
        <li class="nav-item ms-2"><a href="?tab=sales" class="nav-link fw-bold px-4 shadow-sm border" style="<?= getTabStyle('sales', $current_tab, '#10b981') ?>">💰 Історія продажів</a></li>
        <li class="nav-item ms-2"><a href="?tab=store" class="nav-link fw-bold px-4 shadow-sm border" style="<?= getTabStyle('store', $current_tab, '#f59e0b') ?>">📱 Вітрина (Активні)</a></li>
    </ul>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <?php if ($current_tab == 'repair'): ?>
                        <tr>
                            <th class="ps-3"><?= sortLink('id', 'ID', 'repair', $sort, $dir) ?></th>
                            <th><?= sortLink('device', 'Пристрій / Опис', 'repair', $sort, $dir) ?></th>
                            <th><?= sortLink('client', 'Клієнт', 'repair', $sort, $dir) ?></th>
                            <th><?= sortLink('master', 'Майстер', 'repair', $sort, $dir) ?></th>
                            <th><?= sortLink('status', 'Статус', 'repair', $sort, $dir) ?></th>
                            <th><?= sortLink('price', 'Сума', 'repair', $sort, $dir) ?></th>
                            <th><?= sortLink('date', 'Дата', 'repair', $sort, $dir) ?></th>
                            <th class="text-end pe-3">Дії</th>
                        </tr>
                    <?php elseif ($current_tab == 'store'): ?>
                        <tr>
                            <th class="ps-3"><?= sortLink('id', 'ID', 'store', $sort, $dir) ?></th>
                            <th>Фото</th>
                            <th><?= sortLink('name', 'Назва / Модель', 'store', $sort, $dir) ?></th>
                            <th><?= sortLink('description', 'Опис', 'store', $sort, $dir) ?></th>
                            <th><?= sortLink('price', 'Ціна', 'store', $sort, $dir) ?></th>
                            <th class="text-end pe-3">Дії</th>
                        </tr>
                    <?php elseif ($current_tab == 'sales'): ?>
                        <tr>
                            <th class="ps-3"><?= sortLink('id', 'ID', 'sales', $sort, $dir) ?></th>
                            <th>Фото</th>
                            <th><?= sortLink('name', 'Назва / Модель', 'sales', $sort, $dir) ?></th>
                            <th><?= sortLink('description', 'Опис товару', 'sales', $sort, $dir) ?></th>
                            <th><?= sortLink('client', 'Клієнт', 'sales', $sort, $dir) ?></th>
                            <th><?= sortLink('price', 'Ціна продажу', 'sales', $sort, $dir) ?></th>
                            <th class="text-end pe-3">Дії</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody id="ordersTableBody">
                    <?php 
                        if (count($rows) > 0) {
                            foreach ($rows as $row) {
                                echo "<tr>";
                                if ($current_tab == 'repair') {
                                    echo '<td class="ps-3 fw-bold text-secondary">#' . $row['id'] . '</td>';
                                    echo '<td class="fw-bold">' . htmlspecialchars($row['device_model']) . '</td>';
                                    echo '<td>';
                                    if (!empty($row['final_client_name'])) {
                                        echo htmlspecialchars($row['final_client_name']);
                                        if(!empty($row['final_client_phone'])) echo '<div class="small text-muted">' . htmlspecialchars($row['final_client_phone']) . '</div>';
                                    } else {
                                        echo '<span class="text-danger small">ID: ' . $row['client_id'] . '</span>';
                                    }
                                    echo '</td>';
                                    echo '<td>' . htmlspecialchars($row['master_name'] ?? '--') . '</td>';
                                    echo '<td>';
                                    if($row['status']=='new') echo '<span class="badge bg-primary">Новий</span>';
                                    elseif($row['status']=='in_progress') echo '<span class="badge bg-warning text-dark">В роботі</span>';
                                    elseif($row['status']=='done') echo '<span class="badge bg-success">Готово</span>';
                                    else echo '<span class="badge bg-secondary">'.$row['status'].'</span>';
                                    echo '</td>';
                                    echo '<td class="fw-bold">' . number_format($row['final_price'], 0, ' ', ' ') . ' ₴</td>';
                                    echo '<td class="small text-muted">' . date('d.m H:i', strtotime($row['created_at'])) . '</td>';
                                    echo '<td class="text-end pe-3">';
                                    echo '<a href="print_receipt.php?id=' . $row['id'] . '" target="_blank" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-print"></i></a>';
                                    echo '<a href="edit.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-pencil"></i></a>';
                                    echo '<a href="?tab=repair&delete_order_id=' . $row['id'] . '" class="btn btn-sm btn-outline-danger border" onclick="return confirm(\'Видалити?\')"><i class="fa-solid fa-trash"></i></a>';
                                    echo '</td>';
                                } elseif ($current_tab == 'store') {
                                    echo '<td class="ps-3 fw-bold text-secondary">#' . $row['id'] . '</td>';
                                    echo '<td>';
                                    if($row['image']) echo '<img src="uploads/' . $row['image'] . '" width="40" style="border-radius:4px;">';
                                    echo '</td>';
                                    echo '<td class="fw-bold">' . htmlspecialchars($row['name']) . '</td>';
                                    echo '<td class="small text-muted">' . mb_strimwidth($row['description'], 0, 40) . '</td>';
                                    echo '<td class="fw-bold text-success">' . number_format($row['price'], 0, ' ', ' ') . ' ₴</td>';
                                    echo '<td class="text-end pe-3">';
                                    echo '<a href="mark_sold.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border text-success me-1" title="Продано"><i class="fa-solid fa-check"></i></a>';
                                    echo '<a href="edit_product.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border" title="Редагувати"><i class="fa-solid fa-pencil"></i></a>';
                                    echo '<a href="?tab=store&delete_product_id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border text-danger" onclick="return confirm(\'Видалити?\')"><i class="fa-solid fa-trash"></i></a>';
                                    echo '</td>';
                                } elseif ($current_tab == 'sales') {
                                    echo '<td class="ps-3 fw-bold text-secondary">#' . $row['id'] . '</td>';
                                    echo '<td>';
                                    if($row['image']) echo '<img src="uploads/' . $row['image'] . '" width="40" style="border-radius:4px;">';
                                    echo '</td>';
                                    echo '<td class="fw-bold">' . htmlspecialchars($row['name']) . '</td>';
                                    echo '<td class="small text-muted">' . mb_strimwidth($row['description'], 0, 40) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['real_client_name'] ?? 'Каса') . '</td>';
                                    echo '<td class="fw-bold text-success">' . number_format($row['price'], 0, ' ', ' ') . ' ₴</td>';
                                    echo '<td class="text-end pe-3">';
                                    
                                    // === НОВА КНОПКА ===
                                    echo '<a href="sale_details.php?id=' . $row['order_id'] . '" class="btn btn-sm btn-primary border me-1" title="Деталі продажу"><i class="fa-regular fa-eye"></i></a>';
                                    // ===================
                                    
                                    echo '<a href="print_receipt.php?id=' . $row['order_id'] . '&type=product" target="_blank" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-print"></i></a>';
                                    echo '<a href="edit_product.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-dark border me-1"><i class="fa-solid fa-pencil"></i></a>';
                                    echo '<a href="delete_product.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-danger border" onclick="return confirm(\'Видалити?\')"><i class="fa-solid fa-trash"></i></a>';
                                    echo '</td>';
                                }
                                echo "</tr>";
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center py-5 text-muted">Нічого не знайдено</td></tr>';
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    let searchTimeout;
    function liveSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            let inputVal = document.getElementById('liveSearchInput').value;
            let statusVal = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
            let currentTab = '<?= $current_tab ?>';
            let currentSort = '<?= $sort ?>';
            let currentDir = '<?= $dir ?>';

            fetch(`index.php?ajax_search=1&tab=${currentTab}&search=${encodeURIComponent(inputVal)}&status=${statusVal}&sort=${currentSort}&dir=${currentDir}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('ordersTableBody').innerHTML = data;
                })
                .catch(error => console.error('Error:', error));
        }, 300);
    }
    document.getElementById('liveSearchInput').addEventListener('input', liveSearch);
    </script>

<?php else: ?>
    
    <style>
        .hero-section { background-image: url('uploads/banner_bg.jpg'); background-size: cover; background-position: center; height: 550px; display: flex; align-items: center; position: relative; border-radius: 0 0 20px 20px; margin-top: -30px; margin-bottom: 50px; }
        
        .hero-box { 
            background-color: var(--bg-card); /* Адаптивний фон */
            color: var(--text-main);          /* Адаптивний текст */
            padding: 40px; 
            border: 2px solid #1f2937; 
            max-width: 550px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
        }
        
        .hero-btn { background-color: #6366f1; color: white; padding: 12px 30px; text-decoration: none; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; transition: all 0.2s; border-radius: 6px; margin-top: 15px; }
        .hero-btn:hover { background-color: #4f46e5; color: white; transform: translateY(-2px); }
        .full-width-hero { width: 100vw; position: relative; left: 50%; right: 50%; margin-left: -50vw; margin-right: -50vw; }
    </style>

    <div class="full-width-hero">
        <div class="hero-section">
            <div class="container">
                <div class="hero-box">
                    <h2 class="fw-bold mb-3 display-6" style="color: var(--text-main);">Ремонт та Продаж <br> Смартфонів</h2>
                    <p class="text-secondary mb-3" style="color: var(--text-muted) !important;">Сервісний центр <b>MobiMaster</b>.</p>
                    <a href="user_add_repair.php" class="hero-btn shadow">ЗАПИСАТИСЬ НА РЕМОНТ</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h2 class="text-primary fw-light mb-3">Ремонт телефонів будь-якої складності</h2>
                <p class="text-secondary">Ми не обмежуємось лише однією маркою. У випадку виявлення виходу з ладу динаміків, екрану чи акумулятору, ремонт не займе багато часу. Ціна на заміну екрану телефону також приємно здивує. Ремонт телефонів у Львові в сервісному центрі MobiMaster є оптимальною альтернативою купівлі нового смартфону. Компанія Reboot service – це точна діагностика та професійний ремонт смартфонів та іншої техніки з гарантією якості. Завдяки багаторічному досвіду роботи та використанню нового та високоякісного обладнання, ми швидко усунемо несправності будь-якого рівня складності.</p>
            </div>
            <hr class="my-5" style="border-top: 2px solid #6366f1; opacity: 0.3;">
            <div class="text-center mb-5">
                <h2 class="text-primary fw-light mb-3">Магазин техніки та Trade-in</h2>
                <p class="text-secondary">Бажаєте оновити свій смартфон? Завітайте до нашого <a href="user_buy.php">онлайн-магазину</a>!</p>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php include 'footer.php'; ?>