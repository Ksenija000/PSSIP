<?php
session_start();
header('Content-Type: application/json');

$host = '127.0.0.1';
$port = '3306';
$dbname = 'Art_objects_store2';
$user = 'root';
$pass = '20Sukuna20';

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    echo json_encode(['error' => 'db_error']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'auth_required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$artist_id = $data['artist_id'];

$stmt = $db->prepare("SELECT id FROM favorites_artists WHERE user_id = ? AND artist_id = ?");
$stmt->execute([$user_id, $artist_id]);
$exists = $stmt->fetch();

if ($exists) {
    $db->prepare("DELETE FROM favorites_artists WHERE user_id = ? AND artist_id = ?")->execute([$user_id, $artist_id]);
    $action = 'removed';
} else {
    $db->prepare("INSERT INTO favorites_artists (user_id, artist_id, added_at) VALUES (?, ?, NOW())")->execute([$user_id, $artist_id]);
    $action = 'added';
}

// Считаем общее количество товаров в избранном
$total_products = $db->prepare("SELECT COUNT(*) FROM favorites_products WHERE user_id = ?");
$total_products->execute([$user_id]);
$product_count = (int)$total_products->fetchColumn();

// Считаем общее количество художников в избранном
$total_artists = $db->prepare("SELECT COUNT(*) FROM favorites_artists WHERE user_id = ?");
$total_artists->execute([$user_id]);
$artist_count = (int)$total_artists->fetchColumn();

// Общее количество (для сердечка)
$total = $product_count + $artist_count;

echo json_encode([
    'success' => true, 
    'action' => $action, 
    'total' => $total,
    'product_count' => $product_count,
    'artist_count' => $artist_count
]);
?>