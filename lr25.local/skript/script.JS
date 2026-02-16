//кнпка заказа столика
// Получаем все кнопки с классом 'book-table'
const buttons = document.querySelectorAll('.book-table');

// Проходим по каждой кнопке и добавляем обработчик события
buttons.forEach(button => {
    button.addEventListener('click', function () {
        // Переходим на соответствующую страницу
        window.location.href = '404.html';
    });
});


document.getElementById('adminBtn').addEventListener('click', function () {
    console.log(document.getElementById('adminBtn'));
    window.location.href = 'avtoris.php';
});


//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Получаем все элементы с классом 'phoneNumber'
const phoneNumbers = document.querySelectorAll('.phoneNumber');

// Добавляем обработчик события для каждого элемента
phoneNumbers.forEach(function (phoneNumberElement) {
    phoneNumberElement.addEventListener('click', function (event) {
        event.preventDefault(); // Предотвращаем переход по ссылке
        const phoneNumber = this.textContent; // Получаем текст ссылки
        alert('Вы нажали на номер телефона: ' + phoneNumber); // Выводим номер в алерте

        // Открываем телефонный номер в приложении
        window.location.href = 'tel:' + phoneNumber;
    });
});

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////нажатие на кнопку заказа бюда
// Получаем все кнопки с классом 'book-table'
const buttons_zac = document.querySelectorAll('.cart_bt');

// Проходим по каждой кнопке и добавляем обработчик события
buttons_zac.forEach(button => {
    button.addEventListener('click', function () {
        // Получаем URL из атрибута data-url


        // Переходим на соответствующую страницу
        window.location.href = '404.html';
    });
});


//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////Бургер-меню//////////
const burger = document.getElementById('burger');
const overlay = document.getElementById('overlay');

burger.addEventListener('click', () => {
    overlay.classList.toggle('active');
    burger.classList.toggle('active');
});
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////
///////////Слайдер-карусель//////////
let currentIndex = 0; // Текущий индекс слайда
const slides = document.querySelectorAll('.carousel-item'); // Получаем все слайды
const totalSlides = slides.length; // Общее количество слайдов

// Определяем размер сдвига в зависимости от устройства
function getSlideWidth() {
    if (window.innerWidth >= 1152) { // ПК версия
        return 379;
    } else if (window.innerWidth >= 572) { // Планшетная версия
        return 189;
    } else { // Мобильная версия
        return 320;
    }
}

function getTotalSlides() {
    if (window.innerWidth >= 1152) { // ПК версия
        return slides.length - 2;
    } else if (window.innerWidth >= 572) { // Планшетная версия
        return slides.length - 2;
    } else { // Мобильная версия
        return slides.length;
    }
}

// Функция обновления карусели
function updateCarousel(slideWidth) {
    const newTransformValue = -currentIndex * slideWidth; // Вычисляем новое значение для трансформации
    document.querySelector('.menu_continer_content2').style.transform = `translateX(${newTransformValue}px)`;// Исправлено: добавлены обратные кавычки
}

// Обработчик события для кнопки "вперед"
document.querySelector('.bt_circle2').addEventListener('click', () => {
    const slideWidth = getSlideWidth();
    currentIndex = (currentIndex + 1) % getTotalSlides(); // Переход к следующему слайду
    updateCarousel(slideWidth);
});

// Обработчик события для кнопки "назад"
document.querySelector('.bt_circle1').addEventListener('click', () => {
    const slideWidth = getSlideWidth();
    currentIndex = (currentIndex - 1 + getTotalSlides()) % getTotalSlides(); // Переход к предыдущему слайду
    updateCarousel(slideWidth);
});

// Автоматическая прокрутка
let autoScrollInterval = setInterval(() => {
    const slideWidth = getSlideWidth();
    currentIndex = (currentIndex + 1) % getTotalSlides(); // Переход к следующему слайду
    updateCarousel(slideWidth);
}, 3000); // Интервал в 3000 мс (3 секунды)

// Остановка автоматической прокрутки при наведении или касании
const carouselContainer = document.querySelector('.menu_continer_content2_vidim');

carouselContainer.addEventListener('mouseenter', () => {
    clearInterval(autoScrollInterval); // Остановка автоматической прокрутки
});

carouselContainer.addEventListener('mouseleave', () => {
    autoScrollInterval = setInterval(() => {
        const slideWidth = getSlideWidth();
        currentIndex = (currentIndex + 1) % getTotalSlides(); // Переход к следующему слайду
        updateCarousel(slideWidth);
    }, 3000); // Возобновление автоматической прокрутки
});

// Управление с помощью жестов на мобильных устройствах
let startX = null;

carouselContainer.addEventListener('touchstart', (event) => {
    startX = event.touches[0].clientX; // Запоминаем начальную позицию касания
    clearInterval(autoScrollInterval); // Остановка автоматической прокрутки при касании
});

carouselContainer.addEventListener('touchmove', (event) => {
    if (!startX) return;

    const currentX = event.touches[0].clientX;
    const diffX = startX - currentX;

    const slideWidth = getSlideWidth(); // Получаем ширину слайда для мобильных устройств

    if (diffX > 50) { // Проведение влево
        currentIndex = (currentIndex + 1) % getTotalSlides(); // Переход к следующему слайду
        updateCarousel(slideWidth);
        startX = null; // Сбрасываем начальную позицию
    } else if (diffX < -50) { // Проведение вправо
        currentIndex = (currentIndex - 1 + getTotalSlides()) % getTotalSlides(); // Переход к предыдущему слайду
        updateCarousel(slideWidth); startX = null; // Сбрасываем начальную позицию
    }
});

// Восстановление автоматической прокрутки после завершения жеста
carouselContainer.addEventListener('touchend', () => {
    autoScrollInterval = setInterval(() => {
        const slideWidth = getSlideWidth();
        currentIndex = (currentIndex + 1) % getTotalSlides(); // Переход к следующему слайду
        updateCarousel(slideWidth);
    }, 3000); // Возобновление автоматической прокрутки
});
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////Слайдер с индикатором1///////////
const slides1 = document.querySelector('.posetitel_continer_conent_slider');
const slide = document.querySelectorAll('.posetitel_continer_conent');
const dots = document.querySelectorAll('.dot');

let currentIndex1 = 0;

// Определяем размер сдвига в зависимости от устройства
function getSlideWidth_dot() {
    if (window.innerWidth >= 1152) { // ПК версия
        return 850;
    } else if (window.innerWidth >= 572) { // Планшетная версия
        return 425;
    } else { // Мобильная версия
        return 290;
    }
}

function updateSlider(index) {
    slides1.style.transform = `translateX(-${index * getSlideWidth_dot()}px)`;

    slide.forEach((s, i) => {
        s.classList.remove('active');
        if (i === index) {
            s.classList.add('active');
        }
    });

    dots.forEach((dot, i) => {
        dot.classList.remove('active');
        if (i === index) {
            dot.classList.add('active');
        }
    });
}

function nextSlide() {
    currentIndex1 = (currentIndex1 + 1) % slide.length;
    updateSlider(currentIndex1);
}

function prevSlide() {
    currentIndex1 = (currentIndex1 - 1 + slide.length) % slide.length;
    updateSlider(currentIndex1);
}

// Mouse and touch events
let startX1;

slides1.addEventListener('mousedown', (e) => {
    startX1 = e.pageX;
    slides1.classList.add('no-select'); // Добавляем класс, чтобы отключить выделение
});

slides1.addEventListener('mouseup', (e) => {
    const endX = e.pageX;
    slides1.classList.remove('no-select'); // Удаляем класс после завершения перелистывания
    if (startX1 > endX + 50) {
        nextSlide();
    } else if (startX1 < endX - 50) {
        prevSlide();
    }
});

slides1.addEventListener('mouseleave', () => {
    slides1.classList.remove('no-select'); // Удаляем класс, если мышь покинула область слайдов
});

slides1.addEventListener('touchstart', (e) => {
    startX1 = e.touches[0].pageX;
    slides1.classList.add('no-select'); // Добавляем класс, чтобы отключить выделение
});

slides1.addEventListener('touchend', (e) => {
    const endX = e.changedTouches[0].pageX;
    slides1.classList.remove('no-select'); // Удаляем класс после завершения перелистывания
    if (startX1 > endX + 50) {
        nextSlide();
    } else if (startX1 < endX - 50) {
        prevSlide();
    }
});

// Инициализация первого слайда
updateSlider(currentIndex1);
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////Слайдер с индикатором2///////////
const slides2 = document.querySelector('.powara_slider');
const slide2 = document.querySelectorAll('.powara_image');
const dots2 = document.querySelectorAll('.dot_');

let currentIndex2 = 0;

// Определяем размер сдвига в зависимости от устройства
function getSlideWidth_dot2() {
    if (window.innerWidth >= 1152) { // ПК версия
        return 1142;
    } else if (window.innerWidth >= 572) { // Планшетная версия
        return 571;
    } else { // Мобильная версия
        return 300;
    }
}

function updateSlider2(index) {
    slides2.style.transform = `translateX(-${index * getSlideWidth_dot2()}px)`;

    slide2.forEach((s, i) => {
        s.classList.remove('active');
        if (i === index) {
            s.classList.add('active');
        }
    });

    dots2.forEach((dot, i) => {
        dot.classList.remove('active');
        if (i === index) {
            dot.classList.add('active');
        }
    });
}

function nextSlide2() {
    currentIndex2 = (currentIndex2 + 1) % slide2.length;
    updateSlider2(currentIndex2);
}

function prevSlide2() {
    currentIndex2 = (currentIndex2 - 1 + slide2.length) % slide2.length;
    updateSlider2(currentIndex2);
}

// Mouse and touch events
let startX2;

slides2.addEventListener('mousedown', (e) => {
    startX2 = e.pageX;
});

slides2.addEventListener('mouseup', (e) => {
    const endX = e.pageX;
    if (startX2 > endX + 50) {
        nextSlide2();
    } else if (startX2 < endX - 50) {
        prevSlide2();
    }
});

slides2.addEventListener('touchstart', (e) => {
    startX2 = e.touches[0].pageX;
});

slides2.addEventListener('touchend', (e) => {
    const endX = e.changedTouches[0].pageX;
    if (startX2 > endX + 50) {
        nextSlide2();
    } else if (startX2 < endX - 50) {
        prevSlide2();
    }
});

// Инициализация первого слайда
updateSlider2(currentIndex2);
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////Открытие изо в модальном окне///////////

// Получаем элементы модального окна
const modal = document.getElementById("modal");
const modalImg = document.getElementById("img01");
const captionText = document.getElementById("caption");

// Получаем все изображения с классом "more"
const images = document.querySelectorAll(".more");

// Добавляем обработчик события для каждого изображения
images.forEach((img) => {
    // Обработчик клика
    img.addEventListener("click", function () {
        modal.style.display = "block"; // Показываем модальное окно
        modalImg.src = this.src; // Устанавливаем источник изображения в модальном окне
        captionText.innerHTML = this.alt; // Устанавливаем текст подписи
    });

    // Обработчик касания для мобильных устройств
    img.addEventListener("touchstart", function () {
        modal.style.display = "block"; // Показываем модальное окно
        modalImg.src = this.src; // Устанавливаем источник изображения в модальном окне
        captionText.innerHTML = this.alt; // Устанавливаем текст подписи
    });
});

// Получаем элемент закрытия модального окна
const span = document.getElementsByClassName("close")[0];

// Добавляем обработчик события для закрытия модального окна
span.addEventListener("click", function () {
    modal.style.display = "none"; // Скрываем модальное окно
});

// Закрытие модального окна при клике вне изображения
modal.addEventListener("click", function (event) {
    if (event.target === modal) {
        modal.style.display = "none"; // Скрываем модальное окно
    }
});

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

///////////ПОДСКАЗКИ///////////
// Получаем элемент подсказки
const tooltip = document.getElementById("tooltip");

// Получаем все изображения с классом "inf"
const images2 = document.querySelectorAll(".inf");

// Добавляем обработчик события для каждого изображения
images2.forEach((img) => {
    img.addEventListener("mouseenter", function (event) {
        // Получаем текст подсказки из атрибута data-tooltip
        const tooltipText = this.getAttribute("data-tooltip");

        // Устанавливаем текст подсказки
        tooltip.innerHTML = tooltipText;

        // Устанавливаем позицию подсказки
        tooltip.style.left = event.pageX + 'px'; // Позиция по оси X
        tooltip.style.top = event.pageY + 'px'; // Позиция по оси Y

        // Показываем подсказку
        tooltip.style.display = "block";
    });

    img.addEventListener("mousemove", function (event) {
        // Обновляем позицию подсказки при движении мыши
        tooltip.style.left = event.pageX + 'px';
        tooltip.style.top = event.pageY + 'px';
    });

    img.addEventListener("mouseleave", function () {
        // Скрываем подсказку при уходе мыши
        tooltip.style.display = "none";
    });

    // Обработчик касания для мобильных устройств
    img.addEventListener("touchstart", function (event) {
        event.preventDefault(); // Предотвращаем выполнение других действий (например, прокрутки)

        // Получаем текст подсказки из атрибута data-tooltip
        const tooltipText = this.getAttribute("data-tooltip");

        // Устанавливаем текст подсказки
        tooltip.innerHTML = tooltipText;

        // Устанавливаем позицию подсказки на 10px выше точки касания
        const touch = event.touches[0]; // Получаем информацию о касании
        tooltip.style.left = touch.pageX + 'px'; // Позиция по оси X
        tooltip.style.top = (touch.pageY - 10) + 'px'; // Позиция по оси Y с учетом смещения

        // Показываем подсказку
        tooltip.style.display = "block";
    });

    img.addEventListener("touchend", function () {
        // Скрываем подсказку при отпускании пальца
        tooltip.style.display = "none";
    });
});
//////////////////////////////////////////////////////////////////////form//////////////////////////////////////////////////////////



