<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Отримуємо клієнта
$stmt = $pdo->prepare("SELECT id FROM clients WHERE full_name = ?");
$stmt->execute([$_SESSION['full_name']]);
$client = $stmt->fetch();
$client_id = $client ? $client['id'] : 1; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $model = $_POST['model'];
    $price = $_POST['price'];
    $desc  = $_POST['description'];
    $device_name = "Продаж: " . $model; 
    
    // --- ЗАВАНТАЖЕННЯ ФОТО ---
    $imageName = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowed)) {
            $imageName = uniqid() . '.' . $ext; // Унікальна назва
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $imageName);
        }
    }
    // -------------------------

    $sql = "INSERT INTO orders (client_id, device_model, problem_description, status, final_price, image) 
            VALUES (?, ?, ?, 'new', ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id, $device_name, $desc, $price, $imageName]);

    echo "<script>alert('Ваша пропозиція відправлена!'); window.location.href='index.php';</script>";
    exit;
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-lg border-0">
            <div class="card-header text-white border-0 pt-4 pb-3 text-center" style="border-radius: 16px 16px 0 0; background-color: #6366f1;">
                <h3 class="fw-bold mb-0"><i class="fa-solid fa-camera"></i> Продати гаджет</h3>
                <p class="mb-0 mt-2 text-white-50">Додайте фото для кращої оцінки</p>
            </div>
            
            <div class="card-body p-4 p-md-5">
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">Що продаєте?</label>
                        <input type="text" name="model" class="form-control" placeholder="Напр: iPhone X 64GB" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">Фото пристрою:</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <small class="text-muted">Бажано додати реальне фото стану</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">Стан та комплект:</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Опишіть стан..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">Бажана ціна (грн):</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">₴</span>
                            <input type="number" name="price" class="form-control border-start-0 ps-0" placeholder="5000" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-5">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm" style="background-color: #6366f1; border-color: #6366f1;">
                            <i class="fa-solid fa-check-circle"></i> Запропонувати
                        </button>
                        <a href="index.php" class="btn btn-light text-muted">Скасувати</a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>