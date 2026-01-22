<?php

$developer_name = "Савило Ксения Вячеславовна";
$group = "ПЗТ-40";

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Приветственная страница</title>
    <style>
       
        h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        .text {
            color:  #31b2e0;
            font-size: 3em;
            margin: 20px 0;
        }
        .info {
            padding: 20px;
            margin: 20px 0;
        }

        .info-item {
            margin: 10px 0;
            font-size: 18px;
            color:  #000000ff;
        }
      
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
    
        <div class="text">Привет всем!!!</div>
        
        <div class="info">
            <h2>Информация о разработчике:</h2>
            <div class="info-item"><strong>ФИО:</strong> <?php echo $developer_name; ?></div>
            <div class="info-item"><strong>Группа:</strong> <?php echo $group; ?></div>
        </div>
        
        
        <a href="index.html" class="link">На главную</a>
  
</body>
</html>