document.getElementById('survey-form').addEventListener('submit', function (event) {
    event.preventDefault(); // Отменяем стандартную отправку формы

        // Валидация Email через регулярное выражение
        const email = document.getElementById('email').value;
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
        if (!emailRegex.test(email)) {
            alert('Введите корректный email адрес, пример: name@gmail.com');
            return;
        }

        // Валидация возраста (не выбрано значение по умолчанию)
        const age = document.getElementById('age').value;
        if (!age) {
            alert('Пожалуйста, выберите возраст');
            return;
        }

        // Валидация любимого животного (минимум 2 символа)
        const animal = document.getElementById('aaaa').value;
        if (animal.length < 2) {
            alert('Название животного должно содержать минимум 2 символа');
            return;
        }

        // Валидация цвета (только буквы, минимум 2 символа)
        const color = document.getElementById('bbb').value;
        const colorRegex = /^[A-Za-zА-Яа-яЁё]+$/;
        if (!colorRegex.test(color) || color.length < 2) {
            alert('Цвет должен содержать только буквы (минимум 2 символа)');
            return;
        }

    // Собираем данные из формы
    const formData = new FormData(this);
    const data = {
        name: formData.get('name'),
        email: formData.get('email'),
        age: formData.get('age'),
        animal: formData.get('aaaa'),
        color: formData.get('bbb'),
        liked: formData.get('agreement'),
        notRobot: formData.get('acheck') ? 'Да' : 'Нет'
    };

    // Формируем сообщение для вывода
    const message = `
                Собранные данные: Имя: ${data.name}
            Email: ${data.email}
            Возраст: ${data.age}
                Любимое животное: ${data.animal}
                Любимый цвет: ${data.color}
                Понравилась страница: ${data.liked === 'yes' ? 'Да' : 'Нет'}
            Подтверждение, что не робот: ${data.notRobot}`
        ;

    // Выводим данные в диалоговое окно
    alert(message);
});