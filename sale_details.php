<?php
// sale_details.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("ID замовлення не вказано");
}

$order_id = $_GET['id'];

// Отримуємо дані про замовлення + Товар + Клієнт
$sql = "
    SELECT 
        o.*, 
        ps.name as product_name, 
        ps.image as product_image,
        ps.price as original_price,
        c.full_name as client_real_name,
        c.phone as client_real_phone,
        c.email as client_real_email,
        c.address as client_real_address
    FROM orders o
    LEFT JOIN products_services ps ON o.item_id = ps.id
    LEFT JOIN clients c ON o.client_id = c.id
    WHERE o.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) die("Замовлення не знайдено");

// Визначаємо дані контакту (пріоритет: дані з замовлення, якщо пусті -> дані з профілю клієнта)
$contact_name = $order['contact_name'] ?: $order['client_real_name'];
$contact_phone = $order['contact_phone'] ?: $order['client_real_phone'];
$contact_email = $order['contact_email'] ?: $order['client_real_email'];

// Розбираємо опис (де схована адреса та оплата)
// У buy_confirm.php ми писали: "Спосіб оплати: ... \n Адреса доставки: ..."
$description = $order['problem_description'];

include 'header.php';
?>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Головна</a></li>
                    <li class="breadcrumb-item active">Продаж #<?= $order['id'] ?></li>
                </ol>
            </nav>
            <h2 class="fw-bold text-dark mb-0">Деталі продажу</h2>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
            <i class="fa-solid fa-arrow-left me-2"></i> Назад
        </a>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white py-3 text-center border-bottom">
                    <h5 class="mb-0 fw-bold text-success"><i class="fa-solid fa-box-open me-2"></i> Придбаний товар</h5>
                </div>
                <div class="card-body text-center p-4">
                    
                    <?php 
                        $img = $order['product_image'];
                        if (!empty($img)) {
                             if (!file_exists($img) && file_exists("uploads/" . $img)) {
                                $img = "uploads/" . $img;
                             }
                        }
                    ?>
                    
                    <?php if($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" class="img-fluid rounded mb-3 shadow-sm" style="max-height: 250px;">
                    <?php else: ?>
                        <div class="py-5 bg-light rounded mb-3">
                            <i class="fa-solid fa-mobile-screen fa-4x text-muted opacity-25"></i>
                        </div>
                    <?php endif; ?>

                    <h4 class="fw-bold mb-2"><?= htmlspecialchars($order['product_name'] ?? $order['device_model']) ?></h4>
                    
                    <div class="badge bg-success bg-opacity-10 text-success px-3 py-2 fs-5 rounded-pill border border-success mb-3">
                        <?= number_format($order['final_price'], 0, ' ', ' ') ?> ₴
                    </div>

                    <div class="text-muted small">
                        Дата продажу: <strong><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></strong>
                    </div>
                </div>
                <div class="card-footer bg-light p-3 text-center">
                    <a href="print_receipt.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-outline-dark w-100 fw-bold">
                        <i class="fa-solid fa-print me-2"></i> Друкувати чек
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fa-solid fa-user-tag me-2"></i> Інформація про замовлення</h5>
                </div>
                <div class="card-body p-4">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase mb-1">Покупець</label>
                            <div class="fs-5 fw-bold text-dark">
                                <i class="fa-regular fa-id-card me-2 text-primary opacity-50"></i>
                                <?= htmlspecialchars($contact_name) ?>
                            </div>
                            <?php if($order['client_id']): ?>
                                <a href="client_details.php?id=<?= $order['client_id'] ?>" class="btn btn-link btn-sm p-0 text-decoration-none">
                                    Переглянути профіль клієнта →
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <label class="small text-muted fw-bold text-uppercase mb-1">Контакти</label>
                            <div class="mb-1">
                                <i class="fa-solid fa-phone text-success me-2" style="width: 20px;"></i>
                                <a href="tel:<?= htmlspecialchars($contact_phone) ?>" class="text-decoration-none text-dark fw-bold"><?= htmlspecialchars($contact_phone) ?></a>
                            </div>
                            <?php if($contact_email): ?>
                            <div>
                                <i class="fa-solid fa-envelope text-primary me-2" style="width: 20px;"></i>
                                <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($contact_email) ?></a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="border-light">

                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-truck-fast me-2"></i> Доставка та Оплата</h6>
                        <div class="bg-light p-3 rounded border">
                            <pre class="mb-0 text-dark" style="font-family: var(--bs-body-font-family); white-space: pre-wrap; font-size: 1rem;"><?= htmlspecialchars($description) ?></pre>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>