<?php
session_start();       // Відкриваємо сесію
session_destroy();     // Знищуємо всі дані про вхід
header("Location: login.php"); // Перекидаємо на сторінку входу
exit;
?>