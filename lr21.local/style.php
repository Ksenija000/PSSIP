
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Стилизованный текст</title>
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
   
        
        <div class="styled-text">
          <?php
$color = "#31b2e0"; 
$size = 28;      
$fio = "Савило Ксения Вячеславовна";

// текст с применением заданных переменных через HTML
echo "<font color='$color' size='$size'>$fio</font>";

?>
</div>
        
        
        
        <a href="index.html" class="link">На главную</a>

</body>
</html>