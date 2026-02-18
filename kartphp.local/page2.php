<?php
session_start();

echo "<h2>Страница 2</h2>";


echo "Девичья фамилия: " . ($_SESSION['maiden_name'] ?? 'не указана') . "<br>";
echo "Воинское звание: " . ($_SESSION['rank'] ?? 'не указано') . "<br>";


echo "<br><strong>Имя сессии:</strong> " . session_name() . "<br>";
echo "<strong>Идентификатор сессии (PHPSESSID):</strong> " . session_id() . "<br>";
?>