<?php
echo "<h2>Задание 4: Работа с массивами</h2>";
echo "<p>Сформировать ассив из 7 целых элементов. Подсчитать произведение отрицательных элементов массива.
Заменить все отрицательные элементы массива числом 10. Вывести исходный и измененный массивы</p>";

$array = [29, -15, -75, -2, 18, -1, 74];

echo "<div class='result'>";
echo "<h3>Исходный массив:</h3>";
echo  implode(", ", $array);

// Подсчет произведения отрицательных элементов
$product = 1;
$x = false;
echo "<h3>Отрицательные элементы:</h3>";

foreach ($array as $value) {
    if ($value < 0) {
        echo "{$value} ";
        $product *= $value;
        $x = true;
    }
}

if ($x) {
    echo "<p>Произведение отрицательных элементов: {$product}</p>";
} else {
    echo "<p>Отрицательных элементов нет</p>";
}

$modified_array = $array;
foreach ($modified_array as &$value) {
    if ($value < 0) {
        $value = 10;
    }
}

echo "<h3>Измененный массив:</h3>";
echo  implode(", ", $modified_array) ;
echo "</div>";

?>