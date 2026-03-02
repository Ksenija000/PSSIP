<?php
// ============================================
// index.php - ГЛАВНАЯ СТРАНИЦА
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
            
            header('Location: index.php');
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
            
            header('Location: index.php');
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ ГЛАВНОЙ (ТОЛЬКО НЕУДАЛЁННЫЕ ТОВАРЫ)
// ============================================

// 1. Категории с количеством товаров (только активные, неудалённые)
$categories = $db->query("
    SELECT 
        c.*,
        COUNT(p.id) as products_count,
        (
            SELECT p2.image 
            FROM products p2 
            WHERE p2.category_id = c.id AND p2.image IS NOT NULL AND p2.deleted_at IS NULL
            ORDER BY p2.id
            LIMIT 1
        ) as category_image
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

// 2. Новые поступления (последние 5 товаров, только активные, неудалённые)
$new_products = $db->query("
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
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 5
")->fetchAll();

// 3. Художники (первые 3 для главной, счётчик работ только активных)
$featured_artists = $db->query("
    SELECT 
        a.*,
        COUNT(p.id) as products_count,
        COALESCE(AVG(r.rating), 0) as avg_rating
    FROM artists a
    LEFT JOIN products p ON a.id = p.artist_id AND p.deleted_at IS NULL
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'published'
    GROUP BY a.id
    ORDER BY a.id
    LIMIT 3
")->fetchAll();

// 4. Последние отзывы (3 для главной, только на активные товары)
$latest_reviews = $db->query("
    SELECT 
        r.*,
        u.fio as user_name,
        p.name as product_name,
        p.id as product_id
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.status = 'published' AND p.deleted_at IS NULL
    ORDER BY r.created_at DESC
    LIMIT 3
")->fetchAll();

// 5. Статистика для счетчиков (только активные товары)
$stats = [];
$stats['products'] = $db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
$stats['artists'] = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
$stats['countries'] = $db->query("SELECT COUNT(DISTINCT strana) FROM artists WHERE strana IS NOT NULL AND strana != ''")->fetchColumn();
$stats['categories'] = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// 6. Получаем корзину для счетчика
$cart_count = 0;
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cart_count = (int)$stmt->fetchColumn();
} else {
    $cart_count = array_sum($_SESSION['guest_cart'] ?? []);
}

// 7. Получаем избранное для счетчика
$wishlist_count = 0;
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM favorites_products WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wishlist_count += (int)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM favorites_artists WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wishlist_count += (int)$stmt->fetchColumn();
}

// 8. Получаем количество подписчиков для статистики
$subscribers_count = $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1")->fetchColumn();

// ============================================
// ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ ИКОНКИ КАТЕГОРИИ
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
    <title>ARTOBJECT | Галерея современного искусства</title>
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

        .password_password_continer{
display: flex;
align-items: center;
justify-content: center;
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

        /* HERO SECTION */
        .hero {
            position: relative;
            height: 100vh;
            min-height: 800px;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin-bottom: 100px;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                linear-gradient(45deg, rgba(10, 10, 10, 0.9) 0%, rgba(44, 44, 44, 0.8) 100%),
                url('https://images.unsplash.com/photo-1578301978693-85fa9c0320b9?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            z-index: -2;
        }

        .hero-particles {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: var(--primary-orange);
            border-radius: 50%;
            opacity: 0.3;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }

            100% {
                transform: translateY(-1000px) rotate(720deg);
            }
        }

        .hero-content {
            margin-top: 30px;
            position: relative;
            z-index: 2;
            max-width: 800px;
            color: white;
        }

        .hero-tag {
            display: inline-block;
            background: var(--gradient-orange);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 30px;
            letter-spacing: 1px;
        }

        .hero-title {
            font-size: 5rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 25px;
            letter-spacing: -1px;
        }

        .hero-title span {
            background: linear-gradient(135deg, #FFD700 0%, #FF5A30 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 40px;
            max-width: 600px;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
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
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: white;
            color: var(--primary-black);
            border-color: white;
        }

        .hero-scroll {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 0.9rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            opacity: 0.7;
            animation: bounce 2s infinite;
            cursor: pointer;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateX(-50%) translateY(0);
            }

            50% {
                transform: translateX(-50%) translateY(-10px);
            }
        }

        .hero-scroll i {
            font-size: 1.5rem;
        }

        /* STATS SECTION */
        .stats-section {
            background: var(--gradient-dark);
            color: white;
            padding: 80px 0;
            margin-bottom: 100px;
            border-radius: 0 0 40px 40px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }

        .stat-card {
            text-align: center;
            padding: 30px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all var(--transition-fast);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-orange);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--primary-orange);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #FFD700 0%, #FF5A30 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* CATEGORIES */
        .categories {
            padding: 100px 0;
            background: var(--off-white);
            position: relative;
            overflow: hidden;
        }

        .categories::before {
            content: '';
            position: absolute;
            top: -200px;
            right: -200px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, var(--orange-glow) 0%, transparent 70%);
            z-index: 0;
        }

        .categories .section-title,
        .categories .section-subtitle {
            position: relative;
            z-index: 1;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 60px;
            position: relative;
            z-index: 1;
        }

        .category-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-slow);
            position: relative;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .category-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .category-image {
            height: 250px;
            position: relative;
            overflow: hidden;
        }

        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .category-card:hover .category-image img {
            transform: scale(1.1);
        }

        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(10, 10, 10, 0.9));
            padding: 30px;
            color: white;
        }

        .category-overlay h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .category-count {
            color: var(--primary-orange);
            font-weight: 600;
            margin-bottom: 15px;
            display: block;
        }

        .category-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-weight: 600;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            transition: all var(--transition-fast);
            text-decoration: none;
        }

        .category-link:hover {
            background: var(--primary-orange);
            gap: 12px;
        }

        /* NEW ARRIVALS */
        .new-arrivals {
            padding: 100px 0;
            position: relative;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 50px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .section-controls {
            display: flex;
            gap: 15px;
        }

        .nav-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--off-white);
            border: 2px solid var(--gray-medium);
            font-size: 1.2rem;
            color: var(--charcoal);
            transition: all var(--transition-fast);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-btn:hover {
            background: var(--primary-orange);
            border-color: var(--primary-orange);
            color: white;
            transform: scale(1.1);
        }

        .products-slider {
            display: flex;
            gap: 30px;
            overflow-x: auto;
            padding: 20px 10px 40px;
            scrollbar-width: none;
            scroll-behavior: smooth;
        }

        .products-slider::-webkit-scrollbar {
            display: none;
        }

        .product-card {
            flex: 0 0 350px;
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

        .product-image {
            height: 280px;
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
            font-size: 1.1rem;
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
            padding: 25px;
        }

        .product-title {
            font-size: 1.3rem;
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
            font-size: 0.95rem;
            margin-bottom: 15px;
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
            margin-bottom: 15px;
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
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-black);
            margin-bottom: 20px;
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
            font-size: 0.9rem;
            margin-bottom: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-light);
        }

        .btn-add-to-cart-small {
            width: 100%;
            padding: 12px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all var(--transition-fast);
            cursor: pointer;
        }

        .btn-add-to-cart-small:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .btn-add-to-cart-small:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-add-to-cart-small:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* FEATURED ARTISTS */
        .featured-artists {
            padding: 100px 0;
            background: var(--gradient-dark);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .featured-artists::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 30% 30%, rgba(255, 90, 48, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(255, 215, 0, 0.05) 0%, transparent 50%);
        }

        .artists-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 60px;
            position: relative;
            z-index: 1;
        }

        .artist-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all var(--transition-slow);
            text-align: center;
        }

        .artist-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-orange);
        }

        .artist-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-orange);
            margin-bottom: 20px;
            transition: all var(--transition-slow);
        }

        .artist-card:hover .artist-avatar {
            transform: scale(1.1);
            border-color: var(--gold);
        }

        .artist-name {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .artist-country {
            color: var(--primary-orange);
            font-weight: 600;
            margin-bottom: 15px;
            display: block;
        }

        .artist-bio {
            opacity: 0.8;
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .artist-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .artist-stat {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--gold);
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        /* TESTIMONIALS */
        .testimonials {
            padding: 100px 0;
            background: var(--off-white);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 60px;
        }

        .testimonial-card {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            position: relative;
            transition: all var(--transition-fast);
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .testimonial-rating {
            color: var(--gold);
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .testimonial-text {
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 20px;
            color: var(--gray-dark);
            position: relative;
            z-index: 1;
        }

        .testimonial-author {
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 5px;
        }

        .testimonial-product {
            color: var(--primary-orange);
            font-size: 0.9rem;
        }

        /* CTA SECTION */
        .cta-section {
            padding: 120px 0;
            background: var(--gradient-dark);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: -100px;
            left: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 90, 48, 0.2) 0%, transparent 70%);
        }

        .cta-title {
            font-size: 3.5rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .cta-description {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 40px;
            position: relative;
            z-index: 1;
        }

        .cta-form {
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .cta-input {
            width: 100%;
            padding: 18px 25px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.05);
            color: white;
            font-size: 1rem;
            transition: all var(--transition-fast);
        }

        .cta-input:focus {
            outline: none;
            border-color: var(--primary-orange);
            background: rgba(255, 255, 255, 0.1);
        }

        .cta-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
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
            .hero-title {
                font-size: 4rem;
            }

            .section-title {
                font-size: 3rem;
            }

            .header-right {
                gap: 15px;
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

            .hero-title {
                font-size: 3.5rem;
            }

            .products-slider {
                gap: 20px;
            }

            .product-card {
                flex: 0 0 300px;
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

            .hero {
                height: auto;
                min-height: 600px;
                padding: 120px 0 60px;
            }

            .hero-title {
                font-size: 2.8rem;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .section-title {
                font-size: 2.5rem;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .testimonials-grid {
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

            .hero-title {
                font-size: 2.2rem;
            }

            .cta-title {
                font-size: 2.5rem;
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
                                    <span style="margin-left: auto; font-size: 0.8rem; opacity: 0.7;"><?php echo $cat['products_count']; ?></span>
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
                        <span style="margin-left: auto; font-size: 0.8rem; opacity: 0.7;"><?php echo $cat['products_count']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </li>
            <li><a href="catalog_artists.php"><i class="fas fa-paint-brush"></i> Художники</a></li>
            <li><a href="about_Us.php"><i class="fas fa-info-circle"></i> О нас</a></li>
        </ul>

        <div class="mobile-actions">
            <a href="/wishlist.php" class="mobile-action-btn" onclick="checkAuthAndNavigate(event, '/wishlist.php')">
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

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-background"></div>
        <div class="hero-particles" id="particles"></div>

        <div class="container">
            <div class="hero-content">
                <div class="hero-tag">ГАЛЕРЕЯ СОВРЕМЕННОГО ИСКУССТВА</div>
                <h1 class="hero-title">
                    Где <span>искусство</span><br>
                    встречается с <span>душой</span>
                </h1>
                <p class="hero-description">
                    Откройте для себя мир современных мастеров. В нашей коллекции 
                    <strong><?php echo $stats['products']; ?></strong> уникальных работ от 
                    <strong><?php echo $stats['artists']; ?></strong> художников из 
                    <strong><?php echo $stats['countries']; ?></strong> стран мира.
                </p>
                <div class="hero-buttons">
                    <a href="product_catalog.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        Исследовать коллекцию
                    </a>
                    <a href="catalog_artists.php" class="btn btn-secondary">
                        <i class="fas fa-users"></i>
                        Знакомство с художниками
                    </a>
                </div>
            </div>
        </div>

        <div class="hero-scroll">
            <span>Откройте для себя больше</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- STATS SECTION -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🎨</div>
                    <div class="stat-number" data-count="<?php echo $stats['products']; ?>"><?php echo $stats['products']; ?></div>
                    <div class="stat-label">Уникальных работ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👨‍🎨</div>
                    <div class="stat-number" data-count="<?php echo $stats['artists']; ?>"><?php echo $stats['artists']; ?></div>
                    <div class="stat-label">Талантливых художников</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🌍</div>
                    <div class="stat-number" data-count="<?php echo $stats['countries']; ?>"><?php echo $stats['countries']; ?></div>
                    <div class="stat-label">Страны</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-number" data-count="<?php echo $stats['categories']; ?>"><?php echo $stats['categories']; ?></div>
                    <div class="stat-label">Категорий</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CATEGORIES SECTION -->
    <section class="categories">
        <div class="container">
            <h2 class="section-title">Направления <span class="text-gradient">искусства</span></h2>
            <p class="section-subtitle">
                Исследуйте разнообразие художественных форм и техник. Каждое направление открывает
                новые грани прекрасного.
            </p>

            <div class="categories-grid">
                <?php foreach ($categories as $cat): ?>
                <a href="product_catalog.php?category=<?php echo $cat['id']; ?>" class="category-card">
                    <div class="category-image">
                        <img src="<?php 
                            if (!empty($cat['category_image'])) {
                                echo htmlspecialchars($cat['category_image']);
                            } else {
                                $fallback_images = [
                                    1 => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80',
                                    2 => 'https://images.unsplash.com/photo-1563089145-599997674d42?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80',
                                    3 => 'https://images.unsplash.com/photo-1542744095-fcf48d80b0fd?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80',
                                    4 => 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80',
                                    5 => 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80'
                                ];
                                echo $fallback_images[$cat['id']] ?? $fallback_images[1];
                            }
                        ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                    </div>
                    <div class="category-overlay">
                        <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <span class="category-count"><?php echo $cat['products_count']; ?> работ</span>
                        <span class="category-link">
                            Исследовать
                            <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- NEW ARRIVALS -->
    <section class="new-arrivals">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Новые <span class="text-gradient">поступления</span></h2>
                    <p class="section-subtitle">Свежие работы от современных мастеров. Успейте приобрести эксклюзивные
                        произведения.</p>
                </div>
                <div class="section-controls">
                    <button class="nav-btn" onclick="scrollProducts(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="nav-btn" onclick="scrollProducts(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <div class="products-slider" id="productsSlider">
                <?php foreach ($new_products as $product): ?>
                <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                    <?php if ($product['discount_price']): ?>
                    <span class="product-badge">СКИДКА</span>
                    <?php elseif ($product['year_created'] == date('Y')): ?>
                    <span class="product-badge">НОВИНКА</span>
                    <?php endif; ?>
                    
                    <div class="product-image">
                        <img src="<?php echo $product['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80'; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="product-actions">
                            <button class="action-btn" onclick="addToWishlist(event, <?php echo $product['id']; ?>)" title="В избранное">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
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
                            <button class="btn-add-to-cart-small" onclick="addToCart(event, <?php echo $product['id']; ?>)">
                                <i class="fas fa-shopping-bag"></i> В корзину
                            </button>
                        <?php else: ?>
                            <button class="btn-add-to-cart-small" disabled>
                                <i class="fas fa-times-circle"></i> Нет в наличии
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FEATURED ARTISTS -->
    <section class="featured-artists">
        <div class="container">
            <h2 class="section-title">Художники <span class="text-gradient">галереи</span></h2>
            <p class="section-subtitle">
                Знакомьтесь с мастерами, чьи работы покоряют сердца ценителей искусства.
            </p>

            <div class="artists-grid">
                <?php foreach ($featured_artists as $artist): ?>
                <div class="artist-card">
                    <img src="<?php echo $artist['photo'] ?? 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'; ?>" 
                         alt="<?php echo htmlspecialchars($artist['fio']); ?>" class="artist-avatar">
                    <h3 class="artist-name"><?php echo htmlspecialchars($artist['fio']); ?></h3>
                    <span class="artist-country"><?php echo htmlspecialchars($artist['strana'] ?? 'Страна не указана'); ?></span>
                    <p class="artist-bio"><?php echo htmlspecialchars(mb_substr($artist['brief_introduction'] ?? $artist['bio'] ?? '', 0, 100)) . '...'; ?></p>
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
                    <button class="btn btn-secondary" style="width: 100%" onclick="window.location.href='artist.php?id=<?php echo $artist['id']; ?>'">
                        <i class="fas fa-user-circle"></i>
                        Портфолио
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <?php if (!empty($latest_reviews)): ?>
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">Отзывы <span class="text-gradient">на товары</span></h2>
            <p class="section-subtitle">
                Что говорят коллекционеры о приобретённых произведениях искусства.
            </p>

            <div class="testimonials-grid">
                <?php foreach ($latest_reviews as $review): ?>
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $review['rating']): ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <p class="testimonial-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                    <div class="testimonial-author"><?php echo htmlspecialchars($review['user_name']); ?></div>
                    <div class="testimonial-product">Товар: <?php echo htmlspecialchars($review['product_name']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA SECTION -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">Готовы найти своё <span class="text-gradient">искусство</span>?</h2>
            <p class="cta-description">
                Подпишитесь на нашу рассылку и будьте в курсе новых поступлений, эксклюзивных предложений
                и событий в мире искусства. Уже <strong><?php echo $subscribers_count; ?></strong> человек с нами!
            </p>
            <div class="cta-form">
                <?php if ($isLoggedIn): ?>
                    <div style="margin-bottom: 15px; color: rgba(255,255,255,0.8);">
                        <i class="fas fa-envelope"></i> 
                        <?php echo htmlspecialchars($userEmail); ?>
                    </div>
                    <button class="btn btn-primary" style="width: 100%;" onclick="subscribeNewsletter()">
                        <i class="fas fa-paper-plane"></i>
                        Подписаться на новости
                    </button>
                <?php else: ?>
                    <input type="email" class="cta-input" id="subscribeEmail" placeholder="Ваш email" required>
                    <button class="btn btn-primary" style="margin-top: 20px; width: 100%;" onclick="subscribeNewsletter()">
                        <i class="fas fa-paper-plane"></i>
                        Подписаться на новости
                    </button>
                <?php endif; ?>
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
        
        // ============================================
        // ФУНКЦИИ ДЛЯ РАБОТЫ С ИЗБРАННЫМ И КОРЗИНОЙ
        // ============================================
        
        function addToWishlist(event, productId) {
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
                    showNotification(
                        data.action === 'added' ? 'Добавлено в избранное' : 'Удалено из избранного',
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
        // ПОДПИСКА НА НОВОСТИ
        // ============================================
        
        function subscribeNewsletter() {
            let email = '';
            
            if (!isLoggedIn) {
                email = document.getElementById('subscribeEmail')?.value.trim() || '';
                
                if (!email) {
                    showNotification('Введите email', 'error');
                    return;
                }
                
                if (!email.includes('@') || !email.includes('.')) {
                    showNotification('Введите корректный email', 'error');
                    return;
                }
            }
            
            fetch('/api/subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.message === 'subscribed') {
                        showNotification('Спасибо за подписку! Теперь вы будете получать наши новости.', 'success');
                    } else if (data.message === 'subscription_reactivated') {
                        showNotification('Вы снова подписались на нашу рассылку!', 'success');
                    }
                    if (!isLoggedIn) {
                        document.getElementById('subscribeEmail').value = '';
                    }
                } else {
                    if (data.error === 'already_subscribed') {
                        showNotification('Этот email уже подписан на рассылку', 'info');
                    } else if (data.error === 'invalid_email') {
                        showNotification('Некорректный email', 'error');
                    } else {
                        showNotification('Ошибка при подписке', 'error');
                    }
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

        // ============================================
        // ОСТАЛЬНЫЕ ФУНКЦИИ
        // ============================================
        
        function scrollProducts(direction) {
            const slider = document.getElementById('productsSlider');
            const cardWidth = 350 + 30;
            const scrollAmount = direction * cardWidth;
            slider.scrollBy({ left: scrollAmount, behavior: 'smooth' });
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

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация статистики
            const stats = document.querySelectorAll('.stat-number');
            stats.forEach(stat => {
                const target = parseInt(stat.getAttribute('data-count'));
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(current);
                }, 16);
            });

            // Создание частиц
            const particlesContainer = document.getElementById('particles');
            if (particlesContainer) {
                for (let i = 0; i < 30; i++) {
                    const particle = document.createElement('div');
                    particle.classList.add('particle');
                    particle.style.width = `${Math.random() * 10 + 5}px`;
                    particle.style.height = particle.style.width;
                    particle.style.left = `${Math.random() * 100}%`;
                    particle.style.animationDelay = `${Math.random() * 20}s`;
                    particle.style.animationDuration = `${Math.random() * 10 + 20}s`;
                    particlesContainer.appendChild(particle);
                }
            }

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

        // Скролл по клику на hero-scroll
        document.querySelector('.hero-scroll')?.addEventListener('click', function() {
            window.scrollTo({
                top: window.innerHeight,
                behavior: 'smooth'
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