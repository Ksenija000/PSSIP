<?php
session_start();

// Полностью уничтожаем сессию
session_destroy();

header('Location: index.html');
exit();
?>