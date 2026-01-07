<?php
// teacher_report.php

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

include 'header.php';

$query = $_GET['q'] ?? '';      // Рядок пошуку
$type  = $_GET['type'] ?? '';   // Тип об'єкта
$id    = $_GET['id'] ?? '';     // ID об'єкта

$matches = []; 
$dossier = []; 

// --- 1. ПОШУК (Логіка не змінилась, лише стиль відображення нижче) ---
if ($query && empty($id)) {
    $param = "%$query%";
    
    // Співробітники
    $stmt = $pdo->prepare("SELECT id, full_name as name, 'employee' as type, position as info FROM employees WHERE full_name LIKE ?");
    $stmt->execute([$param]);
    $matches = array_merge($matches, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Клієнти
    $stmt = $pdo->prepare("SELECT id, full_name as name, 'client' as type, phone as info FROM clients WHERE full_name LIKE ? OR phone LIKE ?");
    $stmt->execute([$param, $param]);
    $matches = array_merge($matches, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Замовлення
    $stmt = $pdo->prepare("SELECT id, CONCAT('#', id, ' ', device_model) as name, 'order' as type, problem_description as info FROM orders WHERE device_model LIKE ? OR id LIKE ?");
    $stmt->execute([$param, $query]); 
    $matches = array_merge($matches, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Товари
    $stmt = $pdo->prepare("SELECT id, name, 'product' as type, CONCAT(price, ' ₴') as info FROM products_services WHERE name LIKE ? AND type='product'");
    $stmt->execute([$param]);
    $matches = array_merge($matches, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Кандидати
    $stmt = $pdo->prepare("SELECT id, name, 'candidate' as type, vacancy as info FROM job_applications WHERE name LIKE ?");
    $stmt->execute([$param]);
    $matches = array_merge($matches, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// --- 2. ФОРМУВАННЯ ДОСЬЄ ---
if ($id && $type) {
    
    // === КЛІЄНТ (Найбільші зміни тут) ===
    if ($type == 'client') {
        $stmt = $pdo->prepare("SELECT full_name as 'Ім\'я', phone as 'Телефон', email as 'Email', address as 'Адреса', registration_date as 'Дата реєстрації' FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $dossier['info'] = $stmt->fetch(PDO::FETCH_ASSOC);
        $dossier['title'] = $dossier['info']['Ім\'я'] ?? 'Клієнт';

        // 1. РЕМОНТИ (Тільки послуги або де item_id NULL)
        $stmt = $pdo->prepare("
            SELECT o.id, o.device_model, o.status, o.final_price, o.created_at 
            FROM orders o
            LEFT JOIN products_services ps ON o.item_id = ps.id
            WHERE o.client_id = ? AND (ps.type != 'product' OR ps.type IS NULL)
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$id]);
        $dossier['repairs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. ПОКУПКИ (Тільки товари)
        $stmt = $pdo->prepare("
            SELECT o.id, ps.name as product_name, ps.image, o.final_price, o.created_at
            FROM orders o
            JOIN products_services ps ON o.item_id = ps.id
            WHERE o.client_id = ? AND ps.type = 'product'
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$id]);
        $dossier['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. ПОВІДОМЛЕННЯ
        $phone = $dossier['info']['Телефон'];
        $stmt = $pdo->prepare("SELECT message, created_at FROM feedback WHERE phone = ? ORDER BY created_at DESC");
        $stmt->execute([$phone]);
        $dossier['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. ВІДГУКИ (Нове!)
        $name = $dossier['info']['Ім\'я'];
        $stmt = $pdo->prepare("SELECT rating, comment, created_at FROM reviews WHERE user_name = ? ORDER BY created_at DESC");
        $stmt->execute([$name]);
        $dossier['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === СПІВРОБІТНИК ===
    elseif ($type == 'employee') {
        $stmt = $pdo->prepare("SELECT full_name as 'Ім\'я', position as 'Посада', phone as 'Телефон', salary as 'Ставка' FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $dossier['info'] = $stmt->fetch(PDO::FETCH_ASSOC);
        $dossier['title'] = $dossier['info']['Ім\'я'];
        
        $stmt = $pdo->prepare("SELECT id, device_model, status, final_price FROM orders WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$id]);
        $dossier['related']['🛠️ Останні роботи'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === ІНШІ ТИПИ (Замовлення, Товар, Кандидат) ===
    elseif ($type == 'order') {
        $stmt = $pdo->prepare("SELECT id as 'ID', device_model as 'Пристрій', problem_description as 'Проблема', status as 'Статус', final_price as 'Ціна' FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $dossier['info'] = $stmt->fetch(PDO::FETCH_ASSOC);
        $dossier['title'] = "Замовлення #" . $id;
    }
    elseif ($type == 'product') {
        $stmt = $pdo->prepare("SELECT name as 'Назва', price as 'Ціна', is_sold as 'Продано' FROM products_services WHERE id = ?");
        $stmt->execute([$id]);
        $dossier['info'] = $stmt->fetch(PDO::FETCH_ASSOC);
        $dossier['title'] = $dossier['info']['Назва'];
    }
    elseif ($type == 'candidate') {
        $stmt = $pdo->prepare("SELECT name as 'Ім\'я', phone as 'Телефон', vacancy as 'Вакансія' FROM job_applications WHERE id = ?");
        $stmt->execute([$id]);
        $dossier['info'] = $stmt->fetch(PDO::FETCH_ASSOC);
        $dossier['title'] = $dossier['info']['Ім\'я'];
    }
}
?>

<style>
    /* Стиль MobiMaster */
    .hero-search {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        padding: 40px 20px;
        border-radius: 0 0 30px 30px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
    }

    .custom-card {
        border: none;
        border-radius: 16px;
        background: white;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 24px;
        transition: transform 0.2s;
    }
    
    .result-card:hover { transform: translateY(-3px); }

    .dossier-header {
        background: #1f2937; /* Dark */
        color: white;
        padding: 30px;
        border-radius: 16px 16px 0 0;
    }

    .section-title {
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #9ca3af;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1f2937;
    }
    
    /* Таблиці */
    .table-custom thead th {
        background-color: #f9fafb;
        color: #6b7280;
        font-size: 0.75rem;
        text-transform: uppercase;
        border-bottom: 1px solid #e5e7eb;
    }
    .table-custom tbody td {
        vertical-align: middle;
        font-size: 0.95rem;
    }

    /* Маркери типів */
    .type-badge { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 1.2rem; }
    .bg-employee { background: #e0e7ff; color: #6366f1; }
    .bg-client { background: #d1fae5; color: #10b981; }
    .bg-order { background: #fef3c7; color: #f59e0b; }
    .bg-product { background: #fce7f3; color: #ec4899; }
</style>

<div class="hero-search text-center">
    <h2 class="fw-bold mb-2"><i class="fa-solid fa-magnifying-glass-chart me-2"></i> Розумна Вибірка</h2>
    <p class="opacity-75 mb-4">Пошук по всій базі: клієнти, товари, ремонти, співробітники</p>
    
    <form method="GET" class="d-flex justify-content-center">
        <div class="input-group input-group-lg shadow-lg" style="max-width: 600px; border-radius: 50px; overflow: hidden;">
            <span class="input-group-text bg-white border-0 ps-4"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" name="q" class="form-control border-0" placeholder="Введіть запит..." value="<?= htmlspecialchars($query) ?>" autocomplete="off">
            <button class="btn btn-dark px-4 fw-bold" type="submit" style="background-color: #1f2937;">Знайти</button>
        </div>
    </form>
</div>

<div class="container pb-5">

    <?php if ($query && empty($id)): ?>
        <div class="row g-3">
            <?php if (count($matches) === 0): ?>
                <div class="col-12 text-center py-5">
                    <i class="fa-regular fa-folder-open fa-3x text-muted opacity-25 mb-3"></i>
                    <h5 class="text-muted">Нічого не знайдено.</h5>
                </div>
            <?php endif; ?>

            <?php foreach ($matches as $m): ?>
            <div class="col-md-6 col-lg-4">
                <a href="?q=<?=urlencode($query)?>&id=<?=$m['id']?>&type=<?=$m['type']?>" class="text-decoration-none">
                    <div class="custom-card result-card p-3 d-flex align-items-center h-100">
                        <div class="type-badge me-3 bg-<?= $m['type'] ?>">
                            <?php 
                                if($m['type']=='employee') echo '<i class="fa-solid fa-user-tie"></i>'; 
                                elseif($m['type']=='client') echo '<i class="fa-solid fa-user"></i>';
                                elseif($m['type']=='order') echo '<i class="fa-solid fa-wrench"></i>';
                                elseif($m['type']=='product') echo '<i class="fa-solid fa-mobile"></i>';
                                else echo '<i class="fa-solid fa-circle"></i>';
                            ?>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($m['name']) ?></h6>
                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                                <?= $m['type'] ?> • <?= mb_strimwidth($m['info'], 0, 30, '...') ?>
                            </small>
                        </div>
                        <div class="ms-auto text-secondary"><i class="fa-solid fa-chevron-right"></i></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($id && !empty($dossier['info'])): ?>
        
        <div class="custom-card mb-4" style="border-radius: 16px;">
            <div class="dossier-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 mb-2 text-uppercase fw-bold px-3">
                        <?= strtoupper($type) ?>
                    </span>
                    <h2 class="fw-bold mb-0"><?= htmlspecialchars($dossier['title']) ?></h2>
                </div>
                <a href="teacher_report.php?q=<?=urlencode($query)?>" class="btn btn-outline-light btn-sm rounded-pill px-4 fw-bold">
                    <i class="fa-solid fa-xmark me-2"></i> Закрити
                </a>
            </div>
            
            <div class="p-4">
                <div class="row g-4">
                    <?php foreach($dossier['info'] as $key => $val): ?>
                        <div class="col-md-3">
                            <div class="info-label"><?= $key ?></div>
                            <div class="info-value"><?= htmlspecialchars($val ?? '-') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($type == 'client'): ?>
            <div class="row g-4">
                
                <div class="col-lg-6">
                    <div class="custom-card h-100">
                        <div class="card-body p-4">
                            <div class="section-title text-primary">
                                <i class="fa-solid fa-screwdriver-wrench"></i> Історія ремонтів
                            </div>
                            <?php if(!empty($dossier['repairs'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom mb-0">
                                        <thead><tr><th>ID</th><th>Пристрій</th><th>Статус</th><th class="text-end">Ціна</th></tr></thead>
                                        <tbody>
                                            <?php foreach($dossier['repairs'] as $rep): ?>
                                            <tr>
                                                <td class="text-muted small">#<?= $rep['id'] ?></td>
                                                <td class="fw-bold"><?= $rep['device_model'] ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= $rep['status'] ?></span></td>
                                                <td class="text-end fw-bold"><?= $rep['final_price'] ?> ₴</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small text-center py-3">Ремонтів не знайдено</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="custom-card h-100">
                        <div class="card-body p-4">
                            <div class="section-title text-success">
                                <i class="fa-solid fa-cart-shopping"></i> Історія покупок
                            </div>
                            <?php if(!empty($dossier['sales'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom mb-0">
                                        <thead><tr><th>Фото</th><th>Товар</th><th>Дата</th><th class="text-end">Ціна</th></tr></thead>
                                        <tbody>
                                            <?php foreach($dossier['sales'] as $sale): ?>
                                            <tr>
                                                <td>
                                                    <?php if($sale['image']): ?>
                                                        <img src="uploads/<?= $sale['image'] ?>" width="30" class="rounded">
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-box text-muted"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold"><?= $sale['product_name'] ?></td>
                                                <td class="text-muted small"><?= date('d.m.y', strtotime($sale['created_at'])) ?></td>
                                                <td class="text-end fw-bold text-success"><?= $sale['final_price'] ?> ₴</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small text-center py-3">Покупок не знайдено</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="custom-card h-100">
                        <div class="card-body p-4">
                            <div class="section-title text-warning">
                                <i class="fa-solid fa-star"></i> Відгуки клієнта
                            </div>
                            <?php if(!empty($dossier['reviews'])): ?>
                                <ul class="list-group list-group-flush">
                                <?php foreach($dossier['reviews'] as $rev): ?>
                                    <li class="list-group-item px-0 border-light">
                                        <div class="text-warning small mb-1">
                                            <?php for($i=0; $i<$rev['rating']; $i++) echo '<i class="fa-solid fa-star"></i>'; ?>
                                        </div>
                                        <div class="small text-dark fst-italic">"<?= $rev['comment'] ?>"</div>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></div>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small text-center py-3">Відгуків не залишено</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="custom-card h-100">
                        <div class="card-body p-4">
                            <div class="section-title text-secondary">
                                <i class="fa-solid fa-envelope"></i> Вхідні повідомлення
                            </div>
                            <?php if(!empty($dossier['messages'])): ?>
                                <ul class="list-group list-group-flush">
                                <?php foreach($dossier['messages'] as $msg): ?>
                                    <li class="list-group-item px-0 border-light">
                                        <div class="small text-dark"><?= $msg['message'] ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small text-center py-3">Повідомлень немає</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        
        <?php elseif(isset($dossier['related'])): ?>
            <div class="custom-card p-4">
                <?php foreach($dossier['related'] as $title => $rows): ?>
                    <h6 class="fw-bold mb-3"><?= $title ?></h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <?php foreach(array_keys($rows[0]) as $k): ?><th><?= $k ?></th><?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rows as $row): ?>
                                <tr>
                                    <?php foreach($row as $v): ?><td><?= $v ?></td><?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>