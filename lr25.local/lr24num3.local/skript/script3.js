// Получаем элементы
const modal_fm = document.getElementById("modal_fm");
const orderButton_fm = document.getElementById("order-button_fm");
const closeButton_fm = document.querySelector(".close-button_fm");



// Функция открытия модального окна
function openModal() {
    modal_fm.style.display = "block";
}

// Функция закрытия модального окна
function closeModal() {
    modal_fm.style.display = "none";
}

// Открытие по клику на кнопку VIEW MENU
if (orderButton_fm) {
    orderButton_fm.addEventListener("click", function (event) {
        event.preventDefault(); // Предотвращаем отправку формы (если кнопка в форме)
        openModal();
    });
}

// Закрытие по клику на крестик
if (closeButton_fm) {
    closeButton_fm.addEventListener("click", closeModal);
}

// Закрытие при клике вне модального окна
window.addEventListener("click", (event) => {
    if (event.target === modal_fm) {
        closeModal();
    }
});

// Закрытие по клавише Escape
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal_fm.style.display === 'block') {
        closeModal();
    }
});