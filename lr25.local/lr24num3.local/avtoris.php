<?php
session_start();

// Если уже авторизован как админ - перенаправляем в админку
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: admin_panel.php');
    exit();
}

$error = '';
$admin_password = 'admin123'; // Пароль администратора

// Проверяем отправку формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if ($password === $admin_password) {
        // Правильный пароль - создаем сессию администратора
        $_SESSION['admin'] = true;
        
        header('Location: admin_panel.php');
        exit();
    } else {
        $error = 'Неверный пароль!';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Вход в админку</title>
     <link href="https://fonts.googleapis.com/css2?family=Tinos:wght@400;700&family=Open+Sans:wght@700&display=swap"
        rel="stylesheet">
     <link rel="stylesheet" href="lr24/css/style.css">
</head>
<body>
     <div class="black-strip top-strip"></div>

      <div class="content-wrapper">
    <h1 class="text_kab">Вход в кабинет администратора</h1>
    
    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <form method="POST" class="form_adm">
        
            <label class="label_adm">Введите пароль администратора:</label><br> 
            <!-- admin123 -->
            <input type="password" name="password" required class="input_adm">
       
        <button type="submit" class=" home-button button_adm">Войти</button>
    </form>
    
      <a href="index.html" class="home-button">Вернуться на главную</a>
    
</div>
    <div class="black-strip bottom-strip"></div>
</body>
</html>