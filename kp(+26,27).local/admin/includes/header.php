<?php
// ============================================
// admin/includes/header.php - ШАПКА АДМИНКИ
// ============================================
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTOBJECT | Админ-панель</title>
    <style>
        /* ============================================ */
        /* СТИЛИ ДЛЯ АДМИНКИ */
        /* ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        :root {
            --admin-bg: #0f0f0f;
            --admin-surface: #1a1a1a;
            --admin-surface-light: #2a2a2a;
            --admin-border: #333333;
            --admin-text: #e0e0e0;
            --admin-text-secondary: #a0a0a0;
            --primary-orange: #FF5A30;
            --orange-dark: #E64A19;
            --orange-glow: rgba(255, 90, 48, 0.3);
            --success: #4CAF50;
            --warning: #FFC107;
            --danger: #f44336;
            --info: #2196F3;
            --gradient-orange: linear-gradient(135deg, #FF5A30 0%, #FF8A00 100%);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.5);
            --shadow-orange: 0 4px 20px rgba(255, 90, 48, 0.3);
            --transition-fast: 0.2s ease;
        }

        body {
            background-color: var(--admin-bg);
            color: var(--admin-text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ============================================ */
        /* ШАПКА */
        /* ============================================ */
        .admin-header {
            background: var(--admin-surface);
            border-bottom: 1px solid var(--admin-border);
            padding: 15px 25px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .burger-menu {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
            cursor: pointer;
        }

        .burger-menu span {
            display: block;
            width: 100%;
            height: 2px;
            background: var(--admin-text);
            transition: all var(--transition-fast);
        }

        .burger-menu.active span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
            background: var(--primary-orange);
        }

        .burger-menu.active span:nth-child(2) {
            opacity: 0;
        }

        .burger-menu.active span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
            background: var(--primary-orange);
        }

        .logo {
            display: flex;
            flex-direction: column;
        }

        .logo-main {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--admin-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo-main::after {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            background: var(--primary-orange);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        .logo-sub {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--primary-orange);
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-info {
            text-align: right;
        }

        .admin-name {
            font-weight: 600;
            color: var(--admin-text);
            font-size: 0.95rem;
        }

        .admin-role {
            font-size: 0.75rem;
            color: var(--primary-orange);
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-orange);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .logout-btn {
            padding: 6px 12px;
            background: transparent;
            border: 1px solid var(--admin-border);
            border-radius: 20px;
            color: var(--admin-text-secondary);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover {
            border-color: var(--danger);
            color: var(--danger);
        }

        /* ============================================ */
        /* ОСНОВНОЙ КОНТЕЙНЕР */
        /* ============================================ */
        .main-container {
            display: flex;
            flex: 1;
            min-height: calc(100vh - 71px);
        }

        /* ============================================ */
        /* БОКОВОЕ МЕНЮ (ДЕСКТОП) */
        /* ============================================ */
        .sidebar {
            width: 260px;
            background: var(--admin-surface);
            border-right: 1px solid var(--admin-border);
            position: sticky;
            top: 71px;
            height: calc(100vh - 71px);
            overflow-y: auto;
            transition: transform var(--transition-fast);
            scrollbar-width: thin;
            scrollbar-color: var(--primary-orange) var(--admin-surface);
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: var(--admin-surface);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-orange);
            border-radius: 2px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 2px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--admin-text-secondary);
            text-decoration: none;
            transition: all var(--transition-fast);
            border-left: 3px solid transparent;
            font-size: 0.95rem;
        }

        .sidebar-menu a:hover {
            background: var(--admin-surface-light);
            color: var(--admin-text);
            border-left-color: var(--primary-orange);
        }

        .sidebar-menu a.active {
            background: var(--admin-surface-light);
            color: var(--primary-orange);
            border-left-color: var(--primary-orange);
            font-weight: 600;
        }

        .sidebar-menu a i {
            width: 20px;
            font-size: 1.1rem;
        }

        /* ============================================ */
        /* ОСНОВНОЙ КОНТЕНТ */
        /* ============================================ */
        .content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background: var(--admin-bg);
        }

        /* ============================================ */
        /* МОБИЛЬНОЕ МЕНЮ (ВСПЛЫВАЮЩЕЕ) */
        /* ============================================ */
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 200;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-fast);
        }

        .mobile-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: var(--admin-surface);
            z-index: 300;
            transition: left var(--transition-fast);
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .mobile-menu.active {
            left: 0;
        }

        .mobile-menu-header {
            padding: 20px;
            border-bottom: 1px solid var(--admin-border);
            margin-bottom: 20px;
        }

        .mobile-menu .sidebar-menu {
            padding: 0;
        }

        /* ============================================ */
        /* ОБЩИЕ КОМПОНЕНТЫ */
        /* ============================================ */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .card {
            background: var(--admin-surface);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--admin-border);
            transition: all var(--transition-fast);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card-title {
            font-size: 0.9rem;
            color: var(--admin-text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-orange);
            margin-bottom: 5px;
        }

        .card-label {
            font-size: 0.85rem;
            color: var(--admin-text-secondary);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: var(--admin-surface);
            border-radius: 12px;
            overflow: hidden;
            font-size: 0.9rem;
        }

        .table th {
            text-align: left;
            padding: 12px 15px;
            background: var(--admin-surface-light);
            color: var(--admin-text);
            font-weight: 600;
        }

        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--admin-border);
            color: var(--admin-text-secondary);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(33, 150, 243, 0.1);
            color: var(--info);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            color: var(--admin-text);
            border: 1px solid var(--admin-border);
        }

        .btn-secondary:hover {
            border-color: var(--primary-orange);
            color: var(--primary-orange);
        }

        .btn-danger {
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--admin-border);
        }

        .btn-danger:hover {
            border-color: var(--danger);
            background: rgba(244, 67, 54, 0.1);
        }

        /* ============================================ */
        /* ПОДВАЛ */
        /* ============================================ */
        .admin-footer {
            background: var(--admin-surface);
            border-top: 1px solid var(--admin-border);
            padding: 15px 25px;
            text-align: center;
            font-size: 0.85rem;
            color: var(--admin-text-secondary);
        }

        /* ============================================ */
        /* АДАПТАЦИЯ */
        /* ============================================ */
        @media (max-width: 992px) {
            .sidebar {
                display: none;
            }

            .burger-menu {
                display: flex;
            }

            .mobile-menu-overlay {
                display: block;
            }

            .content {
                padding: 20px;
            }

            .card-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            .admin-header {
                padding: 12px 15px;
            }

            .logo-main {
                font-size: 1.5rem;
            }

            .logo-sub {
                font-size: 0.5rem;
            }

            .header-right {
                gap: 10px;
            }

            .admin-info {
                display: none;
            }

            .admin-avatar {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }

            .logout-btn span {
                display: none;
            }

            .content {
                padding: 15px;
            }

            .card-grid {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 0.8rem;
            }

            .table th,
            .table td {
                padding: 8px 10px;
            }
        }

        @media (max-width: 576px) {
            .admin-header {
                padding: 10px;
            }

            .logo-main {
                font-size: 1.3rem;
            }

            .content {
                padding: 10px;
            }

            .mobile-menu {
                width: 250px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- ШАПКА -->
    <header class="admin-header">
        <div class="header-left">
            <div class="burger-menu" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="logo">
                <div class="logo-main">ARTOBJECT</div>
                <div class="logo-sub">ADMIN PANEL</div>
            </div>
        </div>

        <div class="header-right">
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($adminName); ?></div>
                <div class="admin-role">Администратор</div>
            </div>
            <div class="admin-avatar">
                <i class="fas fa-user"></i>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Выйти</span>
            </a>
        </div>
    </header>

    <!-- МОБИЛЬНОЕ МЕНЮ (ОВЕРЛЕЙ) -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>

    <!-- МОБИЛЬНОЕ МЕНЮ -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="logo">
                <div class="logo-main">ARTOBJECT</div>
                <div class="logo-sub">ADMIN PANEL</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/admin/" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Дашборд
            </a></li>
            <li><a href="/admin/orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> Заказы
            </a></li>
            <li><a href="/admin/products.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i> Товары
            </a></li>
            <li><a href="/admin/categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Категории
            </a></li>
            <li><a href="/admin/artists.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'artists.php' ? 'active' : ''; ?>">
                <i class="fas fa-paint-brush"></i> Художники
            </a></li>
            <li><a href="/admin/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Пользователи
            </a></li>
            <li><a href="/admin/reviews.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Отзывы
            </a></li>
            <li><a href="/admin/questions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'questions.php' ? 'active' : ''; ?>">
    <i class="fas fa-question-circle"></i> Вопросы
</a></li>
            <li><a href="/admin/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Отчёты
            </a></li>
            <li><a href="/admin/newsletter.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'newsletter.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Рассылка
            </a></li>
        </ul>
    </div>

    <!-- ОСНОВНОЙ КОНТЕЙНЕР -->
    <div class="main-container">
        <!-- ДЕСКТОПНОЕ БОКОВОЕ МЕНЮ -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="/admin/" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Дашборд
                </a></li>
                <li><a href="/admin/orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i> Заказы
                </a></li>
                <li><a href="/admin/products.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i> Товары
                </a></li>
                <li><a href="/admin/categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> Категории
                </a></li>
                <li><a href="/admin/artists.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'artists.php' ? 'active' : ''; ?>">
                    <i class="fas fa-paint-brush"></i> Художники
                </a></li>
                <li><a href="/admin/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Пользователи
                </a></li>
                <li><a href="/admin/reviews.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i> Отзывы
                </a></li>
              <li><a href="/admin/questions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'questions.php' ? 'active' : ''; ?>">
    <i class="fas fa-question-circle"></i> Вопросы
</a></li>
                <li><a href="/admin/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> Отчёты
                </a></li>
                <li><a href="/admin/newsletter.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'newsletter.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Рассылка
                </a></li>
            </ul>
        </aside>

        <!-- КОНТЕНТ -->
        <main class="content">