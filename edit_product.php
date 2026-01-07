<?php
// edit_product.php
require 'db.php';

// ЗАХИСТ: Тільки адмін
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: index.php"); 
    exit; 
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

// 1. Отримуємо поточні дані товару
$stmt = $pdo->prepare("SELECT * FROM products_services WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { die("Товар не знайдено."); }

// 2. Якщо товар ПРОДАНИЙ, шукаємо хто його купив (з таблиці orders)
$buyer_name = '';
if ($product['is_sold']) {
    $stmtOrder = $pdo->prepare("SELECT contact_name FROM orders WHERE item_id = ? LIMIT 1");
    $stmtOrder->execute([$id]);
    $orderData = $stmtOrder->fetch();
    if ($orderData) {
        $buyer_name = $orderData['contact_name'];
    }
}

// --- ОБРОБКА ФОРМИ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $desc = $_POST['description'];
    $warranty = $_POST['warranty'];

    // --- ЛОГІКА ЗАВАНТАЖЕННЯ ФОТО ---
    $imageName = $product['image']; 

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $newFileName = uniqid() . '.' . $ext;
            $uploadPath = 'uploads/' . $newFileName;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                $imageName = $newFileName; 
                if ($product['image'] && file_exists('uploads/' . $product['image'])) {
                    unlink('uploads/' . $product['image']);
                }
            }
        }
    }

    // 3. ОНОВЛЮЄМО ТОВАР
    $sql = "UPDATE products_services SET name=?, price=?, description=?, warranty_days=?, image=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $price, $desc, $warranty, $imageName, $id]);

    // 4. ОНОВЛЮЄМО ІМ'Я ПОКУПЦЯ (Тільки якщо товар проданий)
    if ($product['is_sold'] && isset($_POST['buyer_name'])) {
        $new_buyer = trim($_POST['buyer_name']);
        // Оновлюємо запис в orders, який прив'язаний до цього товару (item_id)
        $pdo->prepare("UPDATE orders SET contact_name = ? WHERE item_id = ?")->execute([$new_buyer, $id]);
    }

    header("Location: index.php?tab=sales"); // Повертаємо в історію продажів
    exit;
}

include 'header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-6">
        <div class="card shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header text-white border-0 pt-3 pb-3" style="background-color: #6366f1;">
                <h4 class="mb-0 fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Редагувати товар</h4>
            </div>
            
            <div class="card-body p-4 bg-white">
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-4 text-center">
                        <label class="form-label fw-bold d-block text-secondary small">Поточне фото:</label>
                        <div class="d-inline-block p-2 border rounded bg-light">
                            <?php if (!empty($product['image']) && file_exists('uploads/' . $product['image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($product['image']) ?>" style="max-height: 150px; max-width: 100%; border-radius: 8px;">
                            <?php else: ?>
                                <div class="text-muted p-4">
                                    <i class="fa-solid fa-image fa-3x mb-2 opacity-25"></i>
                                    <p class="mb-0 small">Фото відсутнє</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Назва товару:</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
                    </div>

                    
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Завантажити нове фото:</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">Ціна (грн):</label>
                            <input type="number" step="0.01" name="price" class="form-control" value="<?= $product['price'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">Гарантія (днів):</label>
                            <input type="number" name="warranty" class="form-control" value="<?= $product['warranty_days'] ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Опис:</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm" style="background-color: #6366f1; border: none; padding: 12px;">
                            <i class="fa-solid fa-floppy-disk me-2"></i> Зберегти зміни
                        </button>
                        <a href="index.php?tab=sales" class="btn btn-light text-muted fw-bold">Скасувати</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>