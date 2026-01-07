<?php
// feedback.php

// Вмикаємо показ помилок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Перевірка входу
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Обробка форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    if (!empty($name) && !empty($message)) {
        try {
            // ВИПРАВЛЕНО: Таблиця feedback
            $sql = "INSERT INTO feedback (user_id, name, phone, email, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$user_id, $name, $phone, $email, $message])) {
                echo "<script>alert('Повідомлення успішно надіслано!'); window.location.href='index.php';</script>";
                exit;
            } else {
                echo "<script>alert('Не вдалося зберегти повідомлення.');</script>";
            }
        } catch (PDOException $e) {
            die("<div class='alert alert-danger m-5'>Помилка SQL: " . $e->getMessage() . "</div>");
        }
    } else {
        echo "<script>alert('Будь ласка, заповніть ім\'я та повідомлення.');</script>";
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg" style="border-radius: 16px;">
                <div class="card-body p-5 text-center">
                    
                    <h3 class="fw-bold mb-4" style="color: #6366f1;">
                        <i class="fa-solid fa-envelope-open-text me-2"></i> Напишіть нам
                    </h3>
                    <p class="text-muted mb-4">Маєте запитання чи пропозицію?</p> 
                    <p class="text-muted mb-4">Наш менеджер зв'яжеться з вами як найшвидше</p>

                    <form method="POST" autocomplete="off">
                        
                        <div class="form-floating mb-3 text-start">
                            <input type="text" name="name" class="form-control" id="floatingName" placeholder="Ваше ім'я" required>
                            <label for="floatingName">Ваше ім'я</label>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <div class="form-floating text-start">
                                    <input type="text" name="phone" class="form-control" id="floatingPhone" placeholder="Телефон">
                                    <label for="floatingPhone">Телефон</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating text-start">
                                    <input type="email" name="email" class="form-control" id="floatingEmail" placeholder="Email">
                                    <label for="floatingEmail">Email</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-4 text-start">
                            <textarea name="message" class="form-control" placeholder="Повідомлення" id="floatingText" style="height: 150px" required></textarea>
                            <label for="floatingText">Опишіть вашу проблему...</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" style="background-color: #6366f1; border: none; border-radius: 10px;">
                            <i class="fa-solid fa-paper-plane me-2"></i> Надіслати
                        </button>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>