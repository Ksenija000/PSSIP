// Получение элементов
const orderButton_fm = document.getElementById("order-button_fm");
const modal_fm = document.getElementById("modal_fm");
const closeButton_fm = document.querySelector(".close-button_fm");
const dishSelect = document.getElementById("dish");
const quantityInput = document.getElementById("quantity");
const phoneInput = document.querySelector(".phoneMask");
const addressInput = document.getElementById("address");
const confirmButton = document.getElementById("confirm-button");

// Функция для сохранения заказа в Local Storage
function saveOrderToLocalStorage(order) {
    // Получаем текущие заказы из Local Storage
    const existingOrders = JSON.parse(localStorage.getItem('orders')) || [];

    // Добавляем новый заказ
    existingOrders.push({
        ...order,
        timestamp: new Date().toLocaleString() // Добавляем время заказа
    });

    // Сохраняем обратно в Local Storage
    localStorage.setItem('orders', JSON.stringify(existingOrders));
}

// Функция для получения всех заказов из Local Storage
function getOrdersFromLocalStorage() {
    return JSON.parse(localStorage.getItem('orders')) || [];
}

// Функция для очистки Local Storage
function clearLocalStorage() {
    localStorage.removeItem('orders');
    alert('Local Storage пуст!');
}

// Функция для показа истории заказов
function showOrderHistory() {
    const orders = getOrdersFromLocalStorage();

    if (orders.length === 0) {
        return; // Не показываем alert если нет заказов
    }

    let message = 'История ваших заказов:\n\n';
    orders.forEach((order, index) => {
        message += `Заказ ${ index + 1 }: \n`;
        message += `Блюдо: ${ order.dish } \n`;
        message += `Количество: ${ order.quantity } \n`;
        message += `Телефон: ${ order.phone } \n`;
        message += `Адрес: ${ order.address } \n`;
        message += `Время: ${ order.timestamp } \n\n`;
    });

    message += 'Хотите очистить историю заказов?';

    // Показываем alert с кнопками
    if (confirm(message)) {
        // Если пользователь нажал OK -чищаем Local Storage 
        clearLocalStorage();
    } else {
        // Если пользователь нажал Отмена - ничего не делаем
    }
}

// Показываем историю заказов при загрузке страницы
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(showOrderHistory, 500); // Небольшая задержка для лучшего UX
});

// Открытие модального окна
orderButton_fm.addEventListener("click", () => {
    modal_fm.style.display = "block";
});

// Закрытие модального окна
closeButton_fm.addEventListener("click", () => {
    modal_fm.style.display = "none";
});

// Закрытие при нажатии Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal_fm.style.display === 'block') {
        modal_fm.style.display = 'none';
    }
});

// Закрытие модального окна при клике вне его
window.addEventListener("click", (event) => {
    if (event.target === modal_fm) {
        modal_fm.style.display = "none";
    }
});

// Логирование выбранного блюда
dishSelect.addEventListener("change", () => {
    console.log(`Выбрано блюдо: ${ dishSelect.value }`);
});

// Логирование количества
quantityInput.addEventListener("input", () => {
    const quantity = quantityInput.value;
    console.log(`Количество: ${ quantity }`);
});

// Маска для телефона
const mask = new IMask(
    phoneInput,
    { mask: "+{375}(00)000-00-00" }
);

// Пример заполнения адреса
addressInput.addEventListener("focus", () => {
    addressInput.placeholder = "Пример: Улица Пушкина, дом 12, квартира 5";
});

addressInput.addEventListener("blur", () => {
    addressInput.placeholder = "Улица, дом, квартира";
});

// Подтверждение заказа
confirmButton.addEventListener("click", () => {
    const dish = dishSelect.value;
    const quantity = quantityInput.value;
    const phone = phoneInput.value;
    const address = addressInput.value;

    // Проверка на заполненность обязательных полей
    if (phone && address) {
// Создаем объект заказа
        const order = {
            dish: dish,
            quantity: quantity,
            phone: phone,
            address: address
        };

        // Сохраняем заказ в Local Storage
        saveOrderToLocalStorage(order);

        // Показываем подтверждение и историю заказов
        alert(`Ваш заказ принят!\nБлюдо: ${ dish }\nКоличество: ${ quantity }\nТелефон: ${ phone }\nАдрес: ${ address }`);

        // Показываем историю заказов
        showOrderHistory();

        // Закрываем модальное окно и сбрасываем форму
        modal_fm.style.display = "none";
        document.getElementById("order-form").reset();

    } else {
        alert("Пожалуйста, заполните все обязательные поля.");
    }
});
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

// // Функции для работы с Cookies
// function setCookie(name, value, days) {
//     try {
//         const date = new Date();
//         date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
//         const expires = "expires=" + date.toUTCString();
//         document.cookie = name + "=" + encodeURIComponent(JSON.stringify(value)) + ";" + expires + ";path=/";
//         return true;
//     } catch (e) {
//         console.error("Ошибка при сохранении в cookie:", e);
//         return false;
//     }
// }

// function getCookie(name) {
//     try {
//         const nameEQ = name + "=";
//         const ca = document.cookie.split(';');
//         for (let i = 0; i < ca.length; i++) {
//             let c = ca[i];
//             while (c.charAt(0) === ' ') c = c.substring(1, c.length);
//             if (c.indexOf(nameEQ) === 0) {
//                 const cookieValue = c.substring(nameEQ.length, c.length);
//                 return JSON.parse(decodeURIComponent(cookieValue));
//             }
//         }
//         return null;
//     } catch (e) {
//         console.error("Ошибка при чтении cookie:", e);
//         return null;
//     }
// }

// function deleteCookie(name) {
//     document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
// }

// // Функция для сохранения заказа в Cookies
// function saveOrderToCookie(order) {
//     const existingOrders = getCookie('orders') || [];
// ////////////////////////////////////////////////////////////////////////////////////////////////
//     // Добавляем новый заказ
//     existingOrders.push({
//         ...order,
//         timestamp: new Date().toLocaleString()
//     });

//     // Сохраняем обратно в Cookies (срок хранения - 30 дней)
//     return setCookie('orders', existingOrders, 30);
// }

// // Функция для получения всех заказов из Cookies
// function getOrdersFromCookie() {
//     return getCookie('orders') || [];
// }

// // Функция для очистки Cookies
// function clearOrdersCookie() {
//     deleteCookie('orders');
//     alert('История заказов очищена!');
// }

// // Функция для показа истории заказов
// function showOrderHistory() {
//     const orders = getOrdersFromCookie();

//     if (orders.length === 0) {
//         return; // Не показываем alert если нет заказов
//     }

//     let message = 'История ваших заказов:\n\n';
//     orders.forEach((order, index) => {
//         message += `Заказ ${ index + 1 }: \n`;
//         message += `Блюдо: ${ order.dish } \n`;
//         message += `Количество: ${ order.quantity } \n`;
//         message += `Телефон: ${ order.phone } \n`;
//         message += `Адрес: ${ order.address } \n`;
//         message += `Время: ${ order.timestamp } \n\n`;
//     });

//     message += 'Нажмите:\n- "OK" чтобы оставить историю\n- "Отмена" чтобы очистить историю заказов';

//     // Показываем confirm с кнопками
//     const userChoice = confirm(message);
//     if (!userChoice) {
//         // Если пользователь нажал Отмена - очищаем Cookies
//         clearOrdersCookie();
//     }
// }

// // Инициализация при загрузке страницы
// document.addEventListener('DOMContentLoaded', function () {
//     // Получение элементов
//     const orderButton_fm = document.getElementById("order-button_fm");
//     const modal_fm = document.getElementById("modal_fm");
//     const closeButton_fm = document.querySelector(".close-button_fm");
//     const dishSelect = document.getElementById("dish");
//     const quantityInput = document.getElementById("quantity");
//     const phoneInput = document.querySelector(".phoneMask");
//     const addressInput = document.getElementById("address");
//     const confirmButton = document.getElementById("confirm-button");

//     // Проверяем, что все необходимые элементы существуют
//     if (!modal_fm || !closeButton_fm || !dishSelect || !quantityInput || !phoneInput || !addressInput || !confirmButton) {
//     console.error("Не все необходимые элементы найдены на странице");
//     return;
// }

// console.log("Все элементы формы найдены");

// // Показываем историю заказов при загрузке страницы
// setTimeout(showOrderHistory, 500);
// // Открытие модального окна (если кнопка существует)
// if (orderButton_fm) {
//     orderButton_fm.addEventListener("click", () => {
//         modal_fm.style.display = "block";
//         console.log("Модальное окно открыто");
//     });
// } else {
//     console.warn("Кнопка открытия модального окна не найдена");
// }

// // Закрытие модального окна
// closeButton_fm.addEventListener("click", () => {
//     modal_fm.style.display = "none";
//     console.log("Модальное окно закрыто");
// });

// // Закрытие модального окна при клике вне его
// window.addEventListener("click", (event) => {
//     if (event.target === modal_fm) {
//         modal_fm.style.display = "none";
//         console.log("Модальное окно закрыто по клику вне его");
//     }
// });

// // Логирование выбранного блюда
// dishSelect.addEventListener("change", () => {
//     console.log(`Выбрано блюдо: ${ dishSelect.value }`);
// });

// // Логирование количества
// quantityInput.addEventListener("input", () => {
//     const quantity = quantityInput.value;
//     console.log(`Количество: ${ quantity }`);
// });

// // Маска для телефона (если библиотека IMask доступна)
// if (typeof IMask !== 'undefined' && phoneInput) {
//     const mask = new IMask(
//         phoneInput,
//         { mask: "+{375}(00)000-00-00" }
//     );
//     console.log("Маска для телефона применена");
// } else {
//     console.warn("Библиотека IMask не найдена или поле телефона отсутствует");
// }

// // Пример заполнения адреса
// addressInput.addEventListener("focus", () => {
//     addressInput.placeholder = "Пример: Улица Пушкина, дом 12, квартира 5";
// });

// addressInput.addEventListener("blur", () => {
//     addressInput.placeholder = "Улица, дом, квартира";
// });

// // Подтверждение заказа
// confirmButton.addEventListener("click", () => {
//     console.log("Кнопка подтвердить нажата");

//     const dish = dishSelect.value;
//     const quantity = quantityInput.value;
//     const phone = phoneInput.value;
//     const address = addressInput.value;

//     // Проверка на заполненность обязательных полей
//     if (phone && address) {
//         // Создаем объект заказа
//         const order = {
//             dish: dish,
//             quantity: quantity,
//             phone: phone,
//             address: address
//         };

//         // Сохраняем заказ в Cookies
//         const saved = saveOrderToCookie(order);

//         if (saved) {
//             // Показываем подтверждение текущего заказа
//             alert(`Ваш заказ принят!\nБлюдо: ${ dish }\nКоличество: ${ quantity }\nТелефон: ${ phone }\nАдрес: ${ address }`);

//             // Показываем историю заказов
//             showOrderHistory();

//             // Закрываем модальное окно и сбрасываем форму
//             modal_fm.style.display = "none";
//             document.getElementById("order-form").reset();
//             console.log("Заказ сохранен и форма сброшена");
//         } else {
//             alert("Произошла ошибка при сохранении заказа. Попробуйте еще раз.");
//         }

//     } else {
//         alert("Пожалуйста, заполните все обязательные поля.");
//         console.log("Не все обязательные поля заполнены");
//     }
// });

// console.log("Обработчики событий успешно добавлены");
// });
