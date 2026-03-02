<?php
// ============================================
// admin/orders.php - УПРАВЛЕНИЕ ЗАКАЗАМИ (с поддержкой гостей)
// ============================================
require_once 'includes/auth.php';

// Получаем параметры фильтрации
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'list'; // list или details
$order_id = $_GET['id'] ?? 0;

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================

// Изменение статуса заказа
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    
    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    $_SESSION['success_message'] = "Статус заказа #$order_id изменён на '$new_status'";
    header("Location: orders.php" . ($order_id ? "?view=details&id=$order_id" : ""));
    exit;
}

// Удаление заказа
if (isset($_GET['delete'])) {
    $order_id = (int)$_GET['delete'];
    
    // Сначала удаляем связанные позиции
    $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
    // Потом сам заказ
    $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
    
    $_SESSION['success_message'] = "Заказ #$order_id удалён";
    header("Location: orders.php");
    exit;
}

// ============================================
// ПОЛУЧЕНИЕ ДАННЫХ
// ============================================

// Если смотрим детали конкретного заказа
if ($view === 'details' && $order_id > 0) {
    // Информация о заказе
    $stmt = $db->prepare("
        SELECT o.*, 
               u.fio, u.email as user_email, u.phone, u.city, u.address 
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: orders.php");
        exit;
    }
    
    // Товары в заказе
    $stmt = $db->prepare("
        SELECT oi.*, p.name, p.image, p.artist_id, a.fio as artist_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN artists a ON p.artist_id = a.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получаем статистику по заказам
$stats = [];

// Всего заказов
$stats['total'] = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Заказов сегодня
$stats['today'] = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()")->fetchColumn();

// Заказов в обработке
$stats['processing'] = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();

// Заказов в доставке
$stats['delivering'] = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'delivering'")->fetchColumn();

// Доставленных
$stats['delivered'] = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();

// Отменённых
$stats['cancelled'] = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();

// Выручка за месяц
$stmt = $db->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE())");
$stats['month_revenue'] = $stmt->fetchColumn();

// Средний чек
$stmt = $db->query("SELECT COALESCE(AVG(total_price), 0) FROM orders");
$stats['avg_order'] = $stmt->fetchColumn();

// Статистика по гостевым заказам
$stats['guest_orders'] = $db->query("SELECT COUNT(*) FROM orders WHERE user_id IS NULL")->fetchColumn();

// Строим запрос для списка заказов с фильтрацией
$sql = "
    SELECT o.*, 
           u.fio, 
           u.email as user_email,
           CASE 
               WHEN o.user_id IS NULL THEN 'Гость'
               ELSE u.fio 
           END as customer_name,
           CASE 
               WHEN o.user_id IS NULL THEN o.guest_email
               ELSE u.email 
           END as contact_email,
           COUNT(oi.id) as items_count
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE 1=1
";
$params = [];

if ($status_filter) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $sql .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $sql .= " AND (o.id LIKE ? OR u.fio LIKE ? OR u.email LIKE ? OR o.guest_email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " GROUP BY o.id ORDER BY o.order_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Сообщения об успехе/ошибке
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

require_once 'includes/header.php';
?>

<?php if ($success_message): ?>
    <div style="background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 12px 20px; border-radius: 30px; margin-bottom: 20px; border: 1px solid rgba(76, 175, 80, 0.3);">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<!-- ============================================ 
 ДЕТАЛИ ЗАКАЗА
============================================ -->
<?php if ($view === 'details' && isset($order)): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-truck" style="color: var(--primary-orange);"></i>
        Заказ #<?php echo $order['id']; ?>
        <?php if (!$order['user_id']): ?>
            <span style="font-size: 0.9rem; background: rgba(255, 90, 48, 0.1); color: var(--primary-orange); padding: 5px 15px; border-radius: 20px; margin-left: 15px;">
                <i class="fas fa-user"></i> Гостевой заказ
            </span>
        <?php endif; ?>
    </h2>
    <a href="orders.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Назад к списку
    </a>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    <!-- Левая колонка - товары -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">🛒 Состав заказа</h3>
        
        <?php foreach ($order_items as $item): ?>
        <div style="display: flex; gap: 15px; padding: 15px 0; border-bottom: 1px solid var(--admin-border);">
            <div style="width: 60px; height: 60px; background: var(--admin-surface-light); border-radius: 8px; overflow: hidden;">
                <?php if ($item['image']): ?>
                    <img src="<?php echo $item['image']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="fas fa-box" style="font-size: 2rem; color: var(--admin-text-secondary); display: flex; align-items: center; justify-content: center; height: 100%;"></i>
                <?php endif; ?>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600;"><?php echo htmlspecialchars($item['name'] ?? 'Товар удалён'); ?></div>
                <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">
                    <?php echo htmlspecialchars($item['artist_name'] ?? 'Художник неизвестен'); ?> · 
                    <?php echo $item['quantity']; ?> шт. · 
                    <?php echo number_format($item['price'], 2, '.', ' '); ?> BYN
                </div>
            </div>
            <div style="font-weight: 700; color: var(--primary-orange);">
                <?php echo number_format($item['price'] * $item['quantity'], 2, '.', ' '); ?> BYN
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--admin-border);">
            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700;">
                <span>Итого:</span>
                <span style="color: var(--primary-orange);"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> BYN</span>
            </div>
        </div>
    </div>

    <!-- Правая колонка - информация -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">📋 Статус заказа</h3>
            
            <form method="POST">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <select name="status" style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text); margin-bottom: 15px;">
                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>⏳ В обработке</option>
                    <option value="delivering" <?php echo $order['status'] == 'delivering' ? 'selected' : ''; ?>>🚚 Доставляется</option>
                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>✅ Доставлен</option>
                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Отменён</option>
                </select>
                <button type="submit" name="update_status" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Обновить статус
                </button>
            </form>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">
                <?php if ($order['user_id']): ?>
                    👤 Покупатель (зарегистрированный)
                <?php else: ?>
                    👤 Покупатель (гость)
                <?php endif; ?>
            </h3>
            <?php if ($order['user_id']): ?>
                <p><i class="fas fa-user" style="color: var(--primary-orange); width: 20px;"></i> <?php echo htmlspecialchars($order['fio']); ?></p>
                <p><i class="fas fa-envelope" style="color: var(--primary-orange); width: 20px;"></i> <?php echo htmlspecialchars($order['user_email']); ?></p>
            <?php else: ?>
                <p><i class="fas fa-user" style="color: var(--primary-orange); width: 20px;"></i> Гость</p>
                <p><i class="fas fa-envelope" style="color: var(--primary-orange); width: 20px;"></i> <?php echo htmlspecialchars($order['guest_email'] ?: 'Не указан'); ?></p>
            <?php endif; ?>
            <p><i class="fas fa-phone" style="color: var(--primary-orange); width: 20px;"></i> <?php echo htmlspecialchars($order['phone'] ?? '—'); ?></p>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">🏠 Доставка</h3>
            <p><i class="fas fa-map-marker-alt" style="color: var(--primary-orange); width: 20px;"></i> 
                <?php 
                $address = trim($order['city'] . ', ' . $order['address'], ', ');
                echo htmlspecialchars($address ?: $order['shipping_address'] ?: '—'); 
                ?>
            </p>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">💳 Оплата</h3>
            <p><i class="fas fa-credit-card" style="color: var(--primary-orange); width: 20px;"></i> 
                <?php 
                $methods = ['card' => 'Картой онлайн', 'cash' => 'Наличными', 'bank' => 'Безналичный'];
                echo $methods[$order['payment_method']] ?? $order['payment_method'] ?? '—';
                ?>
            </p>
            <p>
                <span class="badge <?php 
                    echo $order['payment_status'] == 'paid' ? 'badge-success' : 
                        ($order['payment_status'] == 'pending' ? 'badge-warning' : 'badge-danger'); 
                ?>">
                    <?php echo $order['payment_status'] == 'paid' ? 'Оплачено' : 
                        ($order['payment_status'] == 'pending' ? 'Ожидает' : 'Не оплачено'); ?>
                </span>
            </p>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 15px;">📅 Даты</h3>
            <p><i class="fas fa-calendar-plus" style="color: var(--primary-orange); width: 20px;"></i> Создан: <?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?></p>
            <p><i class="fas fa-calendar-check" style="color: var(--primary-orange); width: 20px;"></i> Обновлён: <?php echo date('d.m.Y H:i', strtotime($order['updated_at'])); ?></p>
        </div>

        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <a href="orders.php?delete=<?php echo $order['id']; ?>" class="btn btn-danger" style="flex: 1;" onclick="return confirm('Удалить заказ #<?php echo $order['id']; ?>?')">
                <i class="fas fa-trash"></i> Удалить
            </a>
        </div>
    </div>
</div>

<!-- ============================================
ПИСОК ЗАКАЗОВ
============================================ -->
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-truck" style="color: var(--primary-orange);"></i>
        Управление заказами
    </h2>
</div>

<!-- Статистика -->
<div class="card-grid">
    <div class="card">
        <div class="card-title"><i class="fas fa-shopping-bag"></i> Всего</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-clock"></i> В обработке</div>
        <div class="card-value" style="color: var(--warning);"><?php echo $stats['processing']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-truck"></i> Доставляется</div>
        <div class="card-value" style="color: var(--info);"><?php echo $stats['delivering']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle"></i> Доставлено</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['delivered']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-times-circle"></i> Отменено</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['cancelled']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-user"></i> Гостевые</div>
        <div class="card-value" style="color: var(--primary-orange);"><?php echo $stats['guest_orders']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-line"></i> Выручка за месяц</div>
        <div class="card-value"><?php echo number_format($stats['month_revenue'], 0, '.', ' '); ?> BYN</div>
        <div class="card-label">ср. чек <?php echo number_format($stats['avg_order'], 0, '.', ' '); ?> BYN</div>
    </div>
</div>

<!-- Фильтры -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Статус</label>
            <select name="status" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все статусы</option>
                <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>В обработке</option>
                <option value="delivering" <?php echo $status_filter == 'delivering' ? 'selected' : ''; ?>>Доставляется</option>
                <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Доставлен</option>
                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Отменён</option>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Дата с</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Дата по</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="flex: 2; min-width: 200px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск</label>
            <input type="text" name="search" placeholder="№ заказа, имя, email гостя..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Сброс
            </a>
        </div>
    </form>
</div>

<!-- Список заказов -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>№ заказа</th>
                <th>Дата</th>
                <th>Покупатель</th>
                <th>Контакт</th>
                <th>Товаров</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th>Оплата</th>
                <th>Тип</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                    <td><?php echo date('d.m.Y', strtotime($order['order_date'])); ?></td>
                    <td>
                        <?php echo htmlspecialchars($order['customer_name']); ?>
                    </td>
                    <td>
                        <small style="color: var(--admin-text-secondary);"><?php echo htmlspecialchars($order['contact_email'] ?: '—'); ?></small>
                    </td>
                    <td><?php echo $order['items_count']; ?></td>
                    <td><strong style="color: var(--primary-orange);"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> BYN</strong></td>
                    <td>
                        <?php
                        $status_class = '';
                        $status_text = '';
                        switch ($order['status']) {
                            case 'processing':
                                $status_class = 'badge-warning';
                                $status_text = '⏳ В обработке';
                                break;
                            case 'delivering':
                                $status_class = 'badge-info';
                                $status_text = '🚚 Доставляется';
                                break;
                            case 'delivered':
                                $status_class = 'badge-success';
                                $status_text = '✅ Доставлен';
                                break;
                            case 'cancelled':
                                $status_class = 'badge-danger';
                                $status_text = '❌ Отменён';
                                break;
                            default:
                                $status_class = 'badge-secondary';
                                $status_text = $order['status'] ?? '—';
                        }
                        ?>
                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </td>
                    <td>
                        <span class="badge <?php echo $order['payment_status'] == 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $order['payment_status'] == 'paid' ? 'Оплачено' : 'Ожидает'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!$order['user_id']): ?>
                            <span class="badge" style="background: rgba(255, 90, 48, 0.1); color: var(--primary-orange);">
                                <i class="fas fa-user"></i> Гость
                            </span>
                        <?php else: ?>
                            <span class="badge badge-info">
                                <i class="fas fa-user-check"></i> Зарегистр.
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="orders.php?view=details&id=<?php echo $order['id']; ?>" class="btn btn-primary" style="padding: 5px 10px;">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Заказы не найдены
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>