<?php
// ============================================
// product.php - СТРАНИЦА ТОВАРА (с вопросами и пагинацией)
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
    // ВАЖНО: добавляем эту строку для правильной работы с LIMIT
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
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
            
            header('Location: ' . $_SERVER['REQUEST_URI']);
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
            
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ОБРАБОТКА ОТЗЫВА
// ============================================
if (isset($_POST['submit_review']) && $isLoggedIn) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    
    // Проверяем, покупал ли пользователь этот товар
    $stmt = $db->prepare("
        SELECT oi.id 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
        LIMIT 1
    ");
    $stmt->execute([$userId, $product_id]);
    $order_item = $stmt->fetch();
    
    if (!$order_item) {
        $review_error = 'Вы можете оставить отзыв только на купленные товары';
    } elseif ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        // Проверяем, не оставлял ли уже отзыв
        $stmt = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $review_error = 'Вы уже оставляли отзыв на этот товар';
        } else {
            $stmt = $db->prepare("
                INSERT INTO reviews (product_id, user_id, rating, comment, created_at, status) 
                VALUES (?, ?, ?, ?, NOW(), 'pending')
            ");
            $stmt->execute([$product_id, $userId, $rating, $comment]);
            $review_success = 'Спасибо! Ваш отзыв отправлен на модерацию';
        }
    } else {
        $review_error = 'Заполните все поля корректно';
    }
}

// ============================================
// ПОЛУЧАЕМ ID ТОВАРА ИЗ URL
// ============================================
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id === 0) {
    header('Location: product_catalog.php');
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ТОВАРА
// ============================================
$stmt = $db->prepare("
    SELECT 
        p.*,
        c.name as category_name,
        c.id as category_id,
        a.fio as artist_name,
        a.id as artist_id,
        a.photo as artist_photo,
        a.brief_introduction as artist_brief,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT r.id) as reviews_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN artists a ON p.artist_id = a.id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'published'
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: product_catalog.php');
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДОПОЛНИТЕЛЬНЫЕ ФОТО
// ============================================
$additional_images = $db->prepare("
    SELECT image_path FROM product_images 
    WHERE product_id = ? 
    ORDER BY sort_order
");
$additional_images->execute([$product_id]);
$additional_images = $additional_images->fetchAll(PDO::FETCH_COLUMN);

// Формируем массив всех изображений для слайдера
$all_images = array_merge(
    $product['image'] ? [$product['image']] : [],
    $additional_images
);

if (empty($all_images)) {
    $all_images = ['https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80'];
}
// ============================================
// ПОЛУЧАЕМ ОТЗЫВЫ НА ТОВАР (с пагинацией)
// ============================================
$reviews_page = isset($_GET['reviews_page']) ? (int)$_GET['reviews_page'] : 1;
$reviews_limit = 3;
$reviews_offset = ($reviews_page - 1) * $reviews_limit;

$reviews = $db->prepare("
    SELECT 
        r.*,
        u.fio as user_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.status = 'published'
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");

$reviews->bindValue(1, $product_id, PDO::PARAM_INT);
$reviews->bindValue(2, $reviews_limit, PDO::PARAM_INT);
$reviews->bindValue(3, $reviews_offset, PDO::PARAM_INT);
$reviews->execute();

// ============================================
// ПОЛУЧАЕМ ВОПРОСЫ НА ТОВАР (с пагинацией)
// ============================================
$questions_page = isset($_GET['questions_page']) ? (int)$_GET['questions_page'] : 1;
$questions_limit = 3;
$questions_offset = ($questions_page - 1) * $questions_limit;

$questions = $db->prepare("
    SELECT 
        q.*,
        u.fio as user_name
    FROM product_questions q
    LEFT JOIN users u ON q.user_id = u.id
    WHERE q.product_id = ? AND q.status = 'published'
    ORDER BY q.created_at DESC
    LIMIT ? OFFSET ?
");

$questions->bindValue(1, $product_id, PDO::PARAM_INT);
$questions->bindValue(2, $questions_limit, PDO::PARAM_INT);
$questions->bindValue(3, $questions_offset, PDO::PARAM_INT);
$questions->execute();
// ============================================
// ПОЛУЧАЕМ ОБЩЕЕ КОЛИЧЕСТВО ОТЗЫВОВ ДЛЯ ПАГИНАЦИИ
// ============================================
$stmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = 'published'");
$stmt->execute([$product_id]);
$total_reviews = $stmt->fetchColumn();
$reviews_total_pages = ceil($total_reviews / $reviews_limit);

// ============================================
// ПОЛУЧАЕМ ОБЩЕЕ КОЛИЧЕСТВО ВОПРОСОВ ДЛЯ ПАГИНАЦИИ
// ============================================
$stmt = $db->prepare("SELECT COUNT(*) FROM product_questions WHERE product_id = ? AND status = 'published'");
$stmt->execute([$product_id]);
$total_questions = $stmt->fetchColumn();
$questions_total_pages = ceil($total_questions / $questions_limit);

// ============================================
// ПОЛУЧАЕМ ПОХОЖИЕ ТОВАРЫ
// ============================================
$related = $db->prepare("
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
    WHERE p.category_id = ? AND p.id != ?
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 3
");
$related->execute([$product['category_id'], $product_id]);
$related = $related->fetchAll();

// ============================================
// ПОЛУЧАЕМ КАТЕГОРИИ ДЛЯ МЕНЮ
// ============================================
$categories = $db->query("
    SELECT * FROM categories ORDER BY name
")->fetchAll();

// ============================================
// ПОЛУЧАЕМ ИЗБРАННОЕ ДЛЯ ТОВАРА
// ============================================
$product_in_wishlist = false;
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT id FROM favorites_products WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $product_id]);
    $product_in_wishlist = $stmt->fetch() ? true : false;
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
    <title>ARTOBJECT | <?php echo htmlspecialchars($product['name']); ?></title>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
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

        /* PRODUCT SECTION */
        .product-section {
            padding: 60px 0 80px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 80px;
        }

        .product-gallery {
            position: relative;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            aspect-ratio: 1 / 1;
            max-height: 600px;
        }

        .swiper {
            width: 100%;
            height: 100%;
        }

        .swiper-slide {
            text-align: center;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .swiper-button-next,
        .swiper-button-prev {
            color: var(--primary-orange);
            background: rgba(255, 255, 255, 0.8);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: var(--shadow-md);
        }

        .swiper-button-next:after,
        .swiper-button-prev:after {
            font-size: 1.2rem;
        }

        .swiper-pagination-bullet {
            width: 10px;
            height: 10px;
            background: var(--white);
            opacity: 0.7;
        }

        .swiper-pagination-bullet-active {
            background: var(--primary-orange);
            opacity: 1;
        }

        .product-badge-large {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--gradient-orange);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 700;
            z-index: 10;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-header {
            border-bottom: 2px solid var(--gray-light);
            padding-bottom: 20px;
        }

        .product-category {
            color: var(--primary-orange);
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: block;
        }

        .product-title {
            font-size: 2.5rem;
            line-height: 1.2;
            margin-bottom: 15px;
        }

        .product-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .product-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--gray-dark);
            font-size: 0.9rem;
        }

        .product-meta-item i {
            color: var(--primary-orange);
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .stars {
            color: var(--gold);
            font-size: 1rem;
        }

        .rating-value {
            font-weight: 700;
            color: var(--primary-black);
        }

        .reviews-count {
            color: var(--gray-dark);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .reviews-count:hover {
            color: var(--primary-orange);
        }

        .product-price-block {
            background: var(--off-white);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .price-container {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }

        .current-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-orange);
        }

        .old-price {
            font-size: 1.2rem;
            color: var(--gray-dark);
            text-decoration: line-through;
        }

        .stock-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 15px;
            background: var(--white);
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .stock-status.in-stock {
            color: #2ecc71;
        }

        .stock-status i {
            color: #2ecc71;
        }

        .product-actions-large {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 5px 0;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            background: var(--white);
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            overflow: hidden;
            height: 50px;
            width: 180px;
        }

        .quantity-btn,
        .quantity-input {
            width: 60px;
            height: 50px;
            border: none;
            background: none;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--charcoal);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn {
            background: none;
        }

        .quantity-btn:hover {
            background: var(--primary-orange);
            color: white;
        }

        .quantity-input {
            border-left: 2px solid var(--gray-medium);
            border-right: 2px solid var(--gray-medium);
        }

        .quantity-input:focus {
            outline: none;
        }

        .btn-add-to-cart-large {
            flex: 1;
            padding: 10px 25px;
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
            min-width: 180px;
            height: 50px;
        }

        .btn-add-to-cart-large:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .btn-favorite-large {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--white);
            border: 2px solid var(--gray-medium);
            font-size: 1.2rem;
            color: var(--charcoal);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-favorite-large:hover {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
            transform: scale(1.1);
        }

        .btn-favorite-large.active {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .product-details {
            margin-top: 20px;
        }

        .details-tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid var(--gray-light);
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--gray-dark);
            cursor: pointer;
            position: relative;
            transition: all var(--transition-fast);
        }

        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--gradient-orange);
            transition: width var(--transition-fast);
        }

        .tab-btn:hover {
            color: var(--primary-orange);
        }

        .tab-btn.active {
            color: var(--primary-black);
            font-weight: 700;
        }

        .tab-btn.active::after {
            width: 100%;
        }

        .tab-content {
            display: none;
            padding: 15px 0;
        }

        .tab-content.active {
            display: block;
        }

        .description-text {
            color: var(--gray-dark);
            line-height: 1.7;
            font-size: 1rem;
        }

        .description-text p {
            margin-bottom: 15px;
        }

        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .spec-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--off-white);
            border-radius: 12px;
        }

        .spec-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-orange);
            font-size: 1.1rem;
        }

        .spec-info h4 {
            font-size: 0.9rem;
            margin-bottom: 3px;
            color: var(--gray-dark);
            font-weight: 600;
        }

        .spec-info p {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-black);
        }

        /* ARTIST INFO */
        .artist-section {
            background: var(--off-white);
            padding: 60px 0;
        }

        .artist-card-horizontal {
            display: flex;
            gap: 40px;
            align-items: center;
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow-md);
        }

        .artist-image-small {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-orange);
            flex-shrink: 0;
        }

        .artist-image-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .artist-info-small {
            flex: 1;
        }

        .artist-name-small {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .artist-name-small a {
            text-decoration: none;
            color: inherit;
        }

        .artist-name-small a:hover {
            color: var(--primary-orange);
        }

        .artist-bio-small {
            color: var(--gray-dark);
            margin-bottom: 20px;
            line-height: 1.7;
            font-size: 1rem;
        }

        .btn-view-artist {
            padding: 12px 30px;
            background: transparent;
            border: 2px solid var(--primary-orange);
            color: var(--primary-orange);
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }

        .btn-view-artist:hover {
            background: var(--primary-orange);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        /* REVIEWS SECTION */
        .reviews-section {
            padding: 80px 0;
            background: var(--off-white);
        }

        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .reviews-summary-large {
            display: flex;
            align-items: center;
            gap: 20px;
            background: var(--white);
            padding: 15px 30px;
            border-radius: 50px;
            box-shadow: var(--shadow-sm);
        }

        .summary-rating-large {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-orange);
            line-height: 1;
        }

        .summary-stars-large {
            color: var(--gold);
            font-size: 1.1rem;
        }

        .summary-count-large {
            color: var(--gray-dark);
            font-size: 0.95rem;
        }

        .btn-write-review {
            padding: 12px 30px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-write-review:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 30px;
        }

        .review-item {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-md);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .reviewer {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reviewer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-orange);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .reviewer-info h4 {
            font-size: 1.1rem;
            margin-bottom: 3px;
        }

        .reviewer-info span {
            font-size: 0.85rem;
            color: var(--gray-dark);
        }

        .review-rating {
            color: var(--gold);
            font-size: 1rem;
        }

        .review-text {
            color: var(--gray-dark);
            line-height: 1.6;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        .review-date {
            color: var(--gray-medium);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .review-date i {
            color: var(--primary-orange);
        }

        /* REVIEW FORM */
        .review-form-section {
            margin-top: 40px;
            padding: 30px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
        }

        .review-form-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .rating-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .rating-star {
            font-size: 2rem;
            color: var(--gray-medium);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .rating-star:hover,
        .rating-star.active {
            color: var(--gold);
        }

        .review-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--gray-medium);
            border-radius: 15px;
            font-size: 1rem;
            margin-bottom: 20px;
            resize: vertical;
        }

        .review-textarea:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .btn-submit-review {
            padding: 14px 30px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-submit-review:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .review-note {
            margin-top: 15px;
            font-size: 0.9rem;
            color: var(--gray-dark);
        }

        .review-note i {
            color: var(--primary-orange);
        }

        /* REVIEW MESSAGES */
        .review-message {
            padding: 15px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
        }

        .review-message.success {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .review-message.error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        /* REVIEW PAGINATION */
        .reviews-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--white);
            border: 2px solid var(--gray-medium);
            color: var(--charcoal);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
        }

        .pagination-btn:hover {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .pagination-btn.active {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .pagination-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-text {
            color: var(--gray-dark);
            font-size: 0.9rem;
        }

        /* QUESTIONS SECTION */
        .questions-section {
            padding: 80px 0;
            background: var(--white);
        }

        .questions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .btn-ask-question {
            padding: 12px 30px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-ask-question:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .question-form-section {
            background: var(--off-white);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
        }

        .question-form-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
        }

        .question-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--gray-medium);
            border-radius: 15px;
            font-size: 1rem;
            margin-bottom: 20px;
            resize: vertical;
            font-family: inherit;
        }

        .question-textarea:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: var(--shadow-orange);
        }

        .btn-submit-question {
            padding: 14px 30px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-submit-question:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .questions-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 30px;
        }

        .question-item {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
        }

        .question-header {
            margin-bottom: 15px;
        }

        .question-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .question-author-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--gradient-orange);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .question-author-info h4 {
            font-size: 1rem;
            margin-bottom: 3px;
        }

        .question-author-info span {
            font-size: 0.85rem;
            color: var(--gray-dark);
        }

        .question-text {
            color: var(--gray-dark);
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 0.95rem;
            padding-left: 57px;
        }

        .answer-box {
            background: rgba(255, 90, 48, 0.03);
            border-radius: 15px;
            padding: 20px;
            margin-top: 15px;
            border-left: 3px solid var(--primary-orange);
        }

        .answer-header {
            margin-bottom: 10px;
        }

        .answer-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .answer-author-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--primary-black);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .answer-author-info h4 {
            font-size: 0.95rem;
            margin-bottom: 2px;
            color: var(--primary-orange);
        }

        .answer-author-info span {
            font-size: 0.8rem;
            color: var(--gray-dark);
        }

        .answer-text {
            color: var(--charcoal);
            line-height: 1.6;
            font-size: 0.95rem;
            padding-left: 47px;
        }

        .questions-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .question-message {
            padding: 15px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
        }

        .question-message.success {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .question-message.error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .question-message.info {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .empty-questions {
            text-align: center;
            padding: 50px 20px;
            background: var(--off-white);
            border-radius: 20px;
        }

        .empty-questions i {
            font-size: 3rem;
            color: var(--gray-medium);
            margin-bottom: 20px;
        }

        .empty-questions h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .empty-questions p {
            color: var(--gray-dark);
        }

        /* RELATED PRODUCTS */
        .related-products {
            padding: 60px 0;
            background: var(--off-white);
        }

        .related-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .btn-view-all-works {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: var(--gradient-orange);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 40px;
            text-decoration: none;
            transition: all var(--transition-fast);
            border: none;
            box-shadow: var(--shadow-md);
        }

        .btn-view-all-works:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-orange);
        }

        .btn-view-all-works i {
            transition: transform var(--transition-fast);
        }

        .btn-view-all-works:hover i {
            transform: translateX(5px);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
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
            transition: transform 0.6s ease;
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
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            opacity: 0;
            transform: translateX(15px);
            transition: all var(--transition-fast);
            z-index: 3;
        }

        .product-card:hover .product-actions {
            opacity: 1;
            transform: translateX(0);
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border: none;
            font-size: 1rem;
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

        .product-category-small {
            color: var(--primary-orange);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }

        .product-title-small {
            font-size: 1.3rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .product-artist-small {
            color: var(--gray-dark);
            font-size: 0.95rem;
            margin-bottom: 10px;
            display: block;
            text-decoration: none;
        }

        .product-artist-small:hover {
            color: var(--primary-orange);
        }

        .product-price-small {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-black);
            margin-bottom: 15px;
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

            .product-grid {
                gap: 40px;
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

            .product-grid {
                grid-template-columns: 1fr;
            }

            .product-gallery {
                max-height: 500px;
            }

            .artist-card-horizontal {
                flex-direction: column;
                text-align: center;
                padding: 30px;
            }

            .page-header h1 {
                font-size: 3rem;
            }

            .product-title {
                font-size: 2.2rem;
            }

            .reviews-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .questions-header {
                flex-direction: column;
                align-items: flex-start;
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

            .product-gallery {
                max-height: 400px;
            }

            .product-price-block {
                flex-direction: column;
                align-items: flex-start;
            }

            .product-actions-large {
                flex-direction: column;
            }

            .quantity-selector {
                width: 100%;
            }

            .btn-add-to-cart-large {
                width: 100%;
            }

            .specs-grid {
                grid-template-columns: 1fr;
            }

            .reviews-summary-large {
                width: 100%;
                justify-content: center;
            }

            .btn-write-review {
                width: 100%;
                justify-content: center;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .btn-view-all-works {
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

            .product-title {
                font-size: 1.8rem;
            }

            .modal-content {
                padding: 30px 20px;
            }

            .mobile-menu {
                width: 250px;
                padding: 80px 20px 20px;
            }

            .pagination-btn {
                width: 35px;
                height: 35px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
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
                <h1>Карточка <span class="text-gradient">товара</span></h1>
            </div>
        </div>
    </section>

    <!-- PRODUCT SECTION -->
    <section class="product-section">
        <div class="container">
            <div class="product-grid">
                <!-- Слайдер товара -->
                <div class="product-gallery">
                    <div class="swiper" id="productSwiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($all_images as $image): ?>
                            <div class="swiper-slide">
                                <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($all_images) > 1): ?>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <?php endif; ?>
                        <div class="swiper-pagination"></div>
                    </div>
                    <?php if ($product['discount_price']): ?>
                    <span class="product-badge-large">СКИДКА</span>
                    <?php endif; ?>
                </div>

                <!-- Информация о товаре -->
                <div class="product-info">
                    <div class="product-header">
                        <span class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <div class="product-meta">
                            <?php if ($product['size']): ?>
                            <span class="product-meta-item"><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($product['size']); ?></span>
                            <?php endif; ?>
                            <?php if ($product['weight_kg']): ?>
                            <span class="product-meta-item"><i class="fas fa-weight-hanging"></i> <?php echo $product['weight_kg']; ?> кг</span>
                            <?php endif; ?>
                            <?php if ($product['year_created']): ?>
                            <span class="product-meta-item"><i class="fas fa-calendar-alt"></i> <?php echo $product['year_created']; ?></span>
                            <?php endif; ?>
                            <?php if ($product['material']): ?>
                            <span class="product-meta-item"><i class="fas fa-cube"></i> <?php echo htmlspecialchars($product['material']); ?></span>
                            <?php endif; ?>
                        </div>
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
                            <span class="reviews-count" onclick="scrollToReviews()">(<?php echo $product['reviews_count']; ?> отзывов)</span>
                        </div>
                    </div>

                    <div class="product-price-block">
                        <div class="price-container">
                            <?php if ($product['discount_price']): ?>
                                <span class="current-price"><?php echo number_format($product['discount_price'], 2, '.', ' '); ?> BYN</span>
                                <span class="old-price"><?php echo number_format($product['price'], 2, '.', ' '); ?> BYN</span>
                            <?php else: ?>
                                <span class="current-price"><?php echo number_format($product['price'], 2, '.', ' '); ?> BYN</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($product['stock_quantity'] > 0): ?>
                        <div class="stock-status in-stock">
                            <i class="fas fa-check-circle"></i>
                            <span>В наличии (<?php echo $product['stock_quantity']; ?> шт.)</span>
                        </div>
                        <?php else: ?>
                        <div class="stock-status out-of-stock">
                            <i class="fas fa-times-circle"></i>
                            <span>Нет в наличии</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-actions-large">
                        <?php if ($product['stock_quantity'] > 0): ?>
                        <div class="quantity-selector">
                            <button class="quantity-btn" onclick="decreaseQuantity()">−</button>
                            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                            <button class="quantity-btn" onclick="increaseQuantity()">+</button>
                        </div>
                        <button class="btn-add-to-cart-large" onclick="addToCart(event, <?php echo $product['id']; ?>)">
                            <i class="fas fa-shopping-bag"></i> Добавить в корзину
                        </button>
                        <?php else: ?>
                        <button class="btn-add-to-cart-large" style="opacity: 0.5; cursor: not-allowed;" disabled>
                            <i class="fas fa-times-circle"></i> Нет в наличии
                        </button>
                        <?php endif; ?>
                        <button class="btn-favorite-large <?php echo $product_in_wishlist ? 'active' : ''; ?>" 
                                onclick="toggleFavorite(event, <?php echo $product['id']; ?>)" 
                                title="<?php echo $product_in_wishlist ? 'Удалить из избранного' : 'В избранное'; ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>

                    <div class="product-details">
                        <div class="details-tabs">
                            <button class="tab-btn active" onclick="switchTab(event, 'description')">Описание</button>
                            <button class="tab-btn" onclick="switchTab(event, 'specs')">Характеристики</button>
                            <button class="tab-btn" onclick="switchTab(event, 'delivery')">Доставка</button>
                        </div>

                        <div class="tab-content active" id="description">
                            <div class="description-text">
                                <?php if ($product['opisanie']): ?>
                                    <?php echo nl2br(htmlspecialchars($product['opisanie'])); ?>
                                <?php else: ?>
                                    <p>Описание товара пока не добавлено.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-content" id="specs">
                            <div class="specs-grid">
                                <?php if ($product['size']): ?>
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-ruler-combined"></i>
                                    </div>
                                    <div class="spec-info">
                                        <h4>Размер</h4>
                                        <p><?php echo htmlspecialchars($product['size']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($product['weight_kg']): ?>
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-weight-hanging"></i>
                                    </div>
                                    <div class="spec-info">
                                        <h4>Вес</h4>
                                        <p><?php echo $product['weight_kg']; ?> кг</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($product['material']): ?>
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <div class="spec-info">
                                        <h4>Материал</h4>
                                        <p><?php echo htmlspecialchars($product['material']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($product['year_created']): ?>
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="spec-info">
                                        <h4>Год создания</h4>
                                        <p><?php echo $product['year_created']; ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($product['art_style']): ?>
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-palette"></i>
                                    </div>
                                    <div class="spec-info">
                                        <h4>Стиль</h4>
                                        <p><?php echo htmlspecialchars($product['art_style']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-content" id="delivery">
                            <div class="description-text">
                                <p><i class="fas fa-truck" style="color: var(--primary-orange); margin-right: 10px;"></i> <strong>Доставка по Минску:</strong> 10 BYN, бесплатно при заказе от 500 BYN</p>
                                <p><i class="fas fa-globe" style="color: var(--primary-orange); margin-right: 10px;"></i> <strong>Доставка по Беларуси:</strong> от 40 BYN (зависит от региона)</p>
                                <p><i class="fas fa-clock" style="color: var(--primary-orange); margin-right: 10px;"></i> <strong>Сроки:</strong> 1-3 рабочих дня по Минску, 3-7 дней по Беларуси</p>
                                <p><i class="fas fa-credit-card" style="color: var(--primary-orange); margin-right: 10px;"></i> <strong>Оплата:</strong> картой онлайн, наличными курьеру, безналичный расчет</p>
                                <p><small>*Условия доставки одинаковы для всех товаров галереи</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ARTIST SECTION -->
    <?php if ($product['artist_id']): ?>
    <section class="artist-section">
        <div class="container">
            <div class="artist-card-horizontal">
                <div class="artist-image-small">
                    <img src="<?php echo $product['artist_photo'] ?? 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'; ?>" 
                         alt="<?php echo htmlspecialchars($product['artist_name']); ?>">
                </div>
                <div class="artist-info-small">
                    <h2 class="artist-name-small"><a href="artists_page.php?id=<?php echo $product['artist_id']; ?>"><?php echo htmlspecialchars($product['artist_name']); ?></a></h2>
                    <?php if ($product['artist_brief']): ?>
                    <p class="artist-bio-small"><?php echo htmlspecialchars(mb_substr($product['artist_brief'], 0, 200)) . '...'; ?></p>
                    <?php endif; ?>
                    <a href="artists_page.php?id=<?php echo $product['artist_id']; ?>" class="btn-view-artist">
                        Все работы художника
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- REVIEWS SECTION -->
    <section class="reviews-section" id="reviews">
        <div class="container">
            <div class="reviews-header">
                <div class="reviews-summary-large">
                    <span class="summary-rating-large"><?php echo number_format($product['avg_rating'], 1); ?></span>
                    <div>
                        <div class="summary-stars-large">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= round($product['avg_rating'])): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span class="summary-count-large"><?php echo $product['reviews_count']; ?> отзывов</span>
                    </div>
                </div>
                
                <?php if ($isLoggedIn): ?>
                <button class="btn-write-review" onclick="showReviewForm()">
                    <i class="fas fa-pen"></i> Написать отзыв
                </button>
                <?php else: ?>
                <button class="btn-write-review" onclick="toggleAuthModal()">
                    <i class="fas fa-pen"></i> Войдите, чтобы написать отзыв
                </button>
                <?php endif; ?>
            </div>

            <?php if (isset($review_success)): ?>
            <div class="review-message success">
                <i class="fas fa-check-circle"></i> <?php echo $review_success; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($review_error)): ?>
            <div class="review-message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $review_error; ?>
            </div>
            <?php endif; ?>

            <!-- Форма отзыва (скрыта по умолчанию) -->
            <?php if ($isLoggedIn): ?>
            <div id="reviewForm" style="display: none;">
                <div class="review-form-section">
                    <h3 class="review-form-title">Оставить отзыв</h3>
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        
                        <div class="rating-selector" id="ratingSelector">
                            <span class="rating-star" data-rating="1">★</span>
                            <span class="rating-star" data-rating="2">★</span>
                            <span class="rating-star" data-rating="3">★</span>
                            <span class="rating-star" data-rating="4">★</span>
                            <span class="rating-star" data-rating="5">★</span>
                        </div>
                        <input type="hidden" name="rating" id="selectedRating" value="5">
                        
                        <textarea name="comment" class="review-textarea" rows="5" placeholder="Поделитесь впечатлениями о товаре..." required></textarea>
                        
                        <button type="submit" name="submit_review" class="btn-submit-review">
                            <i class="fas fa-paper-plane"></i> Отправить отзыв
                        </button>
                        
                        <div class="review-note">
                            <i class="fas fa-info-circle"></i> 
                            Отзывы проходят модерацию и появятся после проверки администратором.
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Список отзывов -->
            <?php if (!empty($reviews)): ?>
            <div class="reviews-list">
                <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="reviewer">
                            <div class="reviewer-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="reviewer-info">
                                <h4><?php echo htmlspecialchars($review['user_name']); ?></h4>
                                <span><?php echo date('d.m.Y', strtotime($review['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $review['rating']): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="review-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Пагинация для отзывов -->
            <?php if ($reviews_total_pages > 1): ?>
            <div class="reviews-pagination">
                <?php if ($reviews_page > 1): ?>
                <a href="?id=<?php echo $product_id; ?>&reviews_page=<?php echo $reviews_page - 1; ?>&questions_page=<?php echo $questions_page; ?>#reviews" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php else: ?>
                <span class="pagination-btn disabled">
                    <i class="fas fa-chevron-left"></i>
                </span>
                <?php endif; ?>
                
                <span class="pagination-text">
                    Страница <?php echo $reviews_page; ?> из <?php echo $reviews_total_pages; ?>
                </span>
                
                <?php if ($reviews_page < $reviews_total_pages): ?>
                <a href="?id=<?php echo $product_id; ?>&reviews_page=<?php echo $reviews_page + 1; ?>&questions_page=<?php echo $questions_page; ?>#reviews" class="pagination-btn">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="pagination-btn disabled">
                    <i class="fas fa-chevron-right"></i>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div style="text-align: center; padding: 40px; background: var(--white); border-radius: 20px;">
                <i class="far fa-star" style="font-size: 3rem; color: var(--gray-medium); margin-bottom: 20px;"></i>
                <h3>Пока нет отзывов</h3>
                <p style="color: var(--gray-dark);">Будьте первым, кто оставит отзыв об этом товаре!</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- QUESTIONS SECTION -->
    <section class="questions-section" id="questions">
        <div class="container">
            <div class="questions-header">
                <h2 class="section-title">Вопросы <span class="text-gradient">и ответы</span></h2>
                
                <?php if ($isLoggedIn): ?>
                <button class="btn-ask-question" onclick="showQuestionForm()">
                    <i class="fas fa-question"></i> Задать вопрос
                </button>
                <?php else: ?>
                <button class="btn-ask-question" onclick="toggleAuthModal()">
                    <i class="fas fa-question"></i> Войдите, чтобы задать вопрос
                </button>
                <?php endif; ?>
            </div>

            <!-- Форма вопроса (скрыта по умолчанию) -->
            <div id="questionForm" style="display: none;">
                <div class="question-form-section">
                    <h3 class="question-form-title">Задать вопрос</h3>
                    <form id="askQuestionForm" onsubmit="submitQuestion(event)">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <textarea name="question" id="question_text" class="question-textarea" rows="4" placeholder="Введите ваш вопрос о товаре..." required></textarea>
                        <button type="submit" class="btn-submit-question">
                            <i class="fas fa-paper-plane"></i> Отправить вопрос
                        </button>
                    </form>
                </div>
            </div>

            <!-- Сообщение после отправки вопроса -->
            <div id="questionMessage" style="display: none;"></div>

            <!-- Список вопросов -->
            <?php if (!empty($questions)): ?>
            <div class="questions-list" id="questions-list">
                <?php foreach ($questions as $question): ?>
                <div class="question-item" data-id="<?php echo $question['id']; ?>">
                    <div class="question-header">
                        <div class="question-author">
                            <div class="question-author-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="question-author-info">
                                <h4><?php echo htmlspecialchars($question['user_name'] ?? 'Пользователь'); ?></h4>
                                <span><?php echo date('d.m.Y', strtotime($question['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="question-text">
                        <p><?php echo nl2br(htmlspecialchars($question['question'])); ?></p>
                    </div>
                    
                    <?php if ($question['answer']): ?>
                    <div class="answer-box">
                        <div class="answer-header">
                            <div class="answer-author">
                                <div class="answer-author-avatar">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="answer-author-info">
                                    <h4>Администратор</h4>
                                    <span><?php echo date('d.m.Y', strtotime($question['answered_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="answer-text">
                            <p><?php echo nl2br(htmlspecialchars($question['answer'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Пагинация для вопросов -->
            <?php if ($questions_total_pages > 1): ?>
            <div class="questions-pagination">
                <?php if ($questions_page > 1): ?>
                <a href="?id=<?php echo $product_id; ?>&reviews_page=<?php echo $reviews_page; ?>&questions_page=<?php echo $questions_page - 1; ?>#questions" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php else: ?>
                <span class="pagination-btn disabled">
                    <i class="fas fa-chevron-left"></i>
                </span>
                <?php endif; ?>
                
                <span class="pagination-text">
                    Страница <?php echo $questions_page; ?> из <?php echo $questions_total_pages; ?>
                </span>
                
                <?php if ($questions_page < $questions_total_pages): ?>
                <a href="?id=<?php echo $product_id; ?>&reviews_page=<?php echo $reviews_page; ?>&questions_page=<?php echo $questions_page + 1; ?>#questions" class="pagination-btn">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="pagination-btn disabled">
                    <i class="fas fa-chevron-right"></i>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-questions">
                <i class="far fa-question-circle"></i>
                <h3>Пока нет вопросов</h3>
                <p>Будьте первым, кто задаст вопрос об этом товаре!</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- RELATED PRODUCTS -->
    <?php if (!empty($related)): ?>
    <section class="related-products">
        <div class="container">
            <div class="related-header">
                <h2 class="section-title">Похожие <span class="text-gradient">товары</span></h2>
                <a href="product_catalog.php?category=<?php echo $product['category_id']; ?>" class="btn-view-all-works">
                    Все товары категории
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="products-grid">
                <?php foreach ($related as $item): ?>
                <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $item['id']; ?>'">
                    <div class="product-image">
                        <img src="<?php echo $item['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80'; ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php if ($item['discount_price']): ?>
                        <span class="product-badge">СКИДКА</span>
                        <?php endif; ?>
                        <div class="product-actions">
                            <button class="action-btn" onclick="toggleFavorite(event, <?php echo $item['id']; ?>)" title="В избранное">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
                        <span class="product-category-small"><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></span>
                        <h3 class="product-title-small"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <a href="artists_page.php?id=<?php echo $item['artist_id']; ?>" class="product-artist-small"><?php echo htmlspecialchars($item['artist_name']); ?></a>
                        <div class="product-price-small">
                            <?php if ($item['discount_price']): ?>
                                <?php echo number_format($item['discount_price'], 2, '.', ' '); ?> BYN
                            <?php else: ?>
                                <?php echo number_format($item['price'], 2, '.', ' '); ?> BYN
                            <?php endif; ?>
                        </div>
                        <?php if ($item['stock_quantity'] > 0): ?>
                        <button class="btn-add-to-cart-small" onclick="addToCart(event, <?php echo $item['id']; ?>)">
                            <i class="fas fa-shopping-bag"></i> В корзину
                        </button>
                        <?php else: ?>
                        <button class="btn-add-to-cart-small" style="opacity: 0.5; cursor: not-allowed;" disabled>
                            <i class="fas fa-times-circle"></i> Нет в наличии
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
        const productId = <?php echo $product['id']; ?>;
        
        // ============================================
        // ИНИЦИАЛИЗАЦИЯ СЛАЙДЕРА
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            new Swiper('#productSwiper', {
                loop: true,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
            });

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

        // ============================================
        // КОЛИЧЕСТВО ТОВАРА
        // ============================================
        function increaseQuantity() {
            const input = document.getElementById('quantity');
            const max = parseInt(input.getAttribute('max') || 10);
            let val = parseInt(input.value) || 1;
            if (val < max) input.value = val + 1;
        }

        function decreaseQuantity() {
            const input = document.getElementById('quantity');
            let val = parseInt(input.value) || 1;
            if (val > 1) input.value = val - 1;
        }

        // ============================================
        // ПЕРЕКЛЮЧЕНИЕ ВКЛАДОК
        // ============================================
        function switchTab(event, tabId) {
            event.preventDefault();
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        // ============================================
        // СКРОЛЛ К ОТЗЫВАМ
        // ============================================
        function scrollToReviews() {
            document.getElementById('reviews').scrollIntoView({ behavior: 'smooth' });
        }

        // ============================================
        // ПОКАЗ ФОРМЫ ОТЗЫВА
        // ============================================
        function showReviewForm() {
            const form = document.getElementById('reviewForm');
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        }

        // ============================================
        // ВЫБОР РЕЙТИНГА
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating-star');
            const ratingInput = document.getElementById('selectedRating');
            
            if (stars.length) {
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.getAttribute('data-rating');
                        ratingInput.value = rating;
                        
                        stars.forEach((s, index) => {
                            if (index < rating) {
                                s.classList.add('active');
                            } else {
                                s.classList.remove('active');
                            }
                        });
                    });
                });
                
                // По умолчанию выделяем 5 звёзд
                stars[4].classList.add('active');
            }
        });

        // ============================================
        // ФУНКЦИИ ДЛЯ РАБОТЫ С ИЗБРАННЫМ
        // ============================================
        function toggleFavorite(event, productId) {
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
                        data.action === 'added' ? 'Товар добавлен в избранное' : 'Товар удалён из избранного',
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
            window.location.href = 'favorites.php';
        }

        // ============================================
        // ФУНКЦИИ ДЛЯ РАБОТЫ С КОРЗИНОЙ
        // ============================================
        function addToCart(event, productId) {
            event.stopPropagation();
            event.preventDefault();

            const quantity = document.getElementById('quantity')?.value || 1;

            fetch('/api/add_to_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    product_id: productId,
                    quantity: parseInt(quantity)
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
        // ФУНКЦИИ ДЛЯ РАБОТЫ С ВОПРОСАМИ
        // ============================================
        function showQuestionForm() {
            const form = document.getElementById('questionForm');
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        }

        function submitQuestion(event) {
            event.preventDefault();
            
            const form = document.getElementById('askQuestionForm');
            const formData = new FormData(form);
            const question = document.getElementById('question_text').value.trim();
            
            if (!question) {
                showQuestionMessage('Введите вопрос', 'error');
                return;
            }
            
            if (question.length < 10) {
                showQuestionMessage('Вопрос должен содержать не менее 10 символов', 'error');
                return;
            }
            
            fetch('/api/ask_question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    product_id: formData.get('product_id'),
                    question: question 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('question_text').value = '';
                    document.getElementById('questionForm').style.display = 'none';
                    showQuestionMessage('Ваш вопрос отправлен на модерацию. После проверки он появится на сайте.', 'success');
                } else if (data.error === 'auth_required') {
                    showQuestionMessage('Войдите, чтобы задать вопрос', 'error');
                    toggleAuthModal();
                } else if (data.error === 'question_too_short') {
                    showQuestionMessage('Вопрос должен содержать не менее 10 символов', 'error');
                } else {
                    showQuestionMessage('Ошибка при отправке вопроса', 'error');
                }
            })
            .catch(error => {
                showQuestionMessage('Ошибка соединения', 'error');
            });
        }

        function showQuestionMessage(message, type = 'success') {
            const msgDiv = document.getElementById('questionMessage');
            msgDiv.style.display = 'block';
            msgDiv.className = 'question-message ' + type;
            msgDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            
            setTimeout(() => {
                msgDiv.style.display = 'none';
            }, 5000);
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