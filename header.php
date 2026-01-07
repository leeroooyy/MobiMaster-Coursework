<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MobiMaster | Сервісний Центр</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* === 1. ЗМІННІ ТЕМ === */
        :root {
            /* Світла (Light) */
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --bg-secondary: #f9fafb;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --navbar-bg: #6366f1;
            --input-bg: #ffffff;
            --table-border: #e5e7eb;
        }

        /* Темна (Dark) */
        body.dark-mode {
            --bg-body: #111827;       /* Дуже темний фон */
            --bg-card: #1f2937;       /* Темно-сірі картки */
            --bg-secondary: #374151;  /* Для заголовків таблиць */
            --text-main: #f3f4f6;     /* Білий текст */
            --text-muted: #9ca3af;    /* Світло-сірий текст */
            --border-color: #374151;  /* Темні рамки */
            --navbar-bg: #312e81;     /* Темніший хедер */
            --input-bg: #374151;      /* Темні поля */
            --table-border: #4b5563;
        }

        /* === 2. БАЗОВІ СТИЛІ === */
        body { 
            background-color: var(--bg-body) !important;
            color: var(--text-main) !important;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex; flex-direction: column;
            transition: background-color 0.3s, color 0.3s;
        }

        /* === 3. АДАПТАЦІЯ BOOTSTRAP ПІД ТЕМУ === */
        
        /* Картки та білі блоки */
        .card, body.dark-mode .bg-white { 
            background-color: var(--bg-card) !important;
            color: var(--text-main) !important;
            border-color: var(--border-color) !important;
        }

        /* Таблиці */
        .table {
            color: var(--text-main) !important;
            --bs-table-color: var(--text-main);
            --bs-table-bg: transparent;
            --bs-table-border-color: var(--table-border);
        }
        
        /* Заголовки таблиць та світлі фони */
        .table thead th, body.dark-mode .bg-light { 
            background-color: var(--bg-secondary) !important; 
            color: var(--text-main) !important;
            border-color: var(--border-color) !important;
        }
        
        /* Рядки таблиці */
        .table tbody td {
            border-color: var(--border-color) !important;
        }
        
        /* Ефект наведення в таблиці */
        .table-hover tbody tr:hover {
            color: var(--text-main) !important;
            background-color: rgba(255,255,255,0.05) !important;
        }

        /* Поля вводу (input, select) */
        .form-control, .form-select {
            background-color: var(--input-bg) !important;
            color: var(--text-main) !important;
            border-color: var(--border-color) !important;
        }
        .form-control::placeholder { color: var(--text-muted); }
        .form-control:focus { 
            background-color: var(--input-bg) !important; 
            color: var(--text-main) !important; 
        }

        /* Текст */
        .text-dark, h1, h2, h3, h4, h5, h6 { color: var(--text-main) !important; }
        .text-muted, .text-secondary { color: var(--text-muted) !important; }

        /* Навігація */
        .navbar {
            background: var(--navbar-bg) !important;
            padding: 12px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .navbar-brand { font-weight: 800; font-size: 1.6rem; display: flex; align-items: center; gap: 10px; color: white !important; }
        .nav-link { font-weight: 600; color: rgba(255,255,255,0.85) !important; margin-right: 15px; }
        .nav-link:hover { color: white !important; }

        .container-main { flex: 1; padding-top: 30px; padding-bottom: 40px; }
        
        /* Виправлення для модальних вікон */
        .modal-content {
            background-color: var(--bg-card);
            color: var(--text-main);
        }
        .modal-header, .modal-footer {
            border-color: var(--border-color);
        }
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); } /* Білий хрестик в темній темі */
    </style>
</head>
<body>

<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!function_exists('getArrow')) {
    function getArrow($col, $currentSort, $currentDir) {
        if ($col !== $currentSort) return ' <i class="fa-solid fa-sort text-muted opacity-25"></i>';
        return $currentDir === 'asc' ? ' <i class="fa-solid fa-arrow-up-long"></i>' : ' <i class="fa-solid fa-arrow-down-long"></i>';
    }
}

if (isset($_SESSION['user_id'])): 
    $is_admin = ($_SESSION['role'] === 'admin');
?>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fa-solid fa-screwdriver-wrench text-warning"></i> MobiMaster
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto ms-3">
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="user_buy.php">Магазин</a></li>
                    
                    <?php if ($is_admin): ?>
                        <li class="nav-item"><a class="nav-link text-warning" href="admin_dashboard.php"><i class="fa-solid "></i> Адмін-панель</a></li>
                        <li class="nav-item"><a class="nav-link text-warning fw-bold" href="salary.php"><i class="fa-solid "></i> Працівники</a></li>
                        <li class="nav-item"><a class="nav-link text-warning fw-bold" href="admin_jobs.php"><i class="fa-solid "></i> Заявки HR</a></li>
                        
                        <li class="nav-item"><a class="nav-link text-warning fw-bold" href="clients.php"><i class="fa-solid "></i> Користувачі</a></li>
                        
                        <li class="nav-item"><a class="nav-link" href="reports.php">Звіти</a></li>
                        <li class="nav-item"><a class="nav-link text-warning" href="admin_feedback.php"><i class="fa-solid "></i> Вхідні</a></li>
                        <li class="nav-item"><a class="nav-link" href="logs.php">Логи</a></li>
                       
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="location.php">Де нас знайти</a></li>
                        <li class="nav-item"><a class="nav-link" href="feedback.php">Зворотній звʼязок</a></li>
                        <li class="nav-item"><a class="nav-link" href="careers.php">Шукаємо майстрів</a></li>
                        <li class="nav-item"><a class="nav-link" href="reviews.php">Відгуки</a></li>
                        <li class="nav-item"><a class="nav-link text-white fw-bold" href="trade_in.php"><i class="fa-solid "></i> Trade-In</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-light btn-sm rounded-circle shadow-sm d-flex align-items-center justify-content-center" id="themeToggle" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-moon"></i>
                    </button>

                    <div class="text-white text-end d-none d-md-block" style="line-height: 1.2;">
                        <span class="d-block fw-bold" style="font-size: 0.85rem;">
                            <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </span>
                        <span class="badge bg-white text-primary rounded-pill fw-bold" style="font-size: 0.65rem; padding: 4px 8px;">
                            <?= $is_admin ? 'ADMIN' : 'CLIENT' ?>
                        </span>
                    </div>
                    <a href="logout.php" class="btn btn-light text-primary btn-sm rounded-circle shadow-sm" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>

<div class="container container-main">

<script>
    const toggleBtn = document.getElementById('themeToggle');
    const body = document.body;
    const icon = toggleBtn ? toggleBtn.querySelector('i') : null;

    // Перевірка при завантаженні
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        if(icon) { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
    }

    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                localStorage.setItem('theme', 'light');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        });
    }
</script>