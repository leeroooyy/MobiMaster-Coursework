<?php
// print_receipt.php
require_once 'db.php';

if (!isset($_GET['id'])) {
    die("ID замовлення не вказано.");
}

$id = $_GET['id'];

// Отримуємо дані про замовлення + Клієнт + Майстер
$sql = "
    SELECT 
        o.*, 
        COALESCE(o.contact_name, c.full_name) AS client_name,
        COALESCE(o.contact_phone, c.phone) AS client_phone,
        e.full_name AS master_name
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.id
    LEFT JOIN employees e ON o.employee_id = e.id
    WHERE o.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Замовлення не знайдено.");
}

// === ГЕНЕРАЦІЯ QR-КОДУ ===
// Ми кодуємо текст: "Замовлення #ID | Пристрій | Статус | Сума"
// Цей текст зчитається камерою телефону
$qrData = "MobiMaster\nOrder #{$order['id']}\nDevice: {$order['device_model']}\nClient: {$order['client_name']}\nTotal: {$order['final_price']} UAH";
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Чек замовлення #<?= $id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Courier New', Courier, monospace; /* Шрифт як у чеку */
        }
        .receipt-card {
            max-width: 400px;
            margin: 50px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-top: 5px solid #6366f1; /* Ваш фірмовий колір */
            position: relative;
        }
        /* Ефект "зигзагу" знизу чеку (для краси) */
        .receipt-card::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 100%;
            height: 10px;
            background: radial-gradient(circle, transparent, transparent 50%, #fff 50%, #fff 100%) -7px -8px / 16px 16px repeat-x;
        }
        .dashed-line {
            border-top: 2px dashed #ddd;
            margin: 15px 0;
        }
        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
        }
        
        /* При друку ховаємо кнопку і фон */
        @media print {
            body { background: #fff; }
            .btn-print { display: none; }
            .receipt-card { box-shadow: none; border: 1px solid #000; margin: 0; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="btn btn-primary btn-lg rounded-pill shadow btn-print fw-bold" style="background-color: #6366f1; border:none;">
        <i class="fa-solid fa-print me-2"></i> Друкувати
    </button>

    <div class="receipt-card">
        
        <div class="text-center mb-4">
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-screwdriver-wrench text-primary"></i> MobiMaster</h3>
            <p class="small text-muted mb-0">Сервісний центр №1 у Львові</p>
            <p class="small text-muted">вул. Чайковського, 21</p>
        </div>

        <div class="dashed-line"></div>

        <div class="row mb-2">
            <div class="col-6 fw-bold">ЗАМОВЛЕННЯ:</div>
            <div class="col-6 text-end">#<?= $order['id'] ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 fw-bold">ДАТА:</div>
            <div class="col-6 text-end"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
        </div>
        
        <div class="dashed-line"></div>

        <div class="mb-3">
            <span class="text-muted small">КЛІЄНТ:</span><br>
            <strong><?= htmlspecialchars($order['client_name']) ?></strong><br>
            <small><?= htmlspecialchars($order['client_phone']) ?></small>
        </div>

        <div class="mb-3">
            <span class="text-muted small">ПРИСТРІЙ:</span><br>
            <strong><?= htmlspecialchars($order['device_model']) ?></strong>
        </div>

        <div class="mb-3">
            <span class="text-muted small">НЕСПРАВНІСТЬ:</span><br>
            <span><?= nl2br(htmlspecialchars($order['problem_description'])) ?></span>
        </div>

        <div class="dashed-line"></div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <span class="fs-5 fw-bold">ДО СПЛАТИ:</span>
            <span class="fs-4 fw-bold text-dark"><?= number_format($order['final_price'], 0, ' ', ' ') ?> ₴</span>
        </div>

        <div class="text-center">
            <img src="<?= $qrUrl ?>" alt="QR Code" style="width: 120px; height: 120px; border: 4px solid #333; border-radius: 8px;">
            <p class="small text-muted mt-2 mb-0">Відскануйте для перевірки</p>
        </div>

        <div class="text-center mt-4">
            <p class="small fw-bold mb-0">ДЯКУЄМО ЗА ДОВІРУ!</p>
            <p class="small text-muted" style="font-size: 0.7rem;">Гарантія на виконані роботи 30 днів</p>
        </div>

    </div>

</body>
</html>