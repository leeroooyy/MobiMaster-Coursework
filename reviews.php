<?php 
// reviews.php
require 'db.php'; 
include 'header.php'; 

// --- ЛОГІКА ДОДАВАННЯ ВІДГУКУ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $name = trim($_POST['custom_name']);
    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    if (empty($name)) {
        $name = $_SESSION['full_name'];
    }

    $sql = "INSERT INTO reviews (user_id, user_name, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $name, $rating, $comment]);

    echo "<script>window.location.href='reviews.php';</script>";
    exit;
}

// --- ЛОГІКА СТАТИСТИКИ ---
// 1. Загальна статистика (кількість та середній бал)
$statQuery = $pdo->query("SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM reviews");
$stats = $statQuery->fetch(PDO::FETCH_ASSOC);
$totalReviews = $stats['total'];
$averageRating = round($stats['avg_rating'] ?? 0, 1); // Округлюємо до 1 знаку (напр. 4.7)

// 2. Розподіл по оцінках (скільки 5-рок, 4-рок...)
$starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$groupQuery = $pdo->query("SELECT rating, COUNT(*) as cnt FROM reviews GROUP BY rating");
$rows = $groupQuery->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $starCounts[$row['rating']] = $row['cnt'];
}

// --- ОТРИМАННЯ СПИСКУ ВІДГУКІВ ---
$reviews = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll();
?>

<div class="container py-4">
    
    <div class="text-center mb-5">
        <h1 class="fw-bold display-5" style="color: #6366f1;">💬 Відгуки наших клієнтів</h1>
        <p class="text-muted lead">Дізнайтесь, що про нас думають інші</p>
    </div>

    <div class="card border-0 shadow-sm mb-5 overflow-hidden" style="border-radius: 16px;">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center">
                
                <div class="col-md-4 text-center border-end mb-4 mb-md-0">
                    <div class="display-2 fw-bold text-dark"><?= $averageRating ?></div>
                    <div class="text-warning fs-4 mb-2">
                        <?php 
                        $fullStars = floor($averageRating);
                        $halfStar = ($averageRating - $fullStars) >= 0.5;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $fullStars) echo '<i class="fa-solid fa-star"></i>';
                            elseif ($halfStar && $i == $fullStars + 1) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                            else echo '<i class="fa-regular fa-star"></i>';
                        }
                        ?>
                    </div>
                    <p class="text-muted mb-0">На основі <b><?= $totalReviews ?></b> відгуків</p>
                </div>

                <div class="col-md-8 ps-md-5">
                    <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                        <?php 
                            $count = $starCounts[$star];
                            $percent = ($totalReviews > 0) ? ($count / $totalReviews * 100) : 0;
                        ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="text-nowrap me-3 small fw-bold text-muted" style="width: 30px;">
                                <?= $star ?> <i class="fa-solid fa-star text-warning"></i>
                            </div>
                            <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px; background-color: #f3f4f6;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?= $percent ?>%; background-color: #6366f1; border-radius: 4px;" 
                                     aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <div class="text-muted small ms-3 fw-bold" style="width: 30px; text-align: right;">
                                <?= $count ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        
        <div class="col-md-7 mb-4">
            <h4 class="fw-bold mb-4 text-dark">Останні відгуки</h4>
            
            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="card p-4 mb-3 border-0 shadow-sm" style="border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-light d-flex justify-content-center align-items-center text-primary fw-bold me-3" 
                                     style="width: 45px; height: 45px; font-size: 1.2rem;">
                                    <?= mb_substr($rev['user_name'], 0, 1) ?>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($rev['user_name']) ?></h6>
                                    <div class="text-warning small">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <?= ($i <= $rev['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star text-muted opacity-25"></i>' ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></small>
                        </div>
                        
                        <p class="text-secondary mt-2 mb-0" style="line-height: 1.6;">"<?= nl2br(htmlspecialchars($rev['comment'])) ?>"</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted bg-light rounded border border-dashed">
                    <i class="fa-regular fa-comments fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">Поки що немає відгуків. Ваш може бути першим!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-5">
            <div class="card p-4 border-0 shadow-lg sticky-top" style="top: 20px; border-radius: 16px;">
                <h4 class="fw-bold mb-3" style="color: #6366f1;">✍️ Залишити відгук</h4>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Ваше ім'я</label>
                            <input type="text" name="custom_name" class="form-control py-2" 
                                   value="<?= htmlspecialchars($_SESSION['full_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Оцінка сервісу</label>
                            <select name="rating" class="form-select py-2" required>
                                <option value="5">⭐⭐⭐⭐⭐ Відмінно</option>
                                <option value="4">⭐⭐⭐⭐ Добре</option>
                                <option value="3">⭐⭐⭐ Нормально</option>
                                <option value="2">⭐⭐ Погано</option>
                                <option value="1">⭐ Жахливо</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Ваш коментар</label>
                            <textarea name="comment" class="form-control" rows="4" placeholder="Напишіть, що вам сподобалось, а що ні..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" style="background-color: #6366f1; border:none; border-radius: 8px;">
                            Опублікувати відгук
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted mb-3">Щоб залишити відгук, потрібно авторизуватися.</p>
                        <a href="login.php" class="btn btn-outline-primary w-100 fw-bold rounded-pill">Увійти в кабінет</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>