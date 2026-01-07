<?php
require 'db.php';

// Захист: тільки адмін
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') exit;

$tab = $_GET['tab'] ?? 'repair';
$sort = $_GET['sort'] ?? 'date_desc';

// 1. Налаштування сортування
$order_sql = "o.created_at DESC"; // Стандарт
switch ($sort) {
    case 'price_desc': $order_sql = "o.final_price DESC"; break;
    case 'price_asc':  $order_sql = "o.final_price ASC"; break;
    case 'alpha_asc':  $order_sql = "o.device_model ASC"; break;
    case 'date_asc':   $order_sql = "o.created_at ASC"; break;
}

// 2. Запит до бази
$sql = "SELECT o.*, c.full_name AS client_name, e.full_name AS emp_name 
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        LEFT JOIN employees e ON o.employee_id = e.id
        ORDER BY $order_sql";
$stmt = $pdo->query($sql);
$all_orders = $stmt->fetchAll();

// 3. Формування відповіді (HTML рядків)
foreach ($all_orders as $row) {
    // Визначаємо: це Продаж чи Ремонт?
    $is_sale = (strpos($row['device_model'], 'Продаж') !== false);

    // Фільтрація: Якщо вкладка 'sales', показуємо тільки продажі
    if ($tab === 'sales' && !$is_sale) continue;
    if ($tab === 'repair' && $is_sale) continue;

    // Форматування даних
    $price = number_format($row['final_price'], 0, ' ', ' ');
    $date = date('d.m H:i', strtotime($row['created_at']));
    $desc = htmlspecialchars($row['device_model']);
    
    // Статус (колір)
    $statusBadge = match($row['status']) {
        'new' => '<span class="badge bg-primary">Новий</span>',
        'in_progress' => '<span class="badge bg-warning text-dark">В роботі</span>',
        'done' => '<span class="badge bg-success">Готово</span>',
        'issued' => '<span class="badge bg-secondary">Видано</span>',
        default => '<span class="badge bg-secondary">'.$row['status'].'</span>'
    };

    // Вивід рядка таблиці
    echo "<tr>
        <td>#{$row['id']}</td>
        <td><b>{$desc}</b></td>
        <td>" . htmlspecialchars($row['client_name']) . "</td>
        <td>" . ($is_sale ? '—' : htmlspecialchars($row['emp_name'] ?? '—')) . "</td>
        <td>{$statusBadge}</td>
        <td class='fw-bold'>{$price} ₴</td>
        <td><small class='text-muted'>{$date}</small></td>
        <td>
            <a href='edit.php?id={$row['id']}' class='btn btn-sm btn-outline-primary'>✏️</a>
            <a href='delete.php?id={$row['id']}' onclick=\"return confirm('Видалити?')\" class='btn btn-sm btn-outline-danger'>🗑</a>
        </td>
    </tr>";
}
?>