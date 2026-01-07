<?php
// buy_confirm.php

// 1. ВМИКАЄМО ПОКАЗ ПОМИЛОК (Щоб бачити причину білого екрану)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_GET['id'])) {
    header("Location: user_buy.php");
    exit;
}

$product_id = $_GET['id'];

// Отримуємо товар
$stmt = $pdo->prepare("SELECT * FROM products_services WHERE id = ? AND type='product' AND is_sold=0");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не знайдено або він вже проданий.");
}

// Визначаємо ID користувача, якщо він залогінений
$session_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

// --- ОБРОБКА ФОРМИ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name    = trim($_POST['username']);
        $phone   = trim($_POST['phone']);
        $email   = trim($_POST['email']);
        $address = trim($_POST['address']);
        $payment = $_POST['payment'];

        $full_desc = "Покупка товару: " . $product['name'] . "\nСпосіб оплати: $payment\nАдреса доставки: $address";

        // 1. ЛОГІКА КЛІЄНТА (Щоб уникнути помилки, якщо купує гість)
        // Шукаємо клієнта в базі 'clients' за телефоном
        $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE phone = ?");
        $stmtCheck->execute([$phone]);
        $real_client_id = $stmtCheck->fetchColumn();

        if (!$real_client_id) {
            // Якщо клієнта немає — створюємо нового
            $stmtNew = $pdo->prepare("INSERT INTO clients (full_name, phone, email) VALUES (?, ?, ?)");
            $stmtNew->execute([$name, $phone, $email]);
            $real_client_id = $pdo->lastInsertId();
        } else {
            // Якщо є, оновлюємо email, якщо треба
            if (!empty($email)) {
                $pdo->prepare("UPDATE clients SET email = ? WHERE id = ?")->execute([$email, $real_client_id]);
            }
        }

        // 2. СТВОРЕННЯ ЗАМОВЛЕННЯ
        // Додано employee_id = NULL, щоб уникнути помилок сумісності
        $sql = "INSERT INTO orders 
                (client_id, contact_name, contact_phone, contact_email, item_id, device_model, problem_description, status, final_price, employee_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'new', ?, NULL, NOW())";
        
        $stmtOrder = $pdo->prepare($sql);
        $res = $stmtOrder->execute([
            $real_client_id, 
            $name, 
            $phone, 
            $email, 
            $product['id'], 
            $product['name'], 
            $full_desc, 
            $product['price']
        ]);

        // 3. ЯКЩО УСПІШНО
        if ($res) {
            // Позначаємо товар як проданий
            $pdo->prepare("UPDATE products_services SET is_sold = 1 WHERE id = ?")->execute([$product['id']]);
            
            // Лог (якщо функція існує)
            if (function_exists('logEvent')) { 
                logEvent($pdo, $session_user_id ?? 0, 'BUY', "Куплено товар: {$product['name']} ($name)"); 
            }

            echo "<script>
                    alert('✅ Замовлення успішно оформлено! Дякуємо за покупку.'); 
                    window.location.href = 'index.php';
                  </script>";
            exit;
        }

    } catch (PDOException $e) {
        // ВИВІД ПОМИЛКИ
        echo "<div class='container mt-5 py-5'><div class='alert alert-danger shadow p-4'>";
        echo "<h4 class='alert-heading'><i class='fa-solid fa-triangle-exclamation'></i> Помилка оформлення!</h4>";
        echo "<p>Сталася технічна помилка. Будь ласка, покажіть цей текст адміністратору:</p>";
        echo "<hr>";
        echo "<pre class='bg-light p-3 rounded text-danger fw-bold'>" . $e->getMessage() . "</pre>";
        echo "<a href='user_buy.php' class='btn btn-outline-danger mt-3'>Повернутися назад</a>";
        echo "</div></div>";
        exit; // Зупиняємо скрипт, щоб не показувати форму знову
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 16px;">
                <div class="card-header text-white p-4 text-center" style="background-color: #6366f1;">
                    <h4 class="mb-0 fw-bold"><i class="fa-solid fa-cart-shopping me-2"></i> Підтвердження замовлення</h4>
                </div>

                <div class="card-body p-4 p-md-5 bg-light">
                    
                    <div class="text-center mb-4 bg-white p-4 rounded shadow-sm border">
                        
                        <div class="mb-3 d-flex justify-content-center">
                            <?php 
                                // Розумний пошук картинки
                                $img = $product['image'];
                                $img_src = '';

                                if (!empty($img)) {
                                    if (file_exists($img)) {
                                        $img_src = $img;
                                    } elseif (file_exists("uploads/" . $img)) {
                                        $img_src = "uploads/" . $img;
                                    } else {
                                        $img_src = $img;
                                    }
                                }

                                if ($img_src): 
                            ?>
                                <img src="<?= htmlspecialchars($img_src) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" 
                                     class="img-fluid rounded" 
                                     style="max-height: 200px; object-fit: contain;">
                            <?php else: ?>
                                <div class="text-secondary opacity-25">
                                    <i class="fa-solid fa-mobile-screen fa-4x"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h4 class="fw-bold text-dark mt-3"><?= htmlspecialchars($product['name']) ?></h4>
                        <div class="fs-4 fw-bold text-success mt-2"><?= number_format($product['price'], 0, ' ', ' ') ?> ₴</div>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Ваше ім'я:</label>
                            <input type="text" name="username" class="form-control py-2" placeholder="Іван Петров" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Ваш телефон:</label>
                            <input type="text" name="phone" class="form-control py-2" placeholder="+380..." required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Ваш Email (необов'язково):</label>
                            <input type="email" name="email" class="form-control py-2" placeholder="mail@example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Адреса доставки:</label>
                            <input type="text" name="address" class="form-control py-2" placeholder="м. Львів, Нова Пошта №1" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Спосіб оплати:</label>
                            <select name="payment" class="form-select py-2">
                                <option value="Готівка">💵 Готівка при отриманні</option>
                                <option value="Картка">💳 Оплата карткою</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success fw-bold py-3 shadow-sm" style="background: #10b981; border:none; font-size: 1.1rem;">
                                <i class="fa-solid fa-check me-2"></i> Підтвердити покупку
                            </button>
                            <a href="user_buy.php" class="btn btn-light text-muted fw-bold py-3">Скасувати</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>