<?php
echo "<h2>Задание 2:  Работа с датой, временем и календарём </h2>";
echo "<p>Дана дата. Вывести, сколько дней до нее осталось (или сколько дней прошло).</p>";

// Заданная дата
$given_date = "2025-12-31";

// Текущая дата
$current_date = date("Y-m-d");

$date1 = new DateTime($current_date);
$date2 = new DateTime($given_date);

//разница
$interval = $date1->diff($date2);
$days_difference = $interval->days;

if ($date1 > $date2) {
    $result = "С даты <strong>{$given_date}</strong> прошло: <strong>{$days_difference}</strong> дней";
} else {
    $result = "До даты <strong>{$given_date}</strong> осталось: <strong>{$days_difference}</strong> дней";
}

echo "<div class='result'>";
echo "<p>Текущая дата: " . date("Y-m-d") . "</p>";
echo "<p>Заданная дата: {$given_date}</p>";
echo "<p>{$result}</p>";
echo "</div>";
?>