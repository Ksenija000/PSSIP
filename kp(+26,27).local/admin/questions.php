<?php
// ============================================
// admin/questions.php - УПРАВЛЕНИЕ ВОПРОСАМИ
// ============================================
require_once 'includes/auth.php';

// Получаем параметры
$status_filter = $_GET['status'] ?? '';
$product_filter = $_GET['product_id'] ?? '';
$search = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'list'; // list, answer
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================

// Ответ на вопрос
if (isset($_POST['save_answer'])) {
    $id = (int)$_POST['id'];
    $answer = trim($_POST['answer'] ?? '');
    
    if (!empty($answer)) {
        $stmt = $db->prepare("
            UPDATE product_questions 
            SET answer = ?, answered_at = NOW(), answered_by = ?, status = 'published' 
            WHERE id = ?
        ");
        $stmt->execute([$answer, $adminId, $id]);
        
        $_SESSION['success_message'] = "Ответ сохранён";
    }
    
    header("Location: questions.php");
    exit;
}

// Скрытие вопроса
if (isset($_GET['hide'])) {
    $id = (int)$_GET['hide'];
    $db->prepare("UPDATE product_questions SET status = 'hidden' WHERE id = ?")->execute([$id]);
    $_SESSION['success_message'] = "Вопрос скрыт";
    header("Location: questions.php");
    exit;
}

// Публикация вопроса
if (isset($_GET['publish'])) {
    $id = (int)$_GET['publish'];
    $db->prepare("UPDATE product_questions SET status = 'published' WHERE id = ?")->execute([$id]);
    $_SESSION['success_message'] = "Вопрос опубликован";
    header("Location: questions.php");
    exit;
}

// Удаление вопроса
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM product_questions WHERE id = ?")->execute([$id]);
    $_SESSION['success_message'] = "Вопрос удалён";
    header("Location: questions.php");
    exit;
}

// ============================================
// ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ОТВЕТА
// ============================================
if ($view === 'answer' && $question_id > 0) {
    $stmt = $db->prepare("
        SELECT q.*, u.fio as user_name, u.email as user_email, p.name as product_name
        FROM product_questions q
        LEFT JOIN users u ON q.user_id = u.id
        JOIN products p ON q.product_id = p.id
        WHERE q.id = ?
    ");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        header("Location: questions.php");
        exit;
    }
}

// ============================================
// ПОЛУЧЕНИЕ СПИСКА ВОПРОСОВ
// ============================================
$sql = "
    SELECT 
        q.*,
        u.fio as user_name,
        u.email as user_email,
        p.name as product_name,
        p.id as product_id
    FROM product_questions q
    LEFT JOIN users u ON q.user_id = u.id
    JOIN products p ON q.product_id = p.id
    WHERE 1=1
";
$params = [];

if ($status_filter) {
    $sql .= " AND q.status = ?";
    $params[] = $status_filter;
}

if ($product_filter) {
    $sql .= " AND q.product_id = ?";
    $params[] = $product_filter;
}

if ($search) {
    $sql .= " AND (q.question LIKE ? OR q.answer LIKE ? OR u.fio LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY q.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Получаем список товаров для фильтра
$products = $db->query("SELECT id, name FROM products ORDER BY name")->fetchAll();

// Статистика
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) FROM product_questions")->fetchColumn();
$stats['pending'] = $db->query("SELECT COUNT(*) FROM product_questions WHERE status = 'pending'")->fetchColumn();
$stats['published'] = $db->query("SELECT COUNT(*) FROM product_questions WHERE status = 'published'")->fetchColumn();
$stats['hidden'] = $db->query("SELECT COUNT(*) FROM product_questions WHERE status = 'hidden'")->fetchColumn();

require_once 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div style="background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 12px 20px; border-radius: 30px; margin-bottom: 20px; border: 1px solid rgba(76, 175, 80, 0.3);">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- ОТВЕТ НА ВОПРОС -->
<!-- ============================================ -->
<?php if ($view === 'answer' && isset($question)): ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-question-circle" style="color: var(--primary-orange);"></i>
        Ответ на вопрос
    </h2>
    <a href="questions.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Назад к списку
    </a>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- Вопрос -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">📝 Вопрос</h3>
        
        <div style="margin-bottom: 15px;">
            <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Товар</div>
            <div style="font-weight: 600;"><?php echo htmlspecialchars($question['product_name']); ?></div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Автор</div>
            <div style="font-weight: 600;"><?php echo htmlspecialchars($question['user_name'] ?? 'Пользователь'); ?></div>
            <div><?php echo htmlspecialchars($question['user_email'] ?? ''); ?></div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Дата</div>
            <div><?php echo date('d.m.Y H:i', strtotime($question['created_at'])); ?></div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <div style="color: var(--admin-text-secondary); font-size: 0.85rem;">Вопрос</div>
            <div style="background: var(--admin-surface-light); padding: 15px; border-radius: 10px; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($question['question'])); ?>
            </div>
        </div>
    </div>
    
    <!-- Форма ответа -->
    <div class="card">
        <h3 style="margin-bottom: 20px;">✏️ Ваш ответ</h3>
        
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $question['id']; ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: var(--admin-text-secondary);">Ответ</label>
                <textarea name="answer" rows="8" style="width: 100%; padding: 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 10px; color: var(--admin-text);"><?php echo htmlspecialchars($question['answer'] ?? ''); ?></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="save_answer" class="btn btn-primary">
                    <i class="fas fa-save"></i> Сохранить ответ
                </button>
                <a href="questions.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Отмена
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- СПИСОК ВОПРОСОВ -->
<!-- ============================================ -->
<?php else: ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h2 style="font-size: 1.8rem;">
        <i class="fas fa-question-circle" style="color: var(--primary-orange);"></i>
        Управление вопросами
    </h2>
</div>

<!-- Статистика -->
<div class="card-grid">
    <div class="card">
        <div class="card-title">Всего вопросов</div>
        <div class="card-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="card-title" style="color: var(--warning);">Ожидают ответа</div>
        <div class="card-value" style="color: var(--warning);"><?php echo $stats['pending']; ?></div>
    </div>
    <div class="card">
        <div class="card-title" style="color: var(--success);">Опубликовано</div>
        <div class="card-value" style="color: var(--success);"><?php echo $stats['published']; ?></div>
    </div>
    <div class="card">
        <div class="card-title" style="color: var(--danger);">Скрыто</div>
        <div class="card-value" style="color: var(--danger);"><?php echo $stats['hidden']; ?></div>
    </div>
</div>

<!-- Фильтры -->
<div class="card" style="margin-bottom: 25px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Статус</label>
            <select name="status" style="width: 100%; padding: 10px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
                <option value="">Все</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Ожидают ответа</option>
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
        
        <div style="flex: 2; min-width: 200px;">
            <label style="display: block; margin-bottom: 5px; color: var(--admin-text-secondary);">Поиск</label>
            <input type="text" name="search" placeholder="Текст вопроса, ответа, автор" value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 10px 15px; background: var(--admin-surface-light); border: 1px solid var(--admin-border); border-radius: 30px; color: var(--admin-text);">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="questions.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Сброс
            </a>
        </div>
    </form>
</div>

<!-- Таблица вопросов -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Товар</th>
                <th>Автор</th>
                <th>Вопрос</th>
                <th>Дата</th>
                <th>Статус</th>
                <th>Ответ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($questions) > 0): ?>
                <?php foreach ($questions as $q): ?>
                <tr>
                    <td>#<?php echo $q['id']; ?></td>
                    <td>
                        <a href="products.php?view=edit&id=<?php echo $q['product_id']; ?>" style="color: var(--admin-text);">
                            <?php echo htmlspecialchars(mb_substr($q['product_name'], 0, 30)) . '...'; ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($q['user_id']): ?>
                            <strong><?php echo htmlspecialchars($q['user_name'] ?? 'Пользователь'); ?></strong><br>
                            <small><?php echo htmlspecialchars($q['user_email'] ?? ''); ?></small>
                        <?php else: ?>
                            <span style="color: var(--gray-dark);">Пользователь удалён</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars(mb_substr($q['question'], 0, 50)) . '...'; ?></td>
                    <td><?php echo date('d.m.Y', strtotime($q['created_at'])); ?></td>
                    <td>
                        <?php if ($q['status'] == 'published'): ?>
                            <span class="badge badge-success">Опубликован</span>
                        <?php elseif ($q['status'] == 'pending'): ?>
                            <span class="badge badge-warning">Ожидает ответа</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Скрыт</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($q['answer']): ?>
                            <span class="badge badge-success">Есть ответ</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Нет ответа</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="questions.php?view=answer&id=<?php echo $q['id']; ?>" class="btn btn-primary" style="padding: 5px 8px;" title="Ответить">
                            <i class="fas fa-reply"></i>
                        </a>
                        
                        <?php if ($q['status'] != 'published'): ?>
                            <a href="questions.php?publish=<?php echo $q['id']; ?>" class="btn btn-success" style="padding: 5px 8px;" title="Опубликовать">
                                <i class="fas fa-check"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($q['status'] != 'hidden'): ?>
                            <a href="questions.php?hide=<?php echo $q['id']; ?>" class="btn btn-secondary" style="padding: 5px 8px;" title="Скрыть">
                                <i class="fas fa-eye-slash"></i>
                            </a>
                        <?php endif; ?>
                        
                        <a href="questions.php?delete=<?php echo $q['id']; ?>" class="btn btn-danger" style="padding: 5px 8px;" title="Удалить" onclick="return confirm('Удалить вопрос?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                        <i class="fas fa-question-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Вопросы не найдены
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>