<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') exit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$tab    = $_GET['tab'] ?? 'repair';

// === ЛОГІКА ДЛЯ ВІТРИНИ (ТОВАРИ) ===
if ($tab === 'store') {
    $sql = "SELECT * FROM products_services WHERE type='product' AND is_sold=0";
    if ($search) {
        $sql .= " AND name LIKE '%$search%'";
    }
    $products = $pdo->query($sql)->fetchAll();

    if (count($products) > 0) {
        foreach ($products as $p) {
            $img = $p['image'] ? "<img src='uploads/{$p['image']}' style='width:50px; height:50px; object-fit:contain; border-radius:5px;'>" : "<i class='fa-solid fa-mobile text-muted fa-2x'></i>";
            
            echo "
            <tr>
                <td>#{$p['id']}</td>
                <td>{$img}</td>
                <td><b>" . htmlspecialchars($p['name']) . "</b></td>
                <td>" . htmlspecialchars(mb_strimwidth($p['description'], 0, 50, "...")) . "</td>
                <td class='fw-bold text-success'>" . number_format($p['price'], 0, ' ', ' ') . " ₴</td>
                <td class='text-end'>
                    <a href='edit_product.php?id={$p['id']}' class='btn btn-sm btn-outline-primary' title='Редагувати'>
                        <i class='fa-solid fa-pen'></i>
                    </a>
                    <a href='delete_product.php?id={$p['id']}' onclick=\"return confirm('Видалити цей товар?')\" class='btn btn-sm btn-outline-danger' title='Видалити'>
                        <i class='fa-solid fa-trash'></i>
                    </a>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='6' class='text-center py-5 text-muted'>Вітрина порожня.</td></tr>";
    }
    exit; // Зупиняємо скрипт, бо далі йде логіка замовлень
}

// === ЛОГІКА ДЛЯ ЗАМОВЛЕНЬ (РЕМОНТИ / ІСТОРІЯ ПРОДАЖІВ) ===
$sql = "SELECT o.*, c.full_name, c.phone, ps.name as service_name 
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN products_services ps ON o.item_id = ps.id 
        WHERE 1=1";

if ($search) $sql .= " AND (c.full_name LIKE '%$search%' OR o.device_model LIKE '%$search%')";
if ($status) $sql .= " AND o.status = '$status'";

if ($tab === 'sales') {
    $sql .= " AND (o.device_model LIKE 'Продаж%' OR o.device_model = 'Продаж товару')";
} else {
    $sql .= " AND (o.device_model NOT LIKE 'Продаж%' AND o.device_model != 'Продаж товару')";
}

$sql .= " ORDER BY o.created_at DESC";
$orders = $pdo->query($sql)->fetchAll();

if (count($orders) > 0) {
    foreach ($orders as $order) {
        $price = number_format($order['final_price'], 0, ' ', ' ');
        $stClass = match($order['status']) { 'new'=>'bg-primary', 'in_progress'=>'bg-warning text-dark', 'done'=>'bg-success', default=>'bg-secondary' };
        $stName = match($order['status']) { 'new'=>'Новий', 'in_progress'=>'В роботі', 'done'=>'Готово', default=>$order['status'] };
        
        $desc = htmlspecialchars($order['device_model']);
        if ($tab === 'sales') $desc = str_replace('Продаж: ', '', $desc);

        echo "
        <tr>
            <td>#{$order['id']}</td>
            <td><b>{$desc}</b></td>
            <td>" . htmlspecialchars($order['full_name']) . "<br><small class='text-muted'>{$order['phone']}</small></td>
            <td><span class='badge rounded-pill {$stClass}'>{$stName}</span></td>
            <td class='fw-bold'>{$price} ₴</td>
            <td class='text-end'>
                <a href='invoice.php?id={$order['id']}' target='_blank' class='btn btn-sm btn-outline-secondary'><i class='fa-solid fa-print'></i></a>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center py-5 text-muted'>Записів не знайдено.</td></tr>";
}
?>