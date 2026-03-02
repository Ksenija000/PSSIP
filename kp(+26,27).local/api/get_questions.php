<?php
// ============================================
// api/get_questions.php - ПОЛУЧЕНИЕ ВОПРОСОВ С ПАГИНАЦИЕЙ
// ============================================
session_start();
header('Content-Type: application/json');

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

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 3;
$offset = ($page - 1) * $limit;

if ($product_id === 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_product']);
    exit;
}

try {
    // Получаем вопросы для текущей страницы
    $stmt = $db->prepare("
        SELECT 
            q.*,
            u.fio as user_name
        FROM product_questions q
        LEFT JOIN users u ON q.user_id = u.id
        WHERE q.product_id = ? AND q.status = 'published'
        ORDER BY q.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$product_id, $limit, $offset]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Получаем общее количество вопросов
    $stmt = $db->prepare("SELECT COUNT(*) FROM product_questions WHERE product_id = ? AND status = 'published'");
    $stmt->execute([$product_id]);
    $total = $stmt->fetchColumn();
    
    $has_more = ($page * $limit) < $total;
    
    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'has_more' => $has_more,
        'next_page' => $has_more ? $page + 1 : null,
        'total' => $total
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'db_error']);
}
?>