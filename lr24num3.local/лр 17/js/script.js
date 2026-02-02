// События мыши
const bt = document.getElementById('bt');
bt.addEventListener('mouseover', (event) => {
    alert('Ура!');
});
/////////////////////////////////////////////////
// События клавиатуры
const keyboardInput = document.getElementById('keyboardInput');
keyboardInput.addEventListener('keydown', (event) => {
    alert(`Нажата клавиша: ${ event.key }`);
});
/////////////////////////////////////////////////
//Drag&DROP события
const draggable = document.getElementById('draggable');
const dropzone = document.getElementById('dropzone');

draggable.addEventListener('dragstart', (event) => {
    event.dataTransfer.setData('text/plain', 'Это draggable элемент');
});

dropzone.addEventListener('dragover', (event) => {
    event.preventDefault(); // Позволяет сбросить элемент
});

dropzone.addEventListener('drop', (event) => {
    event.preventDefault();
    dropzone.innerHTML = 'Элемент сброшен!';
});

//////////////////////////////////////////////////////
//События указателя

const pointerEventsDiv = document.getElementById('pointerEvents');
pointerEventsDiv.addEventListener('pointerdown', () => {
    pointerEventsDiv.style.backgroundColor = 'lightgreen';
    pointerEventsDiv.innerHTML = 'Указатель нажат!';
});

pointerEventsDiv.addEventListener('pointerup', () => {
    pointerEventsDiv.style.backgroundColor = '';
    pointerEventsDiv.innerHTML = 'Указатель событий здесь';
});

//////////////////////////////////////////////////////
//События полосы прокрутки
const scrollableDiv = document.querySelector('div[id="pll"]');
scrollableDiv.addEventListener('scroll', () => {
    alert('Прокручено!');
});
//////////////////////////////////////////
// События сенсорных экранов
const touchEventsDiv = document.getElementById('touchEvents');
touchEventsDiv.addEventListener('touchstart', () => {
    touchEventsDiv.style.backgroundColor = 'lightcoral';
    touchEventsDiv.innerHTML = 'Коснулись!';
});

touchEventsDiv.addEventListener('touchend', () => {
    touchEventsDiv.style.backgroundColor = '';
    touchEventsDiv.innerHTML = 'Коснитесь здесь (на сенсорном экране)';
});
//////////////////////////////////////////
// События, связанные с таймером
const timerOutput = document.getElementById('timerOutput');
let timer;

document.getElementById('startTimer').addEventListener('click', () => {
    let count = 0;
    timerOutput.innerHTML = 'Таймер запущен';

    timer = setInterval(() => {
        count++;
        timerOutput.innerHTML = `Прошло секунд: ${ count }`;
    }, 1000);

    // Остановить таймер через 10 секунд
    setTimeout(() => {
        clearInterval(timer);
        timerOutput.innerHTML += ' (Таймер остановлен)';
    }, 10000);
});