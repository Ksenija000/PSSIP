<?php

// Создание константы
define("NUM_E", 2.71828);

// Присвоение значения константы переменной
$num_e1 = NUM_E;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Работа с константами в PHP</title>
    <style>
        .type-box {
            padding: 5px 15px;
            background-color: #e3f2fd;
        }
      
        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #0083daff;
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
        <h1>Работа с константами в PHP</h1>

        <ol>
            <li>
            <div >
                Число e равно <?php echo NUM_E; ?>
            </div>
        </li>
        <li>
            <p>Значение константы присвоено переменной</p>
            <p>Тип переменной $num_e1: <span class="type-box"><?php echo gettype($num_e1); ?></span></p>
        </li>
         <li>
            <p> Преобразование типов переменной $num_e1</p>
            <table>
                <tr>
                    <th>Тип</th>
                    <th>Результат</th>
                    <th>Тип после преобразования</th>
                </tr>
                <?php
                // Преобразование в строковый тип
                $num_e1 = (string)$num_e1;
                ?>
                <tr>
                    <td>Строковый</td>
                    <td><?php echo $num_e1; ?></td>
                    <td><span class="type-box"><?php echo gettype($num_e1); ?></span></td>
                </tr>
                
                <?php
                // Преобразование в целый тип
                $num_e1 = (int)$num_e1;
                ?>
                <tr>
                    <td>Целый</td>
                    <td><?php echo $num_e1; ?></td>
                    <td><span class="type-box"><?php echo gettype($num_e1); ?></span></td>
                </tr>
                
                <?php
                // Преобразование в булевский тип
                $num_e1 = (bool)$num_e1;
                ?>
                <tr>
                    <td>Булевский</td>
                    <td><?php echo $num_e1 ? 'true' : 'false'; ?></td>
                    <td><span class="type-box"><?php echo gettype($num_e1); ?></span></td>
                </tr>
            </table>
             </li>
        </ol>
        
        <a href="index.html" class="link">На главную</a>
</body>
</html>