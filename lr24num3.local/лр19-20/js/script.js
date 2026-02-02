// Данные каталога (в реальном проекте можно загружать из отдельного JSON файла)
// Загрузка данных из JSON файла
let catalogData = [];

fetch('catalog.json')
    .then(response => response.json())
    .then(data => {
        catalogData = data;
        displayCatalog();
    })
    .catch(error => {
        console.error('Ошибка загрузки каталога:', error);
    });

// Инициализация корзины
let cart = JSON.parse(localStorage.getItem('cart')) || [];

// Функция для отображения каталога товаров
function displayCatalog() {
    const productGrid = document.getElementById('productGrid');
    productGrid.innerHTML = '';

    catalogData.forEach(product => {
        const productCard = document.createElement('div');
        productCard.className = 'product-card';

        productCard.innerHTML = `
        <img src="${product.image}" alt="${product.name}" class="product-image">
            <div class="product-info">
                <h3 class="product-title">${product.name} <small class="product-title_1">${product.name1}</small></h3>
                <p class="product-description">${product.description}</p>
                <div class="product-price">${product.price} Br</div>
                <button class="add-to-cart" data-id="${product.id}">Добавить в корзину</button>
            </div>
        `;
        
        productGrid.appendChild(productCard);
    });
    
    // Добавляем обработчики событий для кнопок "Добавить в корзину"
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', (e) => {
            const productId = parseInt(e.target.getAttribute('data-id'));
            addToCart(productId);
        });
    });
}

// Функция для добавления товара в корзину
function addToCart(productId) {
    const product = catalogData.find(item => item.id === productId);
    
    if (!product) return;
    
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            image: product.image,
            quantity: 1
        });
    }
    
    // Сохраняем корзину в localStorage
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Обновляем отображение корзины
    displayCart();
    
    // Показываем уведомление
    showNotification(`${product.name} добавлен в корзину!`);
}

// Функция для удаления товара из корзины
function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    
    // Сохраняем корзину в localStorage
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Обновляем отображение корзины
    displayCart();
    
    // Показываем уведомление
    const product = catalogData.find(item => item.id === productId);
    if (product) {
        showNotification(`${product.name} удален из корзины`);
    }
}

// Функция для изменения количества товара в корзине
function updateQuantity(productId, change) {
    const item = cart.find(item => item.id === productId);
    
    if (item) {
        item.quantity += change;
        
        if (item.quantity <= 0) {
            removeFromCart(productId);
            return;
        }
        
        // Сохраняем корзину в localStorage
        localStorage.setItem('cart', JSON.stringify(cart));
        
        // Обновляем отображение корзины
        displayCart();
    }
}

// Функция для отображения корзины
function displayCart() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<div class="empty-cart">Корзина пуста</div>';
        cartTotal.textContent = 'Итого: 0 Br';
        return;
    }
    
    cartItems.innerHTML = '';
    let total = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        const cartItem = document.createElement('div');
        cartItem.className = 'cart-item';
        
        cartItem.innerHTML =`
            <div class="cart-item-info">
                <div class="cart-item-title">${item.name}</div>
                <div class="cart-item-price">${item.price} Br × ${item.quantity} = ${itemTotal} Br</div>
            </div>
            <div class="cart-item-controls">
                <button class="quantity-btn minus" data-id="${item.id}">-</button>
                <span>${item.quantity}</span>
                <button class="quantity-btn plus" data-id="${item.id}">+</button>
                <button class="quantity-btn remove" data-id="${item.id}">×</button>
                </div>
        `;

        cartItems.appendChild(cartItem);
    });

    // Добавляем обработчики событий для кнопок в корзине
    document.querySelectorAll('.quantity-btn.minus').forEach(button => {
        button.addEventListener('click', (e) => {
            const productId = parseInt(e.target.getAttribute('data-id'));
            updateQuantity(productId, -1);
        });
    });

    document.querySelectorAll('.quantity-btn.plus').forEach(button => {
        button.addEventListener('click', (e) => {
            const productId = parseInt(e.target.getAttribute('data-id'));
            updateQuantity(productId, 1);
        });
    });

    document.querySelectorAll('.quantity-btn.remove').forEach(button => {
        button.addEventListener('click', (e) => {
            const productId = parseInt(e.target.getAttribute('data-id'));
            removeFromCart(productId);
        });
    });

    cartTotal.textContent = `Итого: ${total} Br`;
}

// Функция для показа уведомлений
function showNotification(message) {
    // Создаем элемент уведомления
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #000000ff;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        transition: transform 0.3s, opacity 0.3s;
        transform: translateX(100%);
        opacity: 0;
    `;
    
    document.body.appendChild(notification);
    
    // Анимация появления
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
        notification.style.opacity = '1';
    }, 10);
    
    // Анимация исчезновения и удаление через 3 секунды
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        notification.style.opacity = '0';
        
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Функция для оформления заказа
function checkout() {
    if (cart.length === 0) {
        showNotification('Корзина пуста!');
        return;
    }
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    // В реальном приложении здесь была бы отправка данных на сервер
    showNotification(`Заказ оформлен! Сумма: ${total} Br`);

    // Очищаем корзину
    cart = [];
    localStorage.setItem('cart', JSON.stringify(cart));
    displayCart();
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    displayCatalog();
    displayCart();

    // Добавляем обработчик для кнопки оформления заказа
    document.getElementById('checkoutBtn').addEventListener('click', checkout);
});