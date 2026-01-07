<?php
// reports.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Встановлюємо часовий пояс
date_default_timezone_set('Europe/Kiev');

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// --- ФУНКЦІЯ СОРТУВАННЯ ---
if (!function_exists('getArrow')) {
    function getArrow($col, $currentSort, $currentDir) {
        if ($col !== $currentSort) return ' <i class="fa-solid fa-sort opacity-25"></i>';
        return $currentDir === 'asc' ? ' <i class="fa-solid fa-arrow-up-long"></i>' : ' <i class="fa-solid fa-arrow-down-long"></i>';
    }
}

// ==========================================
// 1. ДАНІ ДЛЯ ГРАФІКІВ (ОНОВЛЕНО)
// ==========================================

// А) Графік КІЛЬКОСТІ ЗАМОВЛЕНЬ (Останні 7 днів)
$chart_labels = [];
$chart_data = [];
$total_orders_week = 0;

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    // Рахуємо просто кількість записів у таблиці orders за конкретну дату
    $sql_chart = "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?";
    $stmt = $pdo->prepare($sql_chart);
    $stmt->execute([$date]);
    $count = $stmt->fetchColumn();
    
    $chart_labels[] = date('d.m', strtotime($date)); // Наприклад "10.12"
    $chart_data[] = (int)$count;
    $total_orders_week += $count;
}

// Б) Статуси
$status_counts = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$stat_new = $status_counts['new'] ?? 0;
$stat_work = $status_counts['in_progress'] ?? 0;
$stat_done = $status_counts['done'] ?? 0;


// ==========================================
// 2. ТАБЛИЦІ
// ==========================================

// --- Майстри ---
$emp_sort = $_GET['sort_emp'] ?? 'sum'; $emp_dir = $_GET['dir_emp'] ?? 'desc';
$sql_emp = "SELECT e.full_name as name, COUNT(o.id) as count, COALESCE(SUM(o.final_price), 0) as sum FROM employees e LEFT JOIN orders o ON e.id = o.employee_id AND o.status = 'done' GROUP BY e.id";
$employees = $pdo->query($sql_emp)->fetchAll(PDO::FETCH_ASSOC);
usort($employees, function($a, $b) use ($emp_sort, $emp_dir) {
    if ($a[$emp_sort] == $b[$emp_sort]) return 0;
    return ($emp_dir == 'asc') ? ($a[$emp_sort] <=> $b[$emp_sort]) : ($b[$emp_sort] <=> $a[$emp_sort]);
});

// --- Послуги ---
$svc_sort = $_GET['sort_svc'] ?? 'cnt'; $svc_dir = $_GET['dir_svc'] ?? 'desc';
$allowed_svc = ['name'=>'name','cnt'=>'cnt','sum'=>'sum']; $orderBySvc = $allowed_svc[$svc_sort] ?? 'cnt'; $dirSvc = ($svc_dir==='asc')?'ASC':'DESC';
try {
    $top_services = $pdo->query("SELECT COALESCE(NULLIF(o.problem_description, ''), o.device_model) as name, COUNT(o.id) as cnt, SUM(o.final_price) as sum FROM orders o WHERE o.status='done' AND o.item_id IS NULL GROUP BY name ORDER BY $orderBySvc $dirSvc LIMIT 10")->fetchAll();
} catch(Exception $e) { $top_services = []; }

// --- Клієнти ---
$cl_sort = $_GET['sort_cl'] ?? 'spent'; $cl_dir = $_GET['dir_cl'] ?? 'desc';
$allowed_cl = ['name'=>'c.full_name','orders'=>'total_orders','spent'=>'total_spent']; $orderByCl = $allowed_cl[$cl_sort] ?? 'total_spent'; $dirCl = ($cl_dir==='asc')?'ASC':'DESC';
try {
    $clients_stats = $pdo->query("SELECT c.full_name as name, c.phone, COUNT(o.id) as total_orders, COALESCE(SUM(CASE WHEN o.status='done' THEN o.final_price ELSE 0 END), 0) as total_spent FROM clients c JOIN orders o ON c.id = o.client_id GROUP BY c.id ORDER BY $orderByCl $dirCl LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $clients_stats = []; }

// --- Продажі ---
$sale_sort = $_GET['sort_sale'] ?? 'id'; $sale_dir = $_GET['dir_sale'] ?? 'desc';
$allowed_sale = ['name'=>'name','price'=>'price']; $orderBySale = $allowed_sale[$sale_sort] ?? 'id'; $dirSale = ($sale_dir==='asc')?'ASC':'DESC';
try {
    $sold_items = $pdo->query("SELECT * FROM products_services WHERE type='product' AND is_sold=1 ORDER BY $orderBySale $dirSale")->fetchAll();
} catch(Exception $e) { $sold_items = []; }
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    .stat-card {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        border-radius: 16px;
        padding: 25px;
        height: 100%;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }
    .table-container {
        background: var(--bg-card);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    h5 { color: var(--text-main); font-weight: 800; margin-bottom: 15px; font-size: 1.1rem; }
    .sort-link { text-decoration: none; color: var(--text-main); font-weight: 700; }
    .sort-link:hover { color: #6366f1; }
</style>

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1" style="color: #6366f1;">
                <i class="fa-solid "></i>Аналітика
            </h2>
            <p class="text-secondary mb-0">Статистика та фінансові показники</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
            <i class="fa-solid fa-arrow-left me-2"></i> На головну
        </a>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="mb-1">Активність (7 днів)</h5>
                        <p class="text-secondary small mb-0">Кількість нових замовлень</p>
                    </div>
                    <h3 class="fw-bold text-primary"><?= $total_orders_week ?> <small class="text-muted fs-6 fw-normal">замовлень</small></h3>
                </div>
                <div id="ordersChart"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="stat-card">
                <h5 class="mb-4">Розподіл статусів</h5>
                <div id="statusChart" class="d-flex justify-content-center"></div>
                <div class="text-center mt-3">
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                        Всього в базі: <b><?= array_sum($status_counts) ?></b>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="table-container">
                <div class="p-3 border-bottom border-secondary border-opacity-10">
                    <h5 class="mb-0 text-success"><i class="fa-solid fa-users me-2"></i> Ефективність майстрів</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th><a href="?sort_emp=name&dir_emp=<?= ($emp_sort == 'name' && $emp_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Ім'я <?= getArrow('name', $emp_sort, $emp_dir) ?></a></th>
                                <th class="text-center"><a href="?sort_emp=count&dir_emp=<?= ($emp_sort == 'count' && $emp_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">К-сть <?= getArrow('count', $emp_sort, $emp_dir) ?></a></th>
                                <th class="text-end"><a href="?sort_emp=sum&dir_emp=<?= ($emp_sort == 'sum' && $emp_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Сума <?= getArrow('sum', $emp_sort, $emp_dir) ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($employees as $emp): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= htmlspecialchars($emp['name']) ?></td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= $emp['count'] ?></span></td>
                                    <td class="text-end fw-bold text-success pe-4"><?= number_format($emp['sum'], 0, ' ', ' ') ?> ₴</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="table-container">
                <div class="p-3 border-bottom border-secondary border-opacity-10">
                    <h5 class="mb-0 text-dark"><i class="fa-solid fa-screwdriver-wrench me-2"></i> Популярні послуги</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th><a href="?sort_svc=name&dir_svc=<?= ($svc_sort == 'name' && $svc_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Послуга <?= getArrow('name', $svc_sort, $svc_dir) ?></a></th>
                                <th class="text-center"><a href="?sort_svc=cnt&dir_svc=<?= ($svc_sort == 'cnt' && $svc_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Разів <?= getArrow('cnt', $svc_sort, $svc_dir) ?></a></th>
                                <th class="text-end"><a href="?sort_svc=sum&dir_svc=<?= ($svc_sort == 'sum' && $svc_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Дохід <?= getArrow('sum', $svc_sort, $svc_dir) ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_services as $svc): ?>
                                <tr>
                                    <td class="ps-4"><?= mb_strimwidth(htmlspecialchars($svc['name']), 0, 80, '...') ?></td>
                                    <td class="text-center"><span class="badge bg-warning text-dark"><?= $svc['cnt'] ?></span></td>
                                    <td class="text-end fw-bold pe-4"><?= number_format($svc['sum'], 0, ' ', ' ') ?> ₴</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="table-container">
                <div class="p-3 border-bottom border-secondary border-opacity-10">
                    <h5 class="mb-0 text-info"><i class="fa-solid fa-user-group me-2"></i> Топ клієнтів</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4"><a href="?sort_cl=name&dir_cl=<?= ($cl_sort == 'name' && $cl_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Клієнт <?= getArrow('name', $cl_sort, $cl_dir) ?></a></th>
                                <th>Телефон</th>
                                <th class="text-center"><a href="?sort_cl=orders&dir_cl=<?= ($cl_sort == 'orders' && $cl_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Заявок <?= getArrow('orders', $cl_sort, $cl_dir) ?></a></th>
                                <th class="text-end pe-4"><a href="?sort_cl=spent&dir_cl=<?= ($cl_sort == 'spent' && $cl_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Витрачено <?= getArrow('spent', $cl_sort, $cl_dir) ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients_stats as $cl): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= htmlspecialchars($cl['name']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($cl['phone']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary rounded-pill"><?= $cl['total_orders'] ?></span></td>
                                    <td class="text-end pe-4 fw-bold text-success"><?= number_format($cl['total_spent'], 0, ' ', ' ') ?> ₴</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="table-container">
                <div class="p-3 border-bottom border-secondary border-opacity-10">
                    <h5 class="mb-0 text-primary"><i class="fa-solid fa-cart-shopping me-2"></i> Продані товари</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4"><a href="?sort_sale=name&dir_sale=<?= ($sale_sort == 'name' && $sale_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Товар <?= getArrow('name', $sale_sort, $sale_dir) ?></a></th>
                                <th class="text-center">Статус</th>
                                <th class="text-end pe-4"><a href="?sort_sale=price&dir_sale=<?= ($sale_sort == 'price' && $sale_dir == 'desc') ? 'asc' : 'desc' ?>" class="sort-link">Ціна <?= getArrow('price', $sale_sort, $sale_dir) ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sold_items as $item): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary">Продано</span></td>
                                    <td class="text-end pe-4 fw-bold text-success">+<?= number_format($item['price'], 0, ' ', ' ') ?> ₴</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    const isDark = document.body.classList.contains('dark-mode');
    const labelColor = isDark ? '#e5e7eb' : '#374151';

    // 1. Графік ЗАМОВЛЕНЬ (Area Chart)
    var optionsOrders = {
        series: [{
            name: 'Замовлень',
            data: <?= json_encode($chart_data) ?>
        }],
        chart: {
            type: 'area',
            height: 350,
            toolbar: { show: false },
            fontFamily: 'Inter, sans-serif'
        },
        colors: ['#6366f1'], // Основний колір
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.2,
                stops: [0, 90, 100]
            }
        },
        dataLabels: { enabled: true },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: {
            categories: <?= json_encode($chart_labels) ?>,
            labels: { style: { colors: labelColor } }
        },
        yaxis: {
            labels: { 
                formatter: (value) => { return value.toFixed(0) }, // Цілі числа
                style: { colors: labelColor } 
            }
        },
        grid: {
            borderColor: isDark ? '#374151' : '#e5e7eb',
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
        }
    };

    var chartOrders = new ApexCharts(document.querySelector("#ordersChart"), optionsOrders);
    chartOrders.render();

    // 2. Графік статусів (Donut)
    var optionsStatus = {
        series: [<?= $stat_new ?>, <?= $stat_work ?>, <?= $stat_done ?>],
        labels: ['Нові', 'В роботі', 'Готові'],
        chart: {
            type: 'donut',
            height: 350,
            fontFamily: 'Inter, sans-serif'
        },
        colors: ['#3b82f6', '#f59e0b', '#10b981'],
        legend: {
            position: 'bottom',
            labels: { colors: labelColor }
        },
        plotOptions: {
            pie: {
                donut: {
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Всього',
                            color: labelColor
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false }
    };

    var chartStatus = new ApexCharts(document.querySelector("#statusChart"), optionsStatus);
    chartStatus.render();
</script>

<?php include 'footer.php'; ?>