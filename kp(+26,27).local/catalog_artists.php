<?php
// ============================================
// catalog_artists.php - КАТАЛОГ ХУДОЖНИКОВ
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
            
            header('Location: catalog_artists.php');
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
            
            header('Location: catalog_artists.php');
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ПОЛУЧАЕМ СПИСОК ХУДОЖНИКОВ
// ============================================

// Получаем все страны для фильтра
$countries = $db->query("
    SELECT DISTINCT strana 
    FROM artists 
    WHERE strana IS NOT NULL AND strana != '' 
    ORDER BY strana
")->fetchAll(PDO::FETCH_COLUMN);

// Параметры фильтрации
$country_filter = $_GET['country'] ?? '';
$search_query = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';

// Построение запроса (только художники с активными товарами)
$sql = "
    SELECT 
        a.*,
        COUNT(p.id) as products_count,
        COALESCE(AVG(r.rating), 0) as avg_rating
    FROM artists a
    LEFT JOIN products p ON a.id = p.artist_id AND p.deleted_at IS NULL
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'published'
    WHERE 1=1
";
$params = [];

if ($country_filter) {
    $sql .= " AND a.strana = ?";
    $params[] = $country_filter;
}

if ($search_query) {
    $sql .= " AND (a.fio LIKE ? OR a.bio LIKE ? OR a.style LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " GROUP BY a.id";

// Сортировка
switch ($sort) {
    case 'name_asc':
        $sql .= " ORDER BY a.fio ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY a.fio DESC";
        break;
    case 'works_desc':
        $sql .= " ORDER BY products_count DESC, a.fio ASC";
        break;
    case 'rating_desc':
        $sql .= " ORDER BY avg_rating DESC, products_count DESC";
        break;
    default:
        $sql .= " ORDER BY a.fio ASC";
}

$artists = $db->prepare($sql);
$artists->execute($params);
$artists = $artists->fetchAll();

// ============================================
// ПОЛУЧАЕМ КАТЕГОРИИ ДЛЯ МЕНЮ
// ============================================
$categories = $db->query("
    SELECT * FROM categories ORDER BY name
")->fetchAll();

// ============================================
// ПОЛУЧАЕМ ИЗБРАННОЕ (ХУДОЖНИКИ)
// ============================================
$artist_wishlist = [];
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT artist_id FROM favorites_artists WHERE user_id = ?");
    $stmt->execute([$userId]);
    $artist_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
// ФУНКЦИЯ ДЛЯ ПОСТРОЕНИЯ URL
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
    <title>ARTOBJECT | Художники</title>
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

        /* FILTERS */
        .filters-section {
            padding: 40px 0 20px;
            background: var(--white);
            border-bottom: 1px solid var(--gray-light);
        }

        .filters-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 25px;
            border-radius: 30px;
            background: var(--off-white);
            color: var(--charcoal);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: 2px solid transparent;
            text-decoration: none;
            display: inline-block;
        }

        .filter-tab:hover {
            background: var(--orange-light);
            color: white;
        }

        .filter-tab.active {
            background: var(--gradient-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .filter-sort {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filter-sort select {
            padding: 10px 25px;
            border-radius: 30px;
            border: 2px solid var(--gray-medium);
            background: var(--white);
            font-weight: 600;
            color: var(--charcoal);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .filter-sort select:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .filter-search {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-search input {
            padding: 10px 20px;
            border-radius: 30px;
            border: 2px solid var(--gray-medium);
            background: var(--white);
            width: 250px;
            transition: all var(--transition-fast);
        }

        .filter-search input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: var(--shadow-orange);
        }

        .filter-search button {
            padding: 10px 20px;
            border-radius: 30px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .filter-search button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        /* ARTISTS GRID */
        .artists-section {
            padding: 60px 0 100px;
        }

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

        .artist-image {
            height: 280px;
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

        .artist-favorite {
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
            z-index: 2;
        }

        .artist-favorite:hover {
            background: var(--primary-orange);
            color: white;
            transform: scale(1.1);
        }

        .artist-favorite.active {
            background: var(--primary-orange);
            color: white;
        }

        .artist-info {
            padding: 25px;
            text-align: center;
        }

        .artist-name {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: var(--primary-black);
        }

        .artist-country {
            color: var(--primary-orange);
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }

        .artist-bio {
            color: var(--gray-dark);
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .artist-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px 0;
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

        .view-profile {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 30px;
            background: var(--off-white);
            border-radius: 30px;
            color: var(--charcoal);
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition-fast);
            border: 2px solid transparent;
        }

        .view-profile:hover {
            background: var(--gradient-orange);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .view-profile i {
            transition: transform var(--transition-fast);
        }

        .view-profile:hover i {
            transform: translateX(5px);
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

            .artists-grid {
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

            .page-header h1 {
                font-size: 3rem;
            }

            .filters-grid {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-search input {
                width: 200px;
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

            .section-title {
                font-size: 2.5rem;
            }

            .artists-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                gap: 15px;
            }

            .filter-tabs {
                width: 100%;
                overflow-x: auto;
                padding-bottom: 10px;
            }

            .filter-tabs::-webkit-scrollbar {
                display: none;
            }

            .filter-search {
                width: 100%;
            }

            .filter-search input {
                flex: 1;
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
                                <?php foreach ($categories as $cat): ?>
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
                    <?php foreach ($categories as $cat): ?>
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
                <h1>Художники <span class="text-gradient">галереи</span></h1>
            </div>
        </div>
    </section>

    <!-- FILTERS SECTION -->
    <section class="filters-section">
        <div class="container">
            <div class="filters-grid">
                <div class="filter-tabs">
                    <a href="catalog_artists.php" class="filter-tab <?php echo !$country_filter ? 'active' : ''; ?>">Все</a>
                    <?php foreach ($countries as $country): ?>
                    <a href="?country=<?php echo urlencode($country); ?>" class="filter-tab <?php echo $country_filter == $country ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($country); ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="filter-sort">
                    <form method="GET" id="sortForm">
                        <select name="sort" class="sort-select" onchange="this.form.submit()">
                            <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>По имени (А-Я)</option>
                            <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>По имени (Я-А)</option>
                            <option value="works_desc" <?php echo $sort == 'works_desc' ? 'selected' : ''; ?>>По кол-ву работ</option>
                            <option value="rating_desc" <?php echo $sort == 'rating_desc' ? 'selected' : ''; ?>>По рейтингу</option>
                        </select>
                        <?php if ($country_filter): ?>
                        <input type="hidden" name="country" value="<?php echo htmlspecialchars($country_filter); ?>">
                        <?php endif; ?>
                        <?php if ($search_query): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <?php endif; ?>
                    </form>
                </div>

                <div class="filter-search">
                    <form method="GET" id="searchForm">
                        <input type="text" name="search" placeholder="Поиск художника..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                        <?php if ($country_filter): ?>
                        <input type="hidden" name="country" value="<?php echo htmlspecialchars($country_filter); ?>">
                        <?php endif; ?>
                        <?php if ($sort != 'name_asc'): ?>
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- ARTISTS SECTION -->
    <section class="artists-section">
        <div class="container">
            <?php if (empty($artists)): ?>
            <div style="text-align: center; padding: 60px; background: var(--white); border-radius: 20px;">
                <i class="fas fa-paint-brush" style="font-size: 3rem; color: var(--gray-medium); margin-bottom: 20px;"></i>
                <h3>Художники не найдены</h3>
                <p style="color: var(--gray-dark);">Попробуйте изменить параметры фильтрации</p>
                <a href="catalog_artists.php" class="btn btn-primary" style="margin-top: 20px;">Сбросить фильтры</a>
            </div>
            <?php else: ?>
            <div class="artists-grid">
                <?php foreach ($artists as $artist): ?>
                <div class="artist-card" onclick="window.location.href='artists_page.php?id=<?php echo $artist['id']; ?>'">
                    <div class="artist-image">
                        <img src="<?php echo $artist['photo'] ?? 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'; ?>" 
                             alt="<?php echo htmlspecialchars($artist['fio']); ?>">
                        <button class="artist-favorite <?php echo in_array($artist['id'], $artist_wishlist) ? 'active' : ''; ?>" 
                                onclick="toggleArtistFavorite(event, <?php echo $artist['id']; ?>)" 
                                title="<?php echo in_array($artist['id'], $artist_wishlist) ? 'Удалить из избранного' : 'В избранное'; ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                    <div class="artist-info">
                        <h3 class="artist-name"><?php echo htmlspecialchars($artist['fio']); ?></h3>
                        <span class="artist-country"><?php echo htmlspecialchars($artist['strana'] ?? 'Страна не указана'); ?></span>
                        <p class="artist-bio"><?php echo htmlspecialchars(mb_substr($artist['bio'] ?? $artist['brief_introduction'] ?? '', 0, 150)) . '...'; ?></p>
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
                        <span class="view-profile">
                            Смотреть профиль
                            <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
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
        
        // ============================================
        // ФУНКЦИИ ДЛЯ РАБОТЫ С ИЗБРАННЫМ
        // ============================================
        
        function toggleArtistFavorite(event, artistId) {
            event.stopPropagation();
            event.preventDefault();

            if (!isLoggedIn) {
                showNotification('Войдите, чтобы добавить в избранное', 'info');
                toggleAuthModal();
                return;
            }

            const btn = event.currentTarget;
            const wasActive = btn.classList.contains('active');
            
            if (wasActive) {
                btn.classList.remove('active');
            } else {
                btn.classList.add('active');
            }

            fetch('/api/toggle_favorite_artist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ artist_id: artistId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('wishlist-count').textContent = data.total;
                    document.getElementById('mobile-wishlist-count').textContent = data.total;
                    showNotification(
                        data.action === 'added' ? 'Художник добавлен в избранное' : 'Художник удалён из избранного',
                        data.action === 'added' ? 'success' : 'info'
                    );
                } else if (data.error === 'auth_required') {
                    btn.classList.toggle('active');
                    showNotification('Войдите, чтобы добавить в избранное', 'info');
                    toggleAuthModal();
                } else {
                    btn.classList.toggle('active');
                    showNotification('Ошибка', 'error');
                }
            })
            .catch(error => {
                btn.classList.toggle('active');
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