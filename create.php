<?php
// create.php (Оновлено: додано адресу)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$employees = $pdo->query("SELECT id, full_name FROM employees")->fetchAll();
$services = $pdo->query("SELECT id, name, price FROM products_services WHERE type='service' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name    = trim($_POST['client_name']);
    $client_phone   = trim($_POST['client_phone']);
    $client_email   = trim($_POST['client_email']);
    $client_address = trim($_POST['client_address']); // 1. Отримуємо Адресу
    
    // --- ЛОГІКА КЛІЄНТА ---
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE full_name = ?");
    $stmt->execute([$client_name]);
    $existing = $stmt->fetch();

    if ($existing) {
        $client_id = $existing['id'];
        
        // 2. Якщо клієнт існує, оновлюємо йому дані (email та адресу), якщо вони вказані
        $updateFields = [];
        $updateValues = [];

        if (!empty($client_email)) {
            $updateFields[] = "email = ?";
            $updateValues[] = $client_email;
        }
        if (!empty($client_address)) {
            $updateFields[] = "address = ?";
            $updateValues[] = $client_address;
        }

        if (!empty($updateFields)) {
            $sqlUpd = "UPDATE clients SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updateValues[] = $client_id;
            $pdo->prepare($sqlUpd)->execute($updateValues);
        }

    } else {
        // 3. Якщо клієнт новий - створюємо з адресою
        $stmt = $pdo->prepare("INSERT INTO clients (full_name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_name, $client_phone, $client_email, $client_address]);
        $client_id = $pdo->lastInsertId();
    }

    // --- ЛОГІКА ЗАМОВЛЕННЯ ---
    $employee_id = $_POST['employee_id'];
    $item_id     = $_POST['item_id'];
    $device      = $_POST['device_model'];
    $problem     = $_POST['problem_description'];
    $price       = $_POST['final_price'];
    
    $sql = "INSERT INTO orders 
            (client_id, employee_id, item_id, device_model, problem_description, final_price, status, contact_name, contact_phone, contact_email, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, NOW())";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $client_id, 
        $employee_id, 
        $item_id, 
        $device, 
        $problem, 
        $price,
        $client_name,
        $client_phone,
        $client_email
    ]);

    $new_id = $pdo->lastInsertId();

    // Лог
    if (function_exists('writeLog')) {
        writeLog($pdo, 'INSERT (Ремонт)', "Оформлено ремонт #$new_id ($device). Клієнт: $client_name");
    }

    header("Location: index.php");
    exit;
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header text-white border-0 pt-3 pb-3" style="border-radius: 16px 16px 0 0; background-color: #6366f1;">
                <h4 class="mb-0 fw-bold"><i class="fa-solid fa-screwdriver-wrench me-2"></i> Оформити ремонт</h4>
            </div>
            <div class="card-body p-4 bg-light">
                <form method="POST">
                    
                    <div class="bg-white p-3 rounded shadow-sm border mb-4">
                        <h5 class="text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-user me-2"></i> Клієнт</h5>
                        <div class="row mb-2">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted">ПІБ Клієнта:</label>
                                <input type="text" name="client_name" class="form-control" placeholder="Напр: Петренко Іван" required list="clients_list">
                                <datalist id="clients_list">
                                    <?php 
                                    $existing = $pdo->query("SELECT full_name FROM clients LIMIT 20")->fetchAll();
                                    foreach($existing as $ex) { echo "<option value='{$ex['full_name']}'>"; }
                                    ?>
                                </datalist>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted">Телефон:</label>
                                <input type="text" name="client_phone" class="form-control" placeholder="097 123 45 67" required>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted">Адреса проживання:</label>
                                <input type="text" name="client_address" class="form-control" placeholder="м. Львів, вул. Бандери 12">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted">Email (необов'язково):</label>
                                <input type="email" name="client_email" class="form-control" placeholder="client@example.com">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-3 rounded shadow-sm border mb-4">
                        <h5 class="text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-mobile-screen me-2"></i> Пристрій та Послуга</h5>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Модель пристрою:</label>
                            <input type="text" name="device_model" class="form-control" placeholder="Напр: Samsung S21 Ultra" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Опис проблеми:</label>
                            <textarea name="problem_description" class="form-control" rows="2" placeholder="Розбитий екран, не вмикається..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Оберіть послугу (з прайсу):</label>
                            <select name="item_id" id="itemSelect" class="form-select" onchange="updatePrice()">
                                <option value="" data-price="0">-- Оберіть послугу --</option>
                                <?php foreach ($services as $srv): ?>
                                    <option value="<?= $srv['id'] ?>" data-price="<?= $srv['price'] ?>">
                                        <?= htmlspecialchars($srv['name']) ?> (<?= $srv['price'] ?> грн)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Майстер:</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Оберіть майстра --</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Орієнтовна вартість (грн):</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 fw-bold">₴</span>
                                <input type="number" step="0.01" name="final_price" id="priceInput" class="form-control border-start-0 ps-0 fw-bold text-success fs-5" required placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg shadow fw-bold" style="background-color: #6366f1; border:none;">
                            <i class="fa-solid fa-check me-2"></i> Оформити замовлення
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary border-0">Скасувати</a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function updatePrice() {
        const select = document.getElementById('itemSelect');
        if (select.selectedIndex >= 0) {
            const price = select.options[select.selectedIndex].getAttribute('data-price');
            if (price) {
                document.getElementById('priceInput').value = price;
            }
        }
    }
</script>

<?php include 'footer.php'; ?>