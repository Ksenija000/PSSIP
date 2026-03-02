<?php
// ============================================
// admin/users.php - УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ
// ============================================
require_once 'includes/auth.php';

// Получаем параметры
$view = $_GET['view'] ?? 'list'; // list, view, manage
$user_id = $_GET['id'] ?? 0;
$role_filter = $_GET['role'] ?? '';
$active_filter = $_GET['active'] ?? '';
$search = $_GET['search'] ?? '';

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================

// Управление пользователем (изменение роли и статуса)
if (isset($_POST['manage_user'])) {
    $id = (int)($_POST['id'] ?? 0);
    $role = $_POST['role'] ?? 'buyer';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $db->prepare("UPDATE users SET role = ?, is_active = ? WHERE id = ?");
    $stmt->execute([$role, $is_active, $id]);
    
    $_SESSION['success_message'] = "Настройки пользователя обновлены";
    header("Location: users.php?view=view&id=$id");
    exit;
}

// Блокировка/разблокировка пользователя (быстрое действие)
if (isset($_GET['toggle_active'])) {
    $user_id = (int)$_GET['toggle_active'];
    
    $stmt = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current = $stmt->fetchColumn();
    
    $new_value = $current ? 0 : 1;
    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_value, $user_id]);
    
    $_SESSION['success_message'] = "Статус пользователя изменён";
    header("Location: users.php" . ($view == 'view' ? "?view=view&id=$user_id" : ""));
    exit;
}

// Удаление пользователя
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // 1. Нельзя удалить самого себя
    if ($user_id == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "Нельзя удалить самого себя";
    } else {
        // 2. Проверяем, есть ли заказы у пользователя
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $orders_count = $stmt->fetchColumn();
        
        if ($orders_count > 0) {
            $_SESSION['error_message'] = "Нельзя удалить пользователя с заказами";
        } else {
            // Удаляем из избранного
            $db->prepare("DELETE FROM favorites_products WHERE user_id = ?")->execute([$user_id]);
            $db->prepare("DELETE FROM favorites_artists WHERE user_id = ?")->execute([$user_id]);
            
            // Удаляем из корзины
            $db->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$user_id]);
            
            // Удаляем отзывы
            $db->prepare("DELETE FROM reviews WHERE user_id = ?")->execute([$user_id]);
            
            // Удаляем самого пользователя
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $_SESSION['success_message'] = "Пользователь удалён";
        }
    }
    
    header("Location: users.php");
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ ПРОСМОТРА/УПРАВЛЕНИЯ
// ============================================
if (($view == 'view' || $view == 'manage') && $user_id > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: users.php");
        exit;
    }
    
    // Для просмотра получаем также заказы пользователя
    if ($view == 'view') {
        $stmt = $db->prepare("
            SELECT o.*, COUNT(oi.id) as items_count
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.order_date DESC
        ");
        $stmt->execute([$user_id]);
        $user_orders = $stmt->fetchAll();
        
        // Избранное
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM favorites_products WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $favorites_products = $stmt->fetchColumn();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM favorites_artists WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $favorites_artists = $stmt->fetchColumn();
        
        // Отзывы
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM reviews WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $reviews_count = $stmt->fetchColumn();
    }
}

// ============================================
// ПОЛУЧАЕМ СПИСОК ПОЛЬЗОВАТЕЛЕЙ
// ============================================
$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as orders_count,
           (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as reviews_count
    FROM users u
    WHERE 1=1
";
$params = [];

if ($role_filter) {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
}

if ($active_filter !== '') {
    $sql .= " AND u.is_active = ?";
    $params[] = $active_filter;
}

if ($search) {
    $sql .= " AND (u.fio LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY u.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ============================================
// СТАТИСТИКА
// ============================================
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['admins'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$stats['buyers'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'")->fetchColumn();
$stats['active'] = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$stats['inactive'] = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();
$stats['new_today'] = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Сообщения
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

require_once 'includes/header.php';
?>

<?php if ($success_message): ?>
    <div style="background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 12px 20px; border-radius: 30px; margin-bottom: 20px; border: 1px solid rgba(76, 175, 80, 0.3);">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div style="background: rgba(244, 67, 54, 0.1); color: #f44336; padding: 12px 20px; border-radius: 30px; margin-bottom: 20px; border: 1px solid rgba(244, 67, 54, 0.3);">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- ПРОСМОТР ПОЛЬЗОВАТЕЛЯ -->
<!-- ============================================ -->
<?php if ($view == 'view' && isset($user)): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-user" style="color: var(--primary-orange);"></i>
        Пользователь: <?php echo htmlspecialchars($user['fio']); ?>
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="users.php?view=manage&id=<?php echo $user['id']; ?>" class="btn btn-primary">
            <i class="fas fa-user-cog"></i> Управление
        </a>
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад к списку
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
    <!-- Левая колонка - информация -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">👤 Основная информация</h3>
            
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--admin-border);">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">ID</div>
                <div style="font-weight: 600;">#<?php echo $user['id']; ?></div>
            </div>
            
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--admin-border);">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">ФИО</div>
                <div style="font-weight: 600;"><?php echo htmlspecialchars($user['fio']); ?></div>
            </div>
            
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--admin-border);">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Email</div>
                <div style="font-weight: 600;"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--admin-border);">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Телефон</div>
                <div style="font-weight: 600;"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></div>
            </div>
            
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--admin-border);">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Роль</div>
                <div>
                    <?php if ($user['role'] == 'admin'): ?>
                        <span class="badge badge-warning">Администратор</span>
                    <?php else: ?>
                        <span class="badge badge-info">Покупатель</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--admin-border);">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Статус</div>
                <div>
                    <?php if ($user['is_active']): ?>
                        <span class="badge badge-success">Активен</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Заблокирован</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--admin-border);">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Дата регистрации</div>
                <div><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></div>
            </div>
            
            <?php if ($user['city'] || $user['address']): ?>
            <div style="margin-bottom: 15px;">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Адрес</div>
                <div>
                    <?php 
                    $addr = trim($user['city'] . ', ' . $user['address'], ', ');
                    echo htmlspecialchars($addr ?: '—'); 
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom: 15px;">📊 Статистика</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div style="text-align: center; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-orange);"><?php echo count($user_orders); ?></div>
                    <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">Заказов</div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-orange);"><?php echo $favorites_products + $favorites_artists; ?></div>
                    <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">В избранном</div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-orange);"><?php echo $reviews_count; ?></div>
                    <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">Отзывов</div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-orange);">—</div>
                    <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">Сумма покупок</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Правая колонка - заказы -->
    <div>
        <div class="card">
            <h3 style="margin-bottom: 15px;">📦 Заказы пользователя</h3>
            
            <?php if (count($user_orders) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>№ заказа</th>
                            <th>Дата</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo date('d.m.Y', strtotime($order['order_date'])); ?></td>
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
                            <td>
                                <a href="orders.php?view=details&id=<?php echo $order['id']; ?>" class="btn btn-primary" style="padding: 4px 8px;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: var(--admin-text-secondary); text-align: center; padding: 30px;">
                    <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    У пользователя нет заказов
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЕМ (РОЛЬ И СТАТУС) -->
<!-- ============================================ -->
<?php elseif ($view == 'manage' && isset($user)): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-user-cog" style="color: var(--primary-orange);"></i>
        Управление пользователем: <?php echo htmlspecialchars($user['fio']); ?>
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="users.php?view=view&id=<?php echo $user['id']; ?>" class="btn btn-secondary">
            <i class="fas fa-eye"></i> Просмотр
        </a>
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- Левая колонка - информация (только для чтения) -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">👤 Личные данные (только для просмотра)</h3>
        
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; color: var(--admin-text-secondary); width: 120px;">ФИО:</td>
                <td style="padding: 10px 0;"><?php echo htmlspecialchars($user['fio']); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px 0; color: var(--admin-text-secondary);">Email:</td>
                <td style="padding: 10px 0;"><?php echo htmlspecialchars($user['email']); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px 0; color: var(--admin-text-secondary);">Телефон:</td>
                <td style="padding: 10px 0;"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px 0; color: var(--admin-text-secondary);">Город:</td>
                <td style="padding: 10px 0;"><?php echo htmlspecialchars($user['city'] ?? '—'); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px 0; color: var(--admin-text-secondary);">Адрес:</td>
                <td style="padding: 10px 0;"><?php echo htmlspecialchars($user['address'] ?? '—'); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px 0; color: var(--admin-text-secondary);">Дата регистрации:</td>
                <td style="padding: 10px 0;"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
            </tr>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
            <i class="fas fa-info-circle" style="color: var(--primary-orange);"></i>
            <span style="margin-left: 10px; color: var(--admin-text-secondary);">Личные данные может изменять только сам пользователь в личном кабинете.</span>
        </div>
    </div>
    
    <!-- Правая колонка - управление (можно менять) -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">⚙️ Управление аккаунтом</h3>
        
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Роль</label>
                <select name="role" style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                    <option value="buyer" <?php echo $user['role'] == 'buyer' ? 'selected' : ''; ?>>Покупатель</option>
                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                    <span>Пользователь активен</span>
                </label>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" name="manage_user" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Сохранить изменения
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- СПИСОК ПОЛЬЗОВАТЕЛЕЙ -->
<!-- ============================================ -->
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-users" style="color: var(--primary-orange);"></i>
        Управление пользователями
    </h2>
    <!-- Убрана кнопка "Добавить пользователя" - админ не должен создавать пользователей -->
</div>

<!-- Статистика -->
<div class="card-grid">
    <div class="card">
        <div class="card-title"><i class="fas fa-users"></i> Всего</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-user-tie" style="color: var(--warning);"></i> Админов</div>
        <div class="card-value" style="color: var(--warning);"><?php echo $stats['admins']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-user" style="color: var(--info);"></i> Покупателей</div>
        <div class="card-value" style="color: var(--info);"><?php echo $stats['buyers']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle" style="color: var(--success);"></i> Активных</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['active']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-times-circle" style="color: var(--danger);"></i> Заблокировано</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['inactive']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-user-plus"></i> Новых сегодня</div>
        <div class="card-value"><?php echo $stats['new_today']; ?></div>
    </div>
</div>

<!-- Фильтры и поиск -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Роль</label>
            <select name="role" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все роли</option>
                <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Администраторы</option>
                <option value="buyer" <?php echo $role_filter == 'buyer' ? 'selected' : ''; ?>>Покупатели</option>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Статус</label>
            <select name="active" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все</option>
                <option value="1" <?php echo $active_filter === '1' ? 'selected' : ''; ?>>Активные</option>
                <option value="0" <?php echo $active_filter === '0' ? 'selected' : ''; ?>>Заблокированные</option>
            </select>
        </div>
        
        <div style="flex: 2; min-width: 200px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск</label>
            <input type="text" name="search" placeholder="Имя, email, телефон" value="<?php echo htmlspecialchars($search); ?>" 
                   style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Сброс
            </a>
        </div>
    </form>
</div>

<!-- Список пользователей -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>ФИО</th>
                <th>Email</th>
                <th>Телефон</th>
                <th>Роль</th>
                <th>Статус</th>
                <th>Заказы</th>
                <th>Отзывы</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>#<?php echo $user['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($user['fio']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
                    <td>
                        <?php if ($user['role'] == 'admin'): ?>
                            <span class="badge badge-warning">Админ</span>
                        <?php else: ?>
                            <span class="badge badge-info">Покупатель</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['is_active']): ?>
                            <span class="badge badge-success">Активен</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Блок</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $user['orders_count']; ?></td>
                    <td><?php echo $user['reviews_count']; ?></td>
                    <td>
                        <a href="users.php?view=view&id=<?php echo $user['id']; ?>" class="btn btn-primary" style="padding: 5px 8px;" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="users.php?view=manage&id=<?php echo $user['id']; ?>" class="btn btn-primary" style="padding: 5px 8px;" title="Управление">
                            <i class="fas fa-user-cog"></i>
                        </a>
                        <a href="users.php?toggle_active=<?php echo $user['id']; ?>" class="btn btn-secondary" style="padding: 5px 8px;" title="<?php echo $user['is_active'] ? 'Заблокировать' : 'Разблокировать'; ?>">
                            <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                        </a>
                        
                        <?php if ($user['orders_count'] == 0 && $user['id'] != $_SESSION['admin_id']): ?>
                            <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-danger" style="padding: 5px 8px;" title="Удалить" onclick="return confirm('Удалить пользователя <?php echo htmlspecialchars($user['fio']); ?>?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-danger" style="padding: 5px 8px; opacity: 0.3;" disabled title="<?php echo $user['orders_count'] > 0 ? 'Нельзя удалить (есть заказы)' : 'Нельзя удалить себя'; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Пользователи не найдены
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>