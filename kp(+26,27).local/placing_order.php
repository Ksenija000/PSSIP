<?php
// ============================================
// placing_order.php - ОФОРМЛЕНИЕ ЗАКАЗА
// ============================================
session_start();

// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$isLoggedIn = false;
$userId = 0;
$userName = '';
$userEmail = '';
$userPhone = '';
$userCity = '';
$userAddress = '';

if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT id, fio, email, phone, city, address, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['is_active'] == 1) {
        $isLoggedIn = true;
        $userId = $user['id'];
        $userName = $user['fio'];
        $userEmail = $user['email'];
        $userPhone = $user['phone'];
        $userCity = $user['city'];
        $userAddress = $user['address'];
        $_SESSION['user_name'] = $user['fio'];
        $_SESSION['user_email'] = $user['email'];
    } else {
        $_SESSION = array();
        session_destroy();
    }
}

// ============================================
// ОБРАБОТКА ВХОДА (для модального окна)
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
            
            header('Location: placing_order.php');
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
            
            header('Location: placing_order.php');
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ПОЛУЧАЕМ ВЫБРАННЫЕ ТОВАРЫ ИЗ КОРЗИНЫ
// ============================================
$selected_items = [];

// Сначала проверяем sessionStorage (через POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items'])) {
    $selected_items = json_decode($_POST['selected_items'], true);
    $_SESSION['selected_items'] = $selected_items;
}
// Затем проверяем сессию
else if (isset($_SESSION['selected_items']) && !empty($_SESSION['selected_items'])) {
    $selected_items = $_SESSION['selected_items'];
}
// Проверяем GET параметр (на случай прямого перехода)
else if (isset($_GET['items'])) {
    $selected_items = json_decode($_GET['items'], true);
    $_SESSION['selected_items'] = $selected_items;
}

// Если нет выбранных товаров - возвращаем в корзину
if (empty($selected_items)) {
    header('Location: shopping-bag.php?need_select=1');
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ВЫБРАННЫХ ТОВАРОВ
// ============================================
$placeholders = implode(',', array_fill(0, count($selected_items), '?'));
$params = $selected_items;

$stmt = $db->prepare("
    SELECT 
        p.id,
        p.name,
        p.price,
        p.discount_price,
        p.weight_kg,
        p.image,
        p.stock_quantity,
        a.fio as artist_name,
        a.id as artist_id,
        c.name as category_name
    FROM products p
    LEFT JOIN artists a ON p.artist_id = a.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id IN ($placeholders)
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Получаем количество каждого товара из корзины
$cart_data = [];
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)");
    $stmt->execute(array_merge([$userId], $params));
    $cart_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} else {
    $cart_data = $_SESSION['guest_cart'] ?? [];
}

// Формируем итоговый список товаров с количествами
$order_items = [];
$subtotal = 0;
foreach ($products as $product) {
    $product_id = $product['id'];
    if (isset($cart_data[$product_id])) {
        $quantity = $cart_data[$product_id];
        $price = $product['discount_price'] ?? $product['price'];
        $total = $price * $quantity;
        $subtotal += $total;
        
        $order_items[] = [
            'id' => $product_id,
            'name' => $product['name'],
            'artist_name' => $product['artist_name'],
            'artist_id' => $product['artist_id'],
            'price' => $price,
            'quantity' => $quantity,
            'total' => $total,
            'image' => $product['image'],
            'category' => $product['category_name']
        ];
    }
}

// ============================================
// ФУНКЦИЯ РАСЧЁТА ДОСТАВКИ
// ============================================
function calculateDelivery($city, $subtotal) {
    if ($subtotal >= 500) {
        return 0;
    }
    
    $city_lower = mb_strtolower(trim($city));
    if (strpos($city_lower, 'минск') !== false || strpos($city_lower, 'мин') !== false) {
        return 10;
    }
    
    return 40;
}

// ============================================
// ОБРАБОТКА ФОРМЫ
// ============================================
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$form_data = [];

// Если пользователь авторизован, пропускаем шаг 0
if ($isLoggedIn && $step === 0) {
    $step = 1;
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['save_delivery'])) {
        // Валидация данных доставки
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'card';
        
        // Для гостей добавляем поле email
        $guest_email = '';
        if (!$isLoggedIn) {
            $guest_email = trim($_POST['guest_email'] ?? '');
            if (empty($guest_email)) {
                $errors['guest_email'] = 'Укажите email для отслеживания заказа';
            } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
                $errors['guest_email'] = 'Введите корректный email';
            }
        }
        
        if (empty($city)) {
            $errors['city'] = 'Укажите город доставки';
        }
        if (empty($address)) {
            $errors['address'] = 'Укажите адрес доставки';
        }
        
        if (empty($errors)) {
            $_SESSION['order_data'] = [
                'city' => $city,
                'address' => $address,
                'payment_method' => $payment_method
            ];
            
            // Для гостей сохраняем email в сессию
            if (!$isLoggedIn) {
                $_SESSION['guest_email'] = $guest_email;
            }
            
            header('Location: placing_order.php?step=2');
            exit;
        } else {
            // Сохраняем введённые данные для повторного отображения
            $form_data = [
                'city' => $city,
                'address' => $address,
                'payment_method' => $payment_method,
                'guest_email' => $guest_email ?? ''
            ];
        }
    }
    
    if (isset($_POST['place_order'])) {
        // Финальное оформление заказа
        $order_data = $_SESSION['order_data'] ?? [];
        
        if (empty($order_data['city']) || empty($order_data['address'])) {
            header('Location: placing_order.php?step=1');
            exit;
        }
        
        $city = $order_data['city'];
        $address = $order_data['address'];
        $payment_method = $order_data['payment_method'] ?? 'card';
        
        // Для гостей получаем email из сессии
        $guest_email = $_SESSION['guest_email'] ?? '';
        if (!$isLoggedIn && empty($guest_email)) {
            header('Location: placing_order.php?step=1');
            exit;
        }
        
        // Рассчитываем доставку
        $delivery_cost = calculateDelivery($city, $subtotal);
        $total = $subtotal + $delivery_cost;
        $shipping_address = $city . ', ' . $address;
        
        try {
            $db->beginTransaction();
            
            // Для гостей используем NULL в user_id
            $order_user_id = $isLoggedIn ? $userId : null;
            
            // 1. Создаём заказ
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, 
                    guest_email, 
                    order_date, 
                    total_price, 
                    status, 
                    shipping_address, 
                    updated_at, 
                    payment_method, 
                    payment_status
                ) VALUES (?, ?, NOW(), ?, 'processing', ?, NOW(), ?, 'pending')
            ");
            
            $stmt->execute([
                $order_user_id, 
                $guest_email, 
                $total, 
                $shipping_address, 
                $payment_method
            ]);
            
            $order_id = $db->lastInsertId();
            
            // 2. Добавляем товары в order_items
            $stmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, price, quantity) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($order_items as $item) {
                $stmt->execute([$order_id, $item['id'], $item['price'], $item['quantity']]);
            }
            
            // 3. Удаляем выбранные товары из корзины
            if ($isLoggedIn) {
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $params = array_merge([$userId], $selected_items);
                $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)");
                $stmt->execute($params);
            } else {
                foreach ($selected_items as $product_id) {
                    unset($_SESSION['guest_cart'][$product_id]);
                }
            }
            
            // 4. Очищаем сессионные данные
            unset($_SESSION['selected_items']);
            unset($_SESSION['order_data']);
            if (!$isLoggedIn) {
                unset($_SESSION['guest_email']);
            }
            
            $db->commit();
            
            // Для гостей сохраняем номер заказа в сессию для возможности отслеживания
            if (!$isLoggedIn) {
                $_SESSION['last_guest_order'] = $order_id;
            }
            
            // Перенаправляем на страницу подтверждения
            header("Location: order_confirmation.php?id=$order_id");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors['system'] = 'Ошибка при оформлении заказа: ' . $e->getMessage();
        }
    }
}

// ============================================
// ПОЛУЧАЕМ КАТЕГОРИИ ДЛЯ МЕНЮ
// ============================================
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// ============================================
// ПОЛУЧАЕМ ИЗБРАННОЕ ДЛЯ СЧЁТЧИКА
// ============================================
$wishlist_count = 0;
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM favorites_products WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wishlist_count += (int)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM favorites_artists WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wishlist_count += (int)$stmt->fetchColumn();
}

// ============================================
// ПОЛУЧАЕМ КОРЗИНУ ДЛЯ СЧЁТЧИКА
// ============================================
$cart_count = 0;
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cart_count = (int)$stmt->fetchColumn();
} else {
    $cart_count = array_sum($_SESSION['guest_cart'] ?? []);
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
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTOBJECT | Оформление заказа</title>
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
   .password_password_continer{
display: flex;
align-items: center;
justify-content: center;
        }
        body.no-scroll {
            overflow: hidden;
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        .logo-sub {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary-orange);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* MAIN NAVIGATION */
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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

        /* CHECKOUT STEPS */
        .checkout-steps {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 50px;
            position: relative;
            flex-wrap: wrap;
            padding-top:10px;
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--off-white);
            border: 2px solid var(--gray-medium);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--gray-dark);
            margin-bottom: 10px;
            transition: all var(--transition-fast);
        }

        .step-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .step-line {
            flex: 1;
            height: 2px;
            background: var(--gray-medium);
            margin: 0 15px;
            max-width: 150px;
            transition: all var(--transition-fast);
        }

        .step-item.active .step-number {
            background: var(--gradient-orange);
            border-color: var(--primary-orange);
            color: white;
            transform: scale(1.1);
        }

        .step-item.active .step-label {
            color: var(--primary-orange);
            font-weight: 700;
        }

        .step-item.completed .step-number {
            background: #4CAF50;
            border-color: #4CAF50;
            color: white;
        }

        .step-item.completed .step-number i {
            font-size: 1.2rem;
        }

        .step-item.completed ~ .step-line {
            background: #4CAF50;
        }

        /* STEP CONTAINER */
        .step-container {
            background: var(--white);
            border-radius: 30px;
            box-shadow: var(--shadow-lg);
            padding: 40px;
            margin-bottom: 30px;
        }

        /* ШАГ 0 - ВЫБОР ГОСТЯ ИЛИ ВХОДА */
        .auth-choice {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 40px 0;
        }

        .choice-card {
            background: var(--off-white);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all var(--transition-slow);
            cursor: pointer;
            border: 2px solid transparent;
        }

        .choice-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .choice-card.guest:hover {
            border-color: var(--primary-orange);
        }

        .choice-card.login:hover {
            border-color: var(--primary-black);
        }

        .choice-icon {
            font-size: 3.5rem;
            color: var(--primary-orange);
            margin-bottom: 20px;
        }

        .choice-card.login .choice-icon {
            color: var(--primary-black);
        }

        .choice-card h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .choice-card p {
            color: var(--gray-dark);
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .choice-card .btn {
            width: 100%;
            justify-content: center;
        }

        /* ШАГ 1 - ДОСТАВКА */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--charcoal);
        }

        .required-star {
            color: var(--primary-orange);
            margin-left: 3px;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--gray-medium);
            border-radius: 15px;
            font-size: 1rem;
            transition: all var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: var(--shadow-orange);
        }

        .form-control.error {
            border-color: #f44336;
        }

        .error-text {
            color: #f44336;
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }

        .readonly-info {
            background: var(--off-white);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-row {
            display: flex;
            margin-bottom: 10px;
        }

        .info-label {
            width: 100px;
            color: var(--gray-dark);
        }

        .info-value {
            font-weight: 600;
            color: var(--primary-black);
        }

        .guest-info {
            background: rgba(255, 90, 48, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .guest-info i {
            font-size: 2rem;
            color: var(--primary-orange);
        }

        .guest-info p {
            color: var(--gray-dark);
            font-size: 0.95rem;
        }

        .guest-info a {
            color: var(--primary-orange);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .guest-info a:hover {
            text-decoration: underline;
        }

        /* СПОСОБЫ ОПЛАТЫ */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .payment-method {
            border: 2px solid var(--gray-medium);
            border-radius: 20px;
            padding: 25px 20px;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .payment-method:hover {
            border-color: var(--primary-orange);
            transform: translateY(-5px);
        }

        .payment-method.selected {
            border-color: var(--primary-orange);
            background: rgba(255, 90, 48, 0.05);
        }

        .payment-method i {
            font-size: 2.5rem;
            color: var(--primary-orange);
            margin-bottom: 15px;
        }

        .payment-method h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .payment-method p {
            font-size: 0.85rem;
            color: var(--gray-dark);
        }

        /* ШАГ 2 - ПОДТВЕРЖДЕНИЕ */
        .order-items {
            margin: 30px 0;
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

        .order-item-artist {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 5px;
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

        .order-summary {
            background: var(--off-white);
            border-radius: 20px;
            padding: 25px;
            margin: 30px 0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: var(--gray-dark);
        }

        .summary-row.total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--gray-light);
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary-black);
        }

        .summary-row.total span:last-child {
            color: var(--primary-orange);
        }

        .delivery-info {
            font-size: 0.9rem;
            color: var(--primary-orange);
            margin-top: 5px;
        }

        /* КНОПКИ */
        .step-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .btn {
            padding: 16px 35px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
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
            transform: translateY(-3px);
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
            transform: translateY(-2px);
        }

        .btn-back {
            background: transparent;
            color: var(--gray-dark);
            border: 2px solid var(--gray-medium);
        }

        .btn-back:hover {
            border-color: var(--gray-dark);
            color: var(--charcoal);
        }

        /* ERROR MESSAGE */
        .system-error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            padding: 15px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(244, 67, 54, 0.3);
            text-align: center;
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
        @media (max-width: 1200px) {
            .section-title {
                font-size: 3rem;
            }
        }

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

            .page-header h1 {
                font-size: 3rem;
            }

            .checkout-steps {
                flex-wrap: wrap;
                gap: 15px;
            }

            .step-line {
                display: none;
            }

            .auth-choice {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
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

            .step-container {
                padding: 25px;
            }

            .order-item {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .order-item-details {
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .step-actions {
                flex-direction: column-reverse;
                gap: 15px;
            }

            .step-actions .btn {
                width: 100%;
                justify-content: center;
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

            .modal-content {
                padding: 30px 20px;
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

                    <?php if ($isLoggedIn): ?>
                    <button id="auth-button" onclick="window.location.href='profile.php'">
                        <i class="fas fa-user"></i>
                        <span id="auth-text"><?php echo htmlspecialchars(explode(' ', $userName)[0]); ?></span>
                    </button>
                    <?php else: ?>
                    <button id="auth-button" onclick="toggleAuthModal()">
                        <i class="fas fa-user"></i>
                        <span id="auth-text">Войти</span>
                    </button>
                    <?php endif; ?>

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
                <h1>Оформление <span class="text-gradient">заказа</span></h1>
            </div>
        </div>
    </section>

    <!-- CHECKOUT SECTION -->
    <section class="checkout-section">
        <div class="container">
            <!-- Индикатор шагов -->
            <?php if ($step > 0): ?>
            <div class="checkout-steps">
                <div class="step-item <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-number">
                        <?php if ($step > 1): ?>
                        <i class="fas fa-check"></i>
                        <?php else: ?>
                        1
                        <?php endif; ?>
                    </div>
                    <span class="step-label">Доставка</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item <?php echo $step >= 2 ? 'active' : ''; ?>">
                    <div class="step-number">2</div>
                    <span class="step-label">Подтверждение</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Отображение системных ошибок -->
            <?php if (isset($errors['system'])): ?>
                <div class="system-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errors['system']; ?>
                </div>
            <?php endif; ?>

            <!-- ШАГ 0: Выбор гостевого заказа или входа (только если не авторизован) -->
            <?php if ($step === 0 && !$isLoggedIn): ?>
            <div class="step-container">
                <h2 class="section-title" style="font-size: 2rem; margin-bottom: 30px; text-align: center;">Как продолжить?</h2>
                
                <div class="auth-choice">
                    <div class="choice-card guest" onclick="continueAsGuest()">
                        <div class="choice-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <h3>Как гость</h3>
                        <p>Оформите заказ без регистрации. Письмо с деталями придёт на ваш email.</p>
                        <button class="btn btn-primary">
                            Продолжить как гость <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>

                    <div class="choice-card login" onclick="toggleAuthModal()">
                        <div class="choice-icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <h3>Войти в аккаунт</h3>
                        <p>Отслеживайте заказы, сохраняйте избранное и получайте персональные предложения.</p>
                        <button class="btn btn-secondary">
                            Войти / Зарегистрироваться
                        </button>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="shopping-bag.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> Вернуться в корзину
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- ШАГ 1: Данные доставки -->
            <?php if ($step == 1): ?>
            <div class="step-container">
                <h2 class="section-title" style="font-size: 2rem; margin-bottom: 30px;">Адрес <span class="text-gradient">доставки</span></h2>
                
                <?php if (!$isLoggedIn): ?>
                <div class="guest-info">
                    <i class="fas fa-info-circle"></i>
                    <p>Вы оформляете заказ как гость. После оформления вы сможете отслеживать заказ по email.
                    <a href="#" onclick="toggleAuthModal()">Войти в аккаунт</a></p>
                </div>
                <?php endif; ?>

                <form method="POST" id="deliveryForm">
                    <input type="hidden" name="selected_items" value='<?php echo json_encode($selected_items); ?>'>
                    
                    <?php if ($isLoggedIn): ?>
                    <div class="readonly-info">
                        <div class="info-row">
                            <span class="info-label">ФИО:</span>
                            <span class="info-value"><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($userEmail); ?></span>
                        </div>
                        <?php if ($userPhone): ?>
                        <div class="info-row">
                            <span class="info-label">Телефон:</span>
                            <span class="info-value"><?php echo htmlspecialchars($userPhone); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <!-- Для гостей добавляем поле email -->
                    <div class="form-group full-width">
                        <label class="form-label">
                            Email для отслеживания заказа <span class="required-star">*</span>
                        </label>
                        <input type="email" 
                               name="guest_email" 
                               id="guest_email"
                               class="form-control <?php echo isset($errors['guest_email']) ? 'error' : ''; ?>" 
                               value="<?php echo htmlspecialchars($form_data['guest_email'] ?? ''); ?>" 
                               placeholder="example@email.com"
                               required>
                        <?php if (isset($errors['guest_email'])): ?>
                        <span class="error-text"><?php echo $errors['guest_email']; ?></span>
                        <?php endif; ?>
                        <small style="color: var(--gray-dark); display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> На этот email придёт подтверждение заказа
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">
                                Город <span class="required-star">*</span>
                            </label>
                            <input type="text" 
                                   name="city" 
                                   id="city" 
                                   class="form-control <?php echo isset($errors['city']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['city'] ?? $userCity ?? ''); ?>" 
                                   placeholder="Например: Минск"
                                   oninput="updateDeliveryPreview()"
                                   required>
                            <?php if (isset($errors['city'])): ?>
                            <span class="error-text"><?php echo $errors['city']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">
                                Адрес <span class="required-star">*</span>
                            </label>
                            <input type="text" 
                                   name="address" 
                                   id="address" 
                                   class="form-control <?php echo isset($errors['address']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['address'] ?? $userAddress ?? ''); ?>" 
                                   placeholder="Улица, дом, квартира"
                                   required>
                            <?php if (isset($errors['address'])): ?>
                            <span class="error-text"><?php echo $errors['address']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h2 class="section-title" style="font-size: 2rem; margin: 40px 0 30px;">Способ <span class="text-gradient">оплаты</span></h2>
                    
                    <div class="payment-methods">
                        <div class="payment-method <?php echo ($form_data['payment_method'] ?? 'card') == 'card' ? 'selected' : ''; ?>" onclick="selectPaymentMethod(this, 'card')">
                            <i class="fas fa-credit-card"></i>
                            <h4>Картой онлайн</h4>
                            <p>Visa, Mastercard, МИР</p>
                        </div>
                        <div class="payment-method <?php echo ($form_data['payment_method'] ?? '') == 'cash' ? 'selected' : ''; ?>" onclick="selectPaymentMethod(this, 'cash')">
                            <i class="fas fa-money-bill-wave"></i>
                            <h4>Наличными</h4>
                            <p>Курьеру при получении</p>
                        </div>
                        <div class="payment-method <?php echo ($form_data['payment_method'] ?? '') == 'bank' ? 'selected' : ''; ?>" onclick="selectPaymentMethod(this, 'bank')">
                            <i class="fas fa-university"></i>
                            <h4>Безналичный</h4>
                            <p>Для юридических лиц</p>
                        </div>
                    </div>
                    
                    <input type="hidden" name="payment_method" id="payment_method" value="<?php echo $form_data['payment_method'] ?? 'card'; ?>">
                    
                    <!-- Предварительный просмотр стоимости -->
                    <div class="order-summary" style="margin-top: 30px;">
                        <h3 style="margin-bottom: 20px;">Предварительный расчёт</h3>
                        <div class="summary-row">
                            <span>Товары (<?php echo count($order_items); ?> шт.)</span>
                            <span><?php echo number_format($subtotal, 2, '.', ' '); ?> BYN</span>
                        </div>
                        <div class="summary-row">
                            <span>Доставка</span>
                            <span id="delivery-preview">Рассчитывается...</span>
                        </div>
                        <div class="delivery-info" id="delivery-info"></div>
                        <div class="summary-row total">
                            <span>Итого</span>
                            <span id="total-preview"><?php echo number_format($subtotal, 2, '.', ' '); ?> BYN</span>
                        </div>
                    </div>
                    
                    <div class="step-actions">
                        <a href="shopping-bag.php" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Вернуться в корзину
                        </a>
                        <button type="submit" name="save_delivery" class="btn btn-primary">
                            Продолжить <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ШАГ 2: Подтверждение заказа -->
            <?php if ($step == 2): 
                $order_data = $_SESSION['order_data'] ?? [];
                $city = $order_data['city'] ?? '';
                $address = $order_data['address'] ?? '';
                $payment_method = $order_data['payment_method'] ?? 'card';
                $guest_email = $_SESSION['guest_email'] ?? '';
                
                $delivery_cost = calculateDelivery($city, $subtotal);
                $total = $subtotal + $delivery_cost;
                
                $payment_methods = [
                    'card' => 'Картой онлайн',
                    'cash' => 'Наличными при получении',
                    'bank' => 'Безналичный расчёт'
                ];
            ?>
            <div class="step-container">
                <h2 class="section-title" style="font-size: 2rem; margin-bottom: 30px;">Подтверждение <span class="text-gradient">заказа</span></h2>
                
                <form method="POST">
                    <!-- Состав заказа -->
                    <h3 style="margin-bottom: 20px;">Состав заказа</h3>
                    <div class="order-items">
                        <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="order-item-image">
                                <img src="<?php echo $item['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="order-item-info">
                                <h3><a href="product.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                <a href="artists_page.php?id=<?php echo $item['artist_id']; ?>" class="order-item-artist"><?php echo htmlspecialchars($item['artist_name']); ?></a>
                                <div class="order-item-details">
                                    <span class="order-item-price"><?php echo number_format($item['price'], 2, '.', ' '); ?> BYN</span>
                                    <span class="order-item-quantity">× <?php echo $item['quantity']; ?></span>
                                    <span class="order-item-total"><?php echo number_format($item['total'], 2, '.', ' '); ?> BYN</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Информация о доставке -->
                    <h3 style="margin: 30px 0 20px;">Доставка</h3>
                    <div class="readonly-info">
                        <div class="info-row">
                            <span class="info-label">Город:</span>
                            <span class="info-value"><?php echo htmlspecialchars($city); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Адрес:</span>
                            <span class="info-value"><?php echo htmlspecialchars($address); ?></span>
                        </div>
                        <?php if (!$isLoggedIn && $guest_email): ?>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($guest_email); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label">Способ оплаты:</span>
                            <span class="info-value"><?php echo $payment_methods[$payment_method] ?? $payment_method; ?></span>
                        </div>
                    </div>
                    
                    <!-- Итоговая стоимость -->
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Товары (<?php echo count($order_items); ?> шт.)</span>
                            <span><?php echo number_format($subtotal, 2, '.', ' '); ?> BYN</span>
                        </div>
                        <div class="summary-row">
                            <span>Доставка</span>
                            <span><?php echo $delivery_cost > 0 ? number_format($delivery_cost, 2, '.', ' ') . ' BYN' : 'Бесплатно'; ?></span>
                        </div>
                        <?php if ($delivery_cost == 0 && $subtotal >= 500): ?>
                        <div class="delivery-info">
                            Бесплатная доставка (сумма заказа от 500 BYN)
                        </div>
                        <?php elseif ($delivery_cost > 0): ?>
                        <div class="delivery-info">
                            <?php echo $delivery_cost == 10 ? 'Доставка по Минску — 10 BYN' : 'Доставка в другой регион — 40 BYN'; ?>
                        </div>
                        <?php endif; ?>
                        <div class="summary-row total">
                            <span>Итого к оплате</span>
                            <span><?php echo number_format($total, 2, '.', ' '); ?> BYN</span>
                        </div>
                    </div>
                    
                    <div class="step-actions">
                        <a href="placing_order.php?step=1" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                        <button type="submit" name="place_order" class="btn btn-primary">
                            <i class="fas fa-check"></i> Подтвердить заказ
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
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
        const subtotal = <?php echo $subtotal; ?>;
        
        // ============================================
        // ВЫБОР СПОСОБА ОПЛАТЫ
        // ============================================
        function selectPaymentMethod(element, method) {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('payment_method').value = method;
        }
        
        // ============================================
        // ПРЕДПРОСМОТР СТОИМОСТИ ДОСТАВКИ
        // ============================================
        function updateDeliveryPreview() {
            const city = document.getElementById('city').value.trim();
            const deliveryPreview = document.getElementById('delivery-preview');
            const deliveryInfo = document.getElementById('delivery-info');
            const totalPreview = document.getElementById('total-preview');
            
            if (!city) {
                deliveryPreview.textContent = 'Рассчитывается...';
                deliveryInfo.textContent = '';
                totalPreview.textContent = subtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' BYN';
                return;
            }
            
            let deliveryCost = 0;
            let infoText = '';
            
            if (subtotal >= 500) {
                deliveryCost = 0;
                infoText = 'Бесплатная доставка (сумма заказа от 500 BYN)';
            } else {
                const cityLower = city.toLowerCase();
                if (cityLower.includes('минск') || cityLower.includes('мин')) {
                    deliveryCost = 10;
                    infoText = 'Доставка по Минску — 10 BYN';
                } else {
                    deliveryCost = 40;
                    infoText = 'Доставка в другой регион — 40 BYN';
                }
            }
            
            const total = subtotal + deliveryCost;
            
            deliveryPreview.textContent = deliveryCost > 0 ? deliveryCost.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' BYN' : 'Бесплатно';
            deliveryInfo.textContent = infoText;
            totalPreview.textContent = total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' BYN';
        }

        // ============================================
        // ШАГ 0 - ПРОДОЛЖИТЬ КАК ГОСТЬ
        // ============================================
        function continueAsGuest() {
            window.location.href = 'placing_order.php?step=1';
        }

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

        function checkAuthAndNavigate(event, url) {
            if (!isLoggedIn) {
                event.preventDefault();
                showNotification('Войдите, чтобы просмотреть избранное', 'info');
                toggleAuthModal();
            }
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
            
            // Запускаем предпросмотр доставки при загрузке
            if (document.getElementById('city')) {
                updateDeliveryPreview();
            }
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