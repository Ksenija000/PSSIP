<?php
// ============================================
// shopping-bag.php - КОРЗИНА ПОКУПАТЕЛЯ
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
$isLoggedIn = false;
$userId = 0;
$userName = '';
$userRole = '';
$userEmail = '';

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
            
            header('Location: shopping-bag.php');
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
            
            header('Location: shopping-bag.php');
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ С КОРЗИНОЙ
// ============================================

// Обработка AJAX запросов
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'update_quantity') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($isLoggedIn) {
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $userId, $product_id]);
        } else {
            if (isset($_SESSION['guest_cart'][$product_id])) {
                if ($quantity > 0) {
                    $_SESSION['guest_cart'][$product_id] = $quantity;
                } else {
                    unset($_SESSION['guest_cart'][$product_id]);
                }
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] == 'remove_item') {
        $product_id = (int)$_POST['product_id'];
        
        if ($isLoggedIn) {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $product_id]);
        } else {
            unset($_SESSION['guest_cart'][$product_id]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] == 'clear_cart') {
        if ($isLoggedIn) {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            $_SESSION['guest_cart'] = [];
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// ============================================
// ПОЛУЧАЕМ ТОВАРЫ В КОРЗИНЕ (только активные, неудалённые)
// ============================================
$cart_items = [];
$cart_data = []; // для хранения quantity
$filtered_product_ids = []; // для отслеживания удалённых товаров

if ($isLoggedIn) {
    // Сначала получаем все товары из корзины
    $stmt = $db->prepare("
        SELECT 
            c.quantity,
            c.product_id,
            p.*,
            a.fio as artist_name,
            a.id as artist_id,
            cat.name as category_name
        FROM cart_items c
        LEFT JOIN products p ON c.product_id = p.id
        LEFT JOIN artists a ON p.artist_id = a.id
        LEFT JOIN categories cat ON p.category_id = cat.id
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ");
    $stmt->execute([$userId]);
    $all_cart_items = $stmt->fetchAll();
    
    // Фильтруем только те товары, которые существуют и не удалены
    foreach ($all_cart_items as $item) {
        if ($item['id'] !== null && $item['deleted_at'] === null) {
            $cart_items[] = $item;
            $cart_data[$item['id']] = $item['quantity'];
            $filtered_product_ids[] = $item['product_id'];
        } else {
            // Если товар удалён или не существует - удаляем из корзины
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $item['product_id']]);
        }
    }
} else {
    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
        $product_ids = array_keys($_SESSION['guest_cart']);
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        
        $stmt = $db->prepare("
            SELECT 
                p.*,
                a.fio as artist_name,
                a.id as artist_id,
                cat.name as category_name
            FROM products p
            LEFT JOIN artists a ON p.artist_id = a.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE p.id IN ($placeholders)
        ");
        $stmt->execute($product_ids);
        $products_in_cart = $stmt->fetchAll();
        
        // Фильтруем товары и обновляем guest_cart
        $cart_items = [];
        $new_guest_cart = [];
        
        foreach ($products_in_cart as $product) {
            if ($product['deleted_at'] === null) {
                $cart_items[] = $product;
                $product_id = $product['id'];
                $cart_data[$product_id] = $_SESSION['guest_cart'][$product_id];
                $new_guest_cart[$product_id] = $_SESSION['guest_cart'][$product_id];
                $filtered_product_ids[] = $product_id;
            }
        }
        
        // Обновляем сессию, убирая удалённые товары
        $_SESSION['guest_cart'] = $new_guest_cart;
    }
}

// ============================================
// ПОЛУЧАЕМ КАТЕГОРИИ ДЛЯ МЕНЮ
// ============================================
$categories = $db->query("
    SELECT * FROM categories ORDER BY name
")->fetchAll();

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
    <title>ARTOBJECT | Корзина</title>
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

        .section-subtitle {
            font-size: 1.2rem;
            color: var(--gray-dark);
            max-width: 600px;
            margin: 0 0 3rem 0;
            text-align: left;
        }

        /* КОНТЕЙНЕРЫ */
        .container {
            max-width: 1400px;
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

        /* Мобильное меню (выезжающее) */
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


        /* CART SECTION */
        .cart-section {
            padding: 60px 0 100px;
        }

        .cart-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
        }

        /* CART ITEMS */
        .cart-items {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .cart-header {
            display: grid;
            grid-template-columns: 50px 3fr 1fr 1fr 1fr 0.5fr;
            padding: 20px 30px;
            background: var(--off-white);
            border-bottom: 2px solid var(--gray-light);
            font-weight: 700;
            color: var(--gray-dark);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            align-items: center;
        }

        .cart-header-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-header-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-orange);
        }

        .cart-item {
            display: grid;
            grid-template-columns: 50px 3fr 1fr 1fr 1fr 0.5fr;
            padding: 25px 30px;
            align-items: center;
            border-bottom: 1px solid var(--gray-light);
            transition: all var(--transition-fast);
        }

        .cart-item:hover {
            background: var(--off-white);
        }

        .cart-item-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-orange);
        }

        .cart-item-product {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .cart-item-image {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform var(--transition-fast);
        }

        .cart-item-image:hover img {
            transform: scale(1.1);
        }

        .cart-item-info h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .cart-item-info h3 a {
            text-decoration: none;
            color: inherit;
        }

        .cart-item-info h3 a:hover {
            color: var(--primary-orange);
        }

        .cart-item-artist {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
            text-decoration: none;
        }

        .cart-item-artist:hover {
            color: var(--primary-orange);
        }

        .cart-item-category {
            color: var(--primary-orange);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .cart-item-price {
            font-weight: 700;
            color: var(--primary-black);
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cart-quantity-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--off-white);
            border: 2px solid var(--gray-medium);
            color: var(--charcoal);
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .cart-quantity-btn:hover {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .cart-quantity-input {
            width: 50px;
            height: 32px;
            border: 2px solid var(--gray-medium);
            border-radius: 16px;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .cart-quantity-input:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .cart-item-total {
            font-weight: 800;
            color: var(--primary-orange);
        }

        .cart-item-remove {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--off-white);
            border: 2px solid var(--gray-medium);
            color: var(--gray-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .cart-item-remove:hover {
            background: #f44336;
            color: white;
            border-color: #f44336;
            transform: scale(1.1);
        }

        /* CART SUMMARY */
        .cart-summary {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            padding: 30px;
            position: sticky;
            top: 140px;
        }

        .summary-title {
            font-size: 1.5rem;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-light);
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

        .selected-count {
            font-size: 0.9rem;
            color: var(--gray-dark);
            margin-bottom: 10px;
        }

        .cart-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-checkout {
            width: 100%;
            padding: 16px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all var(--transition-fast);
            cursor: pointer;
        }

        .btn-checkout:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-orange);
        }

        .btn-checkout.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-checkout.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .btn-continue {
            width: 100%;
            padding: 14px;
            background: transparent;
            color: var(--charcoal);
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
            cursor: pointer;
            text-decoration: none;
        }

        .btn-continue:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
            transform: translateY(-2px);
        }

        .btn-clear-cart {
            width: 100%;
            padding: 14px;
            background: transparent;
            color: #f44336;
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
            cursor: pointer;
        }

        .btn-clear-cart:hover {
            border-color: #f44336;
            color: #f44336;
            transform: translateY(-2px);
        }

        /* EMPTY CART */
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
        }

        .empty-cart-icon {
            font-size: 5rem;
            color: var(--gray-medium);
            margin-bottom: 25px;
        }

        .empty-cart h2 {
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .empty-cart p {
            color: var(--gray-dark);
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

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
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-orange);
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

        /* АДАПТАЦИЯ */
        @media (max-width: 1200px) {
            .section-title {
                font-size: 3rem;
            }

            .cart-grid {
                grid-template-columns: 1fr 340px;
                gap: 30px;
            }

            .cart-header {
                grid-template-columns: 50px 3fr 1fr 1fr 1fr 0.5fr;
                padding: 20px;
            }

            .cart-item {
                grid-template-columns: 50px 3fr 1fr 1fr 1fr 0.5fr;
                padding: 20px;
            }
        }

        @media (max-width: 992px) {
            .action-buttons{
                display: none;
            }
            .main-menu {
                display: none;
            }

            .burger-menu {
                display: flex;
            }

            .page-header h1 {
                font-size: 3rem;
            }

            .cart-grid {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
                margin-top: 30px;
            }

            .cart-header {
                display: none;
            }

            .cart-item {
                grid-template-columns: 1fr;
                gap: 15px;
                padding: 20px;
                border-bottom: 1px solid var(--gray-light);
                position: relative;
            }

            .cart-item-checkbox {
                position: absolute;
                top: 20px;
                left: 20px;
            }

            .cart-item-product {
                grid-column: 1 / -1;
                padding-left: 30px;
            }

            .cart-item-price,
            .cart-item-quantity,
            .cart-item-total,
            .cart-item-remove {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 5px 0;
            }

            .cart-item-price::before {
                content: "Цена:";
                font-weight: 600;
                color: var(--gray-dark);
            }

            .cart-item-quantity::before {
                content: "Количество:";
                font-weight: 600;
                color: var(--gray-dark);
            }

            .cart-item-total::before {
                content: "Сумма:";
                font-weight: 600;
                color: var(--gray-dark);
            }

            .cart-item-remove {
                justify-content: flex-end;
            }

            .cart-item-remove button {
                width: 100%;
                border-radius: 30px;
                padding: 10px;
                background: #f44336;
                color: white;
                border: none;
            }

            .cart-item-remove button i {
                margin-right: 8px;
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

            .cart-section {
                padding: 40px 0 60px;
            }

            .cart-item-image {
                width: 80px;
                height: 80px;
            }

            .cart-item-info h3 {
                font-size: 1rem;
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

            .empty-cart h2 {
                font-size: 1.5rem;
            }

            .modal-content {
                padding: 30px 20px;
            }

            .mobile-menu {
                width: 250px;
                padding: 80px 20px 20px;
            }
        }

        @media (max-width: 430px) {
            .cart-item-product {
                flex-direction: column;
                text-align: center;
            }

            .cart-item-image {
                margin: 0 auto;
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
                            <span class="badge" id="cart-count"><?php echo array_sum($cart_data); ?></span>
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
                <span class="badge" id="mobile-cart-count"><?php echo array_sum($cart_data); ?></span>
            </a>
        </div>
    </div>

    <!-- PAGE HEADER -->
    <section class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1>Корзина <span class="text-gradient">покупателя</span></h1>
            </div>
        </div>
    </section>

    <!-- CART SECTION -->
    <section class="cart-section">
        <div class="container">
            <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h2>Корзина пуста</h2>
                <p>Но это никогда не поздно исправить :) В нашем каталоге вы найдёте множество уникальных арт-объектов.</p>
                <a href="product_catalog.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i> Перейти в каталог
                </a>
            </div>
            <?php else: ?>
            <div class="cart-grid">
                <!-- Список товаров -->
                <div class="cart-items">
                    <div class="cart-header">
                        <div class="cart-header-checkbox">
                            <input type="checkbox" id="selectAll" checked onchange="toggleSelectAll(this)">
                        </div>
                        <div>Товар</div>
                        <div>Цена</div>
                        <div>Количество</div>
                        <div>Сумма</div>
                        <div></div>
                    </div>

                    <div id="cart-items-container">
                        <?php foreach ($cart_items as $item): 
                            $quantity = $cart_data[$item['id']];
                            $total = $item['discount_price'] ? $item['discount_price'] * $quantity : $item['price'] * $quantity;
                        ?>
                        <div class="cart-item" data-id="<?php echo $item['id']; ?>" data-price="<?php echo $item['discount_price'] ?? $item['price']; ?>">
                            <div class="cart-item-checkbox">
                                <input type="checkbox" class="item-checkbox" data-id="<?php echo $item['id']; ?>" checked onchange="updateSelectedCount()">
                            </div>
                            <div class="cart-item-product">
                                <div class="cart-item-image">
                                    <img src="<?php echo $item['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="cart-item-info">
                                    <h3><a href="product.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                    <a href="artists_page.php?id=<?php echo $item['artist_id']; ?>" class="cart-item-artist"><?php echo htmlspecialchars($item['artist_name']); ?></a>
                                    <span class="cart-item-category"><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></span>
                                </div>
                            </div>
                            <div class="cart-item-price">
                                <?php if ($item['discount_price']): ?>
                                    <?php echo number_format($item['discount_price'], 2, '.', ' '); ?> BYN
                                <?php else: ?>
                                    <?php echo number_format($item['price'], 2, '.', ' '); ?> BYN
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-quantity">
                                <button class="cart-quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 'decrease')">−</button>
                                <input type="number" class="cart-quantity-input" id="qty-<?php echo $item['id']; ?>" value="<?php echo $quantity; ?>" min="1" max="<?php echo $item['stock_quantity']; ?>" readonly>
                                <button class="cart-quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 'increase')">+</button>
                            </div>
                            <div class="cart-item-total" id="total-<?php echo $item['id']; ?>">
                                <?php echo number_format($total, 2, '.', ' '); ?> BYN
                            </div>
                            <div class="cart-item-remove">
                                <button class="cart-item-remove-btn" onclick="removeFromCart(<?php echo $item['id']; ?>)" title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Блок итогов -->
                <div class="cart-summary">
                    <h3 class="summary-title">Итого</h3>
                    
                    <div class="selected-count" id="selectedCount">
                        Выбрано товаров: <strong id="selectedItemsCount"><?php echo count($cart_items); ?></strong>
                    </div>
                    
                    <div class="summary-row" id="selectedSubtotalRow">
                        <span>Товары (<span id="selectedItemsCount2"><?php echo count($cart_items); ?></span> шт.)</span>
                        <span id="selectedSubtotal"><?php 
                            $subtotal = 0;
                            foreach ($cart_items as $item) {
                                $price = $item['discount_price'] ?? $item['price'];
                                $subtotal += $price * $cart_data[$item['id']];
                            }
                            echo number_format($subtotal, 2, '.', ' ');
                        ?> BYN</span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>К оплате</span>
                        <span id="selectedTotal"><?php echo number_format($subtotal, 2, '.', ' '); ?> BYN</span>
                    </div>

                    <div class="cart-actions">
                        <button class="btn-checkout" id="checkoutBtn" onclick="checkout()">
                            <i class="fas fa-credit-card"></i> Оформить заказ
                        </button>
                        <a href="product_catalog.php" class="btn-continue">
                            <i class="fas fa-arrow-left"></i> Продолжить покупки
                        </a>
                        <button class="btn-clear-cart" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Очистить корзину
                        </button>
                    </div>
                </div>
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
        
        // ============================================
        // ФУНКЦИИ ДЛЯ РАБОТЫ С КОРЗИНОЙ
        // ============================================
        
        function updateQuantity(productId, action) {
            const input = document.getElementById('qty-' + productId);
            let currentQty = parseInt(input.value);
            
            if (action === 'increase') {
                currentQty++;
            } else if (action === 'decrease') {
                currentQty--;
            }
            
            if (currentQty < 1) {
                if (confirm('Удалить товар из корзины?')) {
                    removeFromCart(productId);
                }
                return;
            }
            
            // Отправляем AJAX запрос
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'update_quantity');
            formData.append('product_id', productId);
            formData.append('quantity', currentQty);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = currentQty;
                    updateItemTotal(productId);
                    updateCartCount();
                    updateSelectedTotal();
                }
            })
            .catch(error => {
                showNotification('Ошибка при обновлении количества', 'error');
            });
        }
        
        function updateItemTotal(productId) {
            const item = document.querySelector(`.cart-item[data-id="${productId}"]`);
            const price = parseFloat(item.dataset.price);
            const quantity = parseInt(document.getElementById('qty-' + productId).value);
            const total = price * quantity;
            
            document.getElementById('total-' + productId).textContent = total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' BYN';
        }
        
        function removeFromCart(productId) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'remove_item');
            formData.append('product_id', productId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`.cart-item[data-id="${productId}"]`);
                    item.remove();
                    updateCartCount();
                    updateSelectedCount();
                    
                    const remaining = document.querySelectorAll('.cart-item').length;
                    if (remaining === 0) {
                        location.reload(); // Перезагружаем для показа пустой корзины
                    }
                    
                    showNotification('Товар удалён из корзины', 'info');
                }
            })
            .catch(error => {
                showNotification('Ошибка при удалении', 'error');
            });
        }
        
        function clearCart() {
            if (!confirm('Очистить корзину?')) return;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'clear_cart');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                showNotification('Ошибка при очистке корзины', 'error');
            });
        }
        
        function updateCartCount() {
            let total = 0;
            document.querySelectorAll('.cart-quantity-input').forEach(input => {
                total += parseInt(input.value);
            });
            
            document.getElementById('cart-count').textContent = total;
            document.getElementById('mobile-cart-count').textContent = total;
        }
        
        // ============================================
        // ФУНКЦИИ ДЛЯ ЧЕКБОКСОВ
        // ============================================
        
        function toggleSelectAll(checkbox) {
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            itemCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            const count = checkboxes.length;
            
            document.getElementById('selectedItemsCount').textContent = count;
            document.getElementById('selectedItemsCount2').textContent = count;
            
            // Обновляем "Выбрать все"
            const selectAll = document.getElementById('selectAll');
            const totalItems = document.querySelectorAll('.item-checkbox').length;
            
            if (selectAll) {
                selectAll.checked = count === totalItems;
                selectAll.indeterminate = count > 0 && count < totalItems;
            }
            
            updateSelectedTotal();
        }
        
        function updateSelectedTotal() {
            let subtotal = 0;
            
            document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
                const item = checkbox.closest('.cart-item');
                const productId = item.dataset.id;
                const price = parseFloat(item.dataset.price);
                const quantity = parseInt(document.getElementById('qty-' + productId).value);
                subtotal += price * quantity;
            });
            
            document.getElementById('selectedSubtotal').textContent = subtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' BYN';
            document.getElementById('selectedTotal').textContent = subtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' BYN';
            
            // Блокируем кнопку оформления, если ничего не выбрано
            const checkoutBtn = document.getElementById('checkoutBtn');
            if (subtotal === 0) {
                checkoutBtn.classList.add('disabled');
                checkoutBtn.disabled = true;
            } else {
                checkoutBtn.classList.remove('disabled');
                checkoutBtn.disabled = false;
            }
        }
        
        function checkout() {
            const selectedItems = [];
            document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
                const item = checkbox.closest('.cart-item');
                if (item) {
                    selectedItems.push(item.dataset.id);
                }
            });
            
            if (selectedItems.length === 0) {
                showNotification('Выберите товары для оформления', 'error');
                return;
            }
            
            // Сохраняем в sessionStorage
            sessionStorage.setItem('selectedItems', JSON.stringify(selectedItems));
            
            // Отправляем POST запрос с выбранными товарами
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'placing_order.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_items';
            input.value = JSON.stringify(selectedItems);
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
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
            
            // Обновляем счётчик выбранных при загрузке
            updateSelectedCount();
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