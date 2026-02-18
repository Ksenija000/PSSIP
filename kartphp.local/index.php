<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета</title>
</head>
<body>
    <form action="handler.php" method="post">
        <p>Ваше имя * <br><input type="text" name="name" required></p>
        
        <p>Ваш пол * <br>
            <input type="radio" name="gender" value="М" checked required> М <br>
            <input type="radio" name="gender" value="Ж"> Ж
        </p>
        
        <p>Ваша девичья фамилия <br><input type="text" name="maiden_name"></p>
        
        <p>
            <input type="checkbox" name="served" value="Да"> Служил в арми
           
        </p>
        
        <p>Ваше воинское звание <br>
            <select name="rank">
                <option value="Рядовой" selected>Рядовой</option>
                <option value="Ефрейтор">Ефрейтор</option>
                <option value="Младший сержант">Младший сержант</option>
                <option value="Сержант">Сержант</option>
            </select>
        </p>
        
        <p><input type="submit" value="Submit"></p>
    </form>
</body>
</html>