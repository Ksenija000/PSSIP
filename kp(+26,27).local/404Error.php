<?php
// ============================================
// 404.php - СТРАНИЦА ОШИБКИ 404
// ============================================
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTOBJECT | Страница не найдена</title>
    <style>
        /* ============================================ */
        /* СТИЛИ - ПОЛНОСТЬЮ КАК НА ДРУГИХ СТРАНИЦАХ */
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ============================================ */
        /* ШАПКА - ПОЛНОСТЬЮ КАК НА ДРУГИХ СТРАНИЦАХ */
        /* ============================================ */
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
            /* Добавляем тень, чтобы шапка не сливалась */
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

        .mobile-dropdown>a i:last-child {
            margin-left: auto;
            transition: transform var(--transition-fast);
        }

        .mobile-dropdown.active>a i:last-child {
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
                padding: 15px 15px;
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

        /* ============================================ */
        /* 404 SECTION */
        /* ============================================ */
        .error-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 150px 0 100px;
            min-height: 70vh;
        }

        .error-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }

        .error-number {
            font-size: 12rem;
            font-weight: 900;
            line-height: 1;
            background: var(--gradient-orange);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
            animation: glitch 3s infinite;
        }

        @keyframes glitch {
            0%, 100% { transform: skew(0deg, 0deg); }
            95% { transform: skew(0deg, 0deg); }
            96% { transform: skew(5deg, 2deg) scale(1.02); }
            97% { transform: skew(-5deg, -2deg) scale(0.98); }
            98% { transform: skew(3deg, 1deg); }
        }

        .error-number::before,
        .error-number::after {
            content: '404';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-orange);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.3;
        }

        .error-number::before {
            animation: glitch-left 3s infinite;
            transform: translateX(-5px);
        }

        .error-number::after {
            animation: glitch-right 3s infinite;
            transform: translateX(5px);
        }

        @keyframes glitch-left {
            0%, 100% { transform: translateX(-5px); opacity: 0.3; }
            95% { transform: translateX(-5px); opacity: 0.3; }
            96% { transform: translateX(-15px); opacity: 0.5; }
            97% { transform: translateX(5px); opacity: 0.2; }
            98% { transform: translateX(-8px); opacity: 0.4; }
        }

        @keyframes glitch-right {
            0%, 100% { transform: translateX(5px); opacity: 0.3; }
            95% { transform: translateX(5px); opacity: 0.3; }
            96% { transform: translateX(15px); opacity: 0.5; }
            97% { transform: translateX(-5px); opacity: 0.2; }
            98% { transform: translateX(8px); opacity: 0.4; }
        }

        .error-title {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--primary-black);
        }

        .error-text {
            font-size: 1.2rem;
            color: var(--gray-dark);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .error-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
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
            color: var(--charcoal);
            border: 2px solid var(--gray-medium);
        }

        .btn-secondary:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
            transform: translateY(-2px);
        }

        .error-illustration {
            margin-top: 60px;
            position: relative;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .art-object {
            position: absolute;
            opacity: 0.2;
            animation: float 6s infinite ease-in-out;
        }

        .art-object:nth-child(1) {
            width: 100px;
            height: 100px;
            left: 10%;
            top: 0;
            animation-delay: 0s;
        }

        .art-object:nth-child(2) {
            width: 150px;
            height: 150px;
            right: 10%;
            bottom: 0;
            animation-delay: 1s;
        }

        .art-object:nth-child(3) {
            width: 80px;
            height: 80px;
            left: 30%;
            bottom: 20px;
            animation-delay: 2s;
        }

        .art-object svg {
            width: 100%;
            height: 100%;
            fill: var(--primary-orange);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* ============================================ */
        /* ПОДВАЛ (как на других страницах) */
        /* ============================================ */
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
            color: white;
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

        /* ============================================ */
        /* АДАПТАЦИЯ */
        /* ============================================ */
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

            .error-number {
                font-size: 8rem;
            }

            .error-title {
                font-size: 2.5rem;
            }

            .art-object {
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

            .error-number {
                font-size: 6rem;
            }

            .error-title {
                font-size: 2rem;
            }

            .error-text {
                font-size: 1rem;
                padding: 0 20px;
            }

            .error-actions {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }

            .btn {
                width: 100%;
                max-width: 300px;
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

            .error-number {
                font-size: 4rem;
            }

            .error-title {
                font-size: 1.5rem;
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
    <!-- ШАПКА - ПОЛНОСТЬЮ КАК НА ДРУГИХ СТРАНИЦАХ -->
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
                                <a href="/catalog.php?category=1"><i class="fas fa-palette"></i> Флористическая скульптура</a>
                                <a href="/catalog.php?category=2"><i class="fas fa-monument"></i> Подставка для украшений</a>
                                <a href="/catalog.php?category=3"><i class="fas fa-camera"></i> Искусство эпоксидной смолы</a>
                                <a href="/catalog.php?category=4"><i class="fas fa-desktop"></i> Панно</a>
                                <a href="/catalog.php?category=5"><i class="fas fa-vase"></i> Статуэтка</a>
                            </div>
                        </li>
                        <li><a href="catalog_artists.php"><i class="fas fa-paint-brush"></i> Художники</a></li>
                        <li><a href="about_Us.php"><i class="fas fa-info-circle"></i> О нас</a></li>
                    </ul>
                </nav>

                <div class="header-right">
                    <div class="action-buttons">
                        <button class="icon-btn" onclick="window.location.href='wishlist.php'" title="Избранное">
                            <i class="fas fa-heart"></i>
                            <span class="badge" id="wishlist-count">0</span>
                        </button>
                        <button class="icon-btn" onclick="window.location.href='cart.php'" title="Корзина">
                            <i class="fas fa-shopping-bag"></i>
                            <span class="badge" id="cart-count">0</span>
                        </button>
                    </div>

                    <?php if ($isLoggedIn): ?>
                    <button id="auth-button" onclick="window.location.href='profile.php'">
                        <i class="fas fa-user"></i>
                        <span id="auth-text"><?php echo htmlspecialchars($userName); ?></span>
                    </button>
                    <?php else: ?>
                    <button id="auth-button" onclick="window.location.href='login.php'">
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
                    <a href="/catalog.php?category=1"><i class="fas fa-palette"></i> Флористическая скульптура</a>
                    <a href="/catalog.php?category=2"><i class="fas fa-monument"></i> Подставка для украшений</a>
                    <a href="/catalog.php?category=3"><i class="fas fa-camera"></i> Искусство эпоксидной смолы</a>
                    <a href="/catalog.php?category=4"><i class="fas fa-desktop"></i> Панно</a>
                    <a href="/catalog.php?category=5"><i class="fas fa-vase"></i> Статуэтка</a>
                </div>
            </li>
            <li><a href="catalog_artists.php"><i class="fas fa-paint-brush"></i> Художники</a></li>
            <li><a href="about_Us.php"><i class="fas fa-info-circle"></i> О нас</a></li>
        </ul>

        <div class="mobile-actions">
            <a href="/wishlist.php" class="mobile-action-btn">
                <i class="fas fa-heart"></i>
                <span>Избранное</span>
                <span class="badge" id="mobile-wishlist-count">0</span>
            </a>
            <a href="/cart.php" class="mobile-action-btn">
                <i class="fas fa-shopping-bag"></i>
                <span>Корзина</span>
                <span class="badge" id="mobile-cart-count">0</span>
            </a>
        </div>
    </div>

    <!-- 404 SECTION -->
    <section class="error-section">
        <div class="container">
            <div class="error-content">
                <div class="error-number">404</div>
                <h1 class="error-title">Страница не <span class="text-gradient">найдена</span></h1>
                <p class="error-text">
                    Возможно, она была удалена или никогда не существовала. 
                    Искусство не стоит на месте, и мы постоянно обновляем галерею.
                </p>
                <div class="error-actions">
                    <a href="/" class="btn btn-primary">
                        <i class="fas fa-home"></i> На главную
                    </a>
                    <a href="product_catalog.php" class="btn btn-secondary">
                        <i class="fas fa-th-large"></i> В каталог
                    </a>
                </div>

                <!-- Декоративные элементы -->
                <div class="error-illustration">
                    <div class="art-object">
                        <svg viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="none" stroke="var(--primary-orange)" stroke-width="2"/>
                            <path d="M30 50 L70 50 M50 30 L50 70" stroke="var(--primary-orange)" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="art-object">
                        <svg viewBox="0 0 100 100">
                            <rect x="20" y="20" width="60" height="60" fill="none" stroke="var(--primary-orange)" stroke-width="2"/>
                            <circle cx="50" cy="50" r="15" fill="none" stroke="var(--primary-orange)" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="art-object">
                        <svg viewBox="0 0 100 100">
                            <polygon points="50,20 80,80 20,80" fill="none" stroke="var(--primary-orange)" stroke-width="2"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ПОДВАЛ -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <div class="footer-logo">ARTOBJECT</div>
                    <p class="footer-description">
                        Галерея современного искусства, где каждый может найти произведение,
                        которое говорит с его душой.
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
                        <li><a href="/catalog.php"><i class="fas fa-chevron-right"></i> Каталог работ</a></li>
                        <li><a href="/artists.php"><i class="fas fa-chevron-right"></i> Художники</a></li>
                        <li><a href="/about.php"><i class="fas fa-chevron-right"></i> О галерее</a></li>
                    </ul>
                </div>

                <div class="footer-links-section">
                    <h3 class="footer-heading">Помощь</h3>
                    <ul class="footer-links">
                        <li><a href="/faq.php"><i class="fas fa-chevron-right"></i> Частые вопросы</a></li>
                        <li><a href="/delivery.php"><i class="fas fa-chevron-right"></i> Доставка и оплата</a></li>
                        <li><a href="/privacy.php"><i class="fas fa-chevron-right"></i> Конфиденциальность</a></li>
                    </ul>
                </div>

                <div class="footer-contact">
                    <h3 class="footer-heading">Контакты</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Минск, ул. Искусств, 15</p>
                    <p><i class="fas fa-phone"></i> +375 (29) 123-45-67</p>
                    <p><i class="fas fa-envelope"></i> info@artobject.by</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>© 2025 ARTOBJECT Gallery. Все права защищены.</p>
                <div class="footer-bottom-links">
                    <a href="/privacy.php">Политика конфиденциальности</a>
                    <a href="/terms.php">Пользовательское соглашение</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Мобильное меню
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

        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                closeMobileMenu();
            }
        });

        // Скролл шапки
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>
</body>

</html>