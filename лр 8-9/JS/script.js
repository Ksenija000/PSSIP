function show_prompt() {
    var name = prompt(" Пожалуйста, введите название файла ","file.txt");
    if (name != null && name !="")
    {
        document.write("Файл " + name + " не найден.");
    }
}
// Получаем все кнопки с классом "my-button"
const buttons = document.querySelectorAll('.btn_file');

// Добавляем обработчик событий для каждой кнопки
buttons.forEach(button => {
    button.addEventListener('click', show_prompt);
});


////////////////////////////////////////////////////////////////////

/* Вы получите различные приветствия в
зависимости от того, какой сейчас день
недели. Заметьте, что Воскресенье=0,
Понедельник=1, Вторник=2, и т.д. */
function skidca_f() {
var d = new Date();
var theDay = d.getDay();
switch (theDay) {
    case 5:
        alert("Cкидка 1%");
        break;
    case 6:
        alert("Cкидка 5%");
        break;
    case 0:
        alert("Cкидка 10%");
        break;
    default:
        document.write("Скидки нет!");
}
}

const element = document.getElementById('skidca');

// Добавляем обработчик события для нажатия
element.addEventListener('click', skidca_f);



///////////////////////////////////////////////////////////////////////////////////////////
function calculateSum() {
    const inputElement = document.querySelector('.info_schet_1');
    const n = parseInt(inputElement.value); // Считываем значение n

    let sum = 0;
    for (let i = 1; i <= n; i++) {
        sum += 685; 
    }

    return sum; // Возвращаем сумму
}

document.getElementById('summa').addEventListener('click', function () {
    const result = calculateSum(); // Получаем результат
    document.getElementById('result').innerText = "€" + result; // Выводим результат в div
});
////////////////////////////////////////////////////////////////////////

// Функция с циклом do..while
function displayMessage() {
    let message = "";
    let count = 0;

    do {
        if (count === 5) {
            break; // Прерываем цикл после 5 итераций
        }
        message += "Сообщение номер " + (count + 1) + "\n";
        count++;
    } while (true); // Бесконечный цикл, который прерывается внутри

    alert(message); // Выводим собранные сообщения
}

const sooo = document.getElementById('sooo');

// Добавляем обработчик события для нажатия
sooo.addEventListener('click', displayMessage);

////////////////////////////////////////////////////////////////////////////////
function konf_f() {
var i = -1;
while (i<10)
{
    i++;
    if (i == 3) {
        continue;
    }
    document.write("Раздел политики конфенденциальности номер "+i);
    document.write("</br>");
   
}
}

const konf = document.getElementById('konf');

// Добавляем обработчик события для нажатия
konf.addEventListener('click', konf_f);