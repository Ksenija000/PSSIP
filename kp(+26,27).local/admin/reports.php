<?php
// ============================================
// admin/reports.php - ОТЧЁТЫ И АНАЛИТИКА
// ============================================
require_once 'includes/auth.php';

// Получаем параметры периода
$period = $_GET['period'] ?? 'month'; // today, week, month, quarter, year, custom
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';
$export_type = $_GET['export_type'] ?? '';

// Если выбран предопределённый период
switch ($period) {
    case 'today':
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        break;
    case 'week':
        $date_from = date('Y-m-d', strtotime('-7 days'));
        $date_to = date('Y-m-d');
        break;
    case 'month':
        $date_from = date('Y-m-d', strtotime('-30 days'));
        $date_to = date('Y-m-d');
        break;
    case 'quarter':
        $date_from = date('Y-m-d', strtotime('-90 days'));
        $date_to = date('Y-m-d');
        break;
    case 'year':
        $date_from = date('Y-m-d', strtotime('-365 days'));
        $date_to = date('Y-m-d');
        break;
}

// ============================================
// ЭКСПОРТ В EXCEL
// ============================================
if ($export && $export_type) {
    // Устанавливаем заголовки для скачивания CSV файла
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $export_type . '_' . date('Y-m-d') . '.csv"');
    
    // Создаём поток вывода
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM для Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Разделитель - точка с запятой для русского Excel
    $delimiter = ';';
    
    switch ($export_type) {
        case 'products':
            // Топ товаров
            fputcsv($output, ['Название', 'Категория', 'Художник', 'Продано шт.', 'Выручка BYN)', 'Цена (BYN)', 'В наличии'], $delimiter);
            
            $stmt = $db->prepare("
                SELECT 
                    p.name,
                    c.name as category_name,
                    a.fio as artist_name,
                    COALESCE(SUM(oi.quantity), 0) as sold_count,
                    COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
                    p.price,
                    p.stock_quantity
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.order_date) BETWEEN ? AND ?
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN artists a ON p.artist_id = a.id
                GROUP BY p.id
                ORDER BY revenue DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $products = $stmt->fetchAll();
            
            foreach ($products as $row) {
                fputcsv($output, [
                    $row['name'],
                    $row['category_name'] ?? '—',
                    $row['artist_name'] ?? '—',
                    $row['sold_count'],
                    number_format($row['revenue'], 2, '.', ''),
                    number_format($row['price'], 2, '.', ''),
                    $row['stock_quantity']
                ], $delimiter);
            }
            break;
            
        case 'categories':
            // Продажи по категориям
            fputcsv($output, ['Категория', 'Продано товаров (шт.)', 'Выручка (BYN)', 'Средняя цена (BYN)'], $delimiter);
            
            $stmt = $db->prepare("
                SELECT 
                    c.name,
                    COALESCE(SUM(oi.quantity), 0) as items_sold,
                    COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
                    COALESCE(AVG(p.price), 0) as avg_price
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY c.id
                ORDER BY revenue DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $categories = $stmt->fetchAll();
            
            foreach ($categories as $row) {
                fputcsv($output, [
                    $row['name'],
                    $row['items_sold'],
                    number_format($row['revenue'], 2, '.', ''),
                    number_format($row['avg_price'], 2, '.', '')
                ], $delimiter);
            }
            break;
            
        case 'artists':
            // Продажи по художникам
            fputcsv($output, ['Художник', 'Страна', 'Продано товаров (шт.)', 'Выручка (BYN)', 'Средняя цена (BYN)'], $delimiter);
            
            $stmt = $db->prepare("
                SELECT 
                    a.fio,
                    a.strana,
                    COALESCE(SUM(oi.quantity), 0) as items_sold,
                    COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
                    COALESCE(AVG(p.price), 0) as avg_price
                FROM artists a
                LEFT JOIN products p ON a.id = p.artist_id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY a.id
                ORDER BY revenue DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $artists = $stmt->fetchAll();
            
            foreach ($artists as $row) {
                fputcsv($output, [
                    $row['fio'],
                    $row['strana'] ?? '—',
                    $row['items_sold'],
                    number_format($row['revenue'], 2, '.', ''),
                    number_format($row['avg_price'], 2, '.', '')
                ], $delimiter);
            }
            break;
            
        case 'orders':
            // Детальный отчёт по заказам
            fputcsv($output, ['№ заказа', 'Дата', 'Покупатель', 'Email', 'Телефон', 'Товаров (шт.)', 'Сумма (BYN)', 'Статус', 'Статус оплаты'], $delimiter);
            
            $stmt = $db->prepare("
                SELECT 
                    o.id,
                    o.order_date,
                    u.fio,
                    u.email,
                    u.phone,
                    COUNT(oi.id) as items_count,
                    o.total_price,
                    o.status,
                    o.payment_status
                FROM orders o
                JOIN users u ON o.user_id = u.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY o.id
                ORDER BY o.order_date DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $orders = $stmt->fetchAll();
            
            foreach ($orders as $row) {
                fputcsv($output, [
                    '#' . $row['id'],
                    date('d.m.Y H:i', strtotime($row['order_date'])),
                    $row['fio'],
                    $row['email'],
                    $row['phone'] ?? '—',
                    $row['items_count'],
                    number_format($row['total_price'], 2, '.', ''),
                    $row['status'] ?? 'новый',
                    $row['payment_status'] ?? '—'
                ], $delimiter);
            }
            break;

        case 'customers':
            // Детальный отчёт по покупателям
            fputcsv($output, ['ID', 'ФИО', 'Email', 'Телефон', 'Город', 'Всего заказов', 'Всего потрачено (BYN)', 'Средний чек (BYN)', 'Дата регистрации'], $delimiter);
            
            $stmt = $db->prepare("
                SELECT 
                    u.id,
                    u.fio,
                    u.email,
                    u.phone,
                    u.city,
                    COUNT(DISTINCT o.id) as orders_count,
                    COALESCE(SUM(o.total_price), 0) as total_spent,
                    COALESCE(AVG(o.total_price), 0) as avg_order,
                    u.created_at
                FROM users u
                LEFT JOIN orders o ON u.id = o.user_id AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY u.id
                ORDER BY total_spent DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $customers = $stmt->fetchAll();
            
            foreach ($customers as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['fio'],
                    $row['email'],
                    $row['phone'] ?? '—',
                    $row['city'] ?? '—',
                    $row['orders_count'],
                    number_format($row['total_spent'], 2, '.', ''),
                    number_format($row['avg_order'], 2, '.', ''),
                    date('d.m.Y', strtotime($row['created_at']))
                ], $delimiter);
            }
            break;

        case 'daily':
            // Продажи по дням
            fputcsv($output, ['Дата', 'Заказов', 'Товаров продано', 'Выручка (BYN)', 'Средний чек (BYN)'], $delimiter);
            
            $stmt = $db->prepare("
                SELECT 
                    DATE(o.order_date) as date,
                    COUNT(DISTINCT o.id) as orders,
                    COALESCE(SUM(oi.quantity), 0) as items,
                    COALESCE(SUM(o.total_price), 0) as revenue,
                    COALESCE(AVG(o.total_price), 0) as avg_order
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY DATE(o.order_date)
                ORDER BY date
            ");
            $stmt->execute([$date_from, $date_to]);
            $daily = $stmt->fetchAll();
            
            foreach ($daily as $row) {
                fputcsv($output, [
                    date('d.m.Y', strtotime($row['date'])),
                    $row['orders'],
                    $row['items'],
                    number_format($row['revenue'], 2, '.', ''),
                    number_format($row['avg_order'], 2, '.', '')
                ], $delimiter);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// ============================================
// ПОЛУЧАЕМ ОСНОВНЫЕ ПОКАЗАТЕЛИ
// ============================================

// Выручка за период
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(o.total_price), 0) as revenue,
        COALESCE(AVG(o.total_price), 0) as avg_order,
        COALESCE(SUM(oi.quantity), 0) as items_sold
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE DATE(o.order_date) BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$main_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Выручка за всё время (для сравнения)
$all_time = $db->query("
    SELECT 
        COALESCE(SUM(total_price), 0) as revenue,
        COUNT(*) as orders_count
    FROM orders
")->fetch(PDO::FETCH_ASSOC);

// Продажи по дням за период
$stmt = $db->prepare("
    SELECT 
        DATE(o.order_date) as date,
        COUNT(DISTINCT o.id) as orders,
        COALESCE(SUM(o.total_price), 0) as revenue,
        COALESCE(SUM(oi.quantity), 0) as items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY DATE(o.order_date)
    ORDER BY date
");
$stmt->execute([$date_from, $date_to]);
$daily_sales = $stmt->fetchAll();

// Топ-10 товаров
// Топ товаров - только активные
$stmt = $db->prepare("
    SELECT 
        p.name,
        c.name as category_name,
        a.fio as artist_name,
        COALESCE(SUM(oi.quantity), 0) as sold_count,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
        p.price,
        p.stock_quantity
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.order_date) BETWEEN ? AND ?
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN artists a ON p.artist_id = a.id
    WHERE p.deleted_at IS NULL
    GROUP BY p.id
    ORDER BY revenue DESC
");
$stmt->execute([$date_from, $date_to]);
$top_products = $stmt->fetchAll();

// Топ-10 покупателей
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.fio,
        u.email,
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(o.total_price), 0) as total_spent,
        COALESCE(AVG(o.total_price), 0) as avg_order
    FROM users u
    JOIN orders o ON u.id = o.user_id
    WHERE DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$date_from, $date_to]);
$top_customers = $stmt->fetchAll();

// Продажи по категориям
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.name,
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(oi.quantity), 0) as items_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY revenue DESC
");
$stmt->execute([$date_from, $date_to]);
$category_sales = $stmt->fetchAll();

// Продажи по художникам
$stmt = $db->prepare("
    SELECT 
        a.id,
        a.fio,
        a.strana,
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(oi.quantity), 0) as items_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM artists a
    LEFT JOIN products p ON a.id = p.artist_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY a.id
    ORDER BY revenue DESC
");
$stmt->execute([$date_from, $date_to]);
$artist_sales = $stmt->fetchAll();

// Статусы заказов
$stmt = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        COALESCE(SUM(total_price), 0) as total
    FROM orders
    WHERE DATE(order_date) BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$date_from, $date_to]);
$order_statuses = $stmt->fetchAll();

// Новые пользователи за период
$stmt = $db->prepare("
    SELECT COUNT(*) FROM users 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$new_users = $stmt->fetchColumn();

// Активные пользователи (сделали заказ за период)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT user_id) FROM orders 
    WHERE DATE(order_date) BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$active_users = $stmt->fetchColumn();

require_once 'includes/header.php';
?>

<style>
.chart-container {
    height: 300px;
    margin-bottom: 30px;
}
.report-card {
    background: var(--admin-surface);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--admin-border);
}
.report-value {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--primary-orange);
    line-height: 1.2;
}
.report-label {
    color: var(--admin-text-secondary);
    font-size: 0.9rem;
}
.report-change {
    font-size: 0.9rem;
    margin-top: 5px;
}
.change-positive {
    color: var(--success);
}
.change-negative {
    color: var(--danger);
}
.export-btn {
    float: right;
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-chart-line" style="color: var(--primary-orange);"></i>
        Отчёты и аналитика
    </h2>
    
    <!-- Фильтр по периоду -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div style="display: flex; gap: 5px;">
            <a href="?period=today" class="btn <?php echo $period == 'today' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 8px 16px;">Сегодня</a>
            <a href="?period=week" class="btn <?php echo $period == 'week' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 8px 16px;">Неделя</a>
            <a href="?period=month" class="btn <?php echo $period == 'month' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 8px 16px;">Месяц</a>
            <a href="?period=quarter" class="btn <?php echo $period == 'quarter' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 8px 16px;">Квартал</a>
            <a href="?period=year" class="btn <?php echo $period == 'year' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 8px 16px;">Год</a>
        </div>
        
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="padding: 8px 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
            <span style="color: var(--admin-text-secondary);">—</span>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="padding: 8px 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">
                <i class="fas fa-sync"></i>
            </button>
        </form>
    </div>
</div>

<!-- Ключевые показатели -->
<div class="card-grid">
    <div class="card">
        
       <div class="card-title"><i class="fas fa-money-bill-wave"></i> Выручка</div>
<div class="card-value"><?php echo number_format($main_stats['revenue'], 0, '.', ' '); ?> BYN</div>
        <div class="card-label">за период</div>
        <div style="margin-top: 10px; font-size: 0.9rem; color: var(--admin-text-secondary);">
            Всего: <?php echo number_format($all_time['revenue'], 0, '.', ' '); ?> BYN
        </div>
    </div>
    
    <div class="card">
        <div class="card-title"><i class="fas fa-shopping-bag"></i> Заказы</div>
        <div class="card-value"><?php echo $main_stats['orders_count']; ?></div>
        <div class="card-label">за период</div>
        <div style="margin-top: 10px; font-size: 0.9rem; color: var(--admin-text-secondary);">
            Всего: <?php echo $all_time['orders_count']; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title"><i class="fas fa-calculator"></i> Средний чек</div>
        <div class="card-value"><?php echo number_format($main_stats['avg_order'], 0, '.', ' '); ?> BYN</div>
        <div class="card-label">за период</div>
    </div>
    
    <div class="card">
        <div class="card-title"><i class="fas fa-cube"></i> Продано товаров</div>
        <div class="card-value"><?php echo $main_stats['items_sold']; ?></div>
        <div class="card-label">за период</div>
    </div>
    
    <div class="card">
        <div class="card-title"><i class="fas fa-users"></i> Покупатели</div>
        <div class="card-value"><?php echo $active_users; ?></div>
        <div class="card-label">активных</div>
        <div style="margin-top: 10px; font-size: 0.9rem; color: var(--admin-text-secondary);">
            +<?php echo $new_users; ?> новых
        </div>
    </div>
    
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie"></i> Конверсия</div>
        <div class="card-value">
            <?php 
            $conversion = $active_users > 0 ? round(($main_stats['orders_count'] / $active_users) * 100, 1) : 0;
            echo $conversion; ?>%
        </div>
        <div class="card-label">заказов на покупателя</div>
    </div>
</div>

<!-- График продаж по дням -->
<div class="card" style="margin-top: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3><i class="fas fa-chart-bar" style="color: var(--primary-orange);"></i> Продажи по дням</h3>
       <a href="?export=1&export_type=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-secondary">
    <i class="fas fa-file-excel"></i> Excel
</a>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Заказов</th>
                    <th>Товаров</th>
                    <th>Выручка</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_sales as $day): ?>
                <tr>
                    <td><?php echo date('d.m.Y', strtotime($day['date'])); ?></td>
                    <td><?php echo $day['orders']; ?></td>
                    <td><?php echo $day['items']; ?></td>
                    <td><strong style="color: var(--primary-orange);"><?php echo number_format($day['revenue'], 2, '.', ' '); ?> BYN</strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($daily_sales)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px; color: var(--admin-text-secondary);">
                        Нет данных за выбранный период
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Две колонки: Топ товары и Топ покупатели -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 25px;">
    <!-- Топ-10 товаров -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3><i class="fas fa-crown" style="color: var(--primary-orange);"></i> Топ-10 товаров</h3>
            <a href="?export=1&export_type=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-secondary export-btn" style="float: none;">
                <i class="fas fa-file-excel"></i> Excel
            </a>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Продано</th>
                    <th>Выручка</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_products as $product): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                        <small style="color: var(--primary-orange);"><?php echo htmlspecialchars($product['artist_name'] ?? '—'); ?></small>
                    </td>
                    <td><?php echo $product['sold_count']; ?> шт.</td>
                    <td><strong style="color: var(--primary-orange);"><?php echo number_format($product['revenue'], 2, '.', ' '); ?> BYN</strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_products)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 30px; color: var(--admin-text-secondary);">
                        Нет данных
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Топ-10 покупателей -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3><i class="fas fa-users" style="color: var(--primary-orange);"></i> Топ-10 покупателей</h3>
           <a href="?export=1&export_type=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-secondary">
    <i class="fas fa-file-excel"></i> Excel
</a>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Покупатель</th>
                    <th>Заказов</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_customers as $customer): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($customer['fio']); ?></strong><br>
                        <small style="color: var(--admin-text-secondary);"><?php echo htmlspecialchars($customer['email']); ?></small>
                    </td>
                    <td><?php echo $customer['orders_count']; ?></td>
                    <td><strong style="color: var(--primary-orange);"><?php echo number_format($customer['total_spent'], 2, '.', ' '); ?> BYN</strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_customers)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 30px; color: var(--admin-text-secondary);">
                        Нет данных
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Две колонки: Категории и Художники -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 25px;">
    <!-- Продажи по категориям -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3><i class="fas fa-tags" style="color: var(--primary-orange);"></i> Продажи по категориям</h3>
           <a href="?export=1&export_type=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-secondary">
    <i class="fas fa-file-excel"></i> Excel
</a>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Категория</th>
                    <th>Продано</th>
                    <th>Выручка</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($category_sales as $category): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                    <td><?php echo $category['items_sold']; ?> шт.</td>
                    <td><strong style="color: var(--primary-orange);"><?php echo number_format($category['revenue'], 2, '.', ' '); ?> BYN</strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($category_sales)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 30px; color: var(--admin-text-secondary);">
                        Нет данных
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Продажи по художникам -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3><i class="fas fa-paint-brush" style="color: var(--primary-orange);"></i> Продажи по художникам</h3>
           <a href="?export=1&export_type=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-secondary">
    <i class="fas fa-file-excel"></i> Excel
</a>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Художник</th>
                    <th>Страна</th>
                    <th>Продано</th>
                    <th>Выручка</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($artist_sales as $artist): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($artist['fio']); ?></strong></td>
                    <td><?php echo htmlspecialchars($artist['strana'] ?? '—'); ?></td>
                    <td><?php echo $artist['items_sold']; ?> шт.</td>
                    <td><strong style="color: var(--primary-orange);"><?php echo number_format($artist['revenue'], 2, '.', ' '); ?> BYN</strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($artist_sales)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px; color: var(--admin-text-secondary);">
                        Нет данных
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Статусы заказов -->
<div class="card" style="margin-top: 25px;">
    <h3 style="margin-bottom: 20px;"><i class="fas fa-truck" style="color: var(--primary-orange);"></i> Статусы заказов</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
        <?php 
        $status_colors = [
            'processing' => 'badge-warning',
            'delivering' => 'badge-info',
            'delivered' => 'badge-success',
            'cancelled' => 'badge-danger'
        ];
        foreach ($order_statuses as $status): 
        ?>
        <div style="background: var(--admin-surface-light); border-radius: 12px; padding: 15px; text-align: center;">
            <span class="badge <?php echo $status_colors[$status['status']] ?? 'badge-secondary'; ?>" style="margin-bottom: 10px; display: inline-block;">
                <?php echo $status['status'] ?? 'без статуса'; ?>
            </span>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-orange);"><?php echo $status['count']; ?></div>
            <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">на сумму <?php echo number_format($status['total'], 0, '.', ' '); ?> BYN</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Сводка по периоду -->
<div class="card" style="margin-top: 25px;">
    <h3 style="margin-bottom: 15px;"><i class="fas fa-info-circle" style="color: var(--primary-orange);"></i> Сводка за период</h3>
    
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
        <div>
            <div style="color: var(--admin-text-secondary);">Выручка</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-orange);"><?php echo number_format($main_stats['revenue'], 2, '.', ' '); ?> BYN</div>
        </div>
        <div>
            <div style="color: var(--admin-text-secondary);">Заказы</div>
            <div style="font-size: 1.5rem; font-weight: 700;"><?php echo $main_stats['orders_count']; ?></div>
        </div>
        <div>
            <div style="color: var(--admin-text-secondary);">Товаров продано</div>
            <div style="font-size: 1.5rem; font-weight: 700;"><?php echo $main_stats['items_sold']; ?></div>
        </div>
        <div>
            <div style="color: var(--admin-text-secondary);">Средний чек</div>
            <div style="font-size: 1.5rem; font-weight: 700;"><?php echo number_format($main_stats['avg_order'], 2, '.', ' '); ?> BYN</div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>