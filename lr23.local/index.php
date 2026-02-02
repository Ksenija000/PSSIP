<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lr23</title>
</head>
<body>
    <form action="" method="POST">
        <p>Введите текст:</p>
        <textarea name="textblock" rows="5" cols="40"></textarea>
        <br>
        <button type="submit">Записать в файл</button>
    </form>
    
    <?php
    // Проверяем, была ли отправлена форма
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST["textblock"])) {
        $text = $_POST["textblock"];
        
        // Открыть текстовый файл для записи
        $f = fopen("textfile.txt", "w");
        if ($f) {
            // Записать текст
            fwrite($f, $text);
            // Закрыть текстовый файл
            fclose($f);
            
            echo "<p>Текст успешно записан в файл.</p>";
            
            // Открыть файл для чтения и прочитать
            $f = fopen("textfile.txt", "r");
            if ($f) {
                echo "<p>Содержимое файла:</p>";
                  echo "<pre>" . htmlspecialchars(file_get_contents("textfile.txt")) . "</pre>";
                fclose($f);
            }
        } else {
            echo "<p>Ошибка при открытии файла для записи.</p>";
        }
    }
    ?>
</body>
</html>