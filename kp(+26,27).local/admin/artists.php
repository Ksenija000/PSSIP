<?php
// ============================================
// admin/artists.php - УПРАВЛЕНИЕ ХУДОЖНИКАМИ
// ============================================
require_once 'includes/auth.php';

// Получаем параметры
$view = $_GET['view'] ?? 'list'; // list, add, edit, view
$artist_id = $_GET['id'] ?? 0;
$country_filter = $_GET['country'] ?? '';
$search = $_GET['search'] ?? '';

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================

// Сохранение художника (добавление/редактирование)
if (isset($_POST['save_artist'])) {
    $id = (int)($_POST['id'] ?? 0);
    $fio = trim($_POST['fio'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $brief_introduction = trim($_POST['brief_introduction'] ?? '');
    $strana = trim($_POST['strana'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $photo = trim($_POST['photo'] ?? '');
    $year_of_birth = !empty($_POST['year_of_birth']) ? (int)$_POST['year_of_birth'] : null;
    $year_of_death = !empty($_POST['year_of_death']) ? (int)$_POST['year_of_death'] : null;
    $year_of_career_start = !empty($_POST['year_of_career_start']) ? (int)$_POST['year_of_career_start'] : null;
    $style = trim($_POST['style'] ?? '');
    
    $errors = [];
    
    if (empty($fio)) $errors[] = "Имя художника обязательно";
    
    if (empty($errors)) {
        if ($id > 0) {
            // Обновление существующего художника
            $stmt = $db->prepare("
                UPDATE artists SET 
                    fio = ?, bio = ?, brief_introduction = ?, strana = ?, email = ?,
                    photo = ?, year_of_birth = ?, year_of_death = ?, year_of_career_start = ?, style = ?
                WHERE id = ?
            ");
            $stmt->execute([$fio, $bio, $brief_introduction, $strana, $email, $photo, 
                            $year_of_birth, $year_of_death, $year_of_career_start, $style, $id]);
            
            $_SESSION['success_message'] = "Художник '$fio' обновлён";
        } else {
            // Добавление нового художника
            $stmt = $db->prepare("
                INSERT INTO artists 
                    (fio, bio, brief_introduction, strana, email, photo, year_of_birth, year_of_death, year_of_career_start, style)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fio, $bio, $brief_introduction, $strana, $email, $photo, 
                            $year_of_birth, $year_of_death, $year_of_career_start, $style]);
            
            $_SESSION['success_message'] = "Художник '$fio' добавлен";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    header("Location: artists.php");
    exit;
}

// Удаление художника
if (isset($_GET['delete'])) {
    $artist_id = (int)$_GET['delete'];
    
    // Проверяем, есть ли товары у художника
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE artist_id = ?");
    $stmt->execute([$artist_id]);
    $products_count = $stmt->fetchColumn();
    
    if ($products_count > 0) {
        $_SESSION['error_message'] = "Нельзя удалить художника, у которого есть товары";
    } else {
        // Удаляем из избранного
        $db->prepare("DELETE FROM favorites_artists WHERE artist_id = ?")->execute([$artist_id]);
        
        // Удаляем самого художника
        $stmt = $db->prepare("DELETE FROM artists WHERE id = ?");
        $stmt->execute([$artist_id]);
        
        $_SESSION['success_message'] = "Художник удалён";
    }
    
    header("Location: artists.php");
    exit;
}

// ============================================
// ПОЛУЧАЕМ ДАННЫЕ ДЛЯ ПРОСМОТРА/РЕДАКТИРОВАНИЯ
// ============================================
if (($view == 'view' || $view == 'edit') && $artist_id > 0) {
    $stmt = $db->prepare("SELECT * FROM artists WHERE id = ?");
    $stmt->execute([$artist_id]);
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artist) {
        header("Location: artists.php");
        exit;
    }
    
    // Для просмотра получаем также товары художника
    if ($view == 'view') {
        $stmt = $db->prepare("
               SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.artist_id = ? AND p.deleted_at IS NULL
    ORDER BY p.id DESC
        ");
        $stmt->execute([$artist_id]);
        $artist_products = $stmt->fetchAll();
    }
}

// ============================================
// ПОЛУЧАЕМ СПИСОК ХУДОЖНИКОВ
// ============================================
$sql = "
    SELECT a.*, 
           (SELECT COUNT(*) FROM products WHERE artist_id = a.id) as products_count
    FROM artists a
    WHERE 1=1
";
$params = [];

if ($country_filter) {
    $sql .= " AND a.strana = ?";
    $params[] = $country_filter;
}

if ($search) {
    $sql .= " AND (a.fio LIKE ? OR a.strana LIKE ? OR a.style LIKE ? OR a.bio LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY a.fio";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$artists = $stmt->fetchAll();

// ============================================
// ПОЛУЧАЕМ СПИСОК СТРАН ДЛЯ ФИЛЬТРА
// ============================================
$countries = $db->query("SELECT DISTINCT strana FROM artists WHERE strana IS NOT NULL AND strana != '' ORDER BY strana")->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// СТАТИСТИКА
// ============================================
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
$stats['with_products'] = $db->query("
    SELECT COUNT(DISTINCT artist_id) 
    FROM products 
    WHERE artist_id IS NOT NULL AND deleted_at IS NULL
")->fetchColumn();
$stats['without_products'] = $stats['total'] - $stats['with_products'];
$stats['countries'] = $db->query("SELECT COUNT(DISTINCT strana) FROM artists WHERE strana IS NOT NULL AND strana != ''")->fetchColumn();

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
<!-- ПРОСМОТР ХУДОЖНИКА -->
<!-- ============================================ -->
<?php if ($view == 'view' && isset($artist)): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-paint-brush" style="color: var(--primary-orange);"></i>
        Художник: <?php echo htmlspecialchars($artist['fio']); ?>
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="artists.php?view=edit&id=<?php echo $artist['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Редактировать
        </a>
        <a href="artists.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад к списку
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
    <!-- Левая колонка - информация и фото -->
    <div>
        <div class="card" style="margin-bottom: 20px; text-align: center;">
            <?php if ($artist['photo']): ?>
                <img src="<?php echo htmlspecialchars($artist['photo']); ?>" alt="<?php echo htmlspecialchars($artist['fio']); ?>" 
                     style="width: 200px; height: 200px; object-fit: cover; border-radius: 50%; margin: 0 auto 20px; border: 3px solid var(--primary-orange);">
            <?php else: ?>
                <div style="width: 200px; height: 200px; border-radius: 50%; background: var(--admin-surface-light); margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; border: 3px solid var(--primary-orange);">
                    <i class="fas fa-user" style="font-size: 4rem; color: var(--admin-text-secondary);"></i>
                </div>
            <?php endif; ?>
            
            <h3 style="font-size: 1.5rem; margin-bottom: 5px;"><?php echo htmlspecialchars($artist['fio']); ?></h3>
            <?php if ($artist['strana']): ?>
                <p style="color: var(--primary-orange); margin-bottom: 15px;"><?php echo htmlspecialchars($artist['strana']); ?></p>
            <?php endif; ?>
            
            <?php if ($artist['style']): ?>
                <span class="badge badge-info" style="margin-bottom: 15px;"><?php echo htmlspecialchars($artist['style']); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom: 15px;">📊 Статистика</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div style="text-align: center; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-orange);"><?php echo count($artist_products); ?></div>
                    <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">Работ</div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-orange);">—</div>
                    <div style="font-size: 0.85rem; color: var(--admin-text-secondary);">В избранном</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Правая колонка - детальная информация и товары -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">📝 Биография</h3>
            
            <?php if ($artist['brief_introduction']): ?>
                <div style="margin-bottom: 20px; padding: 15px; background: var(--admin-surface-light); border-radius: 12px;">
                    <div style="color: var(--admin-text-secondary); margin-bottom: 5px;">Кратко:</div>
                    <p><?php echo nl2br(htmlspecialchars($artist['brief_introduction'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($artist['bio']): ?>
                <div>
                    <div style="color: var(--admin-text-secondary); margin-bottom: 5px;">Полная биография:</div>
                    <p style="line-height: 1.7;"><?php echo nl2br(htmlspecialchars($artist['bio'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom: 15px;">ℹ️ Детальная информация</h3>
            
            <table style="width: 100%;">
                <?php if ($artist['email']): ?>
                <tr>
                    <td style="padding: 8px 0; color: var(--admin-text-secondary); width: 150px;">Email:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($artist['email']); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($artist['year_of_birth']): ?>
                <tr>
                    <td style="padding: 8px 0; color: var(--admin-text-secondary);">Год рождения:</td>
                    <td style="padding: 8px 0;"><?php echo $artist['year_of_birth']; ?>
                        <?php if ($artist['year_of_death']): ?>
                            — <?php echo $artist['year_of_death']; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php if ($artist['year_of_career_start']): ?>
                <tr>
                    <td style="padding: 8px 0; color: var(--admin-text-secondary);">Начало карьеры:</td>
                    <td style="padding: 8px 0;"><?php echo $artist['year_of_career_start']; ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Товары художника -->
        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 15px;">🖼️ Товары художника</h3>
            
            <?php if (count($artist_products) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Цена</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artist_products as $product): ?>
                        <tr>
                            <td>#<?php echo $product['id']; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? '—'); ?></td>
                            <td><?php echo number_format($product['price'], 2, '.', ' '); ?> BYN</td>
                            <td>
                                <a href="products.php?view=edit&id=<?php echo $product['id']; ?>" class="btn btn-primary" style="padding: 4px 8px;">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: var(--admin-text-secondary); text-align: center; padding: 30px;">
                    <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    У художника пока нет товаров
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- ФОРМА ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ ХУДОЖНИКА -->
<!-- ============================================ -->
<?php elseif ($view == 'edit' && isset($artist)): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-paint-brush" style="color: var(--primary-orange);"></i>
        Редактирование художника
    </h2>
    <div style="display: flex; gap: 10px;">
        <a href="artists.php?view=view&id=<?php echo $artist['id']; ?>" class="btn btn-secondary">
            <i class="fas fa-eye"></i> Просмотр
        </a>
        <a href="artists.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>
</div>

<div class="card">
    <form method="POST">
        <input type="hidden" name="id" value="<?php echo $artist['id']; ?>">
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <!-- Левая колонка -->
            <div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">ФИО *</label>
                    <input type="text" name="fio" required value="<?php echo htmlspecialchars($artist['fio']); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Страна</label>
                    <input type="text" name="strana" value="<?php echo htmlspecialchars($artist['strana'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($artist['email'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Стиль</label>
                    <input type="text" name="style" value="<?php echo htmlspecialchars($artist['style'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Фото (URL)</label>
                    <input type="text" name="photo" value="<?php echo htmlspecialchars($artist['photo'] ?? ''); ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
            </div>
            
            <!-- Правая колонка - годы -->
            <div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Год рождения</label>
                    <input type="number" name="year_of_birth" value="<?php echo $artist['year_of_birth'] ?? ''; ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Год смерти</label>
                    <input type="number" name="year_of_death" value="<?php echo $artist['year_of_death'] ?? ''; ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Начало карьеры</label>
                    <input type="number" name="year_of_career_start" value="<?php echo $artist['year_of_career_start'] ?? ''; ?>" 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
            </div>
        </div>
        
        <!-- Краткое введение -->
        <div style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Краткое введение</label>
            <textarea name="brief_introduction" rows="3" style="width: 100%; padding: 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 15px; color: var(--admin-text);"><?php echo htmlspecialchars($artist['brief_introduction'] ?? ''); ?></textarea>
        </div>
        
        <!-- Полная биография -->
        <div style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Полная биография</label>
            <textarea name="bio" rows="6" style="width: 100%; padding: 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 15px; color: var(--admin-text);"><?php echo htmlspecialchars($artist['bio'] ?? ''); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 15px; justify-content: flex-end;">
            <button type="submit" name="save_artist" class="btn btn-primary">
                <i class="fas fa-save"></i> Сохранить
            </button>
            <a href="artists.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Отмена
            </a>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- ФОРМА ДОБАВЛЕНИЯ НОВОГО ХУДОЖНИКА -->
<!-- ============================================ -->
<?php elseif ($view == 'add'): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-paint-brush" style="color: var(--primary-orange);"></i>
        Добавление художника
    </h2>
    <a href="artists.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Назад к списку
    </a>
</div>

<div class="card">
    <form method="POST">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <!-- Левая колонка -->
            <div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">ФИО *</label>
                    <input type="text" name="fio" required 
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Страна</label>
                    <input type="text" name="strana" placeholder="Россия, США, ..."
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Email</label>
                    <input type="email" name="email" placeholder="artist@example.com"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Стиль</label>
                    <input type="text" name="style" placeholder="Реализм, Абстракция..."
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Фото (URL)</label>
                    <input type="text" name="photo" placeholder="https://example.com/photo.jpg"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
            </div>
            
            <!-- Правая колонка - годы -->
            <div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Год рождения</label>
                    <input type="number" name="year_of_birth" placeholder="1965"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Год смерти</label>
                    <input type="number" name="year_of_death" placeholder="если применимо"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Начало карьеры</label>
                    <input type="number" name="year_of_career_start" placeholder="1985"
                           style="width: 100%; padding: 12px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                </div>
            </div>
        </div>
        
        <!-- Краткое введение -->
        <div style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Краткое введение</label>
            <textarea name="brief_introduction" rows="3" placeholder="Краткое описание для карточки художника" style="width: 100%; padding: 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 15px; color: var(--admin-text);"></textarea>
        </div>
        
        <!-- Полная биография -->
        <div style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Полная биография</label>
            <textarea name="bio" rows="6" placeholder="Подробная биография художника..." style="width: 100%; padding: 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 15px; color: var(--admin-text);"></textarea>
        </div>
        
        <div style="display: flex; gap: 15px; justify-content: flex-end;">
            <button type="submit" name="save_artist" class="btn btn-primary">
                <i class="fas fa-save"></i> Создать художника
            </button>
            <a href="artists.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Отмена
            </a>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- СПИСОК ХУДОЖНИКОВ -->
<!-- ============================================ -->
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-paint-brush" style="color: var(--primary-orange);"></i>
        Управление художниками
    </h2>
    <a href="artists.php?view=add" class="btn btn-primary">
        <i class="fas fa-plus"></i> Добавить художника
    </a>
</div>

<!-- Статистика -->
<div class="card-grid">
    <div class="card">
        <div class="card-title"><i class="fas fa-paint-brush"></i> Всего художников</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle" style="color: var(--success);"></i> С работами</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['with_products']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-times-circle" style="color: var(--danger);"></i> Без работ</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['without_products']; ?></div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-globe"></i> Стран</div>
        <div class="card-value"><?php echo $stats['countries']; ?></div>
    </div>
</div>

<!-- Фильтры и поиск -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Страна</label>
            <select name="country" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все страны</option>
                <?php foreach ($countries as $country): ?>
                <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $country_filter == $country ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($country); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 2; min-width: 200px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск</label>
            <input type="text" name="search" placeholder="Имя, страна, стиль, биография" value="<?php echo htmlspecialchars($search); ?>" 
                   style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="artists.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Сброс
            </a>
        </div>
    </form>
</div>

<!-- Список художников -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Фото</th>
                <th>ФИО</th>
                <th>Страна</th>
                <th>Стиль</th>
                <th>Работ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($artists) > 0): ?>
                <?php foreach ($artists as $artist): ?>
                <tr>
                    <td>#<?php echo $artist['id']; ?></td>
                    <td>
                        <?php if ($artist['photo']): ?>
                            <img src="<?php echo htmlspecialchars($artist['photo']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--admin-surface-light); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user" style="color: var(--admin-text-secondary);"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($artist['fio']); ?></strong></td>
                    <td><?php echo htmlspecialchars($artist['strana'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($artist['style'] ?? '—'); ?></td>
                    <td>
                        <span class="badge <?php echo $artist['products_count'] > 0 ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $artist['products_count']; ?>
                        </span>
                    </td>
                    <td>
                        <a href="artists.php?view=view&id=<?php echo $artist['id']; ?>" class="btn btn-primary" style="padding: 5px 8px;" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="artists.php?view=edit&id=<?php echo $artist['id']; ?>" class="btn btn-primary" style="padding: 5px 8px;" title="Редактировать">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <?php if ($artist['products_count'] == 0): ?>
                            <a href="artists.php?delete=<?php echo $artist['id']; ?>" class="btn btn-danger" style="padding: 5px 8px;" title="Удалить" onclick="return confirm('Удалить художника <?php echo htmlspecialchars($artist['fio']); ?>?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-danger" style="padding: 5px 8px; opacity: 0.3;" disabled title="Нельзя удалить (есть товары)">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-paint-brush" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Художники не найдены
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>