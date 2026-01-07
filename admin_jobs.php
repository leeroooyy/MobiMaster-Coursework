<?php
// admin_jobs.php

// Вмикаємо показ помилок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Перевірка адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Функція логування
function safeLog($pdo, $action, $details) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $action, $details]);
    } catch (Exception $e) {}
}

// --- ЛОГІКА: ПРИЙНЯТИ НА РОБОТУ ---
if (isset($_POST['approve_app'])) {
    try {
        $app_id = $_POST['app_id'];
        
        $stmt = $pdo->prepare("SELECT * FROM job_applications WHERE id = ?");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch();

        if ($app) {
            $name = $app['name'];
            $phone = $app['phone'];
            $position = $app['vacancy'] . " (Стажер)";
            $default_salary = 0; 

            // hire_date замість created_at
            $sql_emp = "INSERT INTO employees (full_name, phone, position, salary, hire_date) VALUES (?, ?, ?, ?, NOW())";
            $stmt_emp = $pdo->prepare($sql_emp);
            
            if ($stmt_emp->execute([$name, $phone, $position, $default_salary])) {
                $pdo->prepare("UPDATE job_applications SET status = 'approved' WHERE id = ?")->execute([$app_id]);
                safeLog($pdo, 'HR_APPROVE', "Прийнято на роботу: $name ($position)");
                echo "<script>alert('Успішно! Нового майстра додано.'); window.location.href='admin_jobs.php';</script>";
                exit;
            }
        }
    } catch (PDOException $e) {
        die("<div style='padding:20px; color:red;'>Помилка бази даних: " . $e->getMessage() . "</div>");
    }
}

// --- ЛОГІКА: ВІДХИЛИТИ ---
if (isset($_GET['reject_id'])) {
    try {
        $rej_id = $_GET['reject_id'];
        
        $stmt = $pdo->prepare("SELECT name FROM job_applications WHERE id = ?");
        $stmt->execute([$rej_id]);
        $res = $stmt->fetch();
        $cand_name = $res['name'] ?? 'Unknown';

        $pdo->prepare("UPDATE job_applications SET status = 'rejected' WHERE id = ?")->execute([$rej_id]);
        safeLog($pdo, 'HR_REJECT', "Відхилено заявку: $cand_name");

        header("Location: admin_jobs.php");
        exit;
    } catch (Exception $e) {
        die("Помилка: " . $e->getMessage());
    }
}

include 'header.php';

// --- СОРТУВАННЯ ---
$sort = $_GET['sort'] ?? 'created_at';
$dir = $_GET['dir'] ?? 'desc';
$allowed = ['name', 'vacancy', 'experience', 'created_at', 'status'];
if (!in_array($sort, $allowed)) $sort = 'created_at';
$dir = ($dir === 'asc') ? 'asc' : 'desc';

function sortLink($column, $title, $currentSort, $currentDir) {
    $newDir = ($currentSort == $column && $currentDir == 'desc') ? 'asc' : 'desc';
    $icon = '<i class="fa-solid fa-sort opacity-25 ms-1"></i>'; // прибрав text-muted, щоб колір брався від батька
    if ($currentSort == $column) {
        $icon = ($currentDir == 'asc') 
            ? '<i class="fa-solid fa-arrow-up-long text-primary ms-1"></i>' 
            : '<i class="fa-solid fa-arrow-down-long text-primary ms-1"></i>';
    }
    // Використовуємо клас text-reset або змінні кольорів
    return '<a href="?sort=' . $column . '&dir=' . $newDir . '" class="text-decoration-none fw-bold d-flex align-items-center" style="font-size: 0.75rem; letter-spacing: 0.5px; color: var(--text-muted);">' . mb_strtoupper($title) . $icon . '</a>';
}

$new_apps = $pdo->query("SELECT * FROM job_applications WHERE status = 'new' ORDER BY $sort $dir")->fetchAll();
$history_apps = $pdo->query("SELECT * FROM job_applications WHERE status != 'new' ORDER BY $sort $dir LIMIT 50")->fetchAll();
?>

<style>
    /* Прибираємо жорсткий фон body, використовуємо змінні */
    
    .card {
        /* Фон і рамка беруться з header.php через глобальні змінні */
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    
    .card-header-custom {
        background-color: #6366f1; /* Основний бренд-колір залишаємо */
        color: white;
        padding: 16px 24px;
        font-weight: 700;
        font-size: 1.1rem;
    }

    /* Адаптація таблиці під тему */
    .table thead th {
        /* Фон заголовків таблиці береться з глобальних стилів header.php (.bg-light) */
        border-bottom: 2px solid var(--border-color);
        padding-top: 14px;
        padding-bottom: 14px;
    }

    .table tbody td {
        vertical-align: middle;
        padding: 16px 12px;
        border-bottom: 1px solid var(--border-color);
    }

    .badge-vacancy {
        background-color: rgba(99, 102, 241, 0.1); /* Напівпрозорий синій, підходить для обох тем */
        color: #6366f1;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid rgba(99, 102, 241, 0.2);
    }

    /* Кнопки */
    .btn-action {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid transparent;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .msg-box {
        max-width: 300px;
        font-size: 0.9rem;
        line-height: 1.4;
        color: var(--text-muted); /* Адаптивний колір тексту */
    }
    
    /* Текст імені */
    .fw-bold.text-dark {
        color: var(--text-main) !important; /* Перевизначаємо bootstrap text-dark */
    }
</style>

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold" style="color: #4338ca;"><i class="fa-solid"></i>HR: Кандидати</h2>
            <p class="text-secondary mb-0" style="color: var(--text-muted) !important;">Керування заявками та найм персоналу</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
            <i class="fa-solid fa-arrow-left me-2"></i> На головну
        </a>
    </div>

    <div class="card mb-5">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-inbox me-2"></i> Нові заявки</span>
            <span class="badge bg-white text-primary rounded-pill px-3"><?= count($new_apps) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4"><?= sortLink('name', 'Кандидат', $sort, $dir) ?></th>
                        <th><?= sortLink('vacancy', 'Вакансія', $sort, $dir) ?></th>
                        <th><?= sortLink('experience', 'Стаж', $sort, $dir) ?></th>
                        <th class="text-secondary fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px; color: var(--text-muted) !important;">ПОВІДОМЛЕННЯ</th>
                        <th><?= sortLink('created_at', 'Дата', $sort, $dir) ?></th>
                        <th class="text-end pe-4 text-secondary fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px; color: var(--text-muted) !important;">ДІЇ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($new_apps) > 0): ?>
                        <?php foreach ($new_apps as $row): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="small mt-1" style="color: var(--text-muted);"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($row['phone']) ?></div>
                                <?php if($row['email']): ?>
                                    <div class="small" style="color: var(--text-muted);"><i class="fa-solid fa-envelope me-1"></i> <?= htmlspecialchars($row['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-vacancy"><?= htmlspecialchars($row['vacancy']) ?></span></td>
                            
                            <td class="fw-bold" style="color: var(--text-main);">
                                <?= htmlspecialchars($row['experience'] ?: '—') ?>
                            </td>

                            <td>
                                <div class="msg-box">
                                    <?php if($row['message']): ?>
                                        <?= nl2br(htmlspecialchars($row['message'])) ?>
                                    <?php else: ?>
                                        <span class="fst-italic small" style="color: var(--text-muted);">Повідомлення відсутнє</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td class="small" style="white-space: nowrap; color: var(--text-muted);">
                                <?= date('d.m.Y', strtotime($row['created_at'])) ?><br>
                                <span class="opacity-75"><?= date('H:i', strtotime($row['created_at'])) ?></span>
                            </td>
                            
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <form method="POST">
                                        <input type="hidden" name="app_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="approve_app" value="1">
                                        <button type="submit" class="btn btn-success btn-action text-white" style="background-color: #10b981;" title="Прийняти" onclick="return confirm('Прийняти на роботу?')">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </form>
                                    
                                    <a href="?reject_id=<?= $row['id'] ?>" class="btn btn-danger btn-action text-white" style="background-color: #ef4444;" title="Відхилити" onclick="return confirm('Відхилити заявку?')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5" style="color: var(--text-muted);">Нових заявок немає.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h5 class="fw-bold mb-3 ms-1" style="color: var(--text-muted);">Архів заявок</h5>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4"><?= sortLink('name', 'Кандидат', $sort, $dir) ?></th>
                        <th><?= sortLink('vacancy', 'Вакансія', $sort, $dir) ?></th>
                        <th><?= sortLink('experience', 'Стаж', $sort, $dir) ?></th>
                        <th><?= sortLink('status', 'Статус', $sort, $dir) ?></th>
                        <th class="text-end pe-4"><?= sortLink('created_at', 'Дата', $sort, $dir) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_apps as $row): ?>
                    <tr>
                        <td class="ps-4 fw-bold opacity-75" style="color: var(--text-main);">
                            <?= htmlspecialchars($row['name']) ?>
                        </td>
                        <td><?= htmlspecialchars($row['vacancy']) ?></td>
                        <td class="small" style="color: var(--text-muted);"><?= htmlspecialchars($row['experience'] ?: '—') ?></td>
                        <td>
                            <?php if($row['status'] == 'approved'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1"><i class="fa-solid fa-check me-1"></i> Прийнято</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1"><i class="fa-solid fa-ban me-1"></i> Відхилено</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4 small" style="color: var(--text-muted);"><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>