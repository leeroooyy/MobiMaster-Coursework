<?php
// user_add_repair.php

// 1. ВМИКАЄМО ПОКАЗ ПОМИЛОК
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
include 'header.php';

// Отримуємо список майстрів
try {
    $masters = $pdo->query("SELECT id, full_name, position FROM employees")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $masters = [];
}

// --- ОБРОБКА ФОРМИ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Отримуємо та чистимо дані
        $name    = trim($_POST['name']);
        $phone   = trim($_POST['phone']);
        $email   = trim($_POST['email']);
        $address = trim($_POST['address']); // 1. Отримуємо адресу
        
        $device       = trim($_POST['device']);
        $service_type = trim($_POST['service_type']);
        $problem_desc = trim($_POST['problem']);
        $master_id    = !empty($_POST['master_id']) ? $_POST['master_id'] : null;

        // Формуємо повний опис проблеми
        $full_description = $service_type;
        if (!empty($problem_desc)) {
            $full_description .= " [Деталі: " . $problem_desc . "]";
        }

        // 2. РОБОТА З КЛІЄНТОМ
        // Шукаємо клієнта за телефоном
        $stmt_check = $pdo->prepare("SELECT id FROM clients WHERE phone = ?");
        $stmt_check->execute([$phone]);
        $client_id = $stmt_check->fetchColumn();

        if (!$client_id) {
            // Якщо немає - створюємо нового з АДРЕСОЮ
            $stmt_new = $pdo->prepare("INSERT INTO clients (full_name, phone, email, address) VALUES (?, ?, ?, ?)");
            $stmt_new->execute([$name, $phone, $email, $address]);
            $client_id = $pdo->lastInsertId();
        } else {
            // Якщо є - оновлюємо дані (Email та Адресу), якщо вони вказані
            if (!empty($email)) {
                $pdo->prepare("UPDATE clients SET email = ? WHERE id = ?")->execute([$email, $client_id]);
            }
            if (!empty($address)) {
                $pdo->prepare("UPDATE clients SET address = ? WHERE id = ?")->execute([$address, $client_id]);
            }
        }

        // 3. СТВОРЕННЯ ЗАМОВЛЕННЯ
        $sql_order = "INSERT INTO orders 
            (client_id, device_model, problem_description, employee_id, status, created_at, contact_name, contact_phone, contact_email, final_price) 
            VALUES (?, ?, ?, ?, 'new', NOW(), ?, ?, ?, 0)";
        
        $stmt_order = $pdo->prepare($sql_order);
        // Додаємо email в історію замовлення також
        $stmt_order->execute([$client_id, $device, $full_description, $master_id, $name, $phone, $email]);

        // 4. ЛОГУВАННЯ
        try {
            $stmt_log = $pdo->prepare("INSERT INTO logs (user_id, action, details, created_at) VALUES (0, 'CREATE_ORDER', ?, NOW())");
            $stmt_log->execute(["Створено заявку користувачем: $name ($device)"]);
        } catch (Exception $e) {}

        // 5. УСПІХ
        echo "<script>
                alert('✅ Ваша заявка успішно прийнята! Ми зв\'яжемося з вами.');
                window.location.href = 'index.php';
              </script>";
        exit;

    } catch (PDOException $e) {
        echo "<div class='container mt-5'><div class='alert alert-danger shadow-sm border-0'>";
        echo "<h4 class='alert-heading'>Помилка!</h4><p>" . $e->getMessage() . "</p>";
        echo "</div></div>";
    }
}
?>

<div class="container py-5" style="max-width: 700px;">
    
    <div class="card shadow-lg border-0 overflow-hidden" style="border-radius: 16px;">
        <div class="card-header text-white text-center py-4" style="background-color: #6366f1;">
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-screwdriver-wrench me-2"></i> Заявка на ремонт</h2>
            <p class="mb-0 opacity-75">Заповніть форму, і ми зв’яжемося з вами</p>
        </div>
        
        <div class="card-body p-4 p-md-5 bg-white">
            <form method="POST">
                
                <h5 class="text-primary fw-bold mb-3"><i class="fa-regular fa-id-card me-2"></i> Ваші контакти</h5>
                
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label small fw-bold text-secondary">Ваше ім'я</label>
                        <input type="text" name="name" class="form-control py-2" placeholder="Ваше ім'я..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Телефон</label>
                        <input type="text" name="phone" class="form-control py-2" placeholder="Приклад: 097 092 92 80" required>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label small fw-bold text-secondary">Email</label>
                        <input type="email" name="email" class="form-control py-2" placeholder="mail@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Адреса проживання</label>
                        <input type="text" name="address" class="form-control py-2" placeholder="м. Львів, вул...">
                    </div>
                </div>

                <hr class="my-4" style="border-top: 1px solid #e5e7eb;">

                <h5 class="text-primary fw-bold mb-3"><i class="fa-solid fa-mobile-screen-button me-2"></i> Деталі ремонту</h5>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Модель пристрою</label>
                    <input type="text" name="device" class="form-control py-2" placeholder="Напр: iPhone 11, Samsung S21" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Оберіть послугу</label>
                    <select name="service_type" class="form-select py-2">
                        <option value="Діагностика">-- Не знаю / Потрібна діагностика --</option>
                        <option value="Заміна дисплею">Заміна дисплею</option>
                        <option value="Заміна акумулятора">Заміна акумулятора</option>
                        <option value="Заміна скла">Заміна скла</option>
                        <option value="Ремонт після води">Ремонт після води</option>
                        <option value="Прошивка ПЗ">Прошивка / Зняття паролю</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Оберіть майстра (необов'язково)</label>
                    <select name="master_id" class="form-select py-2">
                        <option value="">-- Будь-який вільний майстер --</option>
                        <?php foreach ($masters as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['position']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">Опис проблеми</label>
                    <textarea name="problem" class="form-control" rows="3" placeholder="Опишіть, що сталося..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" style="background-color: #6366f1; border: none; font-size: 1.1rem; border-radius: 8px;">
                    Відправити заявку
                </button>

            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>