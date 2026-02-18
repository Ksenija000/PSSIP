<?php
session_start(); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = htmlspecialchars($_POST['name'] ?? '');
    $gender = htmlspecialchars($_POST['gender'] ?? '');
    $maiden_name = htmlspecialchars($_POST['maiden_name'] ?? '');
    $served = isset($_POST['served']) ? htmlspecialchars($_POST['served']) : 'Нет';
    $rank = htmlspecialchars($_POST['rank'] ?? '');


    $_SESSION['maiden_name'] = $maiden_name;
    $_SESSION['rank'] = $rank;

  
    $a = $rank;          // воинское звание
    $b = $name;          // имя

    $file = fopen("fio.txt", "w") or die("Не удалось открыть файл");
    fwrite($file, "Воинское звание: $a\n");
    fwrite($file, "Имя: $b\n");
    fclose($file);


    echo "<h2>Введенные данные:</h2>";
    echo "Имя: $b <br>";
    echo "Пол: $gender <br>";
    echo "Девичья фамилия: $maiden_name <br>";
    echo "Служил в армии: $served <br>";
    echo "Воинское звание: $a <br>";

    echo "<p>Данные записаны в файл <strong>fio.txt</strong></p>";
    echo "<br><a href='page2.php'>Перейти на page2.php (задание с сессией)</a>";
} else {
    echo "Доступ запрещен";
}
?>