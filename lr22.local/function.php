<?php
echo "<h2>Задание 6: Пользовательская функция</h2>";
echo "<p>Необходимо реализовать расчет по формуле: y = √(4 - x<sup>3</sup>)<br>
Разработать для этих целей пользовательскую функцию.<br>
Передачу результатов расчета оформить через использование оператора return.<br>
 Результаты расчетов отобразить в теле html-документа.<br>
Осуществить обработку исключительных ситуаций (ошибка деления на ноль, корень из отрицательного и т.д.) с выводом соответствующего сообщения.</p>";

function calculateY($x) {
    if (!is_numeric($x)) {
        throw new Exception("Ошибка: '{$x}' не является числом");
    }
    
    $inner = 4 - pow($x, 3);
    
    if ($inner < 0) {
        throw new Exception("Ошибка: нельзя извлечь корень из отрицательного числа ({$inner}) при x = {$x}");
    }
    
    $result = sqrt($inner);
    
    return $result;
}

echo "<div class='result'>";
echo "<h3>Результаты расчета:</h3>";

function printX($z) {
    $x=$z;


echo "<div>------------------------------------------------------------------------------------ <br>";
echo "x={$x}<br>";
    try {
       $y = calculateY($x);
       echo "y = √(4 - x<sup>3</sup>)=" . round($y, 4) . "<br>";
    } catch (Exception $e) {
        echo  $e->getMessage() ;
    }
echo "</div>";
}

   printX(1);
  printX(2);
   printX(10);
    printX(-5);
    printX(0);
echo "</div>";
?>