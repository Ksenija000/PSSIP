// 1. Метод RegExp.test()
document.getElementById('test-result').innerHTML =
    `Проверка email "user@gmail.com": ${/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test("user@gmail.com")}`;

// 2. Метод RegExp.exec()
//g: Флаг глобального поиска, который заставляет выражение искать все совпадения в строке, а не только первое.
const datePattern = /(\d{1,2})\/(\d{1,2})\/(\d{4})/g;
const dateString = "Сегодня 15/03/2023, завтра 16/03/2023";
let match;
let execResult = "";
while ((match = datePattern.exec(dateString)) !== null) {
    execResult += `Найдена дата: ${match[0]}, день: ${match[1]}, месяц: ${match[2]}, год: ${match[3]} <br>`;
}
document.getElementById('exec-result').innerHTML = execResult;

// 3. Метод String.split() с регулярным выражением
const text = "один, два; три: четыре";
const separators = /[,;:\s]+/;
const fruits = text.split(separators);
document.getElementById('split-result').innerHTML =
    `Исходная строка: "${text}"<br>Результат split: [${fruits.join(', ')}]`;

// 4. Метод String.match() с различными флагами
//i (без учёта регистра)
const text1 = "В лесу родилась ёлочка, в лесу она росла";
const match1 = text1.match(/в лесу/i);
const match2 = text1.match(/в лесу/ig);
const multiLineText = "Строка 1\nСтрока 2\nСтрока 3";
const match3 = multiLineText.match(/^Строка/gm);

document.getElementById('match-result').innerHTML =
    `Без флага g: ${JSON.stringify(match1)}<br>` +
    `С флагом g: ${JSON.stringify(match2)}<br>` +
    `С флагами g и m: ${JSON.stringify(match3)}`;

// 5. Метод String.search()
const text2 = "JavaScript - язык программирования";
const position = text2.search(/язык/i);
document.getElementById('search-result').innerHTML =
    `Позиция слова "язык": ${position}`;

// 6. Метод String.replace() с регулярным выражением
const text3 = "Цена: $100, $200, $300";
const result1 = text3.replace(/\$/g, "€");

const text5 = "5 яблок, 3 апельсина, 10 бананов";
const result3 = text5.replace(/\d+/g, (match) => parseInt(match) * 2);

document.getElementById('replace-result').innerHTML =
    `Замена валюты: "${result1}"<br>` +
    `Удвоение количества: "${result3}"`;

// 7. Различные типы регулярных выражений
// Регулярные выражения
const phonePattern = /^\+?(\d{1,3})?[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}$/;
const passwordPattern = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
const htmlPattern = /<([a-z][a-z0-9]*)[^>]*?(\/)?>/gi;
const urlPattern = /https?:\/\/([^\/]+)/gi;

// Примеры
const phoneExamples = ["+7 (123) 456-7890", "123-456-7890", "1234567890", "invalid phone"];
const passwordExamples = ["Password123", "weak", "12345678", "Valid1Password"];
const htmlExamples = ["<div></div>", "<a href='#'>Link</a>", "<invalidTag>Content</invalidTag>"];
const urlExamples = ["https://www.example.com", "http://example.org/path", "invalid_url"];

// Проверка и вывод результатов
let results = "";

// Проверка номеров телефонов
results += "Проверка телефона:<br>";
phoneExamples.forEach(phone => {
    results += `${phone}: ${phonePattern.test(phone)}<br>`;
});

// Проверка паролей
results += "<br>Проверка пароля:<br>";
passwordExamples.forEach(password => {
    results += `${password}: ${passwordPattern.test(password)}<br>`;
});

// Проверка HTML тегов
results += "<br>Проверка HTML тегов:<br>";
htmlExamples.forEach(html => {
    results += `${html}: ${htmlPattern.test(html)}<br>`;
});

// Извлечение доменов из URL
results += "<br>Проверка URL:<br>";
urlExamples.forEach(url => {
    const match = url.match(urlPattern);
    results += `${url}: ${match ? match[0] : 'Не найден'}<br>`;
});

// Вывод результатов на страницу
document.getElementById('patterns-result').innerHTML = results;