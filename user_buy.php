<?php
// user_buy.php
session_start();
require_once 'db.php';

// --- ФУНКЦІЯ ДЛЯ МАЛЮВАННЯ КАРТКИ ТОВАРУ (Щоб не дублювати код) ---
function renderProductCard($item, $is_logged_in) {
    $imgSrc = (!empty($item['image']) && file_exists('uploads/' . $item['image'])) 
              ? 'uploads/' . htmlspecialchars($item['image']) 
              : '';
    
    $btnLink = $is_logged_in ? "buy_confirm.php?id={$item['id']}" : "login.php";
    
    // Форматування ціни
    $price = number_format($item['price'], 0, ' ', ' ');

    $html = '
    <div class="col">
        <div class="card h-100 shadow-sm border-0 transition-hover" style="border-radius: 16px; overflow: hidden;">
            <div class="d-flex justify-content-center align-items-center bg-light position-relative" style="height: 260px; background: radial-gradient(circle, #ffffff 0%, #f3f4f6 100%);">';
    
    if ($imgSrc) {
        $html .= '<img src="' . $imgSrc . '" class="product-image" alt="' . htmlspecialchars($item['name']) . '">';
    } else {
        $html .= '<i class="fa-solid fa-mobile-screen-button fa-4x text-muted opacity-25"></i>';
    }

    $html .= '
                <div class="position-absolute top-0 end-0 m-3">
                    <span class="badge bg-white text-dark border shadow-sm py-2 px-3 rounded-pill">
                        <i class="fa-solid fa-shield-halved text-primary me-1"></i> ' . $item['warranty_days'] . ' днів гарантії
                    </span>
                </div>
            </div>
            
            <div class="card-body d-flex flex-column p-4">
                <h5 class="card-title fw-bold text-dark mb-3">' . htmlspecialchars($item['name']) . '</h5>
                
                <p class="card-text text-secondary small flex-grow-1 mb-4" style="line-height: 1.6;">
                    ' . mb_strimwidth(htmlspecialchars($item['description']), 0, 110, "...") . '
                </p>
                
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <small class="text-muted d-block mb-1">Ціна:</small>
                        <h3 class="fw-bold text-dark mb-0">' . $price . ' <small>₴</small></h3>
                    </div>
                </div>
                
                <hr class="my-4 opacity-10">
                
                <a href="' . $btnLink . '" class="btn-buy-beautiful text-decoration-none">
                    <i class="fa-solid fa-cart-shopping"></i> Купити
                </a>
            </div>
        </div>
    </div>';
    
    return $html;
}

// ==========================================================================
// 1. ОБРОБКА AJAX-ЗАПИТУ (ЖИВИЙ ПОШУК)
// ==========================================================================
if (isset($_GET['ajax_store'])) {
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';
    $min_price = $_GET['min_price'] ?? 0;
    $max_price = $_GET['max_price'] ?? 999999;

    // Сортування
    $order_sql = "ORDER BY id DESC";
    if ($sort == 'price_asc') $order_sql = "ORDER BY price ASC";
    if ($sort == 'price_desc') $order_sql = "ORDER BY price DESC";
    if ($sort == 'name_asc') $order_sql = "ORDER BY name ASC";

    // Побудова запиту
    $sql = "SELECT * FROM products_services WHERE type = 'product' AND is_sold = 0";
    $params = [];

    // Пошук
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Фільтр ціни
    if (is_numeric($min_price) && is_numeric($max_price)) {
        $sql .= " AND price >= ? AND price <= ?";
        $params[] = $min_price;
        $params[] = $max_price;
    }

    $sql .= " $order_sql";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($products) > 0) {
            foreach ($products as $item) {
                echo renderProductCard($item, isset($_SESSION['user_id']));
            }
        } else {
            echo '<div class="col-12 text-center py-5">
                    <i class="fa-solid fa-magnifying-glass fa-3x text-muted opacity-25 mb-3"></i>
                    <h4 class="text-dark">Нічого не знайдено</h4>
                    <p class="text-muted">Спробуйте змінити параметри пошуку або ціни.</p>
                  </div>';
        }
    } catch (PDOException $e) {
        echo '<div class="col-12 text-danger">Помилка: ' . $e->getMessage() . '</div>';
    }
    exit; // Важливо зупинити скрипт тут
}

// ==========================================================================
// 2. ОСНОВНА СТОРІНКА
// ==========================================================================
include 'header.php';

// Отримуємо мінімальну та максимальну ціну для плейсхолдерів
$priceRange = $pdo->query("SELECT MIN(price) as min_p, MAX(price) as max_p FROM products_services WHERE type='product' AND is_sold=0")->fetch();
$globalMin = floor($priceRange['min_p'] ?? 0);
$globalMax = ceil($priceRange['max_p'] ?? 100000);
?>

<div class="container py-5">
    
    <div class="row align-items-center mb-4">
        <div class="col-lg-6">
            <h2 class="fw-bold display-6 mb-1">Магазин смартфонів</h2>
            <p class="text-secondary mb-0">Перевірена техніка з гарантією від MobiMaster</p>
        </div>
        <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user_add_repair.php" class="btn btn-outline-primary fw-bold rounded-pill px-4" >
                    <i class="fa-solid fa-screwdriver-wrench me-2"></i> Записатись на ремонт
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-primary fw-bold rounded-pill px-4">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Увійти
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-4 mb-5" style="border-radius: 16px; background-color: #fff;">
        <div class="row g-3 align-items-end">
            
            <div class="col-lg-4 col-md-6">
                <label class="form-label fw-bold small text-secondary">Пошук моделі</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" id="liveSearch" class="form-control border-start-0" placeholder="iPhone 11, Samsung...">
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <label class="form-label fw-bold small text-secondary">Ціна (грн)</label>
                <div class="input-group">
                    <input type="number" id="priceMin" class="form-control" placeholder="Від <?= $globalMin ?>">
                    <span class="input-group-text bg-light border-start-0 border-end-0">-</span>
                    <input type="number" id="priceMax" class="form-control" placeholder="До <?= $globalMax ?>">
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <label class="form-label fw-bold small text-secondary">Сортування</label>
                <select id="sortSelect" class="form-select">
                    <option value="newest">✨ Спочатку нові</option>
                    <option value="price_asc">📉 Від дешевих</option>
                    <option value="price_desc">📈 Від дорогих</option>
                    <option value="name_asc">🔤 За назвою (А-Я)</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="productsContainer">
        <?php
        // Початкове завантаження (щоб сторінка не була пустою до JS запиту)
        $stmt = $pdo->query("SELECT * FROM products_services WHERE type='product' AND is_sold=0 ORDER BY id DESC");
        $initial_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($initial_products) > 0) {
            foreach ($initial_products as $item) {
                echo renderProductCard($item, isset($_SESSION['user_id']));
            }
        } else {
            echo '<div class="col-12 text-center py-5 text-muted"><h4>Товарів поки немає.</h4></div>';
        }
        ?>
    </div>

    <div id="loadingSpinner" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Завантаження...</span>
        </div>
    </div>

</div>

<style>
    .transition-hover {
        transition: all 0.3s ease;
    }
    .transition-hover:hover {
        transform: translateY(-7px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important;
    }
    .product-image {
        max-height: 85%;
        max-width: 85%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }
    .transition-hover:hover .product-image {
        transform: scale(1.05);
    }
    .btn-buy-beautiful {
        background: linear-gradient(135deg, #6366f1 0%, #8a8dff 100%);
        border: none;
        color: white !important;
        font-weight: 700;
        font-size: 1.1rem;
        padding: 14px 20px;
        border-radius: 14px;
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3);
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        display: flex; align-items: center; justify-content: center; width: 100%; cursor: pointer;
    }
    .btn-buy-beautiful:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #7c7fff 100%);
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.5);
    }
    .btn-buy-beautiful i { margin-right: 12px; transition: transform 0.3s ease; }
    .btn-buy-beautiful:hover i { transform: scale(1.1) rotate(-5deg); }
    .btn-buy-beautiful:active { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const liveSearch = document.getElementById('liveSearch');
    const priceMin = document.getElementById('priceMin');
    const priceMax = document.getElementById('priceMax');
    const sortSelect = document.getElementById('sortSelect');
    const container = document.getElementById('productsContainer');
    const spinner = document.getElementById('loadingSpinner');

    let timeout = null;

    function fetchProducts() {
        // Показуємо спінер, ховаємо товари (можна зробити прозорість замість ховання)
        container.style.opacity = '0.5';
        spinner.classList.remove('d-none');

        const search = liveSearch.value;
        const sort = sortSelect.value;
        const min = priceMin.value || 0;
        const max = priceMax.value || 999999;

        const url = `user_buy.php?ajax_store=1&search=${encodeURIComponent(search)}&sort=${sort}&min_price=${min}&max_price=${max}`;

        fetch(url)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
                container.style.opacity = '1';
                spinner.classList.add('d-none');
            })
            .catch(err => console.error(err));
    }

    // Слухаємо події
    const inputs = [liveSearch, priceMin, priceMax, sortSelect];
    
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(fetchProducts, 300); // Затримка 300мс
        });
        // Для селекта краще реагувати на change
        if(input.tagName === 'SELECT') {
            input.addEventListener('change', fetchProducts);
        }
    });
});
</script>

<?php include 'footer.php'; ?>