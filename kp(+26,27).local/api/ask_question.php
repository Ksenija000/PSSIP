<?php
// ============================================
// api/ask_question.php - ОТПРАВКА ВОПРОСА
// ============================================
session_start();
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'auth_required']);
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
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
$question = isset($data['question']) ? trim($data['question']) : '';

if ($product_id === 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_product']);
    exit;
}

if (empty($question)) {
    echo json_encode(['success' => false, 'error' => 'question_required']);
    exit;
}

if (strlen($question) < 10) {
    echo json_encode(['success' => false, 'error' => 'question_too_short']);
    exit;
}

try {
    // Проверяем, существует ли товар
    $stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'product_not_found']);
        exit;
    }
    
    // Сохраняем вопрос
    $stmt = $db->prepare("
        INSERT INTO product_questions (product_id, user_id, question, created_at, status) 
        VALUES (?, ?, ?, NOW(), 'pending')
    ");
    $stmt->execute([$product_id, $_SESSION['user_id'], $question]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Вопрос отправлен на модерацию',
        'question_id' => $db->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'db_error']);
}
?>