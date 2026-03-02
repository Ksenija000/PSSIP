<?php
// ============================================
// admin/index.php - ДАШБОРД
// ============================================
require_once 'includes/auth.php';

// Статистика (такая же, как была)
$stats = [];
$stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$stats['new_users_today'] = $stmt->fetchColumn();
$stats['total_products'] = $db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
$stats['out_of_stock'] = $db->query("SELECT COUNT(*) FROM products WHERE (stock_quantity = 0 OR stock_quantity IS NULL) AND deleted_at IS NULL")->fetchColumn();
$stats['total_orders'] = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?");
$stmt->execute([$today]);
$stats['orders_today'] = $stmt->fetchColumn();
$stmt = $db->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE DATE(order_date) = ?");
$stmt->execute([$today]);
$stats['revenue_today'] = $stmt->fetchColumn();
$stmt = $db->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE())");
$stmt->execute();
$stats['revenue_month'] = $stmt->fetchColumn();
$stats['total_artists'] = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
$stats['total_categories'] = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$stats['pending_reviews'] = $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending' OR status IS NULL")->fetchColumn();
$stats['avg_rating'] = $db->query("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'published'")->fetchColumn();

$latest_orders = $db->query("
    SELECT o.*, u.fio as user_name 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.order_date DESC
    LIMIT 5
")->fetchAll();

// Популярные товары - только активные
$popular_products = $db->query("
    SELECT p.id, p.name, p.price, p.image, COUNT(oi.id) as sold_count
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    WHERE p.deleted_at IS NULL
    GROUP BY p.id
    ORDER BY sold_count DESC
    LIMIT 5
")->fetchAll();

$latest_reviews = $db->query("
    SELECT r.*, u.fio as user_name, p.name as product_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    ORDER BY r.created_at DESC
    LIMIT 5
")->fetchAll();

$order_status_stats = $db->query("
    SELECT status, COUNT(*) as count 
    FROM orders 
    GROUP BY status
")->fetchAll();

require_once 'includes/header.php';
?>

<!-- Приветствие -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="font-size: 1.8rem; margin-bottom: 5px;">Добро пожаловать, <?php echo htmlspecialchars($adminName); ?>!</h2>
        <p style="color: var(--admin-text-secondary);">Сегодня <?php echo date('d.m.Y'); ?></p>
    </div>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <div style="background: var(--admin-surface-light); padding: 8px 16px; border-radius: 30px; font-size: 0.9rem;">
            <i class="fas fa-shopping-bag" style="color: var(--primary-orange);"></i>
            Заказов: <strong><?php echo $stats['orders_today']; ?></strong>
        </div>
     <div style="background: var(--admin-surface-light); padding: 8px 16px; border-radius: 30px; font-size: 0.9rem;">
    <i class="fas fa-money-bill-wave" style="color: var(--primary-orange);"></i>
    Выручка: <strong><?php echo number_format($stats['revenue_today'], 2, '.', ' '); ?> BYN</strong>
</div>
    </div>
</div>

<!-- Карточки статистики -->
<div class="card-grid">
    <div class="card">
        <div class="card-title"><i class="fas fa-users"></i> Пользователи</div>
        <div class="card-value"><?php echo $stats['total_users']; ?></div>
        <div class="card-label">всего · +<?php echo $stats['new_users_today']; ?> сегодня</div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-box"></i> Товары</div>
        <div class="card-value"><?php echo $stats['total_products']; ?></div>
        <div class="card-label">в каталоге · <?php echo $stats['out_of_stock']; ?> нет в наличии</div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-truck"></i> Заказы</div>
        <div class="card-value"><?php echo $stats['total_orders']; ?></div>
        <div class="card-label">всего · <?php echo $stats['orders_today']; ?> сегодня</div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-star"></i> Отзывы</div>
        <div class="card-value"><?php echo $stats['pending_reviews']; ?></div>
        <div class="card-label">на модерации · рейтинг <?php echo number_format($stats['avg_rating'], 1); ?></div>
    </div>
</div>

<!-- Две колонки -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-bottom: 25px;">
    <!-- Последние заказы -->
    <div class="card">
        <h3 style="margin-bottom: 15px; font-size: 1.2rem;">📦 Последние заказы</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>№</th>
                    <th>Покупатель</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($latest_orders as $order): ?>
                <tr>
                    <td><a href="orders.php?id=<?php echo $order['id']; ?>" style="color: var(--primary-orange);">#<?php echo $order['id']; ?></a></td>
                    <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                    <td><?php echo number_format($order['total_price'], 2, '.', ' '); ?> BYN</td>
                    <td>
                        <?php
                        $status_class = 'badge-info';
                        if ($order['status'] == 'delivered') $status_class = 'badge-success';
                        elseif ($order['status'] == 'processing') $status_class = 'badge-warning';
                        elseif ($order['status'] == 'cancelled') $status_class = 'badge-danger';
                        ?>
                        <span class="badge <?php echo $status_class; ?>"><?php echo $order['status'] ?? 'новый'; ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="orders.php" class="btn btn-secondary" style="margin-top: 15px; width: 100%;">Все заказы</a>
    </div>

    <!-- Популярные товары -->
    <div class="card">
        <h3 style="margin-bottom: 15px; font-size: 1.2rem;">🔥 Популярные товары</h3>
        <?php foreach ($popular_products as $index => $product): ?>
        <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: var(--admin-surface-light); border-radius: 8px; margin-bottom: 8px;">
            <div style="width: 30px; height: 30px; background: var(--gradient-orange); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;"><?php echo $index + 1; ?></div>
            <div style="flex: 1;"><?php echo htmlspecialchars($product['name']); ?></div>
            <div style="font-weight: 700; color: var(--primary-orange);"><?php echo $product['sold_count']; ?></div>
        </div>
        <?php endforeach; ?>
        <a href="products.php" class="btn btn-secondary" style="margin-top: 10px; width: 100%;">Все товары</a>
    </div>
</div>

<!-- Последние отзывы -->
<div class="card">
    <h3 style="margin-bottom: 15px; font-size: 1.2rem;">⭐ Последние отзывы</h3>
    <?php if (count($latest_reviews) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>Товар</th>
                <th>Пользователь</th>
                <th>Рейтинг</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($latest_reviews as $review): ?>
            <tr>
                <td><?php echo htmlspecialchars($review['product_name']); ?></td>
                <td><?php echo htmlspecialchars($review['user_name']); ?></td>
                <td>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fa<?php echo $i <= $review['rating'] ? 's' : 'r'; ?> fa-star" style="color: <?php echo $i <= $review['rating'] ? 'var(--gold)' : 'var(--admin-border)'; ?>"></i>
                    <?php endfor; ?>
                </td>
                <td>
                    <?php if ($review['status'] == 'published'): ?>
                        <span class="badge badge-success">Опубликован</span>
                    <?php elseif ($review['status'] == 'pending' || !$review['status']): ?>
                        <span class="badge badge-warning">На модерации</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Скрыт</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="reviews.php?action=approve&id=<?php echo $review['id']; ?>" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.75rem;">✓</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="reviews.php" class="btn btn-secondary" style="margin-top: 15px; width: 100%;">Все отзывы</a>
    <?php else: ?>
    <p style="color: var(--admin-text-secondary); text-align: center; padding: 20px;">Пока нет отзывов</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>