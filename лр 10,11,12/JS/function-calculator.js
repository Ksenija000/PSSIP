function calculateFunction123() {
    try {
        // Получаем значения из полей ввода
        const a = parseFloat(document.getElementById('a').value);
        const b = parseFloat(document.getElementById('b').value);
        const c = parseFloat(document.getElementById('c').value);
        const x = parseFloat(document.getElementById('x').value);

        // Проверка на корректность ввода
        if (isNaN(a) || isNaN(c) || isNaN(x)) {
            throw new Error("Все поля должны содержать числовые значения");
        }

        // Вычисляем значение функции
        const result = calculateMathFunction(a, b, c, x);

        // Выводим результат
        document.getElementById('functionValue').textContent =
            `Значение функции при a = ${a}, b = ${b}, c = ${c}, x = ${x}: ${result}`;
    } catch (error) {
        // Обработка ошибок
        alert(`Ошибка: ${error.message}`);
    }
}

function calculateMathFunction(a, b, c, x) {
    // Проверка исключительных ситуаций
    if (a < 0 && c === 0) {
        throw new Error("При a<0 и c=0 знаменатель становится нулевым (деление на ноль)");
    }

    // Вычисление по заданным условиям
    if (a < 0 && c !== 0) {
        // Формула: a*x² + b*x + c
        return a * Math.pow(x, 2) + b * x + c;
    } else if (a > 0 && c === 0) {
        // Формула: -a / (x - c)
        // Проверка деления на ноль
        if (x - c === 0) {
            throw new Error("Деление на ноль в формуле -a/(x-c) при x=c");
        }
        return -a / (x - c);
    } else {
        // Формула: a*(x + c)
        return a * (x + c);
    }
}