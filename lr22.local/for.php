<?php
echo "<h2>Задание 3: Операторы организации циклов</h2>";
echo "<p>Вывести Фамилию и Имя n+5 раз (n=12)</p>";

$last_name = "Саввило";
$first_name = "Ксения";
$n = 12; 
$count = $n + 5;

echo "<div class='result'>";
echo "<p>Фамилия: {$last_name}</p>";
echo "<p>Имя: {$first_name}</p>";
echo "<p>Количество повторений:{$n} + 5 = {$count}</p>";
echo "<p>Результат:</p>";

for ($i = 1; $i <= $count; $i++) {
    echo "{$i}. {$last_name} {$first_name}<br>";
}
echo "</div>";

?>