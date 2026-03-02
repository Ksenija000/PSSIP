<?php
// ============================================
// check_admin_debug.php - ПОЛНАЯ ДИАГНОСТИКА
// ============================================
$host = '127.0.0.1';
$port = '3306';
$dbname = 'Art_objects_store2';
$user = 'root';
$pass = '20Sukuna20';

echo "<h2>🔍 ПОЛНАЯ ДИАГНОСТИКА АДМИНА</h2>";

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Подключение к БД успешно!<br><br>";
    
    // 1. Смотрим всех пользователей
    $users = $db->query("SELECT id, fio, email, role, is_active FROM users");
    echo "<h3>Все пользователи:</h3>";
    while ($row = $users->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, FIO: {$row['fio']}, Email: {$row['email']}, Роль: {$row['role']}, Активен: {$row['is_active']}<br>";
    }
    
    // 2. Ищем конкретного админа с логином 'admin'
    $stmt = $db->prepare("SELECT * FROM users WHERE fio = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>🔐 Данные администратора с логином 'admin':</h3>";
    if ($admin) {
        echo "<pre>";
        print_r($admin);
        echo "</pre>";
        
        // 3. Проверяем пароль admin123
        $test_password = 'admin123';
        if (password_verify($test_password, $admin['password_hash'])) {
            echo "<span style='color:green; font-size:18px;'>✅ ПАРОЛЬ '$test_password' ПРАВИЛЬНЫЙ!</span><br>";
        } else {
            echo "<span style='color:red; font-size:18px;'>❌ ПАРОЛЬ '$test_password' НЕ ПОДХОДИТ</span><br>";
            
            // Генерируем новый хэш
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "Новый правильный хэш для 'admin123':<br>";
            echo "<code style='background:#333; color:#0f0; padding:10px; display:block;'>" . $new_hash . "</code><br>";
        }
    } else {
        echo "❌ Пользователь с логином 'admin' не найден!<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Ошибка: " . $e->getMessage();
}
?>