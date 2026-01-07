<?php
// salary.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- ЛОГУВАННЯ ---
function safeLog($pdo, $action, $details) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $action, $details]);
    } catch (Exception $e) {}
}

// --- ОБРОБКА: ОНОВЛЕННЯ СТАВКИ ТА ВІДСОТКА ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_finances'])) {
    $emp_id = $_POST['emp_id'];
    $new_salary = floatval($_POST['new_salary']);
    $new_percent = intval($_POST['new_percent']); // Отримуємо новий відсоток
    
    // Валідація відсотка (0-100)
    if ($new_percent < 0) $new_percent = 0;
    if ($new_percent > 100) $new_percent = 100;

    $stmtName = $pdo->prepare("SELECT full_name FROM employees WHERE id = ?");
    $stmtName->execute([$emp_id]);
    $empName = $stmtName->fetchColumn();

    // Оновлюємо і ставку, і відсоток
    $stmtUpd = $pdo->prepare("UPDATE employees SET salary = ?, bonus_percent = ? WHERE id = ?");
    if ($stmtUpd->execute([$new_salary, $new_percent, $emp_id])) {
        safeLog($pdo, 'UPDATE', "Оновлено фінанси для $empName: Ставка $new_salary, Відсоток $new_percent%");
        $redirect_url = "salary.php";
        if (isset($_GET['sort'])) $redirect_url .= "?sort=" . $_GET['sort'] . "&dir=" . ($_GET['dir'] ?? 'desc');
        header("Location: $redirect_url");
        exit;
    }
}

// --- ОБРОБКА: ЗВІЛЬНЕННЯ СПІВРОБІТНИКА ---
if (isset($_POST['delete_emp'])) {
    $del_id = $_POST['del_id'];
    
    $stmtName = $pdo->prepare("SELECT full_name FROM employees WHERE id = ?");
    $stmtName->execute([$del_id]);
    $delName = $stmtName->fetchColumn();

    $stmtDel = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    if ($stmtDel->execute([$del_id])) {
        safeLog($pdo, 'DELETE', "Звільнено співробітника: $delName");
        echo "<script>alert('Співробітника $delName успішно звільнено.'); window.location.href='salary.php';</script>";
        exit;
    }
}

include 'header.php';

// --- ФУНКЦІЯ СТРІЛОЧОК ---
if (!function_exists('getArrow')) {
    function getArrow($col, $currentSort, $currentDir) {
        if ($col !== $currentSort) return ' <i class="fa-solid fa-sort opacity-25"></i>';
        return $currentDir === 'asc' ? ' <i class="fa-solid fa-arrow-up-long"></i>' : ' <i class="fa-solid fa-arrow-down-long"></i>';
    }
}

// 1. ОТРИМУЄМО ДАНІ (Додано bonus_percent)
$sql = "
    SELECT 
        e.id, e.full_name, e.position, e.salary as base_salary, e.bonus_percent,
        COALESCE(SUM(o.final_price), 0) as total_revenue,
        COUNT(o.id) as orders_count
    FROM employees e
    LEFT JOIN orders o ON e.id = o.employee_id AND o.status = 'done'
    GROUP BY e.id
";

try {
    $stmt = $pdo->query($sql);
    $raw_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='container mt-3'><div class='alert alert-danger'>Помилка БД (Виконайте SQL запит додавання колонки!): " . $e->getMessage() . "</div></div>";
    exit;
}

// 2. ОБЧИСЛЕННЯ
$staff_data = [];
foreach ($raw_staff as $person) {
    // Якщо в базі ще NULL (для старих записів), ставимо 20 за замовчуванням
    $percent = isset($person['bonus_percent']) ? $person['bonus_percent'] : 20;
    
    $revenue = $person['total_revenue'];
    $rate    = $percent / 100;
    $bonus   = $revenue * $rate;
    $total   = $person['base_salary'] + $bonus;

    $staff_data[] = [
        'id'          => $person['id'],
        'full_name'   => $person['full_name'],
        'position'    => $person['position'],
        'orders_count'=> $person['orders_count'],
        'base_salary' => $person['base_salary'],
        'percent'     => $percent, // Індивідуальний відсоток
        'revenue'     => $revenue,
        'bonus'       => $bonus,
        'total_pay'   => $total
    ];
}

// 3. СОРТУВАННЯ
$sort_col = $_GET['sort'] ?? 'total_pay'; 
$sort_dir = $_GET['dir'] ?? 'desc';       

usort($staff_data, function($a, $b) use ($sort_col, $sort_dir) {
    if ($a[$sort_col] == $b[$sort_col]) return 0;
    if ($sort_dir === 'asc') {
        return ($a[$sort_col] < $b[$sort_col]) ? -1 : 1;
    } else {
        return ($a[$sort_col] > $b[$sort_col]) ? -1 : 1;
    }
});
?>

<style>
    .btn-save {
        background-color: #e0e7ff; color: #4338ca; border: none; border-radius: 6px; padding: 6px 10px; transition: all 0.2s; cursor: pointer;
    }
    .btn-save:hover { background-color: #4338ca; color: white; }
    
    .btn-del {
        background-color: #fee2e2; color: #ef4444; border: none; border-radius: 6px; padding: 6px 10px; transition: all 0.2s; cursor: pointer;
    }
    .btn-del:hover { background-color: #ef4444; color: white; }

    .input-salary {
        border: 1px solid #d1d5db; border-radius: 6px; font-weight: 600; color: #1f2937; width: 90px; text-align: center; padding: 4px;
    }
    .input-percent {
        border: 1px solid #d1d5db; border-radius: 6px; font-weight: 600; color: #4b5563; width: 60px; text-align: center; padding: 4px;
    }
    .input-salary:focus, .input-percent:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
</style>

<div class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold" style="color: #6366f1;"><i class="fa-solid "></i>Розрахунок зарплати</h2>
            <p class="text-secondary mb-0">Фінансовий звіт та керування ставками</p>
        </div>
        
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase text-secondary small fw-bold">
                        <tr>
                            <th class="ps-4 py-3"><a href="?sort=full_name&dir=<?= ($sort_col=='full_name'&&$sort_dir=='desc')?'asc':'desc' ?>" class="text-decoration-none text-secondary">Співробітник <?= getArrow('full_name', $sort_col, $sort_dir) ?></a></th>
                            <th class="text-center">Посада</th>
                            
                            <th class="text-center">Фінанси (Ставка / %)</th>
                            
                            <th class="text-end"><a href="?sort=revenue&dir=<?= ($sort_col=='revenue'&&$sort_dir=='desc')?'asc':'desc' ?>" class="text-decoration-none text-secondary">Зроблено робіт <?= getArrow('revenue', $sort_col, $sort_dir) ?></a></th>
                            <th class="text-end text-primary"><a href="?sort=bonus&dir=<?= ($sort_col=='bonus'&&$sort_dir=='desc')?'asc':'desc' ?>" class="text-decoration-none text-primary">Бонус (Сума) <?= getArrow('bonus', $sort_col, $sort_dir) ?></a></th>
                            <th class="text-end"><a href="?sort=total_pay&dir=<?= ($sort_col=='total_pay'&&$sort_dir=='desc')?'asc':'desc' ?>" class="text-decoration-none text-success">РАЗОМ <?= getArrow('total_pay', $sort_col, $sort_dir) ?></a></th>
                            <th class="text-end pe-4">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_data as $person): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex justify-content-center align-items-center fw-bold me-3 text-white shadow-sm" style="width: 40px; height: 40px; font-size: 1.1rem; background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                            <?= mb_substr($person['full_name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold">
                                                <a href="employee_orders.php?id=<?= $person['id'] ?>" class="text-decoration-none text-dark hover-primary"><?= htmlspecialchars($person['full_name']) ?></a>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.8rem;">Замовлень: <strong><?= $person['orders_count'] ?></strong></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-white text-secondary border fw-normal text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                        <?= htmlspecialchars($person['position']) ?>
                                    </span>
                                </td>
                                
                                <td class="text-center">
                                    <form method="POST" class="d-flex align-items-center justify-content-center gap-2">
                                        <input type="hidden" name="emp_id" value="<?= $person['id'] ?>">
                                        <input type="hidden" name="update_finances" value="1">
                                        
                                        <div class="input-group input-group-sm" style="width: 110px;">
                                            <span class="input-group-text bg-light border-end-0 text-muted">₴</span>
                                            <input type="number" name="new_salary" value="<?= floor($person['base_salary']) ?>" class="form-control fw-bold text-center border-start-0" min="0" step="100" title="Ставка">
                                        </div>

                                        <div class="input-group input-group-sm" style="width: 80px;">
                                            <input type="number" name="new_percent" value="<?= $person['percent'] ?>" class="form-control fw-bold text-center text-primary border-end-0" min="0" max="100" title="Відсоток">
                                            <span class="input-group-text bg-light border-start-0 text-primary">%</span>
                                        </div>

                                        <button type="submit" class="btn-save shadow-sm" title="Зберегти зміни"><i class="fa-solid fa-floppy-disk"></i></button>
                                    </form>
                                </td>

                                <td class="text-end text-muted"><?= number_format($person['revenue'], 0, ' ', ' ') ?> ₴</td>
                                <td class="text-end text-primary fw-bold" style="font-size: 1.05rem;">+<?= number_format($person['bonus'], 0, ' ', ' ') ?> ₴</td>
                                <td class="text-end">
                                    <span class="badge bg-success fs-6 shadow-sm px-3 py-2"><?= number_format($person['total_pay'], 0, ' ', ' ') ?> ₴</span>
                                </td>
                                <td class="text-end pe-4">
                                    <form method="POST" onsubmit="return confirm('Ви впевнені, що хочете звільнити <?= htmlspecialchars($person['full_name']) ?>? Ця дія незворотна!');">
                                        <input type="hidden" name="del_id" value="<?= $person['id'] ?>">
                                        <input type="hidden" name="delete_emp" value="1">
                                        <button type="submit" class="btn-del shadow-sm" title="Звільнити"><i class="fa-solid fa-user-xmark"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($staff_data)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Співробітників не знайдено.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>