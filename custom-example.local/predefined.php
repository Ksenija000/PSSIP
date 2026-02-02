<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Константы и Переменные</title>
    <style>
        strong { color: #2c3e50; }
        
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

    <h1>Пример использования предопределенных констант и переменных в PHP</h1>


        <h2>Магические константы</h2>
        <p>Эти константы меняются в зависимости от контекста кода:</p>
        <ul>
            <li><strong>Путь к файлу (__FILE__):</strong> <?php echo __FILE__; ?></li>
            <li><strong>Директория (__DIR__):</strong> <?php echo __DIR__; ?></li>
            <li><strong>Текущая строка (__LINE__):</strong> <?php echo __LINE__; ?></li>
        </ul>


        <h2>Ядро PHP (Предопределенные константы)</h2>
        <p>Информация о самой системе PHP:</p>
        <ul>
            <li><strong>Версия PHP (PHP_VERSION):</strong> <?php echo PHP_VERSION; ?></li>
            <li><strong>Операционная система (PHP_OS):</strong> <?php echo PHP_OS; ?></li>
            <li><strong>Версия расширения Zend (zend_version()):</strong> <?php echo zend_version(); ?></li>
        </ul>



        <h2>Суперглобальные переменные ($_SERVER)</h2>
        <p>Данные о сервере и текущем запросе:</p>
        <ul>
            <li><strong>IP-адрес сервера:</strong> <?php echo $_SERVER['SERVER_ADDR'] ?? 'не определен (Localhost)'; ?></li>
            <li><strong>Ваш IP-адрес:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></li>
            <li><strong>Ваш Браузер:</strong> <?php echo $_SERVER['HTTP_USER_AGENT']; ?></li>
            <li><strong>Метод запроса:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></li>
            <li><strong>Текущий скрипт:</strong> <?php echo $_SERVER['PHP_SELF']; ?></li>
        </ul>
  


       <a href="index.html" class="link">На главную</a>
</body>
</html>