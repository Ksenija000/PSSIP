<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Информация о PHP</title>
    <style>


       .link {
            display: inline-block;
        
            padding: 10px;
            background-color: #2196F3;
            color:  #fbfbfbff;
            text-decoration: none;
            border-radius: 5px;
        }
        .link:hover {
            background-color: #31b2e0;
            
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Информация о настройках php:</h1>
    </div>
    
    <?php
    // Вывод полной информации о PHP
    phpinfo();
    ?>
    
    <br>
    <a href="index.html" class="link">На главную</a>
</body>
</html>