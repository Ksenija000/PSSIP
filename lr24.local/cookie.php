<?php
//установка куки
// Куки устанавливаются ДО любого вывода в браузер
$name = "Tom";
$age = 36;
setcookie("name", $name, time() + 3600); 
setcookie("age", $age, time() + 3600);   // срок действия - 1 час (3600 секунд)
?>

<!DOCTYPE html>
<html>
<head>
    <title>Работа с cookie</title>
</head>
<body>
    <h1>Демонстрация работы с cookie</h1>
    
    <?php
    //  получение и отображение куки
    echo "<h2>Получение куки:</h2>";
    
    if (isset($_COOKIE["name"])) {
        echo "Name: " . htmlspecialchars($_COOKIE["name"]) . "<br>";
    } else {
        echo "cookie 'name' не установлена<br>";
    }
    
    if (isset($_COOKIE["age"])) {
        echo "Age: " . htmlspecialchars($_COOKIE["age"]) . "<br>";
    } else {
        echo "cookie 'age' не установлена<br>";
    }
    
    echo "<hr>";
    echo "<p>Примечание: cookie появятся после перезагрузки страницы (F5)</p>";
    ?>
    
    <br>
    <a href="javascript:location.reload()">Перезагрузить страницу</a><br>
    <a href="del_cookie.php">К удаление cookie</a>
</body>
</html>