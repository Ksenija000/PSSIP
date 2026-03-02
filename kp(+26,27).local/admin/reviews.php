<?php
// ============================================
// admin/reviews.php - МОДЕРАЦИЯ ОТЗЫВОВ
// ============================================
require_once 'includes/auth.php';

// Получаем параметры
$view = $_GET['view'] ?? 'list'; // list, view, edit
$review_id = $_GET['id'] ?? 0;
$status_filter = $_GET['status'] ?? '';
$product_filter = $_GET['product_id'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$search = $_GET['search'] ?? '';

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================

// Одобрение отзыва
if (isset($_GET['approve'])) {
    $review_id = (int)$_GET['approve'];
    
    $stmt = $db->prepare("UPDATE reviews SET status = 'published' WHERE id = ?");
    $stmt->execute([$review_id]);
    
    $_SESSION['success_message'] = "Отзыв опубликован";
    header("Location: reviews.php" . ($view == 'view' ? "?view=view&id=$review_id" : ""));
    exit;
}

// Скрытие отзыва
if (isset($_GET['hide'])) {
    $review_id = (int)$_GET['hide'];
    
    $stmt = $db->prepare("UPDATE reviews SET status = 'hidden' WHERE id = ?");
    $stmt->execute([$review_id]);
    
    $_SESSION['success_message'] = "Отзыв скрыт";
    header("Location: reviews.php" . ($view == 'view' ? "?view=view&id=$review_id" : ""));
    exit;
}

// Удаление отзыва
if (isset($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];
    
    $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$review_id]);
    
    $_SESSION['success_message'] = "Отзыв удалён";
    header("Location: reviews.php");
    exit;
}

// Сохранение отзыва после редактирования
if (isset($_POST['save_review'])) {
    $id = (int)($_POST['id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    
    $errors = [];
    if (empty($comment)) $errors[] = "Текст отзыва не может быть пустым";
    if ($rating < 1 || $rating > 5) $errors[] = "Оценка должна быть от 1 до 5";
    
    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, status = ? WHERE id = ?");
        $stmt->execute([$rating, $comment, $status, $id]);
        
        $_SESSION['success_message'] = "Отзыв обновлён";
        header("Location: reviews.php?view=view&id=$id");
        exit;
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header("Location: reviews.php?view=edit&id=$id");
        exit;
    }
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ ПРОСМОТРА/РЕДАКТИРОВАНИЯ
// ============================================
if (($view == 'view' || $view == 'edit') && $review_id > 0) {
    $stmt = $db->prepare("
        SELECT r.*, 
               u.fio as user_name, u.email as user_email,
               p.name as product_name, p.image as product_image,
               a.fio as artist_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN products p ON r.product_id = p.id
        LEFT JOIN artists a ON p.artist_id = a.id
        WHERE r.id = ?
    ");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$review) {
        header("Location: reviews.php");
        exit;
    }
    
    // Для редактирования проверяем статусы
    if ($view == 'edit') {
        $statuses = ['pending' => 'На модерации', 'published' => 'Опубликован', 'hidden' => 'Скрыт'];
    }
}

// ============================================
// ПОЛУЧАЕМ СПИСОК ТОВАРОВ И ПОЛЬЗОВАТЕЛЕЙ ДЛЯ ФИЛЬТРОВ
// ============================================
$products = $db->query("SELECT id, name FROM products ORDER BY name LIMIT 100")->fetchAll();
$users = $db->query("SELECT id, fio FROM users ORDER BY fio LIMIT 100")->fetchAll();

// ============================================
// ПОСТРОЕНИЕ ЗАПРОСА ДЛЯ СПИСКА ОТЗЫВОВ
// ============================================
$sql = "
    SELECT r.*, 
           u.fio as user_name, u.email as user_email,
           p.name as product_name,
           a.fio as artist_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    LEFT JOIN artists a ON p.artist_id = a.id
    WHERE 1=1
";
$params = [];

if ($status_filter) {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if ($product_filter) {
    $sql .= " AND r.product_id = ?";
    $params[] = $product_filter;
}

if ($user_filter) {
    $sql .= " AND r.user_id = ?";
    $params[] = $user_filter;
}

if ($search) {
    $sql .= " AND (r.comment LIKE ? OR p.name LIKE ? OR u.fio LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// ============================================
// СТАТИСТИКА
// ============================================
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$stats['pending'] = $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending' OR status IS NULL")->fetchColumn();
$stats['published'] = $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'published'")->fetchColumn();
$stats['hidden'] = $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'hidden'")->fetchColumn();
$stats['avg_rating'] = $db->query("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'published'")->fetchColumn();

// Сообщения
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

require_once 'includes/header.php';
?>

<style>
.rating-stars {
    color: var(--gold);
    font-size: 1.1rem;
}
.rating-stars i {
    margin-right: 2px;
}
</style>

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
<!-- ПРОСМОТР ОТЗЫВА -->
<!-- ============================================ -->
<?php if ($view == 'view' && isset($review)): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-star" style="color: var(--primary-orange);"></i>
        Отзыв #<?php echo $review['id']; ?>
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="reviews.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад к списку
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
    <!-- Левая колонка - информация о товаре и пользователе -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">🖼️ Товар</h3>
            
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <?php if ($review['product_image']): ?>
                    <img src="<?php echo htmlspecialchars($review['product_image']); ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 10px;">
                <?php else: ?>
                    <div style="width: 80px; height: 80px; background: var(--admin-surface-light); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-box" style="color: var(--admin-text-secondary); font-size: 2rem;"></i>
                    </div>
                <?php endif; ?>
                
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 5px;">
                        <a href="products.php?view=view&id=<?php echo $review['product_id']; ?>" style="color: var(--admin-text); text-decoration: none;">
                            <?php echo htmlspecialchars($review['product_name']); ?>
                        </a>
                    </div>
                    <?php if ($review['artist_name']): ?>
                        <div style="color: var(--primary-orange); font-size: 0.9rem;"><?php echo htmlspecialchars($review['artist_name']); ?></div>
                    <?php endif; ?>
                    <div style="margin-top: 10px;">
                        <a href="products.php?view=view&id=<?php echo $review['product_id']; ?>" class="btn btn-secondary" style="padding: 5px 10px;">
                            <i class="fas fa-eye"></i> К товару
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">👤 Пользователь</h3>
            
            <div style="margin-bottom: 10px;">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">ФИО</div>
                <div style="font-weight: 600;"><?php echo htmlspecialchars($review['user_name']); ?></div>
            </div>
            
            <div style="margin-bottom: 10px;">
                <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Email</div>
                <div><?php echo htmlspecialchars($review['user_email']); ?></div>
            </div>
            
            <div style="margin-top: 15px;">
                <a href="users.php?view=view&id=<?php echo $review['user_id']; ?>" class="btn btn-secondary" style="padding: 5px 10px;">
                    <i class="fas fa-eye"></i> Профиль
                </a>
            </div>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom: 15px;">⚡ Действия</h3>
            
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php if ($review['status'] != 'published'): ?>
                    <a href="reviews.php?approve=<?php echo $review['id']; ?>&view=view&id=<?php echo $review['id']; ?>" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-check"></i> Опубликовать
                    </a>
                <?php endif; ?>
                
                <?php if ($review['status'] != 'hidden'): ?>
                    <a href="reviews.php?hide=<?php echo $review['id']; ?>&view=view&id=<?php echo $review['id']; ?>" class="btn btn-secondary" style="width: 100%;">
                        <i class="fas fa-eye-slash"></i> Скрыть
                    </a>
                <?php endif; ?>
                
                <a href="reviews.php?delete=<?php echo $review['id']; ?>" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Удалить отзыв?')">
                    <i class="fas fa-trash"></i> Удалить
                </a>
            </div>
        </div>
    </div>
    
    <!-- Правая колонка - содержание отзыва -->
    <div>
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>📝 Содержание</h3>
                <div>
                    <?php
                    $status_class = '';
                    $status_text = '';
                    if ($review['status'] == 'published') {
                        $status_class = 'badge-success';
                        $status_text = 'Опубликован';
                    } elseif ($review['status'] == 'pending' || !$review['status']) {
                        $status_class = 'badge-warning';
                        $status_text = 'На модерации';
                    } else {
                        $status_class = 'badge-danger';
                        $status_text = 'Скрыт';
                    }
                    ?>
                    <span class="badge <?php echo $status_class; ?>" style="font-size: 0.9rem;"><?php echo $status_text; ?></span>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="color: var(--admin-text-secondary); margin-bottom: 5px;">Оценка</div>
                <div class="rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= $review['rating']): ?>
                            <i class="fas fa-star"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <span style="color: var(--admin-text); margin-left: 10px;">(<?php echo $review['rating']; ?>/5)</span>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="color: var(--admin-text-secondary); margin-bottom: 5px;">Дата</div>
                <div><?php echo date('d.m.Y H:i', strtotime($review['created_at'])); ?></div>
            </div>
            
            <div>
                <div style="color: var(--admin-text-secondary); margin-bottom: 5px;">Отзыв</div>
                <div style="background: var(--admin-surface-light); padding: 20px; border-radius: 15px; line-height: 1.7; white-space: pre-line;">
                    <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- СПИСОК ОТЗЫВОВ -->
<!-- ============================================ -->
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-star" style="color: var(--primary-orange);"></i>
        Модерация отзывов
    </h2>
</div>

<!-- Статистика -->
<div class="card-grid">
    <div class="card">
        <div class="card-title"><i class="fas fa-star"></i> Всего отзывов</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-clock" style="color: var(--warning);"></i> На модерации</div>
        <div class="card-value" style="color: var(--warning);"><?php echo $stats['pending']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle" style="color: var(--success);"></i> Опубликовано</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['published']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-eye-slash" style="color: var(--danger);"></i> Скрыто</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['hidden']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-line"></i> Средний рейтинг</div>
        <div class="card-value"><?php echo number_format($stats['avg_rating'], 1); ?></div>
    </div>
</div>

<!-- Фильтры и поиск -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Статус</label>
            <select name="status" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все статусы</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>На модерации</option>
                <option value="published" <?php echo $status_filter == 'published' ? 'selected' : ''; ?>>Опубликованные</option>
                <option value="hidden" <?php echo $status_filter == 'hidden' ? 'selected' : ''; ?>>Скрытые</option>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Товар</label>
            <select name="product_id" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все товары</option>
                <?php foreach ($products as $product): ?>
                <option value="<?php echo $product['id']; ?>" <?php echo $product_filter == $product['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($product['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Пользователь</label>
            <select name="user_id" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все пользователи</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['fio']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 2; min-width: 200px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск</label>
            <input type="text" name="search" placeholder="Текст отзыва, товар, пользователь" value="<?php echo htmlspecialchars($search); ?>" 
                   style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="reviews.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Сброс
            </a>
        </div>
    </form>
</div>

<!-- Список отзывов -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Товар</th>
                <th>Пользователь</th>
                <th>Рейтинг</th>
                <th>Отзыв</th>
                <th>Дата</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $review): ?>
                <tr>
                    <td>#<?php echo $review['id']; ?></td>
                    <td>
                        <a href="products.php?view=view&id=<?php echo $review['product_id']; ?>" style="color: var(--admin-text);">
                            <?php echo htmlspecialchars($review['product_name']); ?>
                        </a>
                        <?php if ($review['artist_name']): ?>
                            <br><small style="color: var(--primary-orange);"><?php echo htmlspecialchars($review['artist_name']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="users.php?view=view&id=<?php echo $review['user_id']; ?>" style="color: var(--admin-text);">
                            <?php echo htmlspecialchars($review['user_name']); ?>
                        </a>
                    </td>
                    <td>
                        <span class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $review['rating']): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </span>
                    </td>
                    <td><?php echo mb_substr(htmlspecialchars($review['comment']), 0, 50); ?>...</td>
                    <td><?php echo date('d.m.Y', strtotime($review['created_at'])); ?></td>
                    <td>
                        <?php
                        $status_class = '';
                        $status_text = '';
                        if ($review['status'] == 'published') {
                            $status_class = 'badge-success';
                            $status_text = 'Опубликован';
                        } elseif ($review['status'] == 'pending' || !$review['status']) {
                            $status_class = 'badge-warning';
                            $status_text = 'На модерации';
                        } else {
                            $status_class = 'badge-danger';
                            $status_text = 'Скрыт';
                        }
                        ?>
                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </td>
                    <td>
                        <a href="reviews.php?view=view&id=<?php echo $review['id']; ?>" class="btn btn-primary" style="padding: 5px 8px;" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if ($review['status'] != 'published'): ?>
                            <a href="reviews.php?approve=<?php echo $review['id']; ?>" class="btn btn-success" style="padding: 5px 8px;" title="Опубликовать">
                                <i class="fas fa-check"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($review['status'] != 'hidden'): ?>
                            <a href="reviews.php?hide=<?php echo $review['id']; ?>" class="btn btn-secondary" style="padding: 5px 8px;" title="Скрыть">
                                <i class="fas fa-eye-slash"></i>
                            </a>
                        <?php endif; ?>
                        
                        <a href="reviews.php?delete=<?php echo $review['id']; ?>" class="btn btn-danger" style="padding: 5px 8px;" title="Удалить" onclick="return confirm('Удалить отзыв?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-star" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Отзывы не найдены
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>