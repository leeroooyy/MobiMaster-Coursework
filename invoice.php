<?php
require 'db.php';

// Перевіряємо, чи передали номер замовлення
if (!isset($_GET['id'])) {
    die("Помилка: Не вказано номер чеку.");
}

$id = $_GET['id'];

// Отримуємо повні дані про замовлення
// Використовуємо LEFT JOIN, бо в ручних продажах може не бути послуги або майстра
$sql = "SELECT o.*, 
               c.full_name AS client_name, 
               c.phone AS client_phone, 
               e.full_name AS emp_name, 
               ps.name AS service_name 
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        LEFT JOIN employees e ON o.employee_id = e.id
        LEFT JOIN products_services ps ON o.item_id = ps.id
        WHERE o.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("Замовлення не знайдено!");
}

// Визначаємо, що саме писати в назві товару
if (!empty($order['service_name'])) {
    $item_name = $order['service_name']; // Якщо це послуга з бази
} else {
    $item_name = $order['device_model']; // Якщо це ручний продаж
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Чек #<?= $order['id'] ?></title>
    <style>
        body { 
            font-family: 'Courier New', monospace; /* Шрифт як у касовому апараті */
            background: #eee; 
            padding: 20px; 
            display: flex;
            justify-content: center;
        }
        .invoice-box {
            background: white;
            width: 300px; /* Ширина як у справжнього чеку */
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2, h3, p { margin: 5px 0; text-align: center; }
        .line { border-bottom: 1px dashed #000; margin: 10px 0; }
        
        .left-right {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .total {
            font-size: 18px;
            font-weight: bold;
            margin-top: 15px;
            border-top: 2px solid #000;
            padding-top: 10px;
            text-align: right;
        }
        
        /* Кнопка друку (не друкується на папері) */
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .invoice-box { box-shadow: none; width: 100%; }
        }
        .btn-print {
            display: block;
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            text-align: center;
            cursor: pointer;
            margin-bottom: 20px;
            border: none;
            font-family: sans-serif;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <div class="invoice-box">
        <button onclick="window.print()" class="btn-print no-print">🖨️ ДРУКУВАТИ</button>

        <h2>ФОП "МАРКІЯН"</h2>
        <p>Сервісний центр та Магазин</p>
        <p>м. Львів, вул. Студентська, 1</p>
        <div class="line"></div>

        <div class="left-right">
            <span>Чек №:</span>
            <span><?= $order['id'] ?></span>
        </div>
        <div class="left-right">
            <span>Дата:</span>
            <span><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
        </div>
        <div class="left-right">
            <span>Клієнт:</span>
            <span><?= htmlspecialchars($order['client_name']) ?></span>
        </div>

        <div class="line"></div>

        <h3>ТОВАРНИЙ ЧЕК</h3>
        
        <p style="text-align: left; font-weight: bold; margin-top: 10px;">
            <?= htmlspecialchars($item_name) ?>
        </p>
        
        <?php if($order['device_model'] && empty($order['service_name'])): ?>
            <p style="text-align: left; font-size: 12px; color: #555;">
                Пристрій: <?= htmlspecialchars($order['device_model']) ?>
            </p>
        <?php endif; ?>

        <?php if($order['problem_description']): ?>
            <p style="text-align: left; font-size: 12px; color: #555;">
                Примітка: <?= htmlspecialchars($order['problem_description']) ?>
            </p>
        <?php endif; ?>

        <div class="total">
            СУМА: <?= number_format($order['final_price'], 2) ?> ГРН
        </div>

        <div class="line"></div>
        <p style="font-size: 12px;">Дякуємо за покупку!</p>
        <p style="font-size: 12px;">Гарантія 30 днів.</p>
    </div>

</body>
</html>