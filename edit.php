<?php
// edit.php
session_start();
require_once 'db.php';

// Перевірка на адміна
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_GET['id'];

// --- 1. ОТРИМУЄМО ДАНІ ПРО ЗАМОВЛЕННЯ ---
// Ми беремо дані з таблиці orders (пріоритет), а якщо там пусто - підтягуємо з clients
$sql = "SELECT 
            o.*, 
            COALESCE(o.contact_name, c.full_name, u.full_name) AS client_name_display,
            COALESCE(o.contact_phone, c.phone) AS client_phone_display,
            COALESCE(o.contact_email, c.email) AS client_email_display 
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        LEFT JOIN users u ON o.client_id = u.id
        WHERE o.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Замовлення не знайдено!");
}

// Визначаємо тип замовлення (Ремонт чи Покупка)
$is_purchase = !empty($order['item_id']); // Якщо є ID товару, значить це покупка

// --- 2. СПИСКИ ДЛЯ ВИБОРУ ---
$employees = $pdo->query("SELECT * FROM employees")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY full_name ASC")->fetchAll();

// --- 3. ОБРОБКА ЗБЕРЕЖЕННЯ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device = $_POST['device'];
    $problem = $_POST['problem'];
    $status = $_POST['status'];
    $price = $_POST['price'];
    $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : NULL;
    
    // Отримуємо нові дані покупця/клієнта
    $input_name  = trim($_POST['client_name']); 
    $input_phone = trim($_POST['client_phone']);
    $input_email = trim($_POST['client_email']);

    // --- ЛОГІКА ПРИВ'ЯЗКИ КЛІЄНТА ---
    // Навіть якщо ми змінюємо ім'я, ми намагаємось знайти або створити клієнта в базі
    $final_client_id = $order['client_id']; 

    if (!empty($input_name)) {
        // Шукаємо клієнта за телефоном або ім'ям
        $stmt_check = $pdo->prepare("SELECT id FROM clients WHERE full_name = ? OR phone = ? LIMIT 1");
        $stmt_check->execute([$input_name, $input_phone]);
        $existing_client = $stmt_check->fetch();

        if ($existing_client) {
            $final_client_id = $existing_client['id'];
            // Оновлюємо email в базі клієнтів
            if (!empty($input_email)) {
                $pdo->prepare("UPDATE clients SET email = ? WHERE id = ?")->execute([$input_email, $final_client_id]);
            }
        } else {
            // Створюємо нового клієнта
            $stmt_new = $pdo->prepare("INSERT INTO clients (full_name, phone, email, registration_date) VALUES (?, ?, ?, NOW())");
            $stmt_new->execute([$input_name, $input_phone, $input_email]);
            $final_client_id = $pdo->lastInsertId();
        }
    }

    // --- ОНОВЛЮЄМО ЗАМОВЛЕННЯ ---
    // Тут ми оновлюємо contact_name, contact_phone, contact_email прямо в замовленні
    $update_sql = "UPDATE orders SET 
                    device_model = ?, 
                    problem_description = ?, 
                    status = ?, 
                    final_price = ?, 
                    employee_id = ?, 
                    client_id = ?,
                    contact_name = ?,  
                    contact_phone = ?,
                    contact_email = ? 
                   WHERE id = ?";
    
    $stmt = $pdo->prepare($update_sql);
    
    if ($stmt->execute([$device, $problem, $status, $price, $employee_id, $final_client_id, $input_name, $input_phone, $input_email, $order_id])) {
        
        if (function_exists('logEvent')) {
            logEvent($pdo, $_SESSION['user_id'], 'UPDATE', "Змінено дані замовлення #$order_id (Клієнт: $input_name)");
        }

        header("Location: index.php"); // Повертаємось на головну
        exit;
    } else {
        echo "<script>alert('Помилка при збереженні!');</script>";
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 16px;">
                
                <div class="card-header text-white p-4 d-flex justify-content-between align-items-center" 
                     style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
                    <div>
                        <h4 class="mb-1 fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Редагування</h4>
                        <p class="mb-0 opacity-75 small">
                            <?= $is_purchase ? '📦 Це замовлення товару' : '🛠 Це замовлення на ремонт' ?> #<?= $order['id'] ?>
                        </p>
                    </div>
                    <a href="index.php" class="btn btn-white text-white border-white bg-transparent btn-sm opacity-75">
                        Скасувати
                    </a>
                </div>

                <div class="card-body p-4 p-md-5 bg-white">
                    <form method="POST">
                        
                        <h6 class="text-uppercase text-secondary fw-bold mb-3 small">
                            <i class="fa-solid fa-user me-2"></i> Дані покупця / Клієнта
                        </h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Ім'я (Можна змінити)</label>
                                <input type="text" 
                                       name="client_name" 
                                       list="clients_datalist" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($order['client_name_display'] ?? '') ?>" 
                                       placeholder="Введіть ім'я..." 
                                       autocomplete="off">
                                <datalist id="clients_datalist">
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= htmlspecialchars($client['full_name']) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Телефон</label>
                                <input type="text" name="client_phone" class="form-control" 
                                       value="<?= htmlspecialchars($order['client_phone_display'] ?? '') ?>" 
                                       placeholder="+380...">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Email</label>
                            <input type="email" name="client_email" class="form-control" 
                                   value="<?= htmlspecialchars($order['client_email_display'] ?? '') ?>" 
                                   placeholder="email@example.com">
                        </div>

                        <hr class="my-4 opacity-25">

                        <h6 class="text-uppercase text-secondary fw-bold mb-3 small">
                            <i class="fa-solid fa-box-open me-2"></i> Деталі замовлення
                        </h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Назва товару / Пристрій</label>
                                <input type="text" name="device" class="form-control" 
                                       value="<?= htmlspecialchars($order['device_model']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-success">Сума (грн)</label>
                                <input type="number" name="price" class="form-control fw-bold text-success" 
                                       value="<?= $order['final_price'] ?>" step="0.01">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Опис / Деталі доставки</label>
                            <textarea name="problem" class="form-control" rows="4"><?= htmlspecialchars($order['problem_description']) ?></textarea>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Відповідальний менеджер</label>
                                <select name="employee_id" class="form-select">
                                    <option value="">-- Не призначено --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?= ($order['employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-info">Статус</label>
                                <select name="status" class="form-select fw-bold text-dark">
                                    <option value="new" <?= ($order['status']=='new')?'selected':'' ?>>🔵 Новий</option>
                                    <option value="in_progress" <?= ($order['status']=='in_progress')?'selected':'' ?>>🟡 В обробці</option>
                                    <option value="done" <?= ($order['status']=='done')?'selected':'' ?>>🟢 Виконано / Продано</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-bold py-3 shadow-sm" style="background-color: #6366f1; border:none;">
                                <i class="fa-solid fa-floppy-disk me-2"></i> Зберегти зміни
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>