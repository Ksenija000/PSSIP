// Загрузка данных при загрузке страницы
document.addEventListener('DOMContentLoaded', function () {
    loadCookieData();
    loadJSONData();

    // Обработчик для кнопки очистки cookie
    document.getElementById('clearCookiesBtn').addEventListener('click', function () {
        clearCookies();
        loadCookieData(); // Обновляем отображение
        loadJSONData(); // Обновляем JSON данные
    });
});

document.getElementById('feedbackForm').addEventListener('submit', function (event) {
    event.preventDefault();

    // Валидация 
    if (!validateForm()) {
        return;
    }

    // Получение данных формы
    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        question: document.getElementById('question').value
    };

    // Сохранение в cookie
    saveToCookies(formData);

    // Сохранение в JSON формате
    saveToJSON(formData);

    // Вывод данных
    showFormData(formData);

    // Очистка формы
    this.reset();
});

// Функция валидации 
function validateForm() {
    const fields = [
        { id: 'name', name: 'Имя' },
        { id: 'email', name: 'Email' },
        { id: 'phone', name: 'Телефон' },
        { id: 'question', name: 'Вопрос' }
    ];

    // Проверка на пустые поля
    for (let field of fields) {
        const value = document.getElementById(field.id).value.trim();
        if (!value) {
            alert(`Поле "${field.name}" не должно быть пустым`);
            return false;
        }
    }

    const email = document.getElementById('email').value;
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
    if (!emailRegex.test(email)) {
        alert('Введите корректный email адрес, пример: name@gmail.com');
        return false;
    }

    const phone = document.getElementById('phone').value;
    const regexphone = /^\+375\s*\(\d{2}\)\s*\d{3}\s*-\s*\d{2}\s*-\s*\d{2}$/;
    if (!regexphone.test(phone)) {
        alert('Введите корректный номер телефона в формате: +375 (XX) XXX-XX-XX');
        return false;
    }

    const question = document.getElementById('question').value;
    if (question.length < 2) {
        alert('Вопрос должен содержать минимум 2 символа');
        return false;
    }

    const name = document.getElementById('name').value;
    const nameRegex = /^[A-Za-zА-Яа-яЁё\s]+$/;
    if (!nameRegex.test(name) || name.length < 2) {
        alert('Имя должно содержать только буквы (минимум 2 символа)');
        return false;
    }

    return true;
}

// Функция отображения данных формы
function showFormData(formData) {
    const outputDiv = document.getElementById('formDataOutput');
    outputDiv.innerHTML =` 
        <div class="field-value">Имя: ${formData.name}</div>
        <div class="field-value">Email: ${formData.email}</div>
        <div class="field-value">Телефон: ${formData.phone}</div>
        <div class="field-value">Вопрос: ${formData.question}</div>
        `;

    document.getElementById('outputSection').style.display = 'block';
}

// Функция сохранения данных в cookie
function saveToCookies(formData) {
    const date = new Date();
    date.setTime(date.getTime() + (7 * 24 * 60 * 60 * 1000)); // Cookie на 7 дней
    const expires = "expires=" + date.toUTCString();

    // Сохраняем каждое поле отдельно
    document.cookie = "formName=" + encodeURIComponent(formData.name) + ";" + expires + ";path=/";
    document.cookie = "formEmail=" + encodeURIComponent(formData.email) + ";" + expires + ";path=/";
    document.cookie = "formPhone=" + encodeURIComponent(formData.phone) + ";" + expires + ";path=/";
    document.cookie = "formQuestion=" + encodeURIComponent(formData.question) + ";" + expires + ";path=/";
}

// Функция сохранения данных в JSON формате
function saveToJSON(formData) {
    localStorage.setItem('formDataJSON', JSON.stringify(formData));

    loadCookieData(); // Обновляем отображение
    loadJSONData(); // Обновляем JSON данные
}
// Функция загрузки данных из cookie
function loadCookieData() {
    const cookies = document.cookie.split(';');
    const cookieData = {};

    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name && value) {
            cookieData[name] = decodeURIComponent(value);
        }
    }

    // Отображаем данные из cookie, если они есть
    const cookieOutput = document.getElementById('cookieDataOutput');
    if (cookieData.formName || cookieData.formEmail || cookieData.formPhone || cookieData.formQuestion) {
        cookieOutput.innerHTML =` 
            <div class="field-value">Имя: ${cookieData.formName || ''}</div>
            <div class="field-value">Email: ${cookieData.formEmail || ''}</div>
            <div class="field-value">Телефон: ${cookieData.formPhone || ''}</div>
            <div class="field-value">Вопрос: ${cookieData.formQuestion || ''}</div>
            `;
        document.getElementById('outputSection').style.display = 'block';
    } else {
        cookieOutput.innerHTML = '<div class="field-value">Cookie пусты</div>';
    }
}

// Функция загрузки и отображения JSON данных
function loadJSONData() {
    const jsonData = localStorage.getItem('formDataJSON');
    const jsonOutput = document.getElementById('jsonOutput');

    if (jsonData) {
        const parsedData = JSON.parse(jsonData);
        jsonOutput.textContent = JSON.stringify(parsedData, null, 2);
        document.getElementById('outputSection').style.display = 'block';
    } else {
        jsonOutput.textContent = 'Нет сохраненных данных в JSON формате';
    }
}

// Функция очистки cookie
function clearCookies() {
    const cookies = document.cookie.split(';');

    for (let cookie of cookies) {
        const eqPos = cookie.indexOf('=');
        const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
        document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
    }

    // Очищаем localStorage (JSON данные)
    localStorage.removeItem('formDataJSON');

    // Очищаем отображение cookie
    document.getElementById('cookieDataOutput').innerHTML = '<div class="field-value">Cookie очищены</div>';
}
