<?php
session_start();
echo $_SESSION['username'] . ' , ты пришел на другую страницу этого сайта!';
echo("<br>");
?>
<a href="page3.php">На следующую страницу</a>