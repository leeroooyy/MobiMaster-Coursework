<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $desc = $_POST['description'];
    $warranty = $_POST['warranty'];

    // --- ЗАВАНТАЖЕННЯ ФОТО ---
    $imageName = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowed)) {
            $imageName = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $imageName);
        }
    }
    // -------------------------

    $sql = "INSERT INTO products_services (name, type, price, description, warranty_days, image) 
            VALUES (?, 'product', ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $price, $desc, $warranty, $imageName]);

    header("Location: index.php");
    exit;
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header text-white border-0 pt-3 pb-3" style="border-radius: 16px 16px 0 0; background-color: #6366f1;">
                <h4 class="mb-0 fw-bold"><i class="fa-solid fa-camera"></i> Додати товар з фото</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Назва:</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Фото товару:</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">Ціна (грн):</label>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">Гарантія (днів):</label>
                            <input type="number" name="warranty" class="form-control" value="30">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Опис:</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg shadow-sm" style="background-color: #6366f1; border-color: #6366f1;">
                            <i class="fa-solid fa-check"></i> Додати
                        </button>
                        <a href="index.php" class="btn btn-light text-muted">Скасувати</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>