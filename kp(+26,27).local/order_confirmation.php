<?php
// ============================================
// order_confirmation.php - ПОДТВЕРЖДЕНИЕ ЗАКАЗА (с печатью)
// ============================================
session_start();

// Подключение к БД (опционально - если нужно показать детали заказа)
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

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем информацию о заказе (если нужно отобразить)
$order = null;
$items = [];
$customer_name = '';
$customer_email = '';

if ($order_id > 0) {
    // Получаем информацию о заказе
    $stmt = $db->prepare("
        SELECT o.*, 
               u.fio as user_fio, 
               u.email as user_email,
               CASE 
                   WHEN o.user_id IS NULL THEN 'Гость'
                   ELSE u.fio 
               END as customer_name,
               CASE 
                   WHEN o.user_id IS NULL THEN o.guest_email
                   ELSE u.email 
               END as contact_email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $customer_name = $order['customer_name'];
        $customer_email = $order['contact_email'];
        
        // Получаем товары в заказе
        $stmt = $db->prepare("
            SELECT oi.*, p.name, p.image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ оформлен | ARTOBJECT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #2C2C2C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .confirmation-card {
            background: white;
            border-radius: 30px;
            padding: 50px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #FF5A30 0%, #FF8A00 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 3rem;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #FF5A30 0%, #FF8A00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
        }
        
        .order-number {
            font-size: 2rem;
            font-weight: 800;
            color: #FF5A30;
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 90, 48, 0.1);
            border-radius: 50px;
            text-align: center;
        }
        
        .message {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
            line-height: 1.6;
            text-align: center;
        }
        
        .info-box {
            background: #f5f5f5;
            border-radius: 15px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-label {
            width: 120px;
            color: #666;
            font-weight: 600;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-weight: 600;
        }
        
        .items-list {
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .item-name {
            flex: 2;
        }
        
        .item-quantity {
            flex: 0.5;
            text-align: center;
        }
        
        .item-price {
            flex: 1;
            text-align: right;
            font-weight: 600;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .total-row span:last-child {
            color: #FF5A30;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 16px 35px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #FF5A30 0%, #FF8A00 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 40px rgba(255, 90, 48, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            color: #333;
            border: 2px solid #ddd;
        }
        
        .btn-secondary:hover {
            border-color: #FF5A30;
            color: #FF5A30;
            transform: translateY(-2px);
        }
        
        .print-btn {
            background: #4CAF50;
            color: white;
        }
        
        .print-btn:hover {
            background: #45a049;
        }
        
        /* Стили для печати */
        @media print {
            body {
                background: white;
                padding: 20px;
            }
            
            .confirmation-card {
                box-shadow: none;
                padding: 0;
            }
            
            .success-icon {
                background: #FF5A30;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .btn-group {
                display: none;
            }
            
            .order-number {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="confirmation-card" id="printable-area">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Спасибо за заказ!</h1>
        
        <div class="order-number">
            №<?php echo $order_id ?: '2025-001'; ?>
        </div>
        
        <?php if ($order): ?>
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Статус:</span>
                <span class="info-value"><?php 
                    $status_text = [
                        'processing' => 'В обработке',
                        'delivering' => 'Доставляется',
                        'delivered' => 'Доставлен',
                        'cancelled' => 'Отменён'
                    ];
                    echo $status_text[$order['status']] ?? $order['status']; 
                ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Дата:</span>
                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Покупатель:</span>
                <span class="info-value"><?php echo htmlspecialchars($customer_name); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value"><?php echo htmlspecialchars($customer_email); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Доставка:</span>
                <span class="info-value"><?php echo htmlspecialchars($order['shipping_address'] ?: '—'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Оплата:</span>
                <span class="info-value"><?php 
                    $methods = ['card' => 'Картой онлайн', 'cash' => 'Наличными', 'bank' => 'Безналичный'];
                    echo $methods[$order['payment_method']] ?? $order['payment_method']; 
                ?></span>
            </div>
        </div>
        
        <?php if (!empty($items)): ?>
        <h3 style="margin: 20px 0 10px;">Состав заказа</h3>
        <div class="items-list">
            <?php foreach ($items as $item): ?>
            <div class="item-row">
                <span class="item-name"><?php echo htmlspecialchars($item['name'] ?? 'Товар'); ?></span>
                <span class="item-quantity">× <?php echo $item['quantity']; ?></span>
                <span class="item-price"><?php echo number_format($item['price'] * $item['quantity'], 2, '.', ' '); ?> BYN</span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="total-row">
            <span>Итого:</span>
            <span><?php echo number_format($order['total_price'], 2, '.', ' '); ?> BYN</span>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="message">
            В ближайшее время вы получите письмо с деталями заказа.
        </div>
        <?php endif; ?>
        
        
        <div class="btn-group">
            <button class="btn print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Распечатать чек
            </button>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> На главную
            </a>
            <a href="product_catalog.php" class="btn btn-secondary">
                <i class="fas fa-shopping-bag"></i> Продолжить покупки
            </a>
        </div>
    </div>


</body>
</html>