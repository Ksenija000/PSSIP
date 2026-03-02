<?php
// ============================================
// admin/products.php - УПРАВЛЕНИЕ ТОВАРАМИ (с валидацией всех чисел)
// ============================================
require_once 'includes/auth.php';

// Восстанавливаем данные формы при ошибке
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Получаем параметры фильтрации
$category_filter = $_GET['category'] ?? '';
$artist_filter = $_GET['artist'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$search = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'list'; // list, add, edit
$product_id = $_GET['id'] ?? 0;
$show_deleted = isset($_GET['show_deleted']) ? true : false;

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ ФИЛЬТРОВ
// ============================================
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$artists = $db->query("SELECT * FROM artists ORDER BY fio")->fetchAll();

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================

// Восстановление товара
if (isset($_GET['restore'])) {
    $product_id = (int)$_GET['restore'];
    
    $stmt = $db->prepare("UPDATE products SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$product_id]);
    
    $_SESSION['success_message'] = "Товар восстановлен";
    header("Location: products.php" . ($show_deleted ? "?show_deleted=1" : ""));
    exit;
}

// Удаление отдельного фото
if (isset($_GET['delete_image']) && isset($_GET['id'])) {
    $image_id = (int)$_GET['delete_image'];
    $product_id = (int)$_GET['id'];
    
    $db->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?")->execute([$image_id, $product_id]);
    
    header("Location: products.php?view=edit&id=$product_id");
    exit;
}

// Удаление товара (мягкое удаление)
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    
    // Проверяем, есть ли товар в НЕзавершённых заказах (processing или delivering)
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.product_id = ? AND o.status IN ('processing', 'delivering')
    ");
    $stmt->execute([$product_id]);
    $active_orders = $stmt->fetchColumn();
    
    if ($active_orders > 0) {
        $_SESSION['error_message'] = "Нельзя удалить товар, который есть в активных заказах (в обработке или доставке)";
    } else {
        try {
            // Получаем информацию о товаре
            $stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            $product_name = $product ? $product['name'] : 'Товар';
            
            // Проверяем, есть ли товар в завершённых заказах
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE oi.product_id = ? AND o.status IN ('delivered', 'cancelled')
            ");
            $stmt->execute([$product_id]);
            $completed_orders = $stmt->fetchColumn();
            
            // Помечаем товар как удалённый (мягкое удаление)
            $stmt = $db->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$product_id]);
            
            // Удаляем из корзины (чтобы не мозолил глаза)
            $db->prepare("DELETE FROM cart_items WHERE product_id = ?")->execute([$product_id]);
            
            // Удаляем из избранного
            $db->prepare("DELETE FROM favorites_products WHERE product_id = ?")->execute([$product_id]);
            
            // Отзывы оставляем (они остаются)
            
            if ($completed_orders > 0) {
                $_SESSION['success_message'] = "Товар \"$product_name\" помечен как удалённый. В каталоге он больше не отображается, но остаётся в истории заказов.";
            } else {
                $_SESSION['success_message'] = "Товар \"$product_name\" удалён";
            }
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Ошибка при удалении товара: " . $e->getMessage();
        }
    }
    
    header("Location: products.php" . ($show_deleted ? "?show_deleted=1" : ""));
    exit;
}

// Добавление/редактирование товара
if (isset($_POST['save_product'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $opisanie = $_POST['opisanie'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $discount_percent = !empty($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : null;
    $size = $_POST['size'] ?? '';
    $weight_kg = (float)($_POST['weight_kg'] ?? 0);
    $material = $_POST['material'] ?? '';
    $year_created = (int)($_POST['year_created'] ?? date('Y'));
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $artist_id = (int)($_POST['artist_id'] ?? 0);
    $art_style = $_POST['art_style'] ?? '';
    $image = $_POST['image'] ?? '';
    
    // Дополнительные фото
    $additional_images = $_POST['additional_images'] ?? [];
    
    // ========== ВАЛИДАЦИЯ ВСЕХ ЧИСЛОВЫХ ПОЛЕЙ ==========
    $errors = [];
    
    // Обязательные поля
    if (empty($name)) {
        $errors[] = "Название товара обязательно";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Выберите категорию";
    }
    
    if ($artist_id <= 0) {
        $errors[] = "Выберите художника";
    }
    
    // Валидация цены
    if (!is_numeric($_POST['price']) || $_POST['price'] === '') {
        $errors[] = "Цена должна быть числом";
    } elseif ($price <= 0) {
        $errors[] = "Цена должна быть больше 0";
    }
    
    // Валидация количества
    if (!is_numeric($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '') {
        $errors[] = "Количество должно быть числом";
    } elseif ($stock_quantity < 0) {
        $errors[] = "Количество товара не может быть меньше 0";
    }
    
    // Валидация веса
    if (!empty($_POST['weight_kg']) && !is_numeric($_POST['weight_kg'])) {
        $errors[] = "Вес должен быть числом";
    } elseif ($weight_kg < 0) {
        $errors[] = "Вес не может быть отрицательным";
    }
    
    // Валидация года
    if (!empty($_POST['year_created']) && !is_numeric($_POST['year_created'])) {
        $errors[] = "Год должен быть числом (можно отрицательным для дат до н.э.)";
    } elseif ($year_created > date('Y')) {
        $errors[] = "Год должен ,быть<= " . (date('Y'));
    }
    
    // Валидация скидок
    if (!empty($_POST['discount_price'])) {
        if (!is_numeric($_POST['discount_price'])) {
            $errors[] = "Цена со скидкой должна быть числом";
        } elseif ($discount_price < 0) {
            $errors[] = "Цена со скидкой не может быть отрицательной";
        } elseif ($discount_price >= $price) {
            $errors[] = "Цена со скидкой должна быть меньше обычной цены";
        }
    }
    
    if (!empty($_POST['discount_percent'])) {
        if (!is_numeric($_POST['discount_percent'])) {
            $errors[] = "Процент скидки должен быть числом";
        } elseif ($discount_percent < 0 || $discount_percent > 100) {
            $errors[] = "Процент скидки должен быть от 0 до 100";
        }
    }
    
    // ========== КОНЕЦ ВАЛИДАЦИИ ==========
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST;
        header("Location: products.php" . ($id > 0 ? "?view=edit&id=$id" : "?view=add"));
        exit;
    }
    
    if ($id > 0) {
        // Обновление существующего товара
        $stmt = $db->prepare("
            UPDATE products SET 
                name = ?, opisanie = ?, price = ?, discount_price = ?, discount_percent = ?,
                size = ?, weight_kg = ?, material = ?, year_created = ?, stock_quantity = ?,
                category_id = ?, artist_id = ?, art_style = ?, image = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $opisanie, $price, $discount_price, $discount_percent,
                        $size, $weight_kg, $material, $year_created, $stock_quantity,
                        $category_id, $artist_id, $art_style, $image, $id]);
        
        // Обработка дополнительных фото
        $sort_order = 1;
        
        // Сначала получим максимальный sort_order существующих фото
        $stmt = $db->prepare("SELECT MAX(sort_order) FROM product_images WHERE product_id = ?");
        $stmt->execute([$id]);
        $max_sort = $stmt->fetchColumn();
        if ($max_sort) $sort_order = $max_sort + 1;
        
        foreach ($additional_images as $img_path) {
            if (!empty(trim($img_path))) {
                // Проверяем, не существует ли уже такое фото
                $stmt = $db->prepare("SELECT id FROM product_images WHERE product_id = ? AND image_path = ?");
                $stmt->execute([$id, $img_path]);
                if (!$stmt->fetch()) {
                    $stmt = $db->prepare("
                        INSERT INTO product_images (product_id, image_path, is_main, sort_order, alt_text, created_at)
                        VALUES (?, ?, 0, ?, ?, NOW())
                    ");
                    $stmt->execute([$id, $img_path, $sort_order++, $name]);
                }
            }
        }
        
        $_SESSION['success_message'] = "Товар '$name' обновлён";
    } else {
        // Добавление нового товара
        $stmt = $db->prepare("
            INSERT INTO products 
                (name, opisanie, price, discount_price, discount_percent, size, weight_kg, 
                 material, year_created, stock_quantity, category_id, artist_id, art_style, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $opisanie, $price, $discount_price, $discount_percent,
                        $size, $weight_kg, $material, $year_created, $stock_quantity,
                        $category_id, $artist_id, $art_style, $image]);
        
        $id = $db->lastInsertId();
        
        // Добавляем дополнительные фото
        $sort_order = 1;
        foreach ($additional_images as $img_path) {
            if (!empty(trim($img_path))) {
                $stmt = $db->prepare("
                    INSERT INTO product_images (product_id, image_path, is_main, sort_order, alt_text, created_at)
                    VALUES (?, ?, 0, ?, ?, NOW())
                ");
                $stmt->execute([$id, $img_path, $sort_order++, $name]);
            }
        }
        
        $_SESSION['success_message'] = "Товар '$name' добавлен (ID: $id)";
    }
    
    header("Location: products.php");
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ РЕДАКТИРОВАНИЯ
// ============================================
if ($view == 'edit' && $product_id > 0) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header("Location: products.php");
        exit;
    }
    
    // Получаем фото товара
    $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$product_id]);
    $product_images = $stmt->fetchAll();
    
    // Получаем информацию о заказах с этим товаром
    $stmt = $db->prepare("
        SELECT 
            o.id,
            o.status,
            o.order_date,
            oi.quantity,
            u.fio as user_name,
            u.email as user_email
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN users u ON o.user_id = u.id
        WHERE oi.product_id = ?
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$product_id]);
    $product_orders = $stmt->fetchAll();
}

// ============================================
// ПОЛУЧАЕМ СТАТИСТИКУ
// ============================================
$stats = [];

// Всего товаров (включая удалённые для статистики)
$stats['total'] = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stats['active'] = $db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
$stats['deleted'] = $db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NOT NULL")->fetchColumn();

// Товаров в наличии (только активные)
$stats['in_stock'] = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity > 0 AND deleted_at IS NULL")->fetchColumn();

// Товаров нет в наличии (только активные)
$stats['out_of_stock'] = $db->query("SELECT COUNT(*) FROM products WHERE (stock_quantity = 0 OR stock_quantity IS NULL) AND deleted_at IS NULL")->fetchColumn();

// Товаров со скидкой (только активные)
$stats['discounted'] = $db->query("SELECT COUNT(*) FROM products WHERE discount_price IS NOT NULL AND deleted_at IS NULL")->fetchColumn();

// Общая стоимость всех товаров
$stmt = $db->query("SELECT SUM(price * stock_quantity) FROM products WHERE deleted_at IS NULL");
$stats['total_value'] = $stmt->fetchColumn() ?? 0;

// Количество категорий и художников
$stats['categories'] = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$stats['artists'] = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();

// ============================================
// ПОСТРОЕНИЕ ЗАПРОСА ДЛЯ СПИСКА ТОВАРОВ
// ============================================
$sql = "
    SELECT 
        p.*, 
        c.name as category_name, 
        a.fio as artist_name,
        (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) as total_orders,
        (SELECT COUNT(*) FROM order_items oi 
         JOIN orders o ON oi.order_id = o.id 
         WHERE oi.product_id = p.id AND o.status IN ('processing', 'delivering')) as active_orders,
        (SELECT COUNT(*) FROM order_items oi 
         JOIN orders o ON oi.order_id = o.id 
         WHERE oi.product_id = p.id AND o.status IN ('delivered', 'cancelled')) as completed_orders
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN artists a ON p.artist_id = a.id
    WHERE 1=1
";
$params = [];

// Фильтр по удалённым
if (!$show_deleted) {
    $sql .= " AND p.deleted_at IS NULL";
}

if ($category_filter) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if ($artist_filter) {
    $sql .= " AND p.artist_id = ?";
    $params[] = $artist_filter;
}

if ($stock_filter == 'in') {
    $sql .= " AND p.stock_quantity > 0";
} elseif ($stock_filter == 'out') {
    $sql .= " AND (p.stock_quantity = 0 OR p.stock_quantity IS NULL)";
}

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.opisanie LIKE ? OR p.material LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY p.deleted_at DESC, p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Сообщения
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

require_once 'includes/header.php';
?>

<!-- Стили для дополнительных фото -->
<style>
.image-preview {
    width: 60px;
    height: 60px;
    border: 2px dashed var(--admin-border);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: var(--admin-surface-light);
}
.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.image-preview i {
    font-size: 1.5rem;
    color: var(--admin-text-secondary);
}
.badge-container {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.badge-deleted {
    background: rgba(158, 158, 158, 0.2);
    color: #9e9e9e;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
}
.deleted-row {
    opacity: 0.6;
    background: rgba(158, 158, 158, 0.05);
}
.deleted-row:hover {
    opacity: 0.8;
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
<!-- ФОРМА ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ ТОВАРА -->
<!-- ============================================ -->
<?php if ($view == 'add' || ($view == 'edit' && isset($product))): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-box" style="color: var(--primary-orange);"></i>
        <?php echo $view == 'add' ? 'Добавление товара' : 'Редактирование товара #' . $product['id']; ?>
    </h2>
    <a href="products.php<?php echo $show_deleted ? '?show_deleted=1' : ''; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Назад к списку
    </a>
</div>

<?php if ($view == 'edit' && !empty($product_orders)): ?>
<div class="card" style="margin-bottom: 20px; background: rgba(33, 150, 243, 0.05); border: 1px solid rgba(33, 150, 243, 0.3);">
    <h3 style="margin-bottom: 15px;"><i class="fas fa-shopping-cart"></i> Заказы с этим товаром</h3>
    <table class="table">
        <thead>
            <tr>
                <th>№ заказа</th>
                <th>Дата</th>
                <th>Покупатель</th>
                <th>Кол-во</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($product_orders as $po): ?>
            <tr>
                <td><a href="orders.php?view=details&id=<?php echo $po['id']; ?>">#<?php echo $po['id']; ?></a></td>
                <td><?php echo date('d.m.Y', strtotime($po['order_date'])); ?></td>
                <td>
                    <?php echo htmlspecialchars($po['user_name']); ?><br>
                    <small><?php echo htmlspecialchars($po['user_email']); ?></small>
                </td>
                <td><?php echo $po['quantity']; ?> шт.</td>
                <td>
                    <?php
                    $status_class = '';
                    $status_text = '';
                    switch ($po['status']) {
                        case 'processing':
                            $status_class = 'badge-warning';
                            $status_text = 'В обработке';
                            break;
                        case 'delivering':
                            $status_class = 'badge-info';
                            $status_text = 'Доставляется';
                            break;
                        case 'delivered':
                            $status_class = 'badge-success';
                            $status_text = 'Доставлен';
                            break;
                        case 'cancelled':
                            $status_class = 'badge-danger';
                            $status_text = 'Отменён';
                            break;
                    }
                    ?>
                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php 
    $has_active = false;
    foreach ($product_orders as $po) {
        if (in_array($po['status'], ['processing', 'delivering'])) {
            $has_active = true;
            break;
        }
    }
    if ($has_active): ?>
    <div style="margin-top: 15px; padding: 10px; background: rgba(244, 67, 54, 0.1); border-radius: 10px; color: #f44336;">
        <i class="fas fa-exclamation-triangle"></i> 
        Внимание! Этот товар есть в активных заказах. Его нельзя удалить, пока заказы не будут завершены.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <form method="POST" id="productForm">
        <input type="hidden" name="id" value="<?php echo $product['id'] ?? 0; ?>">
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <!-- Левая колонка -->
            <div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Название товара *</label>
                    <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($form_data['name'] ?? $product['name'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Категория *</label>
                    <select name="category_id" required style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($form_data['category_id']) && $form_data['category_id'] == $cat['id']) || (isset($product) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Художник *</label>
                    <select name="artist_id" required style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                        <option value="">Выберите художника</option>
                        <?php foreach ($artists as $art): ?>
                        <option value="<?php echo $art['id']; ?>" <?php echo (isset($form_data['artist_id']) && $form_data['artist_id'] == $art['id']) || (isset($product) && $product['artist_id'] == $art['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($art['fio']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Стиль</label>
                    <input type="text" name="art_style" value="<?php echo htmlspecialchars($form_data['art_style'] ?? $product['art_style'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Год создания</label>
                    <input type="number" name="year_created" max="<?php echo date('Y'); ?>" 
                           value="<?php echo $form_data['year_created'] ?? $product['year_created'] ?? date('Y'); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
            </div>
            
            <!-- Правая колонка -->
            <div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Цена *</label>
                    <input type="number" step="0.01" name="price" id="price" required 
                           value="<?php echo $form_data['price'] ?? $product['price'] ?? 0; ?>" 
                           min="0.01"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                    <small style="color: var(--admin-text-secondary);">Должна быть больше 0</small>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Скидка %</label>
                        <input type="number" step="0.01" name="discount_percent" id="discount_percent" 
                               value="<?php echo $form_data['discount_percent'] ?? $product['discount_percent'] ?? ''; ?>" 
                               min="0" max="100"
                               style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                        <small style="color: var(--admin-text-secondary);">От 0 до 100</small>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Цена со скидкой</label>
                        <input type="number" step="0.01" name="discount_price" id="discount_price" 
                               value="<?php echo $form_data['discount_price'] ?? $product['discount_price'] ?? ''; ?>" 
                               min="0"
                               style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                        <small style="color: var(--admin-text-secondary);">Не может быть отрицательной</small>
                    </div>
                </div>
                
                <!-- Количество -->
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Количество в наличии</label>
                    <input type="number" name="stock_quantity" 
                           value="<?php echo htmlspecialchars($form_data['stock_quantity'] ?? $product['stock_quantity'] ?? 0); ?>" 
                           min="0" step="1"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                    <small style="color: var(--admin-text-secondary);">Не может быть меньше 0</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Размер</label>
                    <input type="text" name="size" value="<?php echo htmlspecialchars($form_data['size'] ?? $product['size'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Вес (кг)</label>
                    <input type="number" step="0.01" name="weight_kg" 
                           value="<?php echo $form_data['weight_kg'] ?? $product['weight_kg'] ?? 0; ?>" 
                           min="0"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                    <small style="color: var(--admin-text-secondary);">Не может быть отрицательным</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Материал</label>
                    <input type="text" name="material" value="<?php echo htmlspecialchars($form_data['material'] ?? $product['material'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
            </div>
        </div>
        
        <!-- Описание (на всю ширину) -->
        <div style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Описание</label>
            <textarea name="opisanie" rows="6" style="width: 100%; padding: 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 15px; color: var(--admin-text);"><?php echo htmlspecialchars($form_data['opisanie'] ?? $product['opisanie'] ?? ''); ?></textarea>
        </div>
        
        <!-- Главное фото -->
        <div style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Главное фото (URL)</label>
            <input type="text" name="image" id="main_image" value="<?php echo htmlspecialchars($form_data['image'] ?? $product['image'] ?? ''); ?>" 
                   style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
            <small style="color: var(--admin-text-secondary);">Введите URL главного фото товара</small>
        </div>
        
        <!-- Дополнительные фото -->
        <div style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 10px; color: var(--admin-text-secondary);">
                <i class="fas fa-images"></i> Дополнительные фото (до 5 шт.)
            </label>
            
            <div id="additional-images">
                <?php if ($view == 'edit' && !empty($product_images)): ?>
                    <?php foreach ($product_images as $index => $img): ?>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        <div class="image-preview">
                            <img src="<?php echo $img['image_path']; ?>" alt="">
                        </div>
                        <input type="text" name="additional_images[]" value="<?php echo $img['image_path']; ?>" 
                               style="flex: 1; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                        <input type="hidden" name="image_ids[]" value="<?php echo $img['id']; ?>">
                        <a href="products.php?delete_image=<?php echo $img['id']; ?>&id=<?php echo $product['id']; ?>" class="btn btn-danger" style="padding: 8px 12px;" onclick="return confirm('Удалить это фото?')">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" class="btn btn-secondary" onclick="addImageField()" style="margin-top: 10px;">
                <i class="fas fa-plus"></i> Добавить фото
            </button>
            <small style="color: var(--admin-text-secondary); display: block; margin-top: 5px;">Введите URL дополнительных фото</small>
        </div>
        
        <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
            <button type="submit" name="save_product" class="btn btn-primary">
                <i class="fas fa-save"></i> Сохранить
            </button>
            <a href="products.php<?php echo $show_deleted ? '?show_deleted=1' : ''; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Отмена
            </a>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- СПИСОК ТОВАРОВ -->
<!-- ============================================ -->
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-box" style="color: var(--primary-orange);"></i>
        Управление товарами
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="products.php?view=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Добавить товар
        </a>
        <?php if ($show_deleted): ?>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-eye"></i> Скрыть удалённые
            </a>
        <?php else: ?>
            <a href="products.php?show_deleted=1" class="btn btn-secondary">
                <i class="fas fa-trash"></i> Показать удалённые
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Статистика -->
<div class="card-grid">
    <div class="card">
        <div class="card-title"><i class="fas fa-boxes"></i> Всего товаров</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
        <div class="card-label">активных: <?php echo $stats['active']; ?> · удалено: <?php echo $stats['deleted']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle" style="color: var(--success);"></i> В наличии</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['in_stock']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-times-circle" style="color: var(--danger);"></i> Нет в наличии</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['out_of_stock']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-tags" style="color: var(--warning);"></i> Со скидкой</div>
        <div class="card-value" style="color: var(--warning);"><?php echo $stats['discounted']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-tag"></i> Категорий</div>
        <div class="card-value"><?php echo $stats['categories']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-paint-brush"></i> Художников</div>
        <div class="card-value"><?php echo $stats['artists']; ?></div>
    </div>
</div>

<!-- Фильтры -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <?php if ($show_deleted): ?>
            <input type="hidden" name="show_deleted" value="1">
        <?php endif; ?>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Категория</label>
            <select name="category" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все категории</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Художник</label>
            <select name="artist" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все художники</option>
                <?php foreach ($artists as $art): ?>
                <option value="<?php echo $art['id']; ?>" <?php echo $artist_filter == $art['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($art['fio']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Наличие</label>
            <select name="stock" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все</option>
                <option value="in" <?php echo $stock_filter == 'in' ? 'selected' : ''; ?>>В наличии</option>
                <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Нет в наличии</option>
            </select>
        </div>
        
        <div style="flex: 2; min-width: 200px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск</label>
            <input type="text" name="search" placeholder="Название, описание, материал" value="<?php echo htmlspecialchars($search); ?>" 
                   style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="products.php<?php echo $show_deleted ? '?show_deleted=1' : ''; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Сброс
            </a>
        </div>
    </form>
</div>

<!-- Список товаров -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Фото</th>
                <th>Название</th>
                <th>Категория</th>
                <th>Художник</th>
                <th>Цена</th>
                <th>Наличие</th>
                <th>В заказах</th>
                <th>Продаж</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): 
                    $is_deleted = $product['deleted_at'] !== null;
                ?>
                <tr class="<?php echo $is_deleted ? 'deleted-row' : ''; ?>">
                    <td>#<?php echo $product['id']; ?></td>
                    <td>
                        <?php if ($product['image']): ?>
                            <img src="<?php echo $product['image']; ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: var(--admin-surface-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-box" style="color: var(--admin-text-secondary);"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($is_deleted): ?>
                            <div class="badge-deleted" style="margin-top: 5px;">Удалён</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                        <small style="color: var(--admin-text-secondary);"><?php echo mb_substr($product['opisanie'] ?? '', 0, 50); ?>...</small>
                    </td>
                    <td><?php echo htmlspecialchars($product['category_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($product['artist_name'] ?? '—'); ?></td>
                    <td>
                        <?php if ($product['discount_price']): ?>
                            <span style="color: var(--warning); font-weight: 600;"><?php echo number_format($product['discount_price'], 2, '.', ' '); ?> BYN</span><br>
                            <small style="color: var(--admin-text-secondary); text-decoration: line-through;"><?php echo number_format($product['price'], 2, '.', ' '); ?> BYN</small>
                        <?php else: ?>
                            <span style="font-weight: 600;"><?php echo number_format($product['price'], 2, '.', ' '); ?> BYN</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span class="badge badge-success"><?php echo $product['stock_quantity']; ?> шт.</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="badge-container">
                            <?php if ($product['active_orders'] > 0): ?>
                                <span class="badge badge-danger" title="В активных заказах (в обработке или доставке)">
                                    🔴 <?php echo $product['active_orders']; ?> актив.
                                </span>
                            <?php endif; ?>
                            <?php if ($product['completed_orders'] > 0): ?>
                                <span class="badge badge-warning" title="В завершённых заказах (доставлен или отменён)">
                                    🟡 <?php echo $product['completed_orders']; ?> ист.
                                </span>
                            <?php endif; ?>
                            <?php if ($product['total_orders'] == 0): ?>
                                <span class="badge badge-success">🟢 Нет</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo $product['sold_count'] ?? 0; ?></td>
                    <td>
                        <?php if (!$is_deleted): ?>
                            <a href="products.php?view=edit&id=<?php echo $product['id']; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?>" class="btn btn-primary" style="padding: 5px 10px;" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($is_deleted): ?>
                            <a href="products.php?restore=<?php echo $product['id']; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?>" class="btn btn-success" style="padding: 5px 10px;" title="Восстановить" onclick="return confirm('Восстановить товар?')">
                                <i class="fas fa-undo"></i>
                            </a>
                            <a href="products.php?delete=<?php echo $product['id']; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?>" class="btn btn-danger" style="padding: 5px 10px;" title="Удалить навсегда" onclick="return confirm('Удалить товар навсегда? Это действие нельзя отменить.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php elseif ($product['active_orders'] == 0): ?>
                            <a href="products.php?delete=<?php echo $product['id']; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?>" 
                               class="btn btn-danger" 
                               style="padding: 5px 10px;" 
                               title="Удалить" 
                               onclick="return confirm('<?php 
                                   if ($product['completed_orders'] > 0) {
                                       echo 'Товар есть в ' . $product['completed_orders'] . ' завершённых заказах. После удаления он пропадёт из каталога, но останется в истории заказов. Удалить?';
                                   } else {
                                       echo 'Удалить товар ' . htmlspecialchars($product['name']) . '?';
                                   }
                               ?>')">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-danger" 
                                    style="padding: 5px 10px; opacity: 0.3;" 
                                    disabled 
                                    title="Нельзя удалить: товар в <?php echo $product['active_orders']; ?> активных заказах">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Товары не найдены
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<!-- JavaScript для авторасчёта цен и добавления фото -->
<script>
function calculateDiscount() {
    const price = parseFloat(document.getElementById('price')?.value) || 0;
    const percent = parseFloat(document.getElementById('discount_percent')?.value) || 0;
    
    if (price > 0 && percent > 0 && percent <= 100) {
        const discounted = price * (1 - percent / 100);
        if (discounted >= 0) {
            document.getElementById('discount_price').value = discounted.toFixed(2);
        }
    }
}

function calculatePercent() {
    const price = parseFloat(document.getElementById('price')?.value) || 0;
    const discounted = parseFloat(document.getElementById('discount_price')?.value) || 0;
    
    if (price > 0 && discounted > 0 && discounted < price) {
        const percent = ((price - discounted) / price * 100).toFixed(2);
        if (percent >= 0 && percent <= 100) {
            document.getElementById('discount_percent').value = percent;
        }
    }
}

function addImageField() {
    const container = document.getElementById('additional-images');
    const div = document.createElement('div');
    div.style.display = 'flex';
    div.style.gap = '10px';
    div.style.marginBottom = '10px';
    div.style.alignItems = 'center';
    div.innerHTML = `
        <div class="image-preview">
            <i class="fas fa-image"></i>
        </div>
        <input type="text" name="additional_images[]" placeholder="URL дополнительного фото" 
               style="flex: 1; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        <button type="button" class="btn btn-danger" style="padding: 8px 12px;" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
}

// Добавляем обработчики событий
document.addEventListener('DOMContentLoaded', function() {
    const priceInput = document.getElementById('price');
    const percentInput = document.getElementById('discount_percent');
    const discountInput = document.getElementById('discount_price');
    
    if (priceInput && percentInput) {
        priceInput.addEventListener('input', calculateDiscount);
        percentInput.addEventListener('input', calculateDiscount);
    }
    
    if (priceInput && discountInput) {
        discountInput.addEventListener('input', calculatePercent);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>