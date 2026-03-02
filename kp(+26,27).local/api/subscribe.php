<?php
// ============================================
// api/subscribe.php - ПОДПИСКА НА НОВОСТИ
// ============================================
session_start();
header('Content-Type: application/json');

$host = '127.0.0.1';
$port = '3306';
$dbname = 'Art_objects_store2';
$user = 'root';
$pass = '20Sukuna20';

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Если пользователь авторизован, берём email из БД
if ($userId > 0 && empty($email)) {
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $email = $user['email'];
    }
}

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'email_required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'invalid_email']);
    exit;
}

try {
    // Проверяем, есть ли уже такой email
    $stmt = $db->prepare("SELECT id, is_active FROM newsletter_subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        if ($existing['is_active'] == 0) {
            // Если был отписан - активируем снова
            $stmt = $db->prepare("UPDATE newsletter_subscribers SET is_active = 1, subscribed_at = NOW() WHERE id = ?");
            $stmt->execute([$existing['id']]);
            echo json_encode(['success' => true, 'message' => 'subscription_reactivated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'already_subscribed']);
        }
    } else {
        // Новый подписчик
        $stmt = $db->prepare("INSERT INTO newsletter_subscribers (email, subscribed_at, is_active) VALUES (?, NOW(), 1)");
        $stmt->execute([$email]);
        echo json_encode(['success' => true, 'message' => 'subscribed']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'db_error']);
}