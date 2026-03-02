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

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'];
$quantity = $data['quantity'] ?? 1;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $new_qty = $item['quantity'] + $quantity;
        $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?")->execute([$new_qty, $item['id']]);
    } else {
        $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())")
           ->execute([$user_id, $product_id, $quantity]);
    }
    
    $total = $db->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id = ?");
    $total->execute([$user_id]);
    $count = $total->fetchColumn();
} else {
    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }
    
    if (isset($_SESSION['guest_cart'][$product_id])) {
        $_SESSION['guest_cart'][$product_id] += $quantity;
    } else {
        $_SESSION['guest_cart'][$product_id] = $quantity;
    }
    
    $count = array_sum($_SESSION['guest_cart']);
}

echo json_encode(['success' => true, 'total' => $count]);