<?php
// ============================================
// oop_demo.php - ДЕМОНСТРАЦИЯ ООП
// ============================================
session_start();

// ============================================
// КЛАСС ДЛЯ РАБОТЫ С КОРЗИНОЙ
// ============================================
class Cart {
    private $items = [];
    private $totalCount = 0;
    private $totalSum = 0;
    
    /**
     * Конструктор
     */
    public function __construct() {
        $this->loadDemoData();
    }
    
    /**
     * Загружает тестовые данные
     */
    private function loadDemoData() {
        $this->items = [
            [
                'id' => 1,
                'name' => 'Картина "Весеннее настроение"',
                'price' => 350.00,
                'quantity' => 2,
                'artist' => 'Анна Иванова'
            ],
            [
                'id' => 2,
                'name' => 'Скульптура "Медитация"',
                'price' => 1200.00,
                'quantity' => 1,
                'artist' => 'Петр Сидоров'
            ],
            [
                'id' => 3,
                'name' => 'Панно "Городской пейзаж"',
                'price' => 780.00,
                'quantity' => 1,
                'artist' => 'Елена Петрова'
            ]
        ];
        
        $this->calculateTotals();
    }
    
    /**
     * Пересчёт итогов
     */
    private function calculateTotals() {
        $this->totalCount = 0;
        $this->totalSum = 0;
        
        foreach ($this->items as $item) {
            $this->totalCount += $item['quantity'];
            $this->totalSum += $item['price'] * $item['quantity'];
        }
    }
    
    /**
     * Получить все товары
     */
    public function getItems() {
        return $this->items;
    }
    
    /**
     * Получить общее количество
     */
    public function getTotalCount() {
        return $this->totalCount;
    }
    
    /**
     * Получить общую сумму
     */
    public function getTotalSum() {
        return $this->totalSum;
    }
    
    /**
     * Проверить, пуста ли корзина
     */
    public function isEmpty() {
        return $this->totalCount === 0;
    }
    
    /**
     * Добавить товар
     */
    public function addItem() {
        $newItem = [
            'id' => 4,
            'name' => 'Статуэтка "Гармония"',
            'price' => 450.00,
            'quantity' => 1,
            'artist' => 'Дмитрий Волков'
        ];
        
        $this->items[] = $newItem;
        $this->calculateTotals();
        return true;
    }
    
    /**
     * Удалить товар
     */
    public function removeItem($id) {
        foreach ($this->items as $key => $item) {
            if ($item['id'] == $id) {
                unset($this->items[$key]);
                $this->items = array_values($this->items);
                $this->calculateTotals();
                return true;
            }
        }
        return false;
    }
    
    /**
     * Очистить корзину
     */
    public function clear() {
        $this->items = [];
        $this->totalCount = 0;
        $this->totalSum = 0;
        return true;
    }
    
    /**
     * Сбросить к тестовым данным
     */
    public function reset() {
        $this->loadDemoData();
        return true;
    }
}

// ============================================
// СОЗДАЁМ ОБЪЕКТ КОРЗИНЫ
// ============================================
$cart = new Cart();

// ============================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================
$message = '';

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'add':
            $cart->addItem();
            $message = 'Товар добавлен в корзину';
            break;
        case 'clear':
            $cart->clear();
            $message = 'Корзина очищена';
            break;
        case 'reset':
            $cart->reset();
            $message = 'Корзина сброшена к тестовым данным';
            break;
    }
}

// Удаление конкретного товара
if (isset($_GET['remove'])) {
    $cart->removeItem((int)$_GET['remove']);
    $message = 'Товар удалён из корзины';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Демонстрация ООП - ARTOBJECT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #0a0a0a 0%, #2C2C2C 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header .orange {
            color: #FF5A30;
        }
        
        .message {
            background: rgba(255, 90, 48, 0.1);
            color: #FF5A30;
            padding: 15px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 90, 48, 0.3);
            text-align: center;
        }
        
        .cart-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .cart-card h2 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #FF5A30;
            padding-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-item {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #FF5A30;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th {
            background: #f0f0f0;
            padding: 15px;
            text-align: left;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #FF5A30 0%, #FF8A00 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 90, 48, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            color: #333;
            border: 2px solid #ddd;
        }
        
        .btn-secondary:hover {
            border-color: #FF5A30;
            color: #FF5A30;
        }
        
        .btn-small {
            padding: 5px 10px;
            border-radius: 5px;
            background: #f44336;
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-small:hover {
            background: #d32f2f;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px;
            color: #666;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Шапка -->
        <div class="header">
            <h1>Демонстрация <span class="orange">объектно-ориентированного</span> программирования</h1>
            <p>Класс Cart для работы с корзиной покупателя</p>
        </div>
        
        <!-- Сообщение -->
        <?php if ($message): ?>
            <div class="message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Корзина -->
        <div class="cart-card">
            <h2>Корзина покупателя</h2>
            
            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $cart->getTotalCount(); ?></div>
                    <div class="stat-label">Товаров</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($cart->getTotalSum(), 2); ?> BYN</div>
                    <div class="stat-label">Сумма</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $cart->isEmpty() ? 'Да' : 'Нет'; ?></div>
                    <div class="stat-label">Корзина пуста</div>
                </div>
            </div>
            
            <!-- Список товаров -->
            <?php if ($cart->isEmpty()): ?>
                <div class="empty-cart">
                    <p>Корзина пуста</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Художник</th>
                            <th>Цена</th>
                            <th>Кол-во</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart->getItems() as $item): ?>
                        <tr>
                            <td><strong><?php echo $item['name']; ?></strong></td>
                            <td><?php echo $item['artist']; ?></td>
                            <td><?php echo number_format($item['price'], 2); ?> BYN</td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><strong><?php echo number_format($item['price'] * $item['quantity'], 2); ?> BYN</strong></td>
                            <td>
                                <a href="?remove=<?php echo $item['id']; ?>" class="btn-small" onclick="return confirm('Удалить товар?')">✕</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Кнопки управления -->
            <div class="btn-group">
                <a href="?action=add" class="btn btn-primary">
                     Добавить товар
                </a>
                <a href="?action=clear" class="btn btn-secondary" onclick="return confirm('Очистить корзину?')">
                     Очистить корзину
                </a>
                <a href="?action=reset" class="btn btn-secondary">
                     Сбросить
                </a>
            </div>
        </div>
        
        <!-- Подвал -->
        <div class="footer">
            <p>© 2025 ARTOBJECT Gallery. Демонстрация работы класса Cart.</p>
        </div>
    </div>
</body>
</html>