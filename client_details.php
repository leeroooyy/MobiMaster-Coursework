<?php
// client_details.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$client_id = $_GET['id'];

// 1. Отримуємо дані клієнта
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) die("Клієнта не знайдено");

// 2. Отримуємо РЕМОНТИ
$sql_repairs = "
    SELECT o.*, e.full_name as master_name 
    FROM orders o
    LEFT JOIN employees e ON o.employee_id = e.id
    LEFT JOIN products_services ps ON o.item_id = ps.id
    WHERE o.client_id = ? AND (ps.type != 'product' OR ps.type IS NULL)
    ORDER BY o.created_at DESC
";
$stmt_rep = $pdo->prepare($sql_repairs);
$stmt_rep->execute([$client_id]);
$repairs = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);

// 3. Отримуємо ПОКУПКИ
$sql_purchases = "
    SELECT o.*, ps.name as product_name, ps.image 
    FROM orders o
    JOIN products_services ps ON o.item_id = ps.id
    WHERE o.client_id = ? AND ps.type = 'product'
    ORDER BY o.created_at DESC
";
$stmt_buy = $pdo->prepare($sql_purchases);
$stmt_buy->execute([$client_id]);
$purchases = $stmt_buy->fetchAll(PDO::FETCH_ASSOC);

// 4. Отримуємо ПОВІДОМЛЕННЯ (Feedback)
// Шукаємо за телефоном або email
$sql_msg = "SELECT * FROM feedback WHERE phone = ? OR (email != '' AND email = ?) ORDER BY created_at DESC";
$stmt_msg = $pdo->prepare($sql_msg);
$stmt_msg->execute([$client['phone'], $client['email']]);
$messages = $stmt_msg->fetchAll(PDO::FETCH_ASSOC);

// 5. Отримуємо ВІДГУКИ (Reviews)
// Шукаємо за Іменем (так як reviews прив'язані до users, а клієнт може бути без акаунту, шукаємо по імені)
$sql_reviews = "SELECT * FROM reviews WHERE user_name = ? ORDER BY created_at DESC";
$stmt_rev = $pdo->prepare($sql_reviews);
$stmt_rev->execute([$client['full_name']]);
$reviews = $stmt_rev->fetchAll(PDO::FETCH_ASSOC);

// Функція кольору статусу
function statusColor($s) {
    if ($s == 'new') return 'primary';
    if ($s == 'in_progress') return 'warning text-dark';
    if ($s == 'done') return 'success';
    if ($s == 'issued') return 'dark';
    return 'secondary';
}

include 'header.php';
?>

<div class="container py-5">
    
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="clients.php" class="text-decoration-none">Клієнти</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($client['full_name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex justify-content-center align-items-center text-primary fw-bold" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?= mb_substr($client['full_name'], 0, 1) ?>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($client['full_name']) ?></h4>
                    <p class="text-muted small">ID: #<?= $client['id'] ?></p>
                    
                    <hr class="my-4">
                    
                    <div class="text-start">
                        <div class="mb-3">
                            <label class="small text-muted fw-bold text-uppercase">Телефон</label>
                            <div class="fs-5"><i class="fa-solid fa-phone me-2 text-success"></i><?= htmlspecialchars($client['phone']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="small text-muted fw-bold text-uppercase">Email</label>
                            <div class="fs-6">
                                <?php if($client['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($client['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($client['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Не вказано</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small text-muted fw-bold text-uppercase">Адреса</label>
                            <div class="fs-6">
                                <?php if(!empty($client['address'])): ?>
                                    <i class="fa-solid fa-location-dot me-2 text-danger"></i><?= htmlspecialchars($client['address']) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Не вказано</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small text-muted fw-bold text-uppercase">Дата реєстрації</label>
                            <div class="fs-6">
                                <i class="fa-regular fa-calendar me-2 text-secondary"></i>
                                <?= $client['registration_date'] ? date('d.m.Y H:i', strtotime($client['registration_date'])) : 'Невідомо' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white p-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-envelope me-2"></i> Повідомлення (Feedback)</h6>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if(count($messages) > 0): ?>
                        <?php foreach($messages as $msg): ?>
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></small>
                                    <i class="fa-regular fa-comment-dots text-primary opacity-50"></i>
                                </div>
                                <p class="mb-0 small text-dark"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item p-4 text-center text-muted small">Повідомлень немає</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white p-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-warning"><i class="fa-solid fa-star me-2"></i> Відгуки клієнта</h6>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if(count($reviews) > 0): ?>
                        <?php foreach($reviews as $rev): ?>
                            <li class="list-group-item p-3">
                                <div class="mb-1 text-warning small">
                                    <?php for($i=0; $i<$rev['rating']; $i++) echo '<i class="fa-solid fa-star"></i>'; ?>
                                </div>
                                <p class="mb-1 small text-dark fw-bold">"<?= htmlspecialchars($rev['comment']) ?>"</p>
                                <small class="text-muted" style="font-size: 0.75rem;"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item p-4 text-center text-muted small">Відгуків немає</li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>

        <div class="col-lg-8">
            
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white p-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fa-solid fa-screwdriver-wrench me-2"></i> Історія ремонтів</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Пристрій / Проблема</th>
                                    <th>Майстер</th>
                                    <th>Статус</th>
                                    <th class="text-end pe-4">Ціна</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repairs as $order): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-muted">#<?= $order['id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($order['device_model']) ?></div>
                                        <div class="small text-muted text-truncate" style="max-width: 200px;">
                                            <?= htmlspecialchars($order['problem_description']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($order['master_name']): ?>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-user-gear me-1"></i> <?= htmlspecialchars($order['master_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Не призначено</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?= statusColor($order['status']) ?>"><?= $order['status'] ?></span></td>
                                    <td class="text-end pe-4 fw-bold"><?= number_format($order['final_price'], 0, ' ', ' ') ?> ₴</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($repairs)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Ремонтів ще не було</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white p-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-success"><i class="fa-solid fa-cart-shopping me-2"></i> Історія покупок</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Фото</th>
                                    <th>Товар</th>
                                    <th>Дата</th>
                                    <th class="text-end pe-4">Ціна</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $buy): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if($buy['image']): ?>
                                            <img src="uploads/<?= $buy['image'] ?>" style="width: 40px; height: 40px; object-fit: contain; border-radius: 4px; border: 1px solid #eee;">
                                        <?php else: ?>
                                            <i class="fa-solid fa-box text-muted opacity-25"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= htmlspecialchars($buy['product_name']) ?></td>
                                    <td class="text-muted small"><?= date('d.m.Y', strtotime($buy['created_at'])) ?></td>
                                    <td class="text-end pe-4 fw-bold text-success"><?= number_format($buy['final_price'], 0, ' ', ' ') ?> ₴</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($purchases)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Покупок ще не було</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>