<?php
// ============================================
// order.php - СТРАНИЦА ДЕТАЛЕЙ ЗАКАЗА
// ============================================
session_start();

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

// ============================================
// ПРОВЕРКА АВТОРИЗАЦИИ
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?need_auth=1');
    exit;
}

$isLoggedIn = false;
$userId = 0;
$userName = '';
$userEmail = '';
$userRole = '';

if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT id, fio, email, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['is_active'] == 1) {
        $isLoggedIn = true;
        $userId = $user['id'];
        $userName = $user['fio'];
        $userEmail = $user['email'];
        $userRole = $user['role'];
        $_SESSION['user_name'] = $user['fio'];
        $_SESSION['user_email'] = $user['email'];
    } else {
        $_SESSION = array();
        session_destroy();
        header('Location: index.php?need_auth=1');
        exit;
    }
}

// ============================================
// ОБРАБОТКА ВХОДА
// ============================================
if (isset($_POST['login'])) {
    $fio = $_POST['fio'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $db->prepare("SELECT * FROM users WHERE fio = ?");
    $stmt->execute([$fio]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['is_active'] == 1) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['fio'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
                foreach ($_SESSION['guest_cart'] as $product_id => $quantity) {
                    $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$user['id'], $product_id, $quantity]);
                }
                unset($_SESSION['guest_cart']);
            }
            
            header('Location: order.php?id=' . ($_GET['id'] ?? ''));
            exit;
        } else {
            $login_error = 'Ваш аккаунт заблокирован. Обратитесь к администратору.';
        }
    } else {
        $login_error = 'Неверный логин или пароль';
    }
}

// ============================================
// ОБРАБОТКА РЕГИСТРАЦИИ
// ============================================
if (isset($_POST['register'])) {
    $fio = $_POST['fio'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($fio && $email && $phone && $password) {
        $stmt = $db->prepare("SELECT id FROM users WHERE fio = ?");
        $stmt->execute([$fio]);
        if ($stmt->fetch()) {
            $register_error = 'Пользователь с таким логином уже существует';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (fio, email, phone, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 'buyer', 1, NOW())");
            $stmt->execute([$fio, $email, $phone, $hash]);
            
            $_SESSION['user_id'] = $db->lastInsertId();
            $_SESSION['user_name'] = $fio;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'buyer';
            
            header('Location: order.php?id=' . ($_GET['id'] ?? ''));
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ОБРАБОТКА ОТМЕНЫ ЗАКАЗА
// ============================================
if (isset($_POST['cancel_order'])) {
    $cancel_order_id = (int)$_POST['order_id'];
    
    // Проверяем, принадлежит ли заказ пользователю и можно ли его отменить
    $stmt = $db->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$cancel_order_id, $userId]);
    $order_to_cancel = $stmt->fetch();
    
    if ($order_to_cancel && $order_to_cancel['status'] == 'processing') {
        $stmt = $db->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$cancel_order_id]);
        
        // Перенаправляем на страницу заказа с сообщением
        header('Location: order.php?id=' . $cancel_order_id . '&cancelled=1');
        exit;
    }
}

// ============================================
// ОБРАБОТКА ПОВТОРЕНИЯ ЗАКАЗА (только для доставленных)
// ============================================
if (isset($_POST['repeat_order'])) {
    $repeat_order_id = (int)$_POST['order_id'];
    
    // Получаем заказ, проверяем статус
    $stmt = $db->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$repeat_order_id, $userId]);
    $order_check = $stmt->fetch();
    
    if ($order_check && $order_check['status'] == 'delivered') {
        // Получаем товары из заказа
        $stmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$repeat_order_id]);
        $items_to_repeat = $stmt->fetchAll();
        
        $selected_items = [];
        $unavailable_items = [];
        $partial_items = [];
        
        foreach ($items_to_repeat as $item) {
            // Проверяем, существует ли товар и сколько в наличии (только активные)
            $stmt = $db->prepare("SELECT id, stock_quantity, deleted_at FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch();
            
            if (!$product || $product['deleted_at'] !== null) {
                // Товар удалён
                $unavailable_items[] = "Товар удалён из каталога";
                continue;
            }
            
            if ($product['stock_quantity'] == 0) {
                // Товара нет в наличии
                $unavailable_items[] = "Товар отсутствует в наличии";
                continue;
            }
            
            // Определяем доступное количество
            $available_qty = min($item['quantity'], $product['stock_quantity']);
            
            if ($available_qty < $item['quantity']) {
                // Частично доступен
                $partial_items[] = "Товара доступно только {$available_qty} из {$item['quantity']}";
            }
            
            // Добавляем в корзину доступное количество
            if ($available_qty > 0) {
                $selected_items[] = $item['product_id'];
                
                if ($isLoggedIn) {
                    // Проверяем, есть ли уже такой товар в корзине
                    $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$userId, $item['product_id']]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $new_qty = $existing['quantity'] + $available_qty;
                        $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                        $stmt->execute([$new_qty, $existing['id']]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$userId, $item['product_id'], $available_qty]);
                    }
                } else {
                    if (!isset($_SESSION['guest_cart'])) {
                        $_SESSION['guest_cart'] = [];
                    }
                    
                    if (isset($_SESSION['guest_cart'][$item['product_id']])) {
                        $_SESSION['guest_cart'][$item['product_id']] += $available_qty;
                    } else {
                        $_SESSION['guest_cart'][$item['product_id']] = $available_qty;
                    }
                }
            }
        }
        
        if (!empty($selected_items)) {
            // Сохраняем выбранные товары в сессию
            $_SESSION['selected_items'] = $selected_items;
            
            // Сохраняем информацию о недоступных/частичных товарах
            if (!empty($unavailable_items)) {
                $_SESSION['repeat_unavailable'] = $unavailable_items;
            }
            if (!empty($partial_items)) {
                $_SESSION['repeat_partial'] = $partial_items;
            }
            
            // Сразу переходим на страницу оформления заказа
            header('Location: placing_order.php');
            exit;
        } else {
            // Если ни один товар не доступен
            $_SESSION['repeat_error'] = 'Ни один товар из заказа не доступен для повторения';
            header('Location: order.php?id=' . $repeat_order_id);
            exit;
        }
    } else {
        // Если заказ не доставлен
        $_SESSION['repeat_error'] = 'Повторить можно только доставленные заказы';
        header('Location: order.php?id=' . $repeat_order_id);
        exit;
    }
}

// ============================================
// ПОЛУЧАЕМ ID ЗАКАЗА ИЗ URL
// ============================================
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id === 0) {
    header('Location: profile.php?tab=orders');
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ЗАКАЗА
// ============================================
$stmt = $db->prepare("
    SELECT 
        o.*,
        u.fio as user_fio,
        u.email as user_email,
        u.phone as user_phone,
        u.city as user_city,
        u.address as user_address
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Проверяем, существует ли заказ и принадлежит ли он пользователю
if (!$order) {
    header('Location: profile.php?tab=orders');
    exit;
}

// ============================================
// ПОЛУЧАЕМ ТОВАРЫ В ЗАКАЗЕ - ОДИНАКОВАЯ ЛОГИКА ДЛЯ ВСЕХ СТАТУСОВ!
// ============================================
$stmt = $db->prepare("
    SELECT 
        oi.*,
        COALESCE(p.name, 'Товар удалён') as name,
        p.image,
        p.size,
        p.weight_kg,
        p.material,
        p.stock_quantity,
        p.deleted_at,
        COALESCE(a.fio, 'Художник') as artist_name,
        a.id as artist_id,
        COALESCE(c.name, 'Категория') as category_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN artists a ON p.artist_id = a.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Подсчитываем общее количество единиц товара (штук)
$total_items_count = 0;
foreach ($items as $item) {
    $total_items_count += $item['quantity'];
}

// Подсчитываем стоимость товаров без доставки
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$delivery_cost = $order['total_price'] - $subtotal;

// Определяем сообщение о доставке
$delivery_message = '';
if ($delivery_cost > 0) {
    if ($delivery_cost == 10) {
        $delivery_message = 'Доставка по Минску — 10 BYN';
    } elseif ($delivery_cost == 40) {
        $delivery_message = 'Доставка в другой регион — 40 BYN';
    }
} else {
    $delivery_message = 'Бесплатная доставка';
}

// ============================================
// ПРОВЕРЯЕМ ДОСТУПНОСТЬ ТОВАРОВ ДЛЯ ПОВТОРЕНИЯ
// ============================================
$can_repeat = true;
$repeat_warnings = [];

foreach ($items as $item) {
    // Проверяем, существует ли товар и не удалён ли он
    if ($item['deleted_at'] !== null) {
        $can_repeat = false;
        $repeat_warnings[] = "Товар «{$item['name']}» был удалён из каталога";
        continue;
    }
    
    // Проверяем наличие
    if ($item['stock_quantity'] == 0) {
        $can_repeat = false;
        $repeat_warnings[] = "Товар «{$item['name']}» отсутствует в наличии";
    } elseif ($item['stock_quantity'] < $item['quantity']) {
        $can_repeat = false;
        $repeat_warnings[] = "Товара «{$item['name']}» в наличии только {$item['stock_quantity']} шт. (в заказе {$item['quantity']} шт.)";
    }
}

// Дополнительно: повторять можно только доставленные заказы
$repeat_allowed = ($order['status'] == 'delivered') && $can_repeat;

// ============================================
// ПОЛУЧАЕМ КАТЕГОРИИ ДЛЯ МЕНЮ
// ============================================
$categories = $db->query("
    SELECT * FROM categories ORDER BY name
")->fetchAll();

// ============================================
// ПОЛУЧАЕМ КОРЗИНУ ДЛЯ СЧЁТЧИКА
// ============================================
$cart_count = 0;
$stmt = $db->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id = ?");
$stmt->execute([$userId]);
$cart_count = (int)$stmt->fetchColumn();

// ============================================
// ПОЛУЧАЕМ ИЗБРАННОЕ ДЛЯ СЧЁТЧИКА
// ============================================
$wishlist_count = 0;
$stmt = $db->prepare("SELECT COUNT(*) FROM favorites_products WHERE user_id = ?");
$stmt->execute([$userId]);
$wishlist_count += (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM favorites_artists WHERE user_id = ?");
$stmt->execute([$userId]);
$wishlist_count += (int)$stmt->fetchColumn();

// ============================================
// ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ СТАТУСА ЗАКАЗА
// ============================================
function getOrderStatusInfo($status) {
    switch ($status) {
        case 'processing':
            return [
                'text' => 'В обработке',
                'icon' => 'clock',
                'color' => '#ff9800',
                'class' => 'status-processing'
            ];
        case 'delivering':
            return [
                'text' => 'Доставляется',
                'icon' => 'truck',
                'color' => '#2196F3',
                'class' => 'status-delivering'
            ];
        case 'delivered':
            return [
                'text' => 'Доставлен',
                'icon' => 'check-circle',
                'color' => '#4CAF50',
                'class' => 'status-delivered'
            ];
        case 'cancelled':
            return [
                'text' => 'Отменён',
                'icon' => 'times-circle',
                'color' => '#f44336',
                'class' => 'status-cancelled'
            ];
        default:
            return [
                'text' => $status,
                'icon' => 'clock',
                'color' => '#666',
                'class' => 'status-processing'
            ];
    }
}

// ============================================
// ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ СТАТУСА ОПЛАТЫ
// ============================================
function getPaymentStatusInfo($status) {
    switch ($status) {
        case 'paid':
            return [
                'text' => 'Оплачено',
                'icon' => 'check-circle',
                'color' => '#4CAF50',
                'class' => 'status-paid'
            ];
        case 'pending':
            return [
                'text' => 'Ожидает оплаты',
                'icon' => 'clock',
                'color' => '#ff9800',
                'class' => 'status-pending'
            ];
        case 'failed':
            return [
                'text' => 'Ошибка оплаты',
                'icon' => 'exclamation-circle',
                'color' => '#f44336',
                'class' => 'status-failed'
            ];
        default:
            return [
                'text' => $status,
                'icon' => 'clock',
                'color' => '#666',
                'class' => 'status-pending'
            ];
    }
}

// ============================================
// ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ НАЗВАНИЯ СПОСОБА ОПЛАТЫ
// ============================================
function getPaymentMethodName($method) {
    switch ($method) {
        case 'card':
            return 'Картой онлайн';
        case 'cash':
            return 'Наличными при получении';
        case 'bank':
            return 'Безналичный расчёт';
        default:
            return $method;
    }
}

// ============================================
// ФУНКЦИЯ ДЛЯ ИКОНОК КАТЕГОРИЙ
// ============================================
function getCategoryIcon($category_id, $category_name) {
    $icons = [
        'флористическая' => 'palette',
        'подставка' => 'monument',
        'эпоксидной' => 'camera',
        'панно' => 'desktop',
        'статуэтка' => 'vase'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($category_name, $key) !== false) {
            return $icon;
        }
    }
    return 'image';
}

$order_status = getOrderStatusInfo($order['status']);
$payment_status = getPaymentStatusInfo($order['payment_status']);
$payment_method = getPaymentMethodName($order['payment_method']);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTOBJECT | Заказ №<?php echo $order['id']; ?></title>
    <style>
        /* ============================================ */
        /* СТИЛИ - ПОЛНОСТЬЮ КАК НА ГЛАВНОЙ */
        /* ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        :root {
            --primary-black: #0a0a0a;
            --primary-orange: #FF5A30;
            --orange-light: #FF8A65;
            --orange-dark: #E64A19;
            --orange-glow: rgba(255, 90, 48, 0.3);
            --gold: #FFD700;
            --white: #FFFFFF;
            --off-white: #FAFAFA;
            --gray-light: #F5F5F5;
            --gray-medium: #E0E0E0;
            --gray-dark: #666666;
            --charcoal: #2C2C2C;
            --gradient-orange: linear-gradient(135deg, #FF5A30 0%, #FF8A00 100%);
            --gradient-dark: linear-gradient(135deg, #0a0a0a 0%, #2C2C2C 100%);
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.12);
            --shadow-orange: 0 10px 40px rgba(255, 90, 48, 0.2);
            --transition-fast: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--white);
            color: var(--primary-black);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 100vh;
            background:
                radial-gradient(circle at 20% 80%, rgba(255, 90, 48, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(10, 10, 10, 0.03) 0%, transparent 50%);
            z-index: -1;
            pointer-events: none;
        }

        body.no-scroll {
            overflow: hidden;
        }

        
        .password_password_continer{
display: flex;
align-items: center;
justify-content: center;
        }

        /* ТИПОГРАФИЯ */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-weight: 800;
            line-height: 1.2;
        }

        .text-gradient {
            background: var(--gradient-orange);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title {
            font-size: 3.5rem;
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--gradient-orange);
            border-radius: 2px;
        }

        /* КОНТЕЙНЕРЫ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* HEADER */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1000;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all var(--transition-fast);
        }

        .fixed-header.scrolled {
            padding: 10px 0;
            box-shadow: var(--shadow-md);
            background: rgba(255, 255, 255, 0.98);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            cursor: pointer;
            z-index: 1001;
        }

        .logo-main {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--primary-black);
            letter-spacing: -0.5px;
            position: relative;
            display: flex;
            align-items: center;
        }

        .logo-main::after {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--primary-orange);
            border-radius: 50%;
            margin-left: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
        }

        .logo-sub {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary-orange);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* MAIN NAVIGATION - десктопное меню */
        .main-menu {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .main-menu ul {
            display: flex;
            list-style: none;
            gap: 40px;
        }

        .main-menu a {
            font-weight: 600;
            font-size: 1rem;
            color: var(--charcoal);
            padding: 8px 0;
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .main-menu a i {
            font-size: 0.9rem;
            transition: transform var(--transition-fast);
        }

        .dropdown:hover a i {
            transform: rotate(180deg);
        }

        .main-menu a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-orange);
            transition: width var(--transition-fast);
        }

        .main-menu a:hover {
            color: var(--primary-orange);
        }

        .main-menu a:hover::after {
            width: 100%;
        }

        .dropdown {
            position: relative;
        }

        .dropdown-content {
            position: absolute;
            top: calc(100% + 15px);
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background: var(--white);
            min-width: 220px;
            padding: 15px 0;
            box-shadow: var(--shadow-lg);
            border-radius: 12px;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-fast);
            z-index: 100;
        }

        .dropdown:hover .dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-content a {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-content i {
            color: var(--primary-orange);
        }

        /* HEADER RIGHT */
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 1001;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .icon-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--off-white);
            border: none;
            font-size: 1.2rem;
            color: var(--charcoal);
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .icon-btn:hover {
            background: var(--primary-orange);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary-orange);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }

        #auth-button {
            background: var(--gradient-dark);
            color: white;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-fast);
            border: none;
            cursor: pointer;
        }

        #auth-button:hover {
            background: var(--gradient-orange);
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        /* Бургер-меню */
        .burger-menu {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 30px;
            height: 21px;
            cursor: pointer;
            z-index: 1001;
            margin-left: 10px;
        }

        .burger-menu span {
            display: block;
            width: 100%;
            height: 3px;
            background: var(--primary-black);
            border-radius: 3px;
            transition: all var(--transition-fast);
        }

        .burger-menu.active span:nth-child(1) {
            transform: translateY(9px) rotate(45deg);
            background: var(--primary-orange);
        }

        .burger-menu.active span:nth-child(2) {
            opacity: 0;
        }

        .burger-menu.active span:nth-child(3) {
            transform: translateY(-9px) rotate(-45deg);
            background: var(--primary-orange);
        }

        /* Мобильное меню */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100vh;
            background: var(--white);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            padding: 100px 30px 30px;
            transition: left var(--transition-slow);
            overflow-y: auto;
        }

        .mobile-menu.active {
            left: 0;
        }

        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 10, 10, 0.5);
            backdrop-filter: blur(5px);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-fast);
        }

        .mobile-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .mobile-menu ul {
            list-style: none;
        }

        .mobile-menu li {
            margin-bottom: 20px;
        }

        .mobile-menu a {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--charcoal);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .mobile-menu a i {
            width: 25px;
            color: var(--primary-orange);
        }

        .mobile-dropdown-content {
            padding-left: 40px;
            margin-top: 10px;
            display: none;
        }

        .mobile-dropdown.active .mobile-dropdown-content {
            display: block;
        }

        .mobile-dropdown-content a {
            font-size: 1rem;
            border-bottom: none;
            padding: 8px 0;
        }

        .mobile-dropdown > a i:last-child {
            margin-left: auto;
            transition: transform var(--transition-fast);
        }

        .mobile-dropdown.active > a i:last-child {
            transform: rotate(180deg);
        }

        .mobile-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid var(--gray-light);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .mobile-action-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            background: var(--off-white);
            border-radius: 30px;
            text-decoration: none;
            color: var(--charcoal);
            font-weight: 600;
            transition: all var(--transition-fast);
            position: relative;
        }

        .mobile-action-btn:hover {
            background: var(--primary-orange);
            color: white;
        }

        .mobile-action-btn:hover i {
            color: white;
        }

        .mobile-action-btn i {
            font-size: 1.2rem;
            color: var(--primary-orange);
            transition: all var(--transition-fast);
        }

        .mobile-action-btn .badge {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* PAGE HEADER */
        .page-header {
            margin-top: 120px;
            padding: 60px 0 40px;
            background: var(--gradient-dark);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--orange-glow) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header-content {
            position: relative;
            z-index: 2;
        }

        .page-header h1 {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        /* ORDER SECTION */
        .order-section {
            padding: 40px 0 80px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .order-title {
            font-size: 2rem;
        }

        .order-status-badge {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-processing {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }

        .status-delivering {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        .status-delivered {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .status-cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        .status-paid {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }

        .info-message {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            padding: 15px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            padding: 15px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .order-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        /* ITEMS LIST */
        .order-items {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
        }

        .order-items h2 {
            margin-bottom: 25px;
            font-size: 1.3rem;
        }

        .order-item {
            display: flex;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-image {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-item-info {
            flex: 1;
        }

        .order-item-info h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .order-item-info h3 a {
            text-decoration: none;
            color: inherit;
        }

        .order-item-info h3 a:hover {
            color: var(--primary-orange);
        }

        .product-deleted-badge {
            display: inline-block;
            padding: 3px 10px;
            background: rgba(158, 158, 158, 0.1);
            color: #9e9e9e;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .order-item-artist {
            color: var(--gray-dark);
            font-size: 0.95rem;
            margin-bottom: 5px;
            display: block;
            text-decoration: none;
        }

        .order-item-artist:hover {
            color: var(--primary-orange);
        }

        .order-item-category {
            color: var(--primary-orange);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .order-item-details {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .order-item-price {
            font-weight: 700;
            color: var(--primary-black);
        }

        .order-item-quantity {
            color: var(--gray-dark);
        }

        .order-item-total {
            font-weight: 800;
            color: var(--primary-orange);
        }

        .order-item-meta {
            display: flex;
            gap: 15px;
            margin-top: 5px;
            color: var(--gray-dark);
            font-size: 0.85rem;
        }

        .order-item-meta i {
            color: var(--primary-orange);
            margin-right: 3px;
        }

        /* SIDEBAR */
        .order-sidebar {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
        }

        .sidebar-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--gray-light);
        }

        .sidebar-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .sidebar-section h3 {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: var(--gray-dark);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .info-label {
            color: var(--gray-dark);
        }

        .info-value {
            font-weight: 700;
        }

        .address {
            line-height: 1.7;
            color: var(--charcoal);
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-method i {
            font-size: 1.5rem;
            color: var(--primary-orange);
        }

        .order-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 14px 25px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient-orange);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .btn-secondary {
            background: transparent;
            color: var(--charcoal);
            border: 2px solid var(--gray-medium);
        }

        .btn-secondary:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
        }

        .btn-danger {
            background: transparent;
            color: #f44336;
            border: 2px solid var(--gray-medium);
        }

        .btn-danger:hover {
            border-color: #f44336;
        }

        .btn-secondary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary:disabled:hover {
            border-color: var(--gray-medium);
            color: var(--charcoal);
            transform: none;
        }

        /* КНОПКИ ДЛЯ ПУСТЫХ СОСТОЯНИЙ */
        .btn-primary {
            display: inline-flex;
            padding: 16px 45px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            transition: all var(--transition-fast);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-orange);
        }

        .btn-primary i {
            font-size: 1.1rem;
        }

        .btn-secondary {
            display: inline-flex;
            padding: 12px 30px;
            background: transparent;
            color: var(--charcoal);
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all var(--transition-fast);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
            transform: translateY(-2px);
        }

        .btn-secondary i {
            font-size: 1rem;
        }

        /* FOOTER */
        footer {
            background: var(--primary-black);
            color: white;
            padding: 80px 0 30px;
            position: relative;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 50px;
            margin-bottom: 60px;
        }

        .footer-logo {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-logo::after {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--primary-orange);
            border-radius: 50%;
        }

        .footer-description {
            opacity: 0.7;
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-link {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all var(--transition-fast);
            color: white;
            text-decoration: none;
        }

        .social-link:hover {
            background: var(--primary-orange);
            transform: translateY(-3px);
        }

        .footer-heading {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 25px;
            color: white;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: white;
            opacity: 0.7;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .footer-links a:hover {
            opacity: 1;
            color: var(--primary-orange);
            padding-left: 5px;
        }

        .footer-contact p {
            opacity: 0.7;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-contact i {
            color: var(--primary-orange);
            width: 20px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            opacity: 0.7;
            font-size: 0.9rem;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-bottom-links {
            display: flex;
            gap: 25px;
        }

        .footer-bottom-links a {
            color: white;
            text-decoration: none;
            opacity: 0.7;
        }

        .footer-bottom-links a:hover {
            opacity: 1;
            color: var(--primary-orange);
        }

        /* MODAL */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--white);
            border-radius: 25px;
            padding: 50px;
            max-width: 500px;
            width: 90%;
            position: relative;
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-dark);
            transition: color var(--transition-fast);
        }

        .close-modal:hover {
            color: var(--primary-orange);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            font-size: 1rem;
            transition: all var(--transition-fast);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: var(--shadow-orange);
        }

        .btn-primary-modal {
            background: var(--gradient-orange);
            color: white;
            border: none;
            padding: 16px 35px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all var(--transition-fast);
            cursor: pointer;
            text-decoration: none;
            width: 100%;
            justify-content: center;
        }

        .btn-primary-modal:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-orange);
        }

        /* NOTIFICATION */
        .notification {
            position: fixed;
            top: 100px;
            right: 30px;
            background: var(--white);
            color: var(--primary-black);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 3000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            max-width: 320px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--gradient-orange);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 700;
            margin-bottom: 3px;
            font-size: 0.95rem;
        }

        .notification-message {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        /* АДАПТАЦИЯ */
        @media (max-width: 992px) {
            .main-menu {
                display: none;
            }

            .action-buttons {
                display: none;
            }

            .burger-menu {
                display: flex;
            }

            .order-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-wrap: nowrap;
            }

            .logo-main {
                font-size: 1.8rem;
            }

            #auth-button {
                padding: 10px 16px;
                font-size: 0.9rem;
            }

            .burger-menu {
                margin-left: 5px;
            }

            .page-header {
                margin-top: 100px;
                padding: 40px 0;
            }

            .page-header h1 {
                font-size: 2.5rem;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-item-details {
                flex-direction: column;
                gap: 5px;
                align-items: flex-start;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 15px;
            }

            .logo-main {
                font-size: 1.5rem;
            }

            .logo-sub {
                font-size: 0.6rem;
            }

            #auth-button {
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .burger-menu {
                width: 25px;
                height: 18px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .mobile-menu {
                width: 250px;
                padding: 80px 20px 20px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- HEADER -->
    <header class="fixed-header" id="header">
        <div class="container">
            <div class="header-content">
                <div class="logo" onclick="window.location.href='index.php'">
                    <div class="logo-main">ARTOBJECT</div>
                    <div class="logo-sub">GALLERY</div>
                </div>

                <nav class="main-menu">
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> Главная</a></li>
                        <li class="dropdown">
                            <a href="product_catalog.php"><i class="fas fa-th-large"></i> Каталог <i class="fas fa-chevron-down"></i></a>
                            <div class="dropdown-content">
                                <?php foreach ($categories as $cat): ?>
                                <a href="product_catalog.php?category=<?php echo $cat['id']; ?>">
                                    <i class="fas fa-<?php echo getCategoryIcon($cat['id'], $cat['name']); ?>"></i>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                        <li><a href="catalog_artists.php"><i class="fas fa-paint-brush"></i> Художники</a></li>
                        <li><a href="about_us.php"><i class="fas fa-info-circle"></i> О нас</a></li>
                    </ul>
                </nav>

                <div class="header-right">
                    <div class="action-buttons">
                        <button class="icon-btn" onclick="window.location.href='favorites.php'" title="Избранное">
                            <i class="fas fa-heart"></i>
                            <span class="badge" id="wishlist-count"><?php echo $wishlist_count; ?></span>
                        </button>
                        <button class="icon-btn" onclick="window.location.href='shopping-bag.php'" title="Корзина">
                            <i class="fas fa-shopping-bag"></i>
                            <span class="badge" id="cart-count"><?php echo $cart_count; ?></span>
                        </button>
                    </div>

                    <button id="auth-button" onclick="window.location.href='profile.php'">
                        <i class="fas fa-user"></i>
                        <span id="auth-text"><?php echo htmlspecialchars(explode(' ', $userName)[0]); ?></span>
                    </button>

                    <div class="burger-menu" onclick="toggleMobileMenu()">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Мобильное меню -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>
    <div class="mobile-menu" id="mobileMenu">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Главная</a></li>
            <li class="mobile-dropdown">
                <a href="product_catalog.php" onclick="toggleMobileDropdown(this)">
                    <i class="fas fa-th-large"></i> Каталог
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="mobile-dropdown-content">
                    <?php foreach ($categories as $cat): ?>
                    <a href="product_catalog.php?category=<?php echo $cat['id']; ?>">
                        <i class="fas fa-<?php echo getCategoryIcon($cat['id'], $cat['name']); ?>"></i>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </li>
            <li><a href="catalog_artists.php"><i class="fas fa-paint-brush"></i> Художники</a></li>
            <li><a href="about_us.php"><i class="fas fa-info-circle"></i> О нас</a></li>
        </ul>

        <div class="mobile-actions">
            <a href="favorites.php" class="mobile-action-btn">
                <i class="fas fa-heart"></i>
                <span>Избранное</span>
                <span class="badge" id="mobile-wishlist-count"><?php echo $wishlist_count; ?></span>
            </a>
            <a href="shopping-bag.php" class="mobile-action-btn">
                <i class="fas fa-shopping-bag"></i>
                <span>Корзина</span>
                <span class="badge" id="mobile-cart-count"><?php echo $cart_count; ?></span>
            </a>
        </div>
    </div>

    <!-- PAGE HEADER -->
    <section class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1>Заказ <span class="text-gradient">№<?php echo $order['id']; ?></span></h1>
            </div>
        </div>
    </section>

    <!-- ORDER SECTION -->
    <section class="order-section">
        <div class="container">
            <?php if (isset($_GET['cancelled'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> Заказ успешно отменён
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['repeat_error'])): ?>
                <div class="info-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['repeat_error']; unset($_SESSION['repeat_error']); ?>
                </div>
            <?php endif; ?>

            <div class="order-header">
                <h2 class="order-title">Детали заказа</h2>
                <div class="order-status-badge <?php echo $order_status['class']; ?>">
                    <i class="fas fa-<?php echo $order_status['icon']; ?>"></i>
                    <?php echo $order_status['text']; ?>
                </div>
            </div>

            <div class="order-grid">
                <!-- Левая колонка: товары -->
                <div>
                    <div class="order-items">
                        <h2><i class="fas fa-box"></i> Состав заказа</h2>
                        <?php foreach ($items as $item): 
                            $is_product_deleted = $item['deleted_at'] !== null;
                        ?>
                        <div class="order-item">
                            <div class="order-item-image">
                                <img src="<?php echo $item['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="order-item-info">
                                <h3>
                                    <?php if ($is_product_deleted): ?>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                        <span class="product-deleted-badge">Товар удалён</span>
                                    <?php else: ?>
                                        <a href="product.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a>
                                    <?php endif; ?>
                                </h3>
                                <?php if ($item['artist_id']): ?>
                                    <a href="artists_page.php?id=<?php echo $item['artist_id']; ?>" class="order-item-artist"><?php echo htmlspecialchars($item['artist_name']); ?></a>
                                <?php else: ?>
                                    <span class="order-item-artist"><?php echo htmlspecialchars($item['artist_name']); ?></span>
                                <?php endif; ?>
                                <span class="order-item-category"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                
                                <?php if ($item['size'] || $item['weight_kg'] || $item['material']): ?>
                                <div class="order-item-meta">
                                    <?php if ($item['size']): ?>
                                    <span><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($item['size']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['weight_kg']): ?>
                                    <span><i class="fas fa-weight-hanging"></i> <?php echo $item['weight_kg']; ?> кг</span>
                                    <?php endif; ?>
                                    <?php if ($item['material']): ?>
                                    <span><i class="fas fa-cube"></i> <?php echo htmlspecialchars($item['material']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="order-item-details">
                                    <span class="order-item-price"><?php echo number_format($item['price'], 2, '.', ' '); ?> BYN</span>
                                    <span class="order-item-quantity">× <?php echo $item['quantity']; ?></span>
                                    <span class="order-item-total"><?php echo number_format($item['price'] * $item['quantity'], 2, '.', ' '); ?> BYN</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--gray-light);">
                            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700;">
                                <span>Итого:</span>
                                <span style="color: var(--primary-orange);"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> BYN</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Правая колонка: информация -->
                <div>
                    <div class="order-sidebar">
                        <div class="sidebar-section">
                            <h3>Информация о заказе</h3>
                            <div class="info-row">
                                <span class="info-label">Дата заказа</span>
                                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Статус</span>
                                <span class="info-value">
                                    <span class="order-status-badge <?php echo $order_status['class']; ?>" style="padding: 5px 15px; font-size: 0.9rem;">
                                        <i class="fas fa-<?php echo $order_status['icon']; ?>"></i>
                                        <?php echo $order_status['text']; ?>
                                    </span>
                                </span>
                            </div>
                            <?php if ($order['updated_at'] && $order['updated_at'] != $order['order_date']): ?>
                            <div class="info-row">
                                <span class="info-label">Обновлён</span>
                                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($order['updated_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="sidebar-section">
                            <h3>Сумма заказа</h3>
                            <div class="info-row">
                                <span class="info-label">Товары (<?php echo $total_items_count; ?> шт.)</span>
                                <span class="info-value"><?php echo number_format($subtotal, 2, '.', ' '); ?> BYN</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Доставка</span>
                                <span class="info-value"><?php echo $delivery_cost > 0 ? number_format($delivery_cost, 2, '.', ' ') . ' BYN' : 'Бесплатно'; ?></span>
                            </div>
                            <?php if ($delivery_message): ?>
                            <div style="font-size: 0.9rem; color: var(--primary-orange); margin-top: 5px;">
                                <?php echo $delivery_message; ?>
                            </div>
                            <?php endif; ?>
                            <div class="info-row" style="font-weight: 800; color: var(--primary-orange); margin-top: 10px;">
                                <span class="info-label">ИТОГО</span>
                                <span class="info-value"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> BYN</span>
                            </div>
                        </div>

                        <div class="sidebar-section">
                            <h3>Доставка</h3>
                            <div class="address">
                                <?php 
                                $address = trim($order['shipping_address']);
                                echo htmlspecialchars($address ?: 'Адрес не указан'); 
                                ?>
                            </div>
                        </div>

                        <div class="sidebar-section">
                            <h3>Оплата</h3>
                            <div class="payment-method">
                                <i class="fas fa-<?php echo $order['payment_method'] == 'card' ? 'credit-card' : ($order['payment_method'] == 'cash' ? 'money-bill-wave' : 'university'); ?>"></i>
                                <div>
                                    <div><?php echo $payment_method; ?></div>
                                    <span class="order-status-badge <?php echo $payment_status['class']; ?>" style="padding: 5px 15px; font-size: 0.9rem; margin-top: 5px; display: inline-block;">
                                        <i class="fas fa-<?php echo $payment_status['icon']; ?>"></i>
                                        <?php echo $payment_status['text']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="sidebar-section">
                            <h3>Получатель</h3>
                            <div class="info-row">
                                <span class="info-label">ФИО</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['user_fio']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['user_email']); ?></span>
                            </div>
                            <?php if ($order['user_phone']): ?>
                            <div class="info-row">
                                <span class="info-label">Телефон</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['user_phone']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="order-actions">
                            <?php if ($order['status'] == 'processing'): ?>
                                <form method="POST" style="width: 100%;" onsubmit="return confirm('Вы уверены, что хотите отменить заказ?');">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="cancel_order" class="btn btn-danger" style="width: 100%;">
                                        <i class="fas fa-times"></i> Отменить заказ
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($order['status'] == 'delivered' && $can_repeat): ?>
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="repeat_order" class="btn btn-secondary" style="width: 100%;">
                                        <i class="fas fa-redo-alt"></i> Повторить заказ
                                    </button>
                                </form>
                            <?php elseif ($order['status'] == 'delivered' && !$can_repeat): ?>
                                <div style="margin-bottom: 15px;">
                                    <div class="info-message" style="padding: 12px; font-size: 0.9rem;">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Некоторые товары недоступны для повторения:
                                        <ul style="margin-top: 8px; margin-left: 20px;">
                                            <?php foreach ($repeat_warnings as $warning): ?>
                                                <li><?php echo $warning; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <button class="btn btn-secondary" style="width: 100%; opacity: 0.5; cursor: not-allowed;" disabled>
                                    <i class="fas fa-redo-alt"></i> Повторить заказ
                                </button>
                            <?php endif; ?>

                            <a href="profile.php?tab=orders" class="btn btn-secondary" style="width: 100%;">
                                <i class="fas fa-arrow-left"></i> К списку заказов
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <div class="footer-logo">ARTOBJECT</div>
                    <p class="footer-description">
                        Галерея современного искусства, где каждый может найти произведение,
                        которое говорит с его душой. Мы соединяем талантливых художников с ценителями прекрасного.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-telegram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-vk"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>

                <div class="footer-links-section">
                    <h3 class="footer-heading">Навигация</h3>
                    <ul class="footer-links">
                        <li><a href="product_catalog.php"><i class="fas fa-chevron-right"></i> Каталог работ</a></li>
                        <li><a href="catalog_artists.php"><i class="fas fa-chevron-right"></i> Художники</a></li>
                        <li><a href="about_us.php"><i class="fas fa-chevron-right"></i> О галерее</a></li>
                    </ul>
                </div>

                <div class="footer-contact">
                    <h3 class="footer-heading">Контакты</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Минск, ул. Искусств, 15</p>
                    <p><i class="fas fa-phone"></i> +375 (29) 123-45-67</p>
                    <p><i class="fas fa-envelope"></i> info@artobject.by</p>
                    <p><i class="fas fa-clock"></i> Ежедневно 10:00 - 20:00</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>© 2025 ARTOBJECT Gallery. Все права защищены.</p>
                <div class="footer-bottom-links">
                    <a href="privacy.php">Политика конфиденциальности</a>
                    <a href="terms.php">Пользовательское соглашение</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- MODAL для авторизации/регистрации -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 class="section-title" style="margin-bottom: 30px; font-size: 2rem;">Добро пожаловать</h2>

            <?php if (isset($login_error)): ?>
                <div style="color: #f44336; margin-bottom: 15px; text-align: center;"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <?php if (isset($register_error)): ?>
                <div style="color: #f44336; margin-bottom: 15px; text-align: center;"><?php echo $register_error; ?></div>
            <?php endif; ?>

            <div id="loginForm">
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="fio" class="form-input" placeholder="Логин (ФИО)" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" class="form-input" placeholder="Пароль" required>
                    </div>
                    <button type="submit" name="login" class="btn-primary-modal">
                        <i class="fas fa-sign-in-alt"></i> Войти
                    </button>
                </form>
                <p style="text-align: center; margin-top: 20px; color: var(--gray-dark)">
                    Нет аккаунта? <a href="#" onclick="showRegisterForm()"
                        style="color: var(--primary-orange); font-weight: 600">Зарегистрироваться</a>
                </p>
            </div>

             <div style="text-align: right; margin-top: 10px; margin-bottom: 15px;" class="password_password_continer">
    <a href="forgot-password.php" style="color: var(--gray-dark); font-size: 0.9rem; text-decoration: none;">
        <i class="fas fa-question-circle"></i> Забыли пароль?
    </a>
</div>

            <div id="registerForm" style="display: none">
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="fio" class="form-input" placeholder="ФИО (логин)" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" class="form-input" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <input type="tel" name="phone" class="form-input" placeholder="Телефон" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" class="form-input" placeholder="Пароль" required>
                    </div>
                    <button type="submit" name="register" class="btn-primary-modal">
                        <i class="fas fa-user-plus"></i> Создать аккаунт
                    </button>
                </form>
                <p style="text-align: center; margin-top: 20px; color: var(--gray-dark)">
                    Уже есть аккаунт? <a href="#" onclick="showLoginForm()"
                        style="color: var(--primary-orange); font-weight: 600">Войти</a>
                </p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION -->
    <div class="notification" id="notification">
        <div class="notification-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">Успешно!</div>
            <div class="notification-message"></div>
        </div>
    </div>

    <script>
        // ============================================
        // ДАННЫЕ ИЗ PHP
        // ============================================
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        
        // ============================================
        // МОДАЛЬНЫЕ ОКНА
        // ============================================
        function toggleAuthModal() {
            if (isLoggedIn) {
                window.location.href = 'profile.php';
            } else {
                document.getElementById('authModal').style.display = 'flex';
                closeMobileMenu();
            }
        }

        function closeModal() {
            document.getElementById('authModal').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
        }

        function showRegisterForm() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
        }

        function showLoginForm() {
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
        }

        // ============================================
        // МОБИЛЬНОЕ МЕНЮ
        // ============================================
        function toggleMobileMenu() {
            document.querySelector('.burger-menu').classList.toggle('active');
            document.getElementById('mobileMenu').classList.toggle('active');
            document.getElementById('mobileMenuOverlay').classList.toggle('active');
            document.body.classList.toggle('no-scroll');
        }

        function closeMobileMenu() {
            document.querySelector('.burger-menu').classList.remove('active');
            document.getElementById('mobileMenu').classList.remove('active');
            document.getElementById('mobileMenuOverlay').classList.remove('active');
            document.body.classList.remove('no-scroll');
        }

        function toggleMobileDropdown(e) {
            e.preventDefault();
            e.currentTarget.parentElement.classList.toggle('active');
        }

        // ============================================
        // УВЕДОМЛЕНИЯ
        // ============================================
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const icon = notification.querySelector('.notification-icon i');
            const title = notification.querySelector('.notification-title');
            const msg = notification.querySelector('.notification-message');

            if (type === 'success') {
                icon.className = 'fas fa-check';
                notification.style.borderLeft = '4px solid #4CAF50';
                title.textContent = 'Успешно!';
            } else if (type === 'error') {
                icon.className = 'fas fa-exclamation-circle';
                notification.style.borderLeft = '4px solid #f44336';
                title.textContent = 'Ошибка!';
            } else if (type === 'info') {
                icon.className = 'fas fa-info-circle';
                notification.style.borderLeft = '4px solid #2196F3';
                title.textContent = 'Информация';
            }

            msg.textContent = message;
            notification.classList.add('show');
            setTimeout(() => notification.classList.remove('show'), 3000);
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            // Скролл хедера
            window.addEventListener('scroll', function() {
                const header = document.getElementById('header');
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        });

        // Закрытие модалки по клику вне
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('authModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        // Закрытие мобильного меню при ресайзе
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>