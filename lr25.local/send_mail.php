<?php
// Включаем показ всех ошибок (временно, для отладки)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Проверяем, что форма отправлена
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Буфер для сбора сообщений
    $output = "";
    
    $output .= "1. Форма отправлена методом POST\n";
    
    // Проверяем, пришли ли данные
    $output .= "2. Полученные данные:\n";
    $output .= "dish: " . ($_POST['dish'] ?? 'не задан') . "\n";
    $output .= "quantity: " . ($_POST['quantity'] ?? 'не задан') . "\n";
    $output .= "phone: " . ($_POST['phone'] ?? 'не задан') . "\n";
    $output .= "address: " . ($_POST['address'] ?? 'не задан') . "\n";
    
    // Получаем данные из формы
    $dish = $_POST['dish'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // Проверяем, что функция mail существует
    $output .= "3. Функция mail() " . (function_exists('mail') ? "ДОСТУПНА" : "НЕ ДОСТУПНА") . "\n";
    
    $to = "ksenijasavilo@gmail.com";
    
    // Тема письма
    $subject = "=?utf-8?B?" . base64_encode("Новый заказ из ресторана") . "?=";
    
    // Текст письма
    $message = "
    <html>
    <head>
        <title>Новый заказ</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .order-box { background: #f9f9f9; padding: 20px; border-radius: 10px; }
            h2 { color: #ff6b35; }
        </style>
    </head>
    <body>
        <div class='order-box'>
            <h2>🍽 НОВЫЙ ЗАКАЗ</h2>
            <p><b>Блюдо:</b> $dish</p>
            <p><b>Количество:</b> $quantity</p>
            <p><b>Телефон:</b> $phone</p>
            <p><b>Адрес доставки:</b> $address</p>
        </div>
    </body>
    </html>
    ";
    
    // Заголовки
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    $headers .= "From: Ресторан <noreply@restaurant.ru>\r\n";
    
    $output .= "4. Пытаюсь отправить письмо...\n";
    
    // Отправляем
    if (mail($to, $subject, $message, $headers)) {
        $output .= "5. ✅ Заказ отправлен! Спасибо!";
    } else {
        $output .= "5. ❌ Ошибка отправки\n";
        $output .= "Последняя ошибка PHP: " . (error_get_last()['message'] ?? 'Неизвестная ошибка');
    }
    
    // ВОЗВРАЩАЕМ РЕЗУЛЬТАТ ДЛЯ ALERT
    echo "<script>
        alert(" . json_encode($output) . ");
        window.location.href = 'index.html';
    </script>";
    
} else {
    echo "Форма не отправлена методом POST";
}
?>