<?php
session_start();

// Проверяем, авторизован ли как администратор
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    // Если нет - перенаправляем на страницу входа
    header('Location: admin_login.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Админ-панель</title>
      <link href="https://fonts.googleapis.com/css2?family=Tinos:wght@400;700&family=Open+Sans:wght@700&display=swap"
        rel="stylesheet">
     <link rel="stylesheet" href="lr24/css/style.css">
</head>
<body>
   <div class="black-strip top-strip"></div>

      <div class="content-wrapper2">

    <h1>Административная панель</h1>
    

        <a href="index.html" class="home-button bt_adm_panel">На сайт</a> <hr>
        <a href="logout.php" class="home-button  bt_adm_panel" >Выйти</a>
  
    
    
    <h2>Добро пожаловать, администратор!</h2>
    
    <h3 class="fun">Доступные функции:</h3>
    <ul class="fun">
        <li>В стадии разработки</li>
    </ul>

 </div>
    <div class="black-strip bottom-strip"></div>   
</body>
</html>