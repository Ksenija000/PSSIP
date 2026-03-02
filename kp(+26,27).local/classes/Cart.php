<?php
// ============================================
// classes/Cart.php - КЛАСС ДЛЯ РАБОТЫ С КОРЗИНОЙ
// ============================================

class Cart {
    private $db;
    private $userId;
    private $items = [];
    private $totalCount = 0;
    private $totalSum = 0;
    
    /**
     * Конструктор 
     * @param PDO $db - подключение к БД
     * @param int|null $userId - ID пользователя или null для гостя
     */
    public function __construct($db, $userId = null) {
        $this->db = $db;
        $this->userId = $userId;
        $this->loadItems();
    }
    
    /**
     * Загрузить товары из корзины 
     */
    private function loadItems() {
        $this->items = [];
        $this->totalCount = 0;
        $this->totalSum = 0;
        
        if ($this->userId) {
            // Для авторизованных - из БД
            $stmt = $this->db->prepare("
                SELECT c.*, p.name, p.price, p.discount_price, p.image, p.stock_quantity
                FROM cart_items c
                LEFT JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ? AND p.deleted_at IS NULL
            ");
            $stmt->execute([$this->userId]);
            $this->items = $stmt->fetchAll();
            
            // Считаем итоги
            foreach ($this->items as $item) {
                $this->totalCount += $item['quantity'];
                $price = $item['discount_price'] ?? $item['price'];
                $this->totalSum += $price * $item['quantity'];
            }
        } else {
            // Для гостей - из сессии
            $guestCart = $_SESSION['guest_cart'] ?? [];
            if (!empty($guestCart)) {
                $productIds = array_keys($guestCart);
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                
                $stmt = $this->db->prepare("
                    SELECT id, name, price, discount_price, image, stock_quantity
                    FROM products 
                    WHERE id IN ($placeholders) AND deleted_at IS NULL
                ");
                $stmt->execute($productIds);
                $products = $stmt->fetchAll();
                
                foreach ($products as $product) {
                    $productId = $product['id'];
                    $quantity = $guestCart[$productId];
                    $price = $product['discount_price'] ?? $product['price'];
                    
                    $this->items[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'name' => $product['name'],
                        'price' => $price,
                        'image' => $product['image'],
                        'stock_quantity' => $product['stock_quantity']
                    ];
                    
                    $this->totalCount += $quantity;
                    $this->totalSum += $price * $quantity;
                }
            }
        }
    }
    
    /**
     * Получить все товары в корзине
     * @return array
     */
    public function getItems() {
        return $this->items;
    }
    
    /**
     * Получить общее количество товаров
     * @return int
     */
    public function getTotalCount() {
        return $this->totalCount;
    }
    
    /**
     * Получить общую сумму
     * @return float
     */
    public function getTotalSum() {
        return $this->totalSum;
    }
    
    /**
     * Проверить, пуста ли корзина
     * @return bool
     */
    public function isEmpty() {
        return $this->totalCount === 0;
    }
    
    /**
     * Добавить товар в корзину
     * @param int $productId
     * @param int $quantity
     * @return bool
     */
    public function addItem($productId, $quantity = 1) {
        if ($this->userId) {
            // Проверяем, есть ли уже
            $stmt = $this->db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$this->userId, $productId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Обновляем количество
                $newQty = $existing['quantity'] + $quantity;
                $stmt = $this->db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQty, $existing['id']]);
            } else {
                // Добавляем новый
                $stmt = $this->db->prepare("INSERT INTO cart_items (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$this->userId, $productId, $quantity]);
            }
        } else {
            // Для гостей
            if (!isset($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }
            
            if (isset($_SESSION['guest_cart'][$productId])) {
                $_SESSION['guest_cart'][$productId] += $quantity;
            } else {
                $_SESSION['guest_cart'][$productId] = $quantity;
            }
        }
        
        // Перезагружаем корзину
        $this->loadItems();
        return true;
    }
    
    /**
     * Обновить количество товара
     * @param int $productId
     * @param int $quantity
     * @return bool
     */
    public function updateQuantity($productId, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($productId);
        }
        
        if ($this->userId) {
            $stmt = $this->db->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $this->userId, $productId]);
        } else {
            if (isset($_SESSION['guest_cart'][$productId])) {
                $_SESSION['guest_cart'][$productId] = $quantity;
            }
        }
        
        $this->loadItems();
        return true;
    }
    
    /**
     * Удалить товар из корзины
     * @param int $productId
     * @return bool
     */
    public function removeItem($productId) {
        if ($this->userId) {
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$this->userId, $productId]);
        } else {
            unset($_SESSION['guest_cart'][$productId]);
        }
        
        $this->loadItems();
        return true;
    }
    
    /**
     * Очистить корзину
     * @return bool
     */
    public function clear() {
        if ($this->userId) {
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$this->userId]);
        } else {
            $_SESSION['guest_cart'] = [];
        }
        
        $this->loadItems();
        return true;
    }
    
    /**
     * Магический метод - вызывается при преобразовании в строку
     * @return string
     */
    public function __toString() {
        return "Корзина: {$this->totalCount} товаров на сумму {$this->totalSum} BYN";
    }
    
    /**
     * Получить количество конкретного товара
     * @param int $productId
     * @return int
     */
    public function getItemQuantity($productId) {
        foreach ($this->items as $item) {
            if (($item['product_id'] ?? $item['id']) == $productId) {
                return $item['quantity'];
            }
        }
        return 0;
    }
}
?>