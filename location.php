<?php require 'db.php'; include 'header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-lg border-0 overflow-hidden">
            <div class="row g-0">
                
                <div class="col-md-5 p-5 d-flex flex-column justify-content-center text-white" 
                     style="background-color: #6366f1 !important;">
                    
                    <h2 class="fw-bold mb-4" style="color: white !important;">📍 Наші контакти</h2>
                    
                    <div class="mb-4">
                        <h6 class="text-uppercase text-white-50 small fw-bold" style="color: rgba(255,255,255,0.5) !important;">Адреса</h6>
                        <p class="fs-5 mb-0"><i class="fa-solid fa-location-dot me-2"></i> м. Львів</p>
                        <p class="small">вул. Чайковського, 21</p>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-uppercase text-white-50 small fw-bold" style="color: rgba(255,255,255,0.5) !important;">Телефон</h6>
                        <a href="tel:+380971234567" class="text-white text-decoration-none fs-5 d-block mb-1">
                            <i class="fa-solid fa-phone me-2"></i> +38 (097) 123-45-67
                        </a>
                        <a href="tel:+380631234567" class="text-white text-decoration-none fs-5 d-block">
                            <i class="fa-solid fa-phone me-2"></i> +38 (063) 123-45-67
                        </a>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-uppercase text-white-50 small fw-bold" style="color: rgba(255,255,255,0.5) !important;">Онлайн</h6>
                        <a href="mailto:info@mobimaster.lviv.ua" class="text-white text-decoration-none d-block mb-2">
                            <i class="fa-solid fa-envelope me-2"></i> info@mobimaster.lviv.ua
                        </a>
                        <a href="https://t.me/mobimaster_support" target="_blank" class="btn btn-light fw-bold rounded-pill w-100 mt-2" style="color: #6366f1;">
                            <i class="fa-brands fa-telegram me-2"></i> Написати в Telegram
                        </a>
                    </div>

                    <div class="mt-auto">
                        <h6 class="text-uppercase text-white-50 small fw-bold" style="color: rgba(255,255,255,0.5) !important;">Графік роботи</h6>
                        <div class="d-flex justify-content-between border-bottom border-white border-opacity-25 py-1">
                            <span>Пн - Пт:</span> <span>09:00 - 19:00</span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom border-white border-opacity-25 py-1">
                            <span>Субота:</span> <span>10:00 - 15:00</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 text-white-50">
                            <span>Неділя:</span> <span>Вихідний</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <iframe 
                        width="100%" 
                        height="100%" 
                        frameborder="0" 
                        scrolling="no" 
                        marginheight="0" 
                        marginwidth="0" 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2573.076543218945!2d24.02678931570946!3d49.83968397939574!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x473add6c2e097569%3A0x9f077e3874e5c5e!2z0LLRg9C70LjRhtGPINCn0LDQudC60L7QstGB0YzQutC-0LPQviwgMjEsINCb0YzQstGW0LIsINCb0YzQstGW0LLRgdGM0LrQsCDQvtCx0LvQsNGB0YLRjCwgNzkwMDA!5e0!3m2!1suk!2sua!4v1700000000000!5m2!1suk!2sua"
                        style="border:0; min-height: 500px;" 
                        allowfullscreen>
                    </iframe>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>