<?php
// ============================================
// favorites.php - СТРАНИЦА ИЗБРАННОГО
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
    // Отключаем ONLY_FULL_GROUP_BY для избежания ошибок
    $db->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
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
            
            header('Location: favorites.php');
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
            
            header('Location: favorites.php');
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ОПРЕДЕЛЯЕМ АКТИВНУЮ ВКЛАДКУ
// ============================================
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'products';

// ============================================
// ПОЛУЧАЕМ ИЗБРАННЫЕ ТОВАРЫ (только активные, неудалённые)
// ============================================
$wishlist_products = [];
if ($userId) {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.opisanie,
            p.price,
            p.discount_price,
            p.discount_percent,
            p.size,
            p.weight_kg,
            p.material,
            p.year_created,
            p.stock_quantity,
            p.image,
            p.category_id,
            p.artist_id,
            p.art_style,
            c.name as category_name,
            a.fio as artist_name,
            a.id as artist_id,
            fp.added_at as favorited_at,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.id) as reviews_count
        FROM favorites_products fp
        JOIN products p ON fp.product_id = p.id AND p.deleted_at IS NULL
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN artists a ON p.artist_id = a.id
        LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'published'
        WHERE fp.user_id = ?
        GROUP BY p.id
        ORDER BY fp.added_at DESC
    ");
    $stmt->execute([$userId]);
    $wishlist_products = $stmt->fetchAll();
}

// ============================================
// ПОЛУЧАЕМ ИЗБРАННЫХ ХУДОЖНИКОВ (художники не удаляются)
// ============================================
$wishlist_artists = [];
if ($userId) {
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.fio,
            a.bio,
            a.brief_introduction,
            a.strana,
            a.email,
            a.photo,
            a.year_of_birth,
            a.year_of_death,
            a.year_of_career_start,
            a.style,
            fa.added_at as favorited_at,
            COUNT(p.id) as products_count,
            COALESCE(AVG(r.rating), 0) as avg_rating
        FROM favorites_artists fa
        JOIN artists a ON fa.artist_id = a.id
        LEFT JOIN products p ON a.id = p.artist_id AND p.deleted_at IS NULL
        LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'published'
        WHERE fa.user_id = ?
        GROUP BY a.id
        ORDER BY fa.added_at DESC
    ");
    $stmt->execute([$userId]);
    $wishlist_artists = $stmt->fetchAll();
}

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
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cart_count = (int)$stmt->fetchColumn();
} else {
    $cart_count = array_sum($_SESSION['guest_cart'] ?? []);
}

// ============================================
// ПОЛУЧАЕМ ИЗБРАННОЕ ДЛЯ СЧЁТЧИКА
// ============================================
$wishlist_count = count($wishlist_products) + count($wishlist_artists);

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
    <title>ARTOBJECT | Избранное</title>
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

        
        .password_password_continer{
display: flex;
align-items: center;
justify-content: center;
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

        /* WISHLIST SECTION */
        .wishlist-section {
            padding: 40px 0 100px;
        }

        /* TABS */
        .wishlist-tabs {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 50px;
        }

        .tab-btn {
            padding: 15px 40px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid var(--gray-medium);
            background: var(--white);
            color: var(--charcoal);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .tab-btn i {
            font-size: 1.2rem;
            color: var(--primary-orange);
            transition: all var(--transition-fast);
        }

        .tab-btn:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
            transform: translateY(-2px);
        }

        .tab-btn.active {
            background: var(--gradient-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .tab-btn.active i {
            color: white;
        }

        .tab-btn .badge {
            position: static;
            margin-left: 8px;
            background: var(--primary-orange);
            color: white;
        }

        .tab-btn.active .badge {
            background: white;
            color: var(--primary-orange);
        }

        /* PRODUCTS GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 50px;
        }

        .product-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-slow);
            position: relative;
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-lg);
        }

        .product-card.removing {
            opacity: 0.5;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .product-image {
            height: 250px;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-image img {
            transform: scale(1.1);
        }

        .product-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--gradient-orange);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            z-index: 2;
        }

        .product-actions {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            opacity: 0;
            transform: translateX(20px);
            transition: all var(--transition-fast);
            z-index: 3;
        }

        .product-card:hover .product-actions {
            opacity: 1;
            transform: translateX(0);
        }

        .action-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: none;
            font-size: 1.2rem;
            color: var(--charcoal);
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .action-btn:hover {
            background: var(--primary-orange);
            color: white;
            transform: scale(1.1);
        }

        .action-btn.remove-btn:hover {
            background: #f44336;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            color: var(--primary-orange);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }

        .product-title {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .product-title a {
            text-decoration: none;
            color: inherit;
        }

        .product-title a:hover {
            color: var(--primary-orange);
        }

        .product-artist {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: block;
            text-decoration: none;
        }

        .product-artist:hover {
            color: var(--primary-orange);
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .stars {
            color: var(--gold);
            font-size: 0.9rem;
        }

        .rating-value {
            font-weight: 600;
            color: var(--charcoal);
        }

        .reviews-count {
            color: var(--gray-dark);
            font-size: 0.8rem;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-black);
            margin-bottom: 15px;
        }

        .old-price {
            font-size: 1rem;
            color: var(--gray-dark);
            text-decoration: line-through;
            margin-left: 8px;
            font-weight: 400;
        }

        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: rgba(46, 204, 113, 0.1);
            border-radius: 20px;
            color: #2ecc71;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .stock-status.out-of-stock {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        .btn-add-to-cart {
            width: 100%;
            padding: 12px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
            cursor: pointer;
        }

        .btn-add-to-cart:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .btn-add-to-cart.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--gray-medium);
        }

        .btn-add-to-cart.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .favorited-date {
            font-size: 0.8rem;
            color: var(--gray-medium);
            margin-top: 5px;
        }

        /* ARTISTS GRID */
        .artists-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .artist-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-slow);
            position: relative;
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }

        .artist-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-lg);
        }

        .artist-card.removing {
            opacity: 0.5;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .artist-image {
            height: 250px;
            position: relative;
            overflow: hidden;
        }

        .artist-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .artist-card:hover .artist-image img {
            transform: scale(1.1);
        }

        .artist-remove {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: none;
            font-size: 1.2rem;
            color: var(--charcoal);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            z-index: 3;
            opacity: 0;
            transform: translateX(20px);
        }

        .artist-card:hover .artist-remove {
            opacity: 1;
            transform: translateX(0);
        }

        .artist-remove:hover {
            background: #f44336;
            color: white;
            transform: scale(1.1);
        }

        .artist-info {
            padding: 25px;
            text-align: center;
        }

        .artist-name {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .artist-country {
            color: var(--primary-orange);
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }

        .artist-style {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .artist-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid var(--gray-light);
            border-bottom: 1px solid var(--gray-light);
        }

        .artist-stat {
            text-align: center;
        }

        .artist-stat .stat-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary-orange);
            display: block;
        }

        .artist-stat .stat-label {
            font-size: 0.8rem;
            color: var(--gray-dark);
        }

        .artist-bio {
            color: var(--gray-dark);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .btn-view-artist {
            width: 100%;
            padding: 12px;
            background: transparent;
            border: 2px solid var(--primary-orange);
            color: var(--primary-orange);
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
            cursor: pointer;
        }

        .btn-view-artist:hover {
            background: var(--primary-orange);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        /* EMPTY WISHLIST */
        .empty-wishlist {
            text-align: center;
            padding: 80px 20px;
            background: var(--white);
            border-radius: 30px;
            box-shadow: var(--shadow-md);
        }

        .empty-icon {
            font-size: 5rem;
            color: var(--gray-medium);
            margin-bottom: 25px;
        }

        .empty-wishlist h2 {
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .empty-wishlist p {
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

            .products-grid,
            .artists-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .main-menu {
                display: none;
            }

            .burger-menu {
                display: flex;
            }

            .page-header h1 {
                font-size: 3rem;
            }

            .wishlist-tabs {
                gap: 15px;
            }

            .tab-btn {
                padding: 12px 30px;
                font-size: 1rem;
            }

            .action-buttons {
                display: none;
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

            .wishlist-tabs {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .tab-btn {
                justify-content: center;
            }

            .products-grid,
            .artists-grid {
                grid-template-columns: 1fr;
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

            .product-image {
                height: 200px;
            }

            .artist-image {
                height: 200px;
            }

            .empty-wishlist h2 {
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
                <h1>Моё <span class="text-gradient">избранное</span></h1>
            </div>
        </div>
    </section>

    <!-- WISHLIST SECTION -->
    <section class="wishlist-section">
        <div class="container">
            <!-- Tabs -->
            <div class="wishlist-tabs">
                <a href="?tab=products" class="tab-btn <?php echo $activeTab == 'products' ? 'active' : ''; ?>">
                    <i class="fas fa-image"></i>
                    Товары 
                    <span class="badge"><?php echo count($wishlist_products); ?></span>
                </a>
                <a href="?tab=artists" class="tab-btn <?php echo $activeTab == 'artists' ? 'active' : ''; ?>">
                    <i class="fas fa-paint-brush"></i>
                    Художники 
                    <span class="badge"><?php echo count($wishlist_artists); ?></span>
                </a>
            </div>

            <!-- Products Tab -->
            <?php if ($activeTab == 'products'): ?>
                <?php if (empty($wishlist_products)): ?>
                    <div class="empty-wishlist">
                        <div class="empty-icon">
                            <i class="fas fa-heart-broken"></i>
                        </div>
                        <h2>В избранном пока нет товаров</h2>
                        <p>Добавляйте понравившиеся арт-объекты в избранное, чтобы не потерять их и вернуться к покупке позже.</p>
                        <a href="product_catalog.php" class="btn-primary">
                            <i class="fas fa-arrow-left"></i> Перейти в каталог
                        </a>
                    </div>
                <?php else: ?>
                    <div class="products-grid" id="products-grid">
                        <?php foreach ($wishlist_products as $product): ?>
                        <div class="product-card" data-id="<?php echo $product['id']; ?>" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                            <div class="product-image">
                                <img src="<?php echo $product['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80'; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php if ($product['discount_price']): ?>
                                <span class="product-badge">Скидка</span>
                                <?php endif; ?>
                                <div class="product-actions">
                                    <button class="action-btn remove-btn" onclick="removeFromWishlist(event, 'product', <?php echo $product['id']; ?>)" title="Удалить из избранного">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="product-info">
                                <span class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></span>
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <a href="artists_page.php?id=<?php echo $product['artist_id']; ?>" class="product-artist"><?php echo htmlspecialchars($product['artist_name']); ?></a>
                                
                                <div class="product-rating">
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= round($product['avg_rating'])): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-value"><?php echo number_format($product['avg_rating'], 1); ?></span>
                                    <span class="reviews-count">(<?php echo $product['reviews_count']; ?>)</span>
                                </div>

                                <div class="product-price">
                                    <?php if ($product['discount_price']): ?>
                                        <?php echo number_format($product['discount_price'], 2, '.', ' '); ?> BYN
                                        <span class="old-price"><?php echo number_format($product['price'], 2, '.', ' '); ?> BYN</span>
                                    <?php else: ?>
                                        <?php echo number_format($product['price'], 2, '.', ' '); ?> BYN
                                    <?php endif; ?>
                                </div>

                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <div class="stock-status">
                                        <i class="fas fa-check-circle"></i>
                                        В наличии
                                    </div>
                                    <button class="btn-add-to-cart" onclick="addToCart(event, <?php echo $product['id']; ?>)">
                                        <i class="fas fa-shopping-bag"></i> В корзину
                                    </button>
                                <?php else: ?>
                                    <div class="stock-status out-of-stock">
                                        <i class="fas fa-times-circle"></i>
                                        Нет в наличии
                                    </div>
                                    <button class="btn-add-to-cart disabled" disabled>
                                        <i class="fas fa-shopping-bag"></i> Нет в наличии
                                    </button>
                                <?php endif; ?>
                                
                                <div class="favorited-date">
                                    <i class="far fa-clock"></i> Добавлено <?php echo date('d.m.Y', strtotime($product['favorited_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Artists Tab -->
            <?php if ($activeTab == 'artists'): ?>
                <?php if (empty($wishlist_artists)): ?>
                    <div class="empty-wishlist">
                        <div class="empty-icon">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <h2>Нет избранных художников</h2>
                        <p>Подписывайтесь на художников, чтобы следить за их новыми работами и быть в курсе творчества.</p>
                        <a href="catalog_artists.php" class="btn-primary">
                            <i class="fas fa-arrow-left"></i> Познакомиться с художниками
                        </a>
                    </div>
                <?php else: ?>
                    <div class="artists-grid" id="artists-grid">
                        <?php foreach ($wishlist_artists as $artist): ?>
                        <div class="artist-card" data-id="<?php echo $artist['id']; ?>" onclick="window.location.href='artists_page.php?id=<?php echo $artist['id']; ?>'">
                            <div class="artist-image">
                                <img src="<?php echo $artist['photo'] ?? 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'; ?>" 
                                     alt="<?php echo htmlspecialchars($artist['fio']); ?>">
                                <button class="artist-remove" onclick="removeFromWishlist(event, 'artist', <?php echo $artist['id']; ?>)" title="Удалить из избранного">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="artist-info">
                                <h3 class="artist-name"><?php echo htmlspecialchars($artist['fio']); ?></h3>
                                <span class="artist-country"><?php echo htmlspecialchars($artist['strana'] ?? 'Страна не указана'); ?></span>
                                <?php if ($artist['style']): ?>
                                <span class="artist-style"><?php echo htmlspecialchars($artist['style']); ?></span>
                                <?php endif; ?>
                                
                                <div class="artist-stats">
                                    <div class="artist-stat">
                                        <span class="stat-value"><?php echo $artist['products_count']; ?></span>
                                        <span class="stat-label">Работ</span>
                                    </div>
                                    <?php if ($artist['avg_rating'] > 0): ?>
                                    <div class="artist-stat">
                                        <span class="stat-value"><?php echo number_format($artist['avg_rating'], 1); ?></span>
                                        <span class="stat-label">Рейтинг</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($artist['brief_introduction']): ?>
                                <p class="artist-bio"><?php echo htmlspecialchars(mb_substr($artist['brief_introduction'], 0, 100)) . '...'; ?></p>
                                <?php endif; ?>
                                
                                <button class="btn-view-artist" onclick="window.location.href='artists_page.php?id=<?php echo $artist['id']; ?>'">
                                    <i class="fas fa-user-circle"></i> Смотреть профиль
                                </button>
                                
                                <div class="favorited-date" style="margin-top: 10px;">
                                    <i class="far fa-clock"></i> Добавлено <?php echo date('d.m.Y', strtotime($artist['favorited_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
        // УДАЛЕНИЕ ИЗ ИЗБРАННОГО
        // ============================================
        function removeFromWishlist(event, type, id) {
            event.stopPropagation();
            event.preventDefault();

            if (!confirm('Удалить из избранного?')) {
                return;
            }

            const btn = event.currentTarget;
            const card = btn.closest(type === 'product' ? '.product-card' : '.artist-card');
            
            if (card) {
                card.classList.add('removing');
            }

            const url = type === 'product' ? '/api/toggle_favorite.php' : '/api/toggle_favorite_artist.php';
            const data = type === 'product' ? { product_id: id } : { artist_id: id };

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('wishlist-count').textContent = data.total;
                    document.getElementById('mobile-wishlist-count').textContent = data.total;
                    
                    const productTabBadge = document.querySelector('.tab-btn[href*="products"] .badge');
                    if (productTabBadge) {
                        productTabBadge.textContent = data.product_count;
                    }
                    
                    const artistTabBadge = document.querySelector('.tab-btn[href*="artists"] .badge');
                    if (artistTabBadge) {
                        artistTabBadge.textContent = data.artist_count;
                    }
                    
                    showNotification('Удалено из избранного', 'info');
                    
                    if (card) {
                        setTimeout(() => {
                            card.remove();
                            
                            const container = type === 'product' ? '#products-grid' : '#artists-grid';
                            const remaining = document.querySelectorAll(container + ' > *').length;
                            
                            if (remaining === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                } else {
                    if (card) {
                        card.classList.remove('removing');
                    }
                    showNotification('Ошибка при удалении', 'error');
                }
            })
            .catch(error => {
                if (card) {
                    card.classList.remove('removing');
                }
                showNotification('Ошибка соединения', 'error');
            });
        }

        // ============================================
        // ДОБАВЛЕНИЕ В КОРЗИНУ
        // ============================================
        function addToCart(event, productId) {
            event.stopPropagation();
            event.preventDefault();

            fetch('/api/add_to_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    product_id: productId,
                    quantity: 1 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cart-count').textContent = data.total;
                    document.getElementById('mobile-cart-count').textContent = data.total;
                    showNotification('Товар добавлен в корзину!', 'success');
                } else {
                    showNotification('Ошибка', 'error');
                }
            })
            .catch(error => {
                showNotification('Ошибка соединения', 'error');
            });
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
            window.addEventListener('scroll', function() {
                const header = document.getElementById('header');
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        });

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('authModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>