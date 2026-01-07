<?php
// careers.php
session_start();
require_once 'db.php';

// Обробка форми
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $experience = trim($_POST['experience']);
    $vacancy = trim($_POST['vacancy']);
    $message = trim($_POST['message']);

    $stmt = $pdo->prepare("INSERT INTO job_applications (name, phone, email, experience, vacancy, message) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$name, $phone, $email, $experience, $vacancy, $message])) {
        
        // --- ЗАПИС У ЛОГИ ---
        // Якщо користувач не залогінений, ID буде 0 (Гість)
        $log_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $log_action = 'HR_APPLY';
        $log_details = "Подано заявку на вакансію '$vacancy'. Кандидат: $name, Тел: $phone";

        $stmt_log = $pdo->prepare("INSERT INTO logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt_log->execute([$log_user_id, $log_action, $log_details]);
        // --------------------

        echo "<script>alert('Вашу заявку успішно відправлено! Ми зв\'яжемося з вами.'); window.location.href='careers.php';</script>";
    } else {
        echo "<script>alert('Помилка відправки.');</script>";
    }
}

include 'header.php';
?>

<div class="container py-5">
    
    <div class="text-center mb-5">
        <div class="d-inline-block p-3 rounded-circle bg-white shadow-sm mb-3">
            <i class="fa-solid fa-briefcase fa-2x" style="color: #6366f1;"></i>
        </div>
        <h2 class="fw-bold display-6 mb-2" style="color: #1f2937;">Вакансії MobiMaster</h2>
        <p class="text-secondary fs-5">Стань частиною нашої команди професіоналів!</p>
    </div>

    <div class="row g-4 justify-content-center">
        
        <div class="col-lg-5 col-md-6">
            <div class="card h-100 border-0 shadow-sm transition-hover" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-4 p-lg-5 d-flex flex-column">
                    <div class="mb-3">
                        <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill">
                            <i class="fa-solid fa-fire me-1"></i> Гаряча вакансія
                        </span>
                    </div>
                    <h3 class="fw-bold text-dark mb-2">Майстер з ремонту</h3>
                    <h4 class="text-success fw-bold mb-4">5 000 - 15 000 грн + %</h4>
                    
                    <div class="flex-grow-1">
                        <p class="fw-bold text-secondary mb-2 small text-uppercase">Вимоги:</p>
                        <ul class="list-unstyled text-secondary mb-4">
                            <li class="mb-2"><i class="fa-solid fa-check text-primary me-2"></i> Досвід пайки (BGA)</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-primary me-2"></i> Знання схемотехніки iPhone/Android</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-primary me-2"></i> Акуратність та відповідальність</li>
                        </ul>
                    </div>

                    <button class="btn btn-primary w-100 py-3 fw-bold shadow-sm btn-custom" 
                            data-bs-toggle="modal" data-bs-target="#applyModal" data-vacancy="Майстер з ремонту">
                        Відгукнутися
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-md-6">
            <div class="card h-100 border-0 shadow-sm transition-hover" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-4 p-lg-5 d-flex flex-column">
                    <div class="mb-3">
                        <span class="badge bg-info text-dark fw-bold px-3 py-2 rounded-pill">
                            <i class="fa-solid fa-headset me-1"></i> Робота з людьми
                        </span>
                    </div>
                    <h3 class="fw-bold text-dark mb-2">Менеджер по роботі з клієнтами</h3>
                    <h4 class="text-success fw-bold mb-4">15 000 - 20 000 грн</h4>
                    
                    <div class="flex-grow-1">
                        <p class="fw-bold text-secondary mb-2 small text-uppercase">Вимоги:</p>
                        <ul class="list-unstyled text-secondary mb-4">
                            <li class="mb-2"><i class="fa-solid fa-check text-primary me-2"></i> Вміння вирішувати конфлікти</li>
                            <li class="mb-2"><i class="fa-solid fa-check text-primary me-2"></i> Базове розуміння техніки</li>
                        </ul>
                    </div>

                    <button class="btn btn-primary w-100 py-3 fw-bold shadow-sm btn-custom" 
                            data-bs-toggle="modal" data-bs-target="#applyModal" data-vacancy="Менеджер">
                        Відгукнутися
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="applyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header text-white px-4 py-3" style="background-color: #6366f1;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-paper-plane me-2"></i> Відгук на вакансію</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="apply_job" value="1">
                    <input type="hidden" name="vacancy" id="modalVacancyInput">
                    
                    <div class="text-center mb-4">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary fs-6 px-3 py-2 rounded-pill" id="modalVacancyTitle"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Ваше ПІБ <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control py-2" required placeholder="Іванов Іван">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Телефон <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control py-2" required placeholder="+380...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Стаж роботи <span class="text-danger">*</span></label>
                            <input type="text" name="experience" class="form-control py-2" required placeholder="Напр: 2 роки">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Email (необов'язково)</label>
                        <input type="email" name="email" class="form-control py-2" placeholder="mail@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Коротко про себе</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Розкажіть, чому ми маємо взяти саме вас..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" style="background-color: #6366f1; border:none;">Надіслати анкету</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .btn-custom {
        background-color: #6366f1 !important;
        border: none !important;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-custom:hover {
        background-color: #4f46e5 !important;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3) !important;
    }
    .transition-hover {
        transition: transform 0.3s ease;
    }
    .transition-hover:hover {
        transform: translateY(-5px);
    }
</style>

<script>
    const applyModal = document.getElementById('applyModal');
    if (applyModal) {
        applyModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const vacancy = button.getAttribute('data-vacancy');
            document.getElementById('modalVacancyInput').value = vacancy;
            document.getElementById('modalVacancyTitle').textContent = vacancy;
        });
    }
</script>

<?php include 'footer.php'; ?>