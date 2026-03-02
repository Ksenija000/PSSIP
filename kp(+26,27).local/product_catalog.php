<?php
// ============================================
// product_catalog.php - КАТАЛОГ ТОВАРОВ
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
            
            header('Location: product_catalog.php');
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
            
            header('Location: product_catalog.php');
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ПОЛУЧАЕМ ПАРАМЕТРЫ ФИЛЬТРАЦИИ
// ============================================
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$artist_filter = isset($_GET['artist']) ? (int)$_GET['artist'] : 0;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 500000;
$material_filter = $_GET['material'] ?? '';
$style_filter = $_GET['style'] ?? '';
$year_from = isset($_GET['year_from']) ? (int)$_GET['year_from'] : 0;
$year_to = isset($_GET['year_to']) ? (int)$_GET['year_to'] : 0;
$in_stock_only = isset($_GET['in_stock']) ? true : false;
$search_query = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'default';
$view_mode = $_GET['view'] ?? 'grid';

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ ФИЛЬТРОВ
// ============================================
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Художники — получаем всех для фильтрации (художники не удаляются)
$all_artists = $db->query("SELECT id, fio FROM artists ORDER BY fio")->fetchAll();
$total_artists = count($all_artists);
$artists_limit = isset($_GET['artists_limit']) ? (int)$_GET['artists_limit'] : 3;
$artists = array_slice($all_artists, 0, $artists_limit);

// Материалы (только от активных товаров)
$all_materials = $db->query("
    SELECT DISTINCT material FROM products 
    WHERE material IS NOT NULL AND deleted_at IS NULL 
    ORDER BY material
")->fetchAll(PDO::FETCH_COLUMN);
$total_materials = count($all_materials);
$materials_limit = isset($_GET['materials_limit']) ? (int)$_GET['materials_limit'] : 3;
$materials = array_slice($all_materials, 0, $materials_limit);

// Стили (только от активных товаров)
$all_styles = $db->query("
    SELECT DISTINCT art_style FROM products 
    WHERE art_style IS NOT NULL AND deleted_at IS NULL 
    ORDER BY art_style
")->fetchAll(PDO::FETCH_COLUMN);
$total_styles = count($all_styles);
$styles_limit = isset($_GET['styles_limit']) ? (int)$_GET['styles_limit'] : 3;
$styles = array_slice($all_styles, 0, $styles_limit);

// Годы (только от активных товаров)
$years = $db->query("
    SELECT DISTINCT year_created FROM products 
    WHERE year_created IS NOT NULL AND deleted_at IS NULL 
    ORDER BY year_created DESC
")->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// ПОСТРОЕНИЕ SQL ЗАПРОСА (ТОЛЬКО АКТИВНЫЕ ТОВАРЫ)
// ============================================
$sql = "
    SELECT 
        p.*,
        c.name as category_name,
        a.fio as artist_name,
        a.id as artist_id,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT r.id) as reviews_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN artists a ON p.artist_id = a.id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'published'
    WHERE p.deleted_at IS NULL
";
$count_sql = "SELECT COUNT(DISTINCT p.id) FROM products p WHERE p.deleted_at IS NULL";
$params = [];

if ($category_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $count_sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if ($artist_filter > 0) {
    $sql .= " AND p.artist_id = ?";
    $count_sql .= " AND p.artist_id = ?";
    $params[] = $artist_filter;
}

if ($min_price > 0) {
    $sql .= " AND p.price >= ?";
    $count_sql .= " AND p.price >= ?";
    $params[] = $min_price;
}

if ($max_price < 500000) {
    $sql .= " AND p.price <= ?";
    $count_sql .= " AND p.price <= ?";
    $params[] = $max_price;
}

if ($material_filter) {
    $sql .= " AND p.material LIKE ?";
    $count_sql .= " AND p.material LIKE ?";
    $params[] = "%$material_filter%";
}

if ($style_filter) {
    $sql .= " AND p.art_style = ?";
    $count_sql .= " AND p.art_style = ?";
    $params[] = $style_filter;
}

if ($year_from > 0) {
    $sql .= " AND p.year_created >= ?";
    $count_sql .= " AND p.year_created >= ?";
    $params[] = $year_from;
}

if ($year_to > 0) {
    $sql .= " AND p.year_created <= ?";
    $count_sql .= " AND p.year_created <= ?";
    $params[] = $year_to;
}

if ($in_stock_only) {
    $sql .= " AND p.stock_quantity > 0";
    $count_sql .= " AND p.stock_quantity > 0";
}

if ($search_query) {
    $sql .= " AND (p.name LIKE ? OR p.opisanie LIKE ?)";
    $count_sql .= " AND (p.name LIKE ? OR p.opisanie LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " GROUP BY p.id";

// Сортировка
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY p.year_created DESC, p.id DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY reviews_count DESC, p.id DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC, reviews_count DESC";
        break;
    default:
        $sql .= " ORDER BY p.id DESC";
}

// Получаем общее количество товаров
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();

// Получаем товары
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ============================================
// ПОЛУЧАЕМ ИЗБРАННОЕ ПОЛЬЗОВАТЕЛЯ
// ============================================
$wishlist = [];
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT product_id FROM favorites_products WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================
// ПОЛУЧАЕМ КОРЗИНУ
// ============================================
$cart_items = [];
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cart_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $cart_items = $cart_db;
} else {
    $cart_items = $_SESSION['guest_cart'] ?? [];
}

$cart_count = array_sum($cart_items);

// ============================================
// ПОЛУЧАЕМ КАТЕГОРИИ ДЛЯ МЕНЮ
// ============================================
$menu_categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

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
// ФУНКЦИЯ ДЛЯ ПОСТРОЕНИЯ URL С СОХРАНЕНИЕМ ПАРАМЕТРОВ
// ============================================
function buildUrl($params = []) {
    $current = $_GET;
    foreach ($params as $key => $value) {
        $current[$key] = $value;
    }
    return '?' . http_build_query($current);
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
    <title>ARTOBJECT | Каталог арт-объектов</title>
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

        /* FILTER SIDEBAR */
        .catalog-section {
            padding: 60px 0 100px;
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
        }

        .filter-sidebar {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            height: fit-content;
            position: sticky;
            top: 140px;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-light);
        }

        .filter-header h3 {
            font-size: 1.3rem;
        }

        .filter-reset {
            color: var(--primary-orange);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
        }

        .filter-reset:hover {
            color: var(--orange-dark);
            text-decoration: underline;
        }

        .filter-group {
            margin-bottom: 30px;
        }

        .filter-group-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group-title i {
            color: var(--primary-orange);
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .filter-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-orange);
        }

        .filter-checkbox span {
            color: var(--gray-dark);
            font-size: 0.95rem;
        }

        .filter-checkbox .count {
            margin-left: auto;
            color: var(--gray-medium);
            font-size: 0.85rem;
        }

        .show-more-btn {
            display: inline-block;
            margin-top: 10px;
            color: var(--primary-orange);
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
        }

        .show-more-btn:hover {
            text-decoration: underline;
        }

        .price-inputs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .price-input {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--gray-medium);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all var(--transition-fast);
            width: 100%;
        }

        .price-input:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-orange);
        }

        .year-select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            font-size: 0.95rem;
            background: var(--white);
            color: var(--charcoal);
            cursor: pointer;
        }

        .year-select:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        /* CATALOG MAIN */
        .catalog-main {
            width: 100%;
        }

        .catalog-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px 20px;
            background: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow-sm);
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            font-size: 0.95rem;
            transition: all var(--transition-fast);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: var(--shadow-orange);
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-dark);
            font-size: 1.1rem;
            cursor: pointer;
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .sort-select {
            padding: 10px 25px;
            border-radius: 30px;
            border: 2px solid var(--gray-medium);
            background: var(--white);
            font-weight: 600;
            color: var(--charcoal);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .sort-select:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .view-toggle {
            display: flex;
            gap: 8px;
        }

        .view-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--off-white);
            border: 2px solid var(--gray-medium);
            color: var(--charcoal);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
        }

        .view-btn:hover {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .view-btn.active {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        /* PRODUCTS GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 50px;
        }

        .products-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .products-list .product-card {
            display: flex;
            flex-direction: row;
            height: 300px;
        }

        .products-list .product-image {
            width: 200px;
            height: 100%;
        }

        .products-list .product-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
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

        .action-btn.active {
            background: var(--primary-orange);
            color: white;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            color: var(--primary-orange);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .product-title {
            font-size: 1.2rem;
            margin-bottom: 8px;
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
            margin-bottom: 12px;
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
            margin-bottom: 10px;
        }

        .old-price {
            font-size: 1rem;
            color: var(--gray-dark);
            text-decoration: line-through;
            margin-left: 8px;
            font-weight: 400;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            color: var(--gray-dark);
            font-size: 0.85rem;
            padding-top: 10px;
            border-top: 1px solid var(--gray-light);
            margin-bottom: 15px;
        }

        .btn-add-to-cart {
            width: 100%;
            padding: 12px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.95rem;
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
        }

        .btn-add-to-cart.disabled:hover {
            transform: none;
            box-shadow: none;
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

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .catalog-grid {
                grid-template-columns: 1fr;
            }

            .filter-sidebar {
                position: static;
                margin-bottom: 30px;
            }

            .page-header h1 {
                font-size: 3rem;
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
                margin-left: 10px;
            }

            .page-header {
                margin-top: 100px;
                padding: 40px 0;
            }

            .page-header h1 {
                font-size: 2.5rem;
            }

            .catalog-toolbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box {
                max-width: 100%;
                width: 100%;
            }

            .toolbar-right {
                width: 100%;
                justify-content: space-between;
            }

            .products-grid {
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

            .toolbar-right {
                flex-direction: column;
                align-items: flex-start;
            }

            .sort-select {
                width: 100%;
            }

            .view-toggle {
                width: 100%;
                justify-content: center;
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
                <div class="logo" onclick="window.location.href='/'">
                    <div class="logo-main">ARTOBJECT</div>
                    <div class="logo-sub">GALLERY</div>
                </div>

                <nav class="main-menu">
                    <ul>
                        <li><a href="/"><i class="fas fa-home"></i> Главная</a></li>
                        <li class="dropdown">
                            <a href="product_catalog.php"><i class="fas fa-th-large"></i> Каталог <i class="fas fa-chevron-down"></i></a>
                            <div class="dropdown-content">
                                <?php foreach ($menu_categories as $cat): ?>
                                <a href="product_catalog.php?category=<?php echo $cat['id']; ?>">
                                    <i class="fas fa-<?php echo getCategoryIcon($cat['id'], $cat['name']); ?>"></i>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                        <li><a href="catalog_artists.php"><i class="fas fa-paint-brush"></i> Художники</a></li>
                        <li><a href="about_Us.php"><i class="fas fa-info-circle"></i> О нас</a></li>
                    </ul>
                </nav>

                <div class="header-right">
                    <div class="action-buttons">
                        <button class="icon-btn" onclick="toggleWishlist()" title="Избранное">
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
            <li><a href="/"><i class="fas fa-home"></i> Главная</a></li>
            <li class="mobile-dropdown">
                <a href="product_catalog.php" onclick="toggleMobileDropdown(this)">
                    <i class="fas fa-th-large"></i> Каталог
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="mobile-dropdown-content">
                    <?php foreach ($menu_categories as $cat): ?>
                    <a href="product_catalog.php?category=<?php echo $cat['id']; ?>">
                        <i class="fas fa-<?php echo getCategoryIcon($cat['id'], $cat['name']); ?>"></i>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </li>
            <li><a href="catalog_artists.php"><i class="fas fa-paint-brush"></i> Художники</a></li>
            <li><a href="about_Us.php"><i class="fas fa-info-circle"></i> О нас</a></li>
        </ul>

        <div class="mobile-actions">
            <a href="/favorites.php" class="mobile-action-btn" onclick="checkAuthAndNavigate(event, '/favorites.php')">
                <i class="fas fa-heart"></i>
                <span>Избранное</span>
                <span class="badge" id="mobile-wishlist-count"><?php echo $wishlist_count; ?></span>
            </a>
            <a href="/shopping-bag.php" class="mobile-action-btn">
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
                <h1>Каталог <span class="text-gradient">арт-объектов</span></h1>
            </div>
        </div>
    </section>

    <!-- CATALOG SECTION -->
    <section class="catalog-section">
        <div class="container">
            <div class="catalog-grid">
                <!-- FILTER SIDEBAR -->
                <aside class="filter-sidebar">
                    <div class="filter-header">
                        <h3>Фильтры</h3>
                        <a href="product_catalog.php" class="filter-reset">Сбросить всё</a>
                    </div>

                    <form id="filterForm" method="GET">
                        <!-- Категории -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-tag"></i> Категории
                            </div>
                            <div class="filter-options">
                                <?php foreach ($categories as $cat): ?>
                                <label class="filter-checkbox">
                                    <input type="checkbox" name="category" value="<?php echo $cat['id']; ?>" 
                                           onchange="submitFilter()"
                                           <?php echo $category_filter == $cat['id'] ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Художники с кнопкой "Показать ещё" -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-paint-brush"></i> Художники
                            </div>
                            <div class="filter-options">
                                <?php foreach ($artists as $art): ?>
                                <label class="filter-checkbox">
                                    <input type="checkbox" name="artist" value="<?php echo $art['id']; ?>"
                                           onchange="submitFilter()"
                                           <?php echo $artist_filter == $art['id'] ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($art['fio']); ?></span>
                                </label>
                                <?php endforeach; ?>
                                
                                <?php if ($artists_limit < $total_artists): ?>
                                <a href="<?php echo buildUrl(['artists_limit' => $artists_limit + 3]); ?>" class="show-more-btn">
                                    <i class="fas fa-chevron-down"></i> Показать ещё (<?php echo $total_artists - $artists_limit; ?>)
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Цена -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-ruble-sign"></i> Цена (BYN)
                            </div>
                            <div class="price-inputs">
                                <input type="number" class="price-input" name="min_price" placeholder="от" value="<?php echo $min_price; ?>" onchange="submitFilter()">
                                <input type="number" class="price-input" name="max_price" placeholder="до" value="<?php echo $max_price; ?>" onchange="submitFilter()">
                            </div>
                        </div>

                        <!-- Материалы с кнопкой "Показать ещё" -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-cube"></i> Материал
                            </div>
                            <div class="filter-options">
                                <?php foreach ($materials as $material): ?>
                                <label class="filter-checkbox">
                                    <input type="checkbox" name="material" value="<?php echo $material; ?>"
                                           onchange="submitFilter()"
                                           <?php echo $material_filter == $material ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($material); ?></span>
                                </label>
                                <?php endforeach; ?>
                                
                                <?php if ($materials_limit < $total_materials): ?>
                                <a href="<?php echo buildUrl(['materials_limit' => $materials_limit + 3]); ?>" class="show-more-btn">
                                    <i class="fas fa-chevron-down"></i> Показать ещё (<?php echo $total_materials - $materials_limit; ?>)
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Стили с кнопкой "Показать ещё" -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-palette"></i> Стиль
                            </div>
                            <div class="filter-options">
                                <?php foreach ($styles as $style): ?>
                                <label class="filter-checkbox">
                                    <input type="checkbox" name="style" value="<?php echo $style; ?>"
                                           onchange="submitFilter()"
                                           <?php echo $style_filter == $style ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($style); ?></span>
                                </label>
                                <?php endforeach; ?>
                                
                                <?php if ($styles_limit < $total_styles): ?>
                                <a href="<?php echo buildUrl(['styles_limit' => $styles_limit + 3]); ?>" class="show-more-btn">
                                    <i class="fas fa-chevron-down"></i> Показать ещё (<?php echo $total_styles - $styles_limit; ?>)
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Год создания -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-calendar"></i> Год создания
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <select name="year_from" class="year-select" onchange="submitFilter()">
                                    <option value="0">От</option>
                                    <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year_from == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="year_to" class="year-select" onchange="submitFilter()">
                                    <option value="0">До</option>
                                    <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year_to == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- В наличии -->
                        <div class="filter-group">
                            <label class="filter-checkbox">
                                <input type="checkbox" name="in_stock" value="1" 
                                       onchange="submitFilter()"
                                       <?php echo $in_stock_only ? 'checked' : ''; ?>>
                                <span>Только в наличии</span>
                            </label>
                        </div>

                        <input type="hidden" name="sort" id="sortInput" value="<?php echo $sort; ?>">
                        <input type="hidden" name="search" id="searchInput" value="<?php echo htmlspecialchars($search_query); ?>">
                        <input type="hidden" name="view" id="viewInput" value="<?php echo $view_mode; ?>">
                    </form>
                </aside>

                <!-- CATALOG MAIN -->
                <main class="catalog-main">
                    <div class="catalog-toolbar">
                        <div class="search-box">
                            <form method="GET" id="searchForm">
                                <input type="text" name="search" placeholder="Поиск по названию..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <i class="fas fa-search" onclick="document.getElementById('searchForm').submit()"></i>
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key != 'search'): ?>
                                        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>
                        </div>
                        <div class="toolbar-right">
                            <form method="GET" id="sortForm">
                                <select class="sort-select" name="sort" onchange="this.form.submit()">
                                    <option value="default" <?php echo $sort == 'default' ? 'selected' : ''; ?>>Сортировка</option>
                                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>По цене (возрастание)</option>
                                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>По цене (убывание)</option>
                                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>По новизне</option>
                                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>По популярности</option>
                                    <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>По рейтингу</option>
                                </select>
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key != 'sort'): ?>
                                        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>
                            <div class="view-toggle">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'grid'])); ?>" class="view-btn <?php echo $view_mode == 'grid' ? 'active' : ''; ?>">
                                    <i class="fas fa-th"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'list'])); ?>" class="view-btn <?php echo $view_mode == 'list' ? 'active' : ''; ?>">
                                    <i class="fas fa-list"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px; color: var(--gray-dark);">
                        Найдено товаров: <strong><?php echo $total_products; ?></strong>
                    </div>

                    <div class="<?php echo $view_mode == 'grid' ? 'products-grid' : 'products-list'; ?>" id="productsContainer">
                        <?php if (empty($products)): ?>
                            <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: var(--white); border-radius: 20px;">
                                <i class="fas fa-box-open" style="font-size: 3rem; color: var(--gray-medium); margin-bottom: 20px;"></i>
                                <h3>Товары не найдены</h3>
                                <p style="color: var(--gray-dark);">Попробуйте изменить параметры фильтрации</p>
                                <a href="product_catalog.php" class="btn btn-primary" style="margin-top: 20px;">Сбросить фильтры</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                                <div class="product-image">
                                    <img src="<?php echo $product['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php if ($product['discount_price']): ?>
                                    <span class="product-badge">СКИДКА</span>
                                    <?php elseif ($product['year_created'] == date('Y')): ?>
                                    <span class="product-badge">НОВИНКА</span>
                                    <?php endif; ?>
                                    <div class="product-actions">
                                        <button class="action-btn <?php echo in_array($product['id'], $wishlist) ? 'active' : ''; ?>" 
                                                onclick="toggleFavorite(event, <?php echo $product['id']; ?>)" 
                                                title="В избранное">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <span class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></span>
                                    <h3 class="product-title"><a href="product.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
                                    <a href="artist.php?id=<?php echo $product['artist_id']; ?>" class="product-artist"><?php echo htmlspecialchars($product['artist_name']); ?></a>
                                    
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

                                    <div class="product-meta">
                                        <span><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($product['size'] ?? '—'); ?></span>
                                        <span><i class="fas fa-weight-hanging"></i> <?php echo $product['weight_kg'] ? $product['weight_kg'] . ' кг' : '—'; ?></span>
                                    </div>

                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <button class="btn-add-to-cart" onclick="addToCart(event, <?php echo $product['id']; ?>)">
                                            <i class="fas fa-shopping-bag"></i> В корзину
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-add-to-cart disabled" disabled>
                                            <i class="fas fa-times-circle"></i> Нет в наличии
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </main>
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
                        <li><a href="about_Us.php"><i class="fas fa-chevron-right"></i> О галерее</a></li>
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
        const wishlist = <?php echo json_encode($wishlist); ?>;

        function submitFilter() {
            document.getElementById('filterForm').submit();
        }

        function toggleFavorite(event, productId) {
            event.stopPropagation();
            
            if (!isLoggedIn) {
                showNotification('Войдите, чтобы добавить в избранное', 'info');
                toggleAuthModal();
                return;
            }

            const button = event.currentTarget;
            const wasActive = button.classList.contains('active');
            
            if (wasActive) {
                button.classList.remove('active');
            } else {
                button.classList.add('active');
            }

            fetch('/api/toggle_favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('wishlist-count').textContent = data.total;
                    document.getElementById('mobile-wishlist-count').textContent = data.total;
                    showNotification(data.action === 'added' ? 'Добавлено в избранное' : 'Удалено из избранного', 
                                   data.action === 'added' ? 'success' : 'info');
                } else if (data.error === 'auth_required') {
                    button.classList.toggle('active');
                    showNotification('Войдите, чтобы добавить в избранное', 'info');
                    toggleAuthModal();
                } else {
                    button.classList.toggle('active');
                    showNotification('Ошибка', 'error');
                }
            })
            .catch(error => {
                button.classList.toggle('active');
                showNotification('Ошибка соединения', 'error');
            });
        }

        function toggleWishlist() {
            if (!isLoggedIn) {
                showNotification('Войдите, чтобы просмотреть избранное', 'info');
                toggleAuthModal();
                return;
            }
            window.location.href = '/favorites.php';
        }

        function checkAuthAndNavigate(event, url) {
            if (!isLoggedIn) {
                event.preventDefault();
                showNotification('Войдите, чтобы просмотреть избранное', 'info');
                toggleAuthModal();
            }
        }

        function addToCart(event, productId) {
            event.stopPropagation();

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

        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
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