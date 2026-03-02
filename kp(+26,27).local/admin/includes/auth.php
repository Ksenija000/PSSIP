<?php
// ============================================
// admin/includes/auth.php - ПРОВЕРКА ПРАВ
// ============================================
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

// Подключение к БД
$host = '127.0.0.1';
$port = '3306';
$dbname = 'Art_objects_store2';
$user = 'root';
$pass = '20Sukuna20';

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'] ?? 'Администратор';
$adminEmail = $_SESSION['admin_email'] ?? '';

// Проверяем, что админ до сих пор активен
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' AND is_active = 1");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}
?>