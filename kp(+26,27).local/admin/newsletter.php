<?php
// ============================================
// admin/newsletter.php - УПРАВЛЕНИЕ ПОДПИСКОЙ
// ============================================
require_once 'includes/auth.php';

// Получаем параметры фильтрации
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Обработка действий
if (isset($_GET['action'])) {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($_GET['action'] == 'delete' && $id > 0) {
        $db->prepare("DELETE FROM newsletter_subscribers WHERE id = ?")->execute([$id]);
        $_SESSION['success_message'] = "Подписчик удалён";
        header("Location: newsletter.php" . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    }
    
    if ($_GET['action'] == 'toggle' && $id > 0) {
        $stmt = $db->prepare("UPDATE newsletter_subscribers SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Статус изменён";
        header("Location: newsletter.php" . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    }
    
    if ($_GET['action'] == 'export') {
        // Экспорт в CSV с учётом фильтров
        $sql = "SELECT email, subscribed_at, is_active FROM newsletter_subscribers WHERE 1=1";
        $params = [];
        
        if ($status_filter !== '') {
            $sql .= " AND is_active = ?";
            $params[] = $status_filter;
        }
        
        if ($date_from) {
            $sql .= " AND DATE(subscribed_at) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND DATE(subscribed_at) <= ?";
            $params[] = $date_to;
        }
        
        if ($search) {
            $sql .= " AND email LIKE ?";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY subscribed_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $subscribers = $stmt->fetchAll();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subscribers_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, ['Email', 'Дата подписки', 'Статус'], ';');
        
        foreach ($subscribers as $sub) {
            fputcsv($output, [
                $sub['email'],
                date('d.m.Y H:i', strtotime($sub['subscribed_at'])),
                $sub['is_active'] ? 'Активен' : 'Отписан'
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}

// Получаем статистику
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
$stats['active'] = $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1")->fetchColumn();
$stats['inactive'] = $stats['total'] - $stats['active'];

// Получаем даты для статистики по дням
$daily_stats = $db->query("
    SELECT 
        DATE(subscribed_at) as date,
        COUNT(*) as count
    FROM newsletter_subscribers
    WHERE subscribed_at IS NOT NULL
    GROUP BY DATE(subscribed_at)
    ORDER BY date DESC
    LIMIT 30
")->fetchAll();

// Построение запроса с фильтрацией
$sql = "SELECT * FROM newsletter_subscribers WHERE 1=1";
$count_sql = "SELECT COUNT(*) FROM newsletter_subscribers WHERE 1=1";
$params = [];

if ($status_filter !== '') {
    $sql .= " AND is_active = ?";
    $count_sql .= " AND is_active = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $sql .= " AND DATE(subscribed_at) >= ?";
    $count_sql .= " AND DATE(subscribed_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(subscribed_at) <= ?";
    $count_sql .= " AND DATE(subscribed_at) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $sql .= " AND email LIKE ?";
    $count_sql .= " AND email LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY subscribed_at DESC";

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Получаем общее количество с учётом фильтров
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_subscribers = $stmt->fetchColumn();
$total_pages = ceil($total_subscribers / $limit);

// Добавляем LIMIT к основному запросу
$sql .= " LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$subscribers = $stmt->fetchAll();

// Функция для построения URL с сохранением параметров
function buildUrl($params = []) {
    $current = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '') {
            unset($current[$key]);
        } else {
            $current[$key] = $value;
        }
    }
    return '?' . http_build_query($current);
}

require_once 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div style="background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 12px 20px; border-radius: 30px; margin-bottom: 20px; border: 1px solid rgba(76, 175, 80, 0.3);">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-envelope" style="color: var(--primary-orange);"></i>
        Управление подпиской
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="?action=export<?php echo $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-primary">
            <i class="fas fa-download"></i> Экспорт
        </a>
        <a href="newsletter.php" class="btn btn-secondary">
            <i class="fas fa-sync"></i> Сброс
        </a>
    </div>
</div>

<!-- Статистика -->
<div class="card-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 25px;">
    <div class="card">
        <div class="card-title"><i class="fas fa-users"></i> Всего подписчиков</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle" style="color: var(--success);"></i> Активных</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['active']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-times-circle" style="color: var(--danger);"></i> Отписанных</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['inactive']; ?></div>
    </div>
</div>

<!-- График подписок (последние 30 дней) -->
<?php if (!empty($daily_stats)): ?>
<div class="card" style="margin-bottom: 25px;">
    <h3 style="margin-bottom: 15px;">📊 Подписки по дням</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <?php foreach ($daily_stats as $day): ?>
        <div style="flex: 1; min-width: 80px; text-align: center; padding: 10px; background: var(--admin-surface-light); border-radius: 8px;">
            <div style="font-size: 0.8rem; color: var(--admin-text-secondary);"><?php echo date('d.m', strtotime($day['date'])); ?></div>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary-orange);"><?php echo $day['count']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Фильтры -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Статус</label>
            <select name="status" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все</option>
                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Активные</option>
                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Отписанные</option>
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
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск по email</label>
            <input type="text" name="search" placeholder="Введите email..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="newsletter.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Сброс
            </a>
        </div>
    </form>
</div>

<!-- Таблица подписчиков -->
<div class="card">
    <div style="margin-bottom: 15px; color: var(--admin-text-secondary);">
        Найдено: <strong><?php echo $total_subscribers; ?></strong> подписчиков
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Дата подписки</th>
                <th>Статус</th>
                <th style="width: 150px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($subscribers) > 0): ?>
                <?php foreach ($subscribers as $sub): ?>
                <tr>
                    <td>#<?php echo $sub['id']; ?></td>
                    <td><?php echo htmlspecialchars($sub['email']); ?></td>
                    <td><?php echo $sub['subscribed_at'] ? date('d.m.Y H:i', strtotime($sub['subscribed_at'])) : '—'; ?></td>
                    <td>
                        <?php if ($sub['is_active']): ?>
                            <span class="badge badge-success">Активен</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Отписан</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?action=toggle&id=<?php echo $sub['id']; ?><?php echo $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-secondary" style="padding: 5px 10px;" title="<?php echo $sub['is_active'] ? 'Деактивировать' : 'Активировать'; ?>">
                            <i class="fas fa-<?php echo $sub['is_active'] ? 'ban' : 'check'; ?>"></i>
                        </a>
                        <a href="?action=delete&id=<?php echo $sub['id']; ?><?php echo $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-danger" style="padding: 5px 10px;" title="Удалить" onclick="return confirm('Удалить подписчика <?php echo htmlspecialchars($sub['email']); ?>?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-envelope-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        <?php if ($status_filter || $date_from || $date_to || $search): ?>
                            Подписчики по заданным критериям не найдены
                        <?php else: ?>
                            Подписчиков пока нет
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Пагинация -->
    <?php if ($total_pages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
        <?php if ($page > 1): ?>
            <a href="<?php echo buildUrl(['page' => $page - 1]); ?>" class="btn btn-secondary" style="padding: 5px 10px;">
                <i class="fas fa-chevron-left"></i>
            </a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i >= $page - 2 && $i <= $page + 2): ?>
                <a href="<?php echo buildUrl(['page' => $i]); ?>" class="btn btn-secondary" style="padding: 5px 10px; <?php echo $i == $page ? 'background: var(--primary-orange); color: white; border-color: var(--primary-orange);' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="<?php echo buildUrl(['page' => $page + 1]); ?>" class="btn btn-secondary" style="padding: 5px 10px;">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>