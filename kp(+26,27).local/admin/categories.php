<?php
// ============================================
// admin/categories.php - УПРАВЛЕНИЕ КАТЕГОРИЯМИ
// ============================================
require_once 'includes/auth.php';

// Получаем параметры
$view = $_GET['view'] ?? 'list'; // list, add, edit
$category_id = $_GET['id'] ?? 0;
$search = $_GET['search'] ?? '';

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================

// Сохранение категории (добавление/редактирование)
if (isset($_POST['save_category'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $opisanie = trim($_POST['opisanie'] ?? '');
    
    if (empty($name)) {
        $_SESSION['error_message'] = "Название категории обязательно";
    } else {
        if ($id > 0) {
            // Обновление существующей категории
            $stmt = $db->prepare("UPDATE categories SET name = ?, opisanie = ? WHERE id = ?");
            $stmt->execute([$name, $opisanie, $id]);
            $_SESSION['success_message'] = "Категория '$name' обновлена";
        } else {
            // Добавление новой категории
            $stmt = $db->prepare("INSERT INTO categories (name, opisanie) VALUES (?, ?)");
            $stmt->execute([$name, $opisanie]);
            $_SESSION['success_message'] = "Категория '$name' добавлена";
        }
    }
    
    header("Location: categories.php");
    exit;
}

// Удаление категории
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    // Проверяем, есть ли товары в наличии в этой категории
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM products 
        WHERE category_id = ? AND deleted_at IS NULL AND stock_quantity > 0
    ");
    $stmt->execute([$category_id]);
    $in_stock_products = $stmt->fetchColumn();
    
    if ($in_stock_products > 0) {
        $_SESSION['error_message'] = "Нельзя удалить категорию, в которой есть товары в наличии";
    } else {
        // Удаляем категорию
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $_SESSION['success_message'] = "Категория удалена";
    }
    
    header("Location: categories.php");
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ РЕДАКТИРОВАНИЯ
// ============================================
if ($view == 'edit' && $category_id > 0) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        header("Location: categories.php");
        exit;
    }
}

// ============================================
// ПОЛУЧАЕМ СПИСОК КАТЕГОРИЙ С ДЕТАЛЬНОЙ ИНФОРМАЦИЕЙ
// ============================================
$sql = "
    SELECT 
        c.*,
        COUNT(p.id) as total_products,
        COUNT(CASE WHEN p.deleted_at IS NULL AND p.stock_quantity > 0 THEN 1 END) as in_stock_count,
        COUNT(CASE WHEN p.deleted_at IS NULL AND (p.stock_quantity = 0 OR p.stock_quantity IS NULL) THEN 1 END) as out_of_stock_count,
        COUNT(CASE WHEN p.deleted_at IS NOT NULL THEN 1 END) as deleted_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
";
$params = [];

if ($search) {
    $sql .= " WHERE c.name LIKE ? OR c.opisanie LIKE ?";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " GROUP BY c.id ORDER BY c.name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

// ============================================
// СТАТИСТИКА (ТОЛЬКО ТОВАРЫ В НАЛИЧИИ)
// ============================================
$stats = [];

// Всего категорий
$stats['total'] = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Категории с товарами в наличии (stock_quantity > 0)
$stats['with_products'] = $db->query("
    SELECT COUNT(DISTINCT category_id) 
    FROM products 
    WHERE category_id IS NOT NULL AND deleted_at IS NULL AND stock_quantity > 0
")->fetchColumn();

// Пустые категории (нет товаров в наличии)
$stats['empty'] = $stats['total'] - $stats['with_products'];

// Сообщения
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

require_once 'includes/header.php';
?>

<style>
.badge-container {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}
.badge-in-stock {
    background: rgba(76, 175, 80, 0.1);
    color: #4CAF50;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-out-of-stock {
    background: rgba(255, 152, 0, 0.1);
    color: #ff9800;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-deleted {
    background: rgba(158, 158, 158, 0.1);
    color: #9e9e9e;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.category-row {
    transition: all var(--transition-fast);
}
.category-row.only-deleted {
    opacity: 0.7;
    background: rgba(158, 158, 158, 0.02);
}
.category-row.only-deleted:hover {
    opacity: 0.9;
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
<!-- ФОРМА ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ КАТЕГОРИИ -->
<!-- ============================================ -->
<?php if ($view == 'add' || ($view == 'edit' && isset($category))): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-tag" style="color: var(--primary-orange);"></i>
        <?php echo $view == 'add' ? 'Добавление категории' : 'Редактирование категории'; ?>
    </h2>
    <a href="categories.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Назад к списку
    </a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST">
        <input type="hidden" name="id" value="<?php echo $category['id'] ?? 0; ?>">
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; color: var(--admin-text-secondary);">Название категории *</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>" 
                   style="width: 100%; padding: 12px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; color: var(--admin-text-secondary);">Описание</label>
            <textarea name="opisanie" rows="5" style="width: 100%; padding: 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 15px; color: var(--admin-text);"><?php echo htmlspecialchars($category['opisanie'] ?? ''); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 15px; justify-content: flex-end;">
            <button type="submit" name="save_category" class="btn btn-primary">
                <i class="fas fa-save"></i> Сохранить
            </button>
            <a href="categories.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Отмена
            </a>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- СПИСОК КАТЕГОРИЙ -->
<!-- ============================================ -->
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-tags" style="color: var(--primary-orange);"></i>
        Управление категориями
    </h2>
    <a href="categories.php?view=add" class="btn btn-primary">
        <i class="fas fa-plus"></i> Добавить категорию
    </a>
</div>

<!-- Статистика - ТРИ РАЗДЕЛА -->
<div class="card-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 25px;">
    <div class="card">
        <div class="card-title"><i class="fas fa-tags"></i> Всего категорий</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle" style="color: var(--success);"></i> С товарами</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['with_products']; ?></div>
        <div class="card-label">есть в наличии</div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-times-circle" style="color: var(--danger);"></i> Пустые</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['empty']; ?></div>
        <div class="card-label">нет в наличии</div>
    </div>
</div>

<!-- Поиск -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
        <div style="flex: 1;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск категорий</label>
            <input type="text" name="search" placeholder="Название или описание" value="<?php echo htmlspecialchars($search); ?>" 
                   style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Найти
            </button>
            <?php if ($search): ?>
                <a href="categories.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Сброс
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Список категорий -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Название</th>
                <th>Описание</th>
                <th>Товары</th>
                <th style="width: 150px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($categories) > 0): ?>
                <?php foreach ($categories as $cat): 
                    $has_in_stock = $cat['in_stock_count'] > 0;
                    $has_out_of_stock = $cat['out_of_stock_count'] > 0;
                    $has_deleted = $cat['deleted_count'] > 0;
                    $total_active = $cat['in_stock_count'] + $cat['out_of_stock_count'];
                    
                    // Определяем, есть ли вообще какие-то активные товары (не удалённые)
                    $has_active = $total_active > 0;
                ?>
                <tr class="category-row <?php echo (!$has_active && $has_deleted) ? 'only-deleted' : ''; ?>">
                    <td>#<?php echo $cat['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                        <?php if (!$has_active && $has_deleted): ?>
                            <span style="margin-left: 8px; font-size: 0.75rem; color: var(--warning);">
                                <i class="fas fa-clock"></i> только удалённые
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($cat['opisanie']): ?>
                            <?php echo mb_substr(htmlspecialchars($cat['opisanie']), 0, 100); ?>...
                        <?php else: ?>
                            <span style="color: var(--admin-text-secondary);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="badge-container">
                            <?php if ($cat['in_stock_count'] > 0): ?>
                                <span class="badge-in-stock" title="В наличии">
                                    <i class="fas fa-check-circle"></i> <?php echo $cat['in_stock_count']; ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($cat['out_of_stock_count'] > 0): ?>
                                <span class="badge-out-of-stock" title="Нет в наличии">
                                    <i class="fas fa-clock"></i> <?php echo $cat['out_of_stock_count']; ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($cat['deleted_count'] > 0): ?>
                                <span class="badge-deleted" title="Удалены из каталога">
                                    <i class="fas fa-trash"></i> <?php echo $cat['deleted_count']; ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($cat['total_products'] == 0): ?>
                                <span class="badge badge-danger">0</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Информационная строка с итогами -->
                        <?php if ($total_active > 0): ?>
                            <div style="font-size: 0.75rem; color: var(--admin-text-secondary); margin-top: 5px;">
                                <i class="fas fa-box"></i> Всего активно: <?php echo $total_active; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="categories.php?view=edit&id=<?php echo $cat['id']; ?>" class="btn btn-primary" style="padding: 5px 10px;" title="Редактировать">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <?php if ($cat['in_stock_count'] == 0): // Нет товаров в наличии ?>
                            <a href="categories.php?delete=<?php echo $cat['id']; ?>" 
                               class="btn btn-danger" 
                               style="padding: 5px 10px;" 
                               title="Удалить" 
                               onclick="return confirm('Удалить категорию <?php echo htmlspecialchars($cat['name']); ?>?\n\n<?php 
                                   if ($cat['out_of_stock_count'] > 0) echo 'В категории есть товары не в наличии (' . $cat['out_of_stock_count'] . ' шт.)\n';
                                   if ($cat['deleted_count'] > 0) echo 'В категории есть удалённые товары (' . $cat['deleted_count'] . ' шт.)\n';
                               ?>')">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-danger" 
                                    style="padding: 5px 10px; opacity: 0.5;" 
                                    disabled 
                                    title="Нельзя удалить: есть товары в наличии (<?php echo $cat['in_stock_count']; ?> шт.)">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-tags" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        <?php if ($search): ?>
                            Категории по запросу "<?php echo htmlspecialchars($search); ?>" не найдены
                        <?php else: ?>
                            Категории не найдены
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>