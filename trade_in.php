<?php
// trade_in.php
session_start();
require_once 'db.php';
include 'header.php';
?>

<style>
    .calc-card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(99, 102, 241, 0.1);
        overflow: hidden;
    }
    .price-display {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        transition: transform 0.3s;
    }
    .price-display:hover { transform: translateY(-5px); }
    .option-card {
        border: 2px solid #f3f4f6;
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .option-card:hover { border-color: #6366f1; background-color: #eef2ff; }
    
    /* Приховуємо стандартні радіо-кнопки */
    .btn-check:checked + .option-card {
        border-color: #6366f1;
        background-color: #6366f1;
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
</style>

<div class="container py-5">
    
    <div class="text-center mb-5">
        <h1 class="fw-bold display-5" style="color: #1f2937;">Trade-In Калькулятор</h1>
        <p class="text-muted fs-5">Дізнайтесь вартість вашого старого гаджета за 1 хвилину</p>
    </div>

    <div class="row g-4 justify-content-center">
        
        <div class="col-lg-7">
            <div class="calc-card bg-white p-4 p-md-5">
                
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-mobile-screen-button text-primary me-2"></i> Оберіть модель</h5>
                <select id="modelSelect" class="form-select form-select-lg mb-4 fw-bold text-secondary" style="border-radius: 12px;">
                    <option value="0" disabled selected>-- Оберіть зі списку --</option>
                    
                    <optgroup label="Apple iPhone">
                        <option value="120000">iPhone 17 Pro Max</option>
                        <option value="95000">iPhone 17 Pro</option>
                        <option value="90000">iPhone 17</option>
                        <option value="60000">iPhone 16 Pro Max</option>
                        <option value="55000">iPhone 16 Plus</option>
                        <option value="50000">iPhone 15</option>
                        <option value="42000">iPhone 15 Pro Max</option>
                        <option value="38000">iPhone 15 Pro</option>
                        <option value="32000">iPhone 15 Plus</option>
                        <option value="32000">iPhone 15</option>
                        <option value="35000">iPhone 14 Pro Max</option>
                        <option value="30000">iPhone 14 Pro</option>
                        <option value="24000">iPhone 14</option>
                        <option value="22000">iPhone 13 Pro</option>
                        <option value="19000">iPhone 13</option>
                        <option value="16000">iPhone 12 Pro</option>
                        <option value="13500">iPhone 12</option>
                        <option value="11000">iPhone 11</option>
                        <option value="9000">iPhone XR / XS</option>
                        <option value="7000">iPhone X</option>
                        <option value="5000">iPhone 8 / SE 2020</option>
                    </optgroup>

                    <optgroup label="Samsung">
                        <option value="38000">Samsung S24 Ultra</option>
                        <option value="30000">Samsung S24</option>
                        <option value="28000">Samsung S23 Ultra</option>
                        <option value="22000">Samsung S23</option>
                        <option value="18000">Samsung S22 Ultra</option>
                        <option value="14000">Samsung S21 FE</option>
                        <option value="12000">Samsung S21</option>
                        <option value="9000">Samsung S20</option>
                        <option value="11000">Samsung A54</option>
                        <option value="8000">Samsung A53</option>
                    </optgroup>

                    <optgroup label="Xiaomi / Poco">
                        <option value="18000">Xiaomi 13T Pro</option>
                        <option value="14000">Xiaomi 13 Lite</option>
                        <option value="12000">Xiaomi 12T</option>
                        <option value="9000">Poco F5</option>
                        <option value="7500">Poco X5 Pro</option>
                        <option value="6000">Redmi Note 12 Pro</option>
                        <option value="4500">Redmi Note 11</option>
                    </optgroup>

                    <optgroup label="Google Pixel">
                        <option value="150000">Google Pixel 10 Fold</option>
                        <option value="50000">Google Pixel 10 Pro</option>
                        <option value="45000">Google Pixel 10 </option>
                        <option value="40000">Google Pixel 9 Pro</option>
                        <option value="35000">Google Pixel 9</option>
                        <option value="30000">Google Pixel 8 Pro</option>
                        <option value="25000">Google Pixel 8</option>
                        <option value="20000">Google Pixel 7 Pro</option>
                        <option value="17000">Google Pixel 7</option>
                        <option value="13000">Google Pixel 6 Pro</option>
                        <option value="10500">Google Pixel 6</option>
                    </optgroup>
                </select>

                <h5 class="fw-bold mb-3"><i class="fa-regular fa-star text-primary me-2"></i> Зовнішній стан</h5>
                <div class="row g-2 mb-4">
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="condition" id="cond1" value="1" checked>
                        <label class="option-card w-100 text-center" for="cond1">
                            <i class="fa-solid fa-gem mb-2 fs-4"></i><br>
                            <small class="fw-bold">Ідеал</small>
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="condition" id="cond2" value="0.85">
                        <label class="option-card w-100 text-center" for="cond2">
                            <i class="fa-solid fa-wand-magic-sparkles mb-2 fs-4"></i><br>
                            <small class="fw-bold">Потертості</small>
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="condition" id="cond3" value="0.6">
                        <label class="option-card w-100 text-center" for="cond3">
                            <i class="fa-solid fa-heart-crack mb-2 fs-4"></i><br>
                            <small class="fw-bold">Подряпини</small>
                        </label>
                    </div>
                </div>

                <h5 class="fw-bold mb-3"><i class="fa-solid fa-triangle-exclamation text-primary me-2"></i> Технічні проблеми</h5>
                
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="brokenScreen" data-cost="0.1">
                    <label class="form-check-label fw-bold" for="brokenScreen">Розбитий дисплей / Плями</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="badBattery" data-cost="0.1">
                    <label class="form-check-label fw-bold" for="badBattery">Погана батарея (менше 80%)</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="noFaceID" data-cost="0.1">
                    <label class="form-check-label fw-bold" for="noFaceID">Не працює FaceID / TouchID</label>
                </div>
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" id="noKit" data-fixed="500">
                    <label class="form-check-label fw-bold" for="noKit">Немає комплекту (коробка/зарядка)</label>
                </div>

            </div>
        </div>

        <div class="col-lg-4">
            <div class="price-display h-100 d-flex flex-column justify-content-center shadow-lg">
                <h4 class="mb-4 opacity-75">Орієнтовна вартість викупу</h4>
                
                <h1 class="display-3 fw-bold mb-0">
                    <span id="finalPrice">0</span> <span class="fs-4">₴</span>
                </h1>
                
                <hr class="border-white opacity-25 my-4">
                
                <p class="small opacity-75 mb-4">Ціна є попередньою. Точну оцінку проведе майстер у сервісному центрі.</p>
                
                <a href="user_add_repair.php" class="btn btn-light text-primary fw-bold btn-lg rounded-pill shadow-sm">
                    <i class="fa-solid fa-hand-holding-dollar me-2"></i> Хочу продати
                </a>
            </div>
        </div>

    </div>
</div>

<script>
    // Основна логіка калькулятора на чистому JS
    const modelSelect = document.getElementById('modelSelect');
    const finalPriceEl = document.getElementById('finalPrice');
    const checkboxes = document.querySelectorAll('.form-check-input');
    const radios = document.querySelectorAll('input[name="condition"]');

    function calculate() {
        // 1. Базова ціна моделі
        let price = parseFloat(modelSelect.value);
        if (price === 0) {
            finalPriceEl.textContent = "0";
            return;
        }

        // 2. Коефіцієнт зовнішнього стану (Ідеал = 1, Потертий = 0.85...)
        let conditionRate = 1;
        radios.forEach(r => {
            if (r.checked) conditionRate = parseFloat(r.value);
        });
        price = price * conditionRate;

        // 3. Віднімання за поломки
        checkboxes.forEach(chk => {
            if (chk.checked) {
                // Якщо є відсоток зниження (наприклад 0.4 за екран)
                if (chk.dataset.cost) {
                    let penalty = parseFloat(modelSelect.value) * parseFloat(chk.dataset.cost);
                    price -= penalty;
                }
                // Якщо фіксована сума (наприклад 500 грн за коробку)
                if (chk.dataset.fixed) {
                    price -= parseFloat(chk.dataset.fixed);
                }
            }
        });

        // 4. Захист від від'ємних значень
        if (price < 0) price = 0;

        // 5. Анімація чисел
        animateValue(finalPriceEl, parseInt(finalPriceEl.textContent.replace(/\s/g, '')), Math.floor(price), 500);
    }

    // Функція анімації (щоб цифри гарно "бігли")
    function animateValue(obj, start, end, duration) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            obj.innerHTML = value.toLocaleString('uk-UA'); // Формат 10 000
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    // Слухаємо зміни
    modelSelect.addEventListener('change', calculate);
    checkboxes.forEach(el => el.addEventListener('change', calculate));
    radios.forEach(el => el.addEventListener('change', calculate));
</script>

<?php include 'footer.php'; ?>