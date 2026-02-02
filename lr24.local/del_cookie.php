<?php
// Удаление куки
setcookie("name", "", time() - 3600);
setcookie("age", "", time() - 3600);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Удаление куки</title>
</head>
<body>
    <h1>Удаление куки</h1>
    
    <?php
    echo "<h2>Статус куки:</h2>";
    
    if (isset($_COOKIE["name"])) {
        echo "Name cookie still exists: " . htmlspecialchars($_COOKIE["name"]) . "<br>";
    } else {
        echo "Куки 'name' удалена<br>";
    }
    
    if (isset($_COOKIE["age"])) {
        echo "Age cookie still exists: " . htmlspecialchars($_COOKIE["age"]) . "<br>";
    } else {
        echo "Куки 'age' удалена<br>";
    }
    
    echo "<p>Примечание: Для полного удаления обновите страницу (F5)</p>";
    ?>
    
    <br>
    <a href="cookie.php">Назад к установке куки</a><br> 
    <a href="javascript:location.reload()">Обновить</a>
</body>
</html>