<?php
// ============================================
// profile.php - ЛИЧНЫЙ КАБИНЕТ ПОЛЬЗОВАТЕЛЯ (с вопросами)
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
$userPhone = '';
$userCity = '';
$userAddress = '';
$userRole = '';

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
            
            header('Location: profile.php');
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
            
            header('Location: profile.php');
            exit;
        }
    } else {
        $register_error = 'Заполните все поля';
    }
}

// ============================================
// ОБРАБОТКА ОБНОВЛЕНИЯ ПРОФИЛЯ
// ============================================
if (isset($_POST['update_profile'])) {
    $fio = trim($_POST['fio'] ?? $userName);
    $email = trim($_POST['email'] ?? $userEmail);
    $phone = trim($_POST['phone'] ?? $userPhone);
    $city = trim($_POST['city'] ?? $userCity);
    $address = trim($_POST['address'] ?? $userAddress);
    
    $update_errors = [];
    
    // Проверка email на уникальность (если изменился)
    if ($email != $userEmail) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $update_errors['email'] = 'Этот email уже используется';
        }
    }
    
    // Проверка телефона
    if (empty($phone)) {
        $update_errors['phone'] = 'Телефон обязателен для заполнения';
    }
    
    if (empty($update_errors)) {
        $stmt = $db->prepare("UPDATE users SET fio = ?, email = ?, phone = ?, city = ?, address = ? WHERE id = ?");
        $stmt->execute([$fio, $email, $phone, $city, $address, $userId]);
        
        $_SESSION['user_name'] = $fio;
        $_SESSION['user_email'] = $email;
        
        $update_success = 'Данные успешно обновлены';
        
        $userName = $fio;
        $userEmail = $email;
        $userPhone = $phone;
        $userCity = $city;
        $userAddress = $address;
    }
}

// ============================================
// ОБРАБОТКА СМЕНЫ ПАРОЛЯ
// ============================================
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    $password_errors = [];
    
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user_data = $stmt->fetch();
    
    if (!password_verify($current, $user_data['password_hash'])) {
        $password_errors['current'] = 'Неверный текущий пароль';
    }
    
    if (strlen($new) < 6) {
        $password_errors['new'] = 'Пароль должен быть не менее 6 символов';
    }
    
    if ($new != $confirm) {
        $password_errors['confirm'] = 'Пароли не совпадают';
    }
    
    if (empty($password_errors)) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        $password_success = 'Пароль успешно изменён';
    }
}

// ============================================
// ОБРАБОТКА ПОДПИСКИ НА НОВОСТИ
// ============================================
if (isset($_POST['subscribe_newsletter'])) {
    $stmt = $db->prepare("SELECT id, is_active FROM newsletter_subscribers WHERE email = ?");
    $stmt->execute([$userEmail]);
    $subscriber = $stmt->fetch();
    
    if ($subscriber) {
        if ($subscriber['is_active'] == 0) {
            $stmt = $db->prepare("UPDATE newsletter_subscribers SET is_active = 1 WHERE id = ?");
            $stmt->execute([$subscriber['id']]);
            $newsletter_message = 'Вы успешно подписались на рассылку';
            $newsletter_status = 'success';
        } else {
            $newsletter_message = 'Вы уже подписаны на рассылку';
            $newsletter_status = 'info';
        }
    } else {
        $stmt = $db->prepare("INSERT INTO newsletter_subscribers (email, subscribed_at, is_active) VALUES (?, NOW(), 1)");
        $stmt->execute([$userEmail]);
        $newsletter_message = 'Вы успешно подписались на рассылку';
        $newsletter_status = 'success';
    }
}

if (isset($_POST['unsubscribe_newsletter'])) {
    $stmt = $db->prepare("UPDATE newsletter_subscribers SET is_active = 0 WHERE email = ?");
    $stmt->execute([$userEmail]);
    $newsletter_message = 'Вы отписались от рассылки';
    $newsletter_status = 'info';
}

// Получаем статус подписки
$is_subscribed = false;
$stmt = $db->prepare("SELECT is_active FROM newsletter_subscribers WHERE email = ?");
$stmt->execute([$userEmail]);
$subscriber = $stmt->fetch();
if ($subscriber) {
    $is_subscribed = $subscriber['is_active'] == 1;
}

// ============================================
// ОБРАБОТКА РЕДАКТИРОВАНИЯ ОТЗЫВА
// ============================================
if (isset($_POST['edit_review'])) {
    $review_id = (int)$_POST['review_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    $stmt = $db->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
    $stmt->execute([$review_id, $userId]);
    
    if ($stmt->fetch() && $rating >= 1 && $rating <= 5 && !empty($comment)) {
        $stmt = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, status = 'pending' WHERE id = ?");
        $stmt->execute([$rating, $comment, $review_id]);
        $review_message = 'Отзыв обновлён и отправлен на модерацию';
        $review_status = 'success';
    } else {
        $review_error = 'Ошибка при редактировании отзыва';
    }
}

// ============================================
// ОБРАБОТКА УДАЛЕНИЯ ОТЗЫВА
// ============================================
if (isset($_POST['delete_review'])) {
    $review_id = (int)$_POST['review_id'];
    
    $stmt = $db->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
    $stmt->execute([$review_id, $userId]);
    
    if ($stmt->fetch()) {
        $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$review_id]);
        $review_message = 'Отзыв удалён';
        $review_status = 'info';
    }
}

// ============================================
// ОПРЕДЕЛЯЕМ ПАРАМЕТРЫ ФИЛЬТРАЦИИ
// ============================================
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';
$order_status_filter = isset($_GET['order_status']) ? $_GET['order_status'] : '';
$order_search = isset($_GET['order_search']) ? trim($_GET['order_search']) : '';

// ============================================
// ПОЛУЧАЕМ ЗАКАЗЫ ПОЛЬЗОВАТЕЛЯ С ФИЛЬТРАЦИЕЙ
// ============================================
$orders_query = "
    SELECT 
        o.*,
        COUNT(oi.id) as items_count,
        SUM(oi.quantity) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = :user_id
";
$params = ['user_id' => $userId];

// Для вкладки orders показываем ВСЕ заказы, кроме доставленных (они в отдельной вкладке)
if ($activeTab == 'orders' && empty($order_status_filter)) {
    $orders_query .= " AND o.status != 'delivered'";
}

// Добавляем фильтр по статусу (если указан)
if (!empty($order_status_filter)) {
    $orders_query .= " AND o.status = :status";
    $params['status'] = $order_status_filter;
}

// Добавляем поиск по ID заказа
if (!empty($order_search)) {
    $orders_query .= " AND CAST(o.id AS CHAR) LIKE :search";
    $params['search'] = "%$order_search%";
}

$orders_query .= " GROUP BY o.id ORDER BY o.order_date DESC";

$stmt = $db->prepare($orders_query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ============================================
// ПОЛУЧАЕМ ИСТОРИЮ ПОКУПОК (доставленные заказы)
// ============================================
$purchases = $db->prepare("
    SELECT 
        o.*,
        COUNT(oi.id) as items_count,
        SUM(oi.quantity) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ? AND o.status = 'delivered'
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$purchases->execute([$userId]);
$purchases = $purchases->fetchAll();

// ============================================
// ПОЛУЧАЕМ ОТЗЫВЫ ПОЛЬЗОВАТЕЛЯ (товары могут быть удалены, но отзывы остаются)
// ============================================
$reviews = $db->prepare("
    SELECT 
        r.*,
        p.name as product_name,
        p.image as product_image,
        p.id as product_id,
        p.deleted_at
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$reviews->execute([$userId]);
$reviews = $reviews->fetchAll();

// ============================================
// ПОЛУЧАЕМ ВОПРОСЫ ПОЛЬЗОВАТЕЛЯ
// ============================================
$user_questions = $db->prepare("
    SELECT 
        q.*,
        p.name as product_name,
        p.image as product_image,
        p.id as product_id
    FROM product_questions q
    JOIN products p ON q.product_id = p.id
    WHERE q.user_id = ?
    ORDER BY q.created_at DESC
");
$user_questions->execute([$userId]);
$user_questions = $user_questions->fetchAll();

$questions_count = count($user_questions);

// ============================================
// ПОЛУЧАЕМ ТОВАРЫ, ДОСТУПНЫЕ ДЛЯ ОТЗЫВА (только активные, неудалённые)
// ============================================
$awaiting_review = $db->prepare("
    SELECT DISTINCT
        p.id,
        p.name,
        p.image,
        a.fio as artist_name,
        o.order_date
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN artists a ON p.artist_id = a.id
    LEFT JOIN reviews r ON r.product_id = p.id AND r.user_id = o.user_id
    WHERE o.user_id = ? AND o.status = 'delivered' AND r.id IS NULL AND p.deleted_at IS NULL
    ORDER BY o.order_date DESC
");
$awaiting_review->execute([$userId]);
$awaiting_review = $awaiting_review->fetchAll();

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
    <title>ARTOBJECT | Личный кабинет</title>
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

        /* PROFILE SECTION */
        .profile-section {
            padding: 40px 0 80px;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .profile-welcome {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-orange);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .profile-welcome h2 {
            font-size: 2rem;
        }

        .profile-welcome p {
            color: var(--gray-dark);
        }

        .profile-logout {
            padding: 12px 25px;
            background: transparent;
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            color: var(--gray-dark);
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }

        .profile-logout:hover {
            border-color: #f44336;
            color: #f44336;
        }

        /* ФИЛЬТРЫ */
        .filters-section {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }

        .filters-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-dark);
            font-size: 0.9rem;
        }

        .filter-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            background: var(--white);
            color: var(--charcoal);
            font-size: 0.95rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-medium);
            border-radius: 30px;
            font-size: 0.95rem;
            transition: all var(--transition-fast);
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: none;
            background: var(--gradient-orange);
            color: white;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .filter-reset {
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: 2px solid var(--gray-medium);
            background: transparent;
            color: var(--charcoal);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .filter-reset:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
        }

        /* TABS */
        .profile-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--gray-light);
            padding-bottom: 10px;
        }

        .tab-btn {
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            background: transparent;
            border: 2px solid transparent;
            color: var(--gray-dark);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .tab-btn i {
            font-size: 1.1rem;
        }

        .tab-btn:hover {
            color: var(--primary-orange);
        }

        .tab-btn.active {
            background: var(--gradient-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .results-info {
            margin-bottom: 20px;
            color: var(--gray-dark);
            font-size: 0.95rem;
        }

        /* CARDS */
        .orders-grid,
        .purchases-grid,
        .reviews-grid,
        .awaiting-grid,
        .questions-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card,
        .purchase-card,
        .review-card,
        .awaiting-card,
        .question-card {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            transition: all var(--transition-fast);
        }

        .order-card:hover,
        .purchase-card:hover,
        .review-card:hover,
        .awaiting-card:hover,
        .question-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .order-info,
        .purchase-info,
        .review-info,
        .awaiting-info,
        .question-info {
            flex: 2;
            min-width: 250px;
        }

        .order-number {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .order-number a {
            text-decoration: none;
            color: inherit;
        }

        .order-number a:hover {
            color: var(--primary-orange);
        }

        .order-date {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .order-items {
            font-size: 0.95rem;
            margin-bottom: 5px;
        }

        .order-total {
            font-weight: 700;
            color: var(--primary-orange);
            font-size: 1.1rem;
        }

        .order-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 10px;
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

        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }

        .status-paid {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .order-actions,
        .purchase-actions,
        .review-actions,
        .awaiting-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: 2px solid transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-outline {
            background: transparent;
            border-color: var(--gray-medium);
            color: var(--charcoal);
        }

        .btn-outline:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
        }

        .btn-danger {
            background: transparent;
            border-color: var(--gray-medium);
            color: #f44336;
        }

        .btn-danger:hover {
            border-color: #f44336;
            background: rgba(244, 67, 54, 0.05);
        }

        .btn-primary-small {
            background: var(--gradient-orange);
            color: white;
        }

        .btn-primary-small:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .btn-success {
            background: #4CAF50;
            color: white;
            border: none;
        }

        .btn-success:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        /* REVIEW CARD */
        .review-product,
        .question-product {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .review-product-image,
        .question-product-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .review-product-image img,
        .question-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .review-product-info h4,
        .question-product-info h4 {
            font-size: 1.1rem;
            margin-bottom: 3px;
        }

        .review-product-info h4 a,
        .question-product-info h4 a {
            text-decoration: none;
            color: inherit;
        }

        .review-product-info h4 a:hover,
        .question-product-info h4 a:hover {
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

        .review-rating {
            color: var(--gold);
            margin: 5px 0;
        }

        .review-text {
            color: var(--gray-dark);
            line-height: 1.6;
            margin: 10px 0;
        }

        .review-date,
        .question-date {
            color: var(--gray-medium);
            font-size: 0.85rem;
        }

        /* QUESTION CARD */
        .question-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .question-text {
            background: var(--off-white);
            padding: 15px 20px;
            border-radius: 15px;
        }

        .question-text strong {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-black);
        }

        .question-text p {
            color: var(--gray-dark);
            line-height: 1.6;
            margin: 0;
        }

        .answer-box {
            background: rgba(255, 90, 48, 0.03);
            border-left: 4px solid var(--primary-orange);
            padding: 15px 20px;
            border-radius: 10px;
        }

        .answer-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--primary-orange);
            font-weight: 600;
            flex-wrap: wrap;
        }

        .answer-date {
            color: var(--gray-dark);
            font-size: 0.85rem;
            font-weight: normal;
            margin-left: auto;
        }

        .answer-text p {
            color: var(--charcoal);
            line-height: 1.6;
            margin: 0;
        }

        /* AWAITING CARD */
        .awaiting-product {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .awaiting-product-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
        }

        .awaiting-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .awaiting-product-info h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .awaiting-product-info h4 a {
            text-decoration: none;
            color: inherit;
        }

        .awaiting-product-info h4 a:hover {
            color: var(--primary-orange);
        }

        .awaiting-purchase-date {
            color: var(--gray-dark);
            font-size: 0.9rem;
        }

        /* PROFILE EDIT FORM */
        .profile-form {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
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

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            padding: 12px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .info-message {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            padding: 12px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .password-change {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid var(--gray-light);
        }

        .password-change h3 {
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .btn-save {
            padding: 16px 35px;
            background: var(--gradient-orange);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .newsletter-section {
            margin-top: 30px;
            padding: 20px;
            background: var(--off-white);
            border-radius: 15px;
        }

        .newsletter-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .newsletter-status {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .newsletter-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .newsletter-badge.subscribed {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .newsletter-badge.unsubscribed {
            background: rgba(158, 158, 158, 0.1);
            color: #757575;
        }

        .btn-newsletter {
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: 2px solid transparent;
        }

        .btn-newsletter.subscribe {
            background: var(--gradient-orange);
            color: white;
        }

        .btn-newsletter.unsubscribe {
            background: transparent;
            border-color: #f44336;
            color: #f44336;
        }

        .btn-newsletter.unsubscribe:hover {
            background: #f44336;
            color: white;
        }

        /* РЕЙТИНГ */
        .rating-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
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

        /* EMPTY STATES */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--gray-medium);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--gray-dark);
            margin-bottom: 25px;
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

        /* МОДАЛЬНОЕ ОКНО */
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
            padding: 40px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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

            .page-header h1 {
                font-size: 3rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .filters-grid {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-btn,
            .filter-reset {
                width: 100%;
                text-align: center;
            }

            .answer-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .answer-date {
                margin-left: 0;
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

            .profile-tabs {
                flex-direction: column;
            }

            .tab-btn {
                width: 100%;
                justify-content: center;
            }

            .order-card,
            .purchase-card,
            .review-card,
            .awaiting-card,
            .question-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-actions,
            .purchase-actions,
            .review-actions,
            .awaiting-actions {
                width: 100%;
            }

            .order-actions .btn-small,
            .purchase-actions .btn-small,
            .review-actions .btn-small,
            .awaiting-actions .btn-small {
                flex: 1;
                text-align: center;
                justify-content: center;
            }

            .question-product {
                flex-direction: column;
                text-align: center;
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

            .profile-header {
                flex-direction: column;
                align-items: flex-start;
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
                <h1>Личный <span class="text-gradient">кабинет</span></h1>
            </div>
        </div>
    </section>

    <!-- PROFILE SECTION -->
    <section class="profile-section">
        <div class="container">
            <!-- Приветствие и выход -->
            <div class="profile-header">
                <div class="profile-welcome">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($userName); ?></h2>
                        <p><?php echo htmlspecialchars($userEmail); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="profile-logout">
                    <i class="fas fa-sign-out-alt"></i> Выйти
                </a>
            </div>

            <!-- Сообщения -->
            <?php if (isset($update_success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $update_success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($password_success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $password_success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($newsletter_message)): ?>
                <div class="<?php echo $newsletter_status == 'success' ? 'success-message' : 'info-message'; ?>">
                    <i class="fas fa-<?php echo $newsletter_status == 'success' ? 'check-circle' : 'info-circle'; ?>"></i> 
                    <?php echo $newsletter_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($review_message)): ?>
                <div class="<?php echo $review_status == 'success' ? 'success-message' : 'info-message'; ?>">
                    <i class="fas fa-<?php echo $review_status == 'success' ? 'check-circle' : 'info-circle'; ?>"></i> 
                    <?php echo $review_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($review_error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $review_error; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="profile-tabs">
                <a href="?tab=orders" class="tab-btn <?php echo $activeTab == 'orders' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i> Заказы 
                    <span class="badge" style="position: static; margin-left: 5px; background: <?php echo $activeTab == 'orders' ? 'white' : 'var(--primary-orange)'; ?>; color: <?php echo $activeTab == 'orders' ? 'var(--primary-orange)' : 'white'; ?>;">
                        <?php echo count($orders); ?>
                    </span>
                </a>
                <a href="?tab=purchases" class="tab-btn <?php echo $activeTab == 'purchases' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Покупки 
                    <span class="badge" style="position: static; margin-left: 5px; background: <?php echo $activeTab == 'purchases' ? 'white' : 'var(--primary-orange)'; ?>; color: <?php echo $activeTab == 'purchases' ? 'var(--primary-orange)' : 'white'; ?>;">
                        <?php echo count($purchases); ?>
                    </span>
                </a>
                <a href="?tab=reviews" class="tab-btn <?php echo $activeTab == 'reviews' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i> Мои отзывы 
                    <span class="badge" style="position: static; margin-left: 5px; background: <?php echo $activeTab == 'reviews' ? 'white' : 'var(--primary-orange)'; ?>; color: <?php echo $activeTab == 'reviews' ? 'var(--primary-orange)' : 'white'; ?>;">
                        <?php echo count($reviews); ?>
                    </span>
                </a>
                <a href="?tab=questions" class="tab-btn <?php echo $activeTab == 'questions' ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i> Мои вопросы 
                    <span class="badge" style="position: static; margin-left: 5px; background: <?php echo $activeTab == 'questions' ? 'white' : 'var(--primary-orange)'; ?>; color: <?php echo $activeTab == 'questions' ? 'var(--primary-orange)' : 'white'; ?>;">
                        <?php echo $questions_count; ?>
                    </span>
                </a>
                <a href="?tab=awaiting" class="tab-btn <?php echo $activeTab == 'awaiting' ? 'active' : ''; ?>">
                    <i class="fas fa-pen"></i> Ждут отзыва 
                    <span class="badge" style="position: static; margin-left: 5px; background: <?php echo $activeTab == 'awaiting' ? 'white' : 'var(--primary-orange)'; ?>; color: <?php echo $activeTab == 'awaiting' ? 'var(--primary-orange)' : 'white'; ?>;">
                        <?php echo count($awaiting_review); ?>
                    </span>
                </a>
                <a href="?tab=profile" class="tab-btn <?php echo $activeTab == 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i> Редактировать профиль
                </a>
            </div>

            <!-- Фильтры (только для вкладки заказов) -->
            <?php if ($activeTab == 'orders'): ?>
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <input type="hidden" name="tab" value="orders">
                    
                    <div class="filter-group">
                        <label class="filter-label">Статус заказа</label>
                        <select name="order_status" class="filter-select">
                            <option value="">Все статусы</option>
                            <option value="processing" <?php echo $order_status_filter == 'processing' ? 'selected' : ''; ?>>В обработке</option>
                            <option value="delivering" <?php echo $order_status_filter == 'delivering' ? 'selected' : ''; ?>>Доставляется</option>
                            <option value="delivered" <?php echo $order_status_filter == 'delivered' ? 'selected' : ''; ?>>Доставлен</option>
                            <option value="cancelled" <?php echo $order_status_filter == 'cancelled' ? 'selected' : ''; ?>>Отменён</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Поиск по № заказа</label>
                        <input type="text" name="order_search" class="filter-input" placeholder="Введите номер заказа" value="<?php echo htmlspecialchars($order_search); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search"></i> Применить
                        </button>
                        <a href="?tab=orders" class="filter-reset">
                            <i class="fas fa-times"></i> Сброс
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Результаты поиска -->
            <?php if ($activeTab == 'orders' && (!empty($order_status_filter) || !empty($order_search))): ?>
                <div class="results-info">
                    Найдено заказов: <strong><?php echo count($orders); ?></strong>
                    <?php if (!empty($order_status_filter)): ?>
                        · Статус: <?php 
                            $status_names = [
                                'processing' => 'В обработке',
                                'delivering' => 'Доставляется',
                                'delivered' => 'Доставлен',
                                'cancelled' => 'Отменён'
                            ];
                            echo $status_names[$order_status_filter] ?? $order_status_filter;
                        ?>
                    <?php endif; ?>
                    <?php if (!empty($order_search)): ?>
                        · Поиск: "<?php echo htmlspecialchars($order_search); ?>"
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB: ЗАКАЗЫ (текущие) -->
            <?php if ($activeTab == 'orders'): ?>
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h3>Заказы не найдены</h3>
                        <p>Попробуйте изменить параметры фильтрации или перейдите в каталог.</p>
                        <a href="product_catalog.php" class="btn-primary">
                            <i class="fas fa-arrow-left"></i> Перейти в каталог
                        </a>
                    </div>
                <?php else: ?>
                    <div class="orders-grid">
                        <?php foreach ($orders as $order): 
                            $status = getOrderStatusInfo($order['status']);
                        ?>
                        <div class="order-card">
                            <div class="order-info">
                                <div class="order-number">
                                    <a href="order.php?id=<?php echo $order['id']; ?>">Заказ №<?php echo $order['id']; ?></a>
                                </div>
                                <div class="order-date">от <?php echo date('d.m.Y', strtotime($order['order_date'])); ?></div>
                                <div class="order-items"><?php echo $order['total_items']; ?> товара</div>
                                <div class="order-total"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> BYN</div>
                                <div>
                                    <span class="order-status <?php echo $status['class']; ?>">
                                        <i class="fas fa-<?php echo $status['icon']; ?>"></i>
                                        <?php echo $status['text']; ?>
                                    </span>
                                    
                                    <?php if ($order['payment_status'] == 'paid'): ?>
                                    <span class="order-status status-paid" style="margin-left: 10px;">
                                        <i class="fas fa-check-circle"></i> Оплачено
                                    </span>
                                    <?php elseif ($order['status'] != 'cancelled'): ?>
                                    <span class="order-status status-pending" style="margin-left: 10px;">
                                        <i class="fas fa-clock"></i> Ожидает оплаты
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="order-actions">
                                <a href="order.php?id=<?php echo $order['id']; ?>" class="btn-small btn-outline">
                                    <i class="fas fa-eye"></i> Детали
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- TAB: ПОКУПКИ (история) -->
            <?php if ($activeTab == 'purchases'): ?>
                <?php if (empty($purchases)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>У вас пока нет завершённых покупок</h3>
                        <p>После получения заказа он появится в этом разделе.</p>
                    </div>
                <?php else: ?>
                    <div class="purchases-grid">
                        <?php foreach ($purchases as $purchase): ?>
                        <div class="purchase-card">
                            <div class="purchase-info">
                                <div class="order-number">
                                    <a href="order.php?id=<?php echo $purchase['id']; ?>">Заказ №<?php echo $purchase['id']; ?></a>
                                </div>
                                <div class="order-date">от <?php echo date('d.m.Y', strtotime($purchase['order_date'])); ?></div>
                                <div class="order-items"><?php echo $purchase['total_items']; ?> товара</div>
                                <div class="order-total"><?php echo number_format($purchase['total_price'], 2, '.', ' '); ?> BYN</div>
                                <div class="order-status status-delivered">
                                    <i class="fas fa-check-circle"></i> Доставлен
                                </div>
                            </div>
                            <div class="purchase-actions">
                                <a href="order.php?id=<?php echo $purchase['id']; ?>" class="btn-small btn-outline">
                                    <i class="fas fa-eye"></i> Детали
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- TAB: МОИ ОТЗЫВЫ -->
            <?php if ($activeTab == 'reviews'): ?>
                <?php if (empty($reviews)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Вы ещё не оставляли отзывы</h3>
                        <p>Поделитесь впечатлениями о приобретённых товарах.</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-grid">
                        <?php foreach ($reviews as $review): 
                            $is_product_deleted = !empty($review['deleted_at']);
                        ?>
                        <div class="review-card" id="review-<?php echo $review['id']; ?>">
                            <div class="review-info">
                                <div class="review-product">
                                    <div class="review-product-image">
                                        <img src="<?php echo $review['product_image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'; ?>" alt="<?php echo htmlspecialchars($review['product_name']); ?>">
                                    </div>
                                    <div class="review-product-info">
                                        <h4>
                                            <?php if (!$is_product_deleted): ?>
                                                <a href="product.php?id=<?php echo $review['product_id']; ?>"><?php echo htmlspecialchars($review['product_name']); ?></a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($review['product_name']); ?>
                                                <span class="product-deleted-badge">Товар удалён</span>
                                            <?php endif; ?>
                                        </h4>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $review['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-date"><?php echo date('d.m.Y', strtotime($review['created_at'])); ?></div>
                                        <?php if ($review['status'] == 'pending'): ?>
                                            <span class="badge badge-warning" style="margin-top: 5px;">На модерации</span>
                                        <?php elseif ($review['status'] == 'published'): ?>
                                            <span class="badge badge-success" style="margin-top: 5px;">Опубликован</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger" style="margin-top: 5px;">Скрыт</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="review-text"><?php echo htmlspecialchars($review['comment']); ?></p>
                            </div>
                            <div class="review-actions">
                                <?php if (!$is_product_deleted): ?>
                                <button class="btn-small btn-outline" onclick="openEditModal(<?php echo $review['id']; ?>, <?php echo $review['rating']; ?>, '<?php echo htmlspecialchars(addslashes($review['comment'])); ?>')">
                                    <i class="fas fa-edit"></i> Редактировать
                                </button>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить отзыв?');">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <button type="submit" name="delete_review" class="btn-small btn-danger">
                                        <i class="fas fa-trash"></i> Удалить
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- TAB: МОИ ВОПРОСЫ -->
            <?php if ($activeTab == 'questions'): ?>
                <?php if (empty($user_questions)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h3>У вас пока нет вопросов</h3>
                        <p>Задайте вопрос о товаре на его странице, и мы обязательно ответим.</p>
                        <a href="product_catalog.php" class="btn-primary">
                            <i class="fas fa-arrow-left"></i> Перейти в каталог
                        </a>
                    </div>
                <?php else: ?>
                    <div class="questions-grid">
                        <?php foreach ($user_questions as $question): ?>
                        <div class="question-card" id="question-<?php echo $question['id']; ?>">
                            <div class="question-info">
                                <div class="question-product">
                                    <div class="question-product-image">
                                        <img src="<?php echo $question['product_image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'; ?>" 
                                             alt="<?php echo htmlspecialchars($question['product_name']); ?>">
                                    </div>
                                    <div class="question-product-info">
                                        <h4><a href="product.php?id=<?php echo $question['product_id']; ?>"><?php echo htmlspecialchars($question['product_name']); ?></a></h4>
                                        <div class="question-date"><?php echo date('d.m.Y', strtotime($question['created_at'])); ?></div>
                                        <?php if ($question['status'] == 'pending'): ?>
                                            <span class="badge badge-warning" style="margin-top: 5px;">Ожидает ответа</span>
                                        <?php elseif ($question['status'] == 'published'): ?>
                                            <span class="badge badge-success" style="margin-top: 5px;">Опубликован</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger" style="margin-top: 5px;">Скрыт</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="question-content">
                                    <div class="question-text">
                                        <strong>Ваш вопрос:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($question['question'])); ?></p>
                                    </div>
                                    
                                    <?php if ($question['answer']): ?>
                                    <div class="answer-box">
                                        <div class="answer-header">
                                            <i class="fas fa-user-tie" style="color: var(--primary-orange);"></i>
                                            <strong>Ответ администратора</strong>
                                            <span class="answer-date"><?php echo date('d.m.Y', strtotime($question['answered_at'])); ?></span>
                                        </div>
                                        <div class="answer-text">
                                            <p><?php echo nl2br(htmlspecialchars($question['answer'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- TAB: ЖДУТ ОТЗЫВА -->
            <?php if ($activeTab == 'awaiting'): ?>
                <?php if (empty($awaiting_review)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-pen"></i>
                        </div>
                        <h3>Нет товаров, ждущих отзыва</h3>
                        <p>Вы оставили отзывы на все купленные товары. Спасибо!</p>
                    </div>
                <?php else: ?>
                    <div class="awaiting-grid">
                        <?php foreach ($awaiting_review as $item): ?>
                        <div class="awaiting-card">
                            <div class="awaiting-info">
                                <div class="awaiting-product">
                                    <div class="awaiting-product-image">
                                        <img src="<?php echo $item['image'] ?? 'https://images.unsplash.com/photo-1543857778-c4a1a569e388?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    </div>
                                    <div class="awaiting-product-info">
                                        <h4><a href="product.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h4>
                                        <div class="awaiting-purchase-date">Куплен <?php echo date('d.m.Y', strtotime($item['order_date'])); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="awaiting-actions">
                                <a href="product.php?id=<?php echo $item['id']; ?>#reviews" class="btn-small btn-primary-small">
                                    <i class="fas fa-pen"></i> Написать отзыв
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- TAB: РЕДАКТИРОВАНИЕ ПРОФИЛЯ -->
            <?php if ($activeTab == 'profile'): ?>
                <div class="profile-form">
                    <form method="POST">
                        <h3 style="margin-bottom: 20px;">Личные данные</h3>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">ФИО</label>
                                <input type="text" class="form-control" name="fio" value="<?php echo htmlspecialchars($userName); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($update_errors['email']) ? 'error' : ''; ?>" name="email" value="<?php echo htmlspecialchars($userEmail); ?>" required>
                                <?php if (isset($update_errors['email'])): ?>
                                    <span class="error-text"><?php echo $update_errors['email']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Телефон</label>
                                <input type="tel" class="form-control <?php echo isset($update_errors['phone']) ? 'error' : ''; ?>" name="phone" value="<?php echo htmlspecialchars($userPhone ?? ''); ?>" required>
                                <?php if (isset($update_errors['phone'])): ?>
                                    <span class="error-text"><?php echo $update_errors['phone']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Город</label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($userCity ?? ''); ?>">
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Адрес доставки</label>
                                <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($userAddress ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Подписка на новости -->
                        <div class="newsletter-section">
                            <h3 class="newsletter-title">Рассылка новостей</h3>
                            <div class="newsletter-status">
                                <?php if ($is_subscribed): ?>
                                    <span class="newsletter-badge subscribed">
                                        <i class="fas fa-check-circle"></i> Вы подписаны на рассылку
                                    </span>
                                    <button type="submit" name="unsubscribe_newsletter" class="btn-newsletter unsubscribe">
                                        <i class="fas fa-bell-slash"></i> Отписаться
                                    </button>
                                <?php else: ?>
                                    <span class="newsletter-badge unsubscribed">
                                        <i class="fas fa-bell-slash"></i> Вы не подписаны на рассылку
                                    </span>
                                    <button type="submit" name="subscribe_newsletter" class="btn-newsletter subscribe">
                                        <i class="fas fa-bell"></i> Подписаться
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <button type="submit" name="update_profile" class="btn-save">
                                <i class="fas fa-save"></i> Сохранить изменения
                            </button>
                        </div>
                    </form>

                    <!-- Смена пароля -->
                    <div class="password-change">
                        <h3>Смена пароля</h3>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label class="form-label">Текущий пароль</label>
                                    <input type="password" class="form-control <?php echo isset($password_errors['current']) ? 'error' : ''; ?>" name="current_password">
                                    <?php if (isset($password_errors['current'])): ?>
                                        <span class="error-text"><?php echo $password_errors['current']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Новый пароль</label>
                                    <input type="password" class="form-control <?php echo isset($password_errors['new']) ? 'error' : ''; ?>" name="new_password">
                                    <?php if (isset($password_errors['new'])): ?>
                                        <span class="error-text"><?php echo $password_errors['new']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Подтверждение</label>
                                    <input type="password" class="form-control <?php echo isset($password_errors['confirm']) ? 'error' : ''; ?>" name="confirm_password">
                                    <?php if (isset($password_errors['confirm'])): ?>
                                        <span class="error-text"><?php echo $password_errors['confirm']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="text-align: right;">
                                <button type="submit" name="change_password" class="btn-save">
                                    <i class="fas fa-key"></i> Изменить пароль
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- МОДАЛЬНОЕ ОКНО РЕДАКТИРОВАНИЯ ОТЗЫВА -->
    <div class="modal-overlay" id="editReviewModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2 class="section-title" style="margin-bottom: 30px; font-size: 2rem;">Редактировать <span class="text-gradient">отзыв</span></h2>
            
            <form method="POST" id="editReviewForm">
                <input type="hidden" name="review_id" id="edit_review_id">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">Ваша оценка</label>
                    <div class="rating-selector" id="editRatingSelector">
                        <span class="rating-star" data-rating="1">★</span>
                        <span class="rating-star" data-rating="2">★</span>
                        <span class="rating-star" data-rating="3">★</span>
                        <span class="rating-star" data-rating="4">★</span>
                        <span class="rating-star" data-rating="5">★</span>
                    </div>
                    <input type="hidden" name="rating" id="edit_rating" value="5">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">Ваш отзыв</label>
                    <textarea name="comment" id="edit_comment" rows="5" class="form-control" required placeholder="Поделитесь впечатлениями о товаре..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                    <button type="submit" name="edit_review" class="btn-primary">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

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
        // ДЕЙСТВИЯ С ЗАКАЗАМИ
        // ============================================
        function repeatOrder(orderId) {
            showNotification('Товары добавлены в корзину', 'success');
            setTimeout(() => {
                window.location.href = 'shopping-bag.php';
            }, 1500);
        }

        // ============================================
        // ДЕЙСТВИЯ С ОТЗЫВАМИ
        // ============================================
        function openEditModal(reviewId, rating, comment) {
            document.getElementById('edit_review_id').value = reviewId;
            document.getElementById('edit_comment').value = comment;
            document.getElementById('edit_rating').value = rating;
            
            const stars = document.querySelectorAll('#editRatingSelector .rating-star');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
            
            document.getElementById('editReviewModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editReviewModal').style.display = 'none';
        }

        function writeReview(productId) {
            window.location.href = 'product.php?id=' + productId + '#reviews';
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

        // Инициализация выбора рейтинга
        document.addEventListener('DOMContentLoaded', function() {
            const ratingSelector = document.getElementById('editRatingSelector');
            if (ratingSelector) {
                const stars = ratingSelector.querySelectorAll('.rating-star');
                const ratingInput = document.getElementById('edit_rating');
                
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.dataset.rating;
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

        // Закрытие модального окна по клику вне
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('editReviewModal');
            if (event.target === modal) {
                closeEditModal();
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