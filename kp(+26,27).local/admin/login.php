<?php
// ============================================
// admin/login.php - ВХОД ДЛЯ АДМИНИСТРАТОРА ПО FIO
// ============================================
session_start();

// Если уже авторизован как админ - отправляем в админку
if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit;
}

$error = '';

// ПОДКЛЮЧЕНИЕ К БД
$host = '127.0.0.1';
$port = '3306';
$dbname = 'Art_objects_store2';
$user = 'root';
$pass = '20Sukuna20'; // пустой пароль для OpenServer

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fio = $_POST['fio'] ?? '';           // ЛОГИН = fio
    $password = $_POST['password'] ?? '';

    // Ищем пользователя по fio (логину)
    $stmt = $db->prepare("SELECT * FROM users WHERE fio = ?");
    $stmt->execute([$fio]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Проверяем пароль
        if (password_verify($password, $user['password_hash'])) {
            // Проверяем роль и активность
            if ($user['role'] === 'admin' && $user['is_active'] == 1) {
                // УСПЕШНЫЙ ВХОД
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['fio'];
                $_SESSION['admin_email'] = $user['email'];
                
                header('Location: /admin/');
                exit;
            } else {
                $error = 'У вас нет прав администратора или аккаунт заблокирован';
            }
        } else {
            $error = 'Неверный пароль';
        }
    } else {
        $error = 'Пользователь с таким логином (FIO) не найден';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTOBJECT | Вход для администратора</title>
    <style>
        /* Стили полностью сохраняем */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: #1a1a1a;
            border-radius: 30px;
            padding: 50px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 90, 48, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

        .logo-main {
            font-size: 2.5rem;
            font-weight: 900;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo-main::after {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #FF5A30;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        .logo-sub {
            font-size: 0.8rem;
            font-weight: 600;
            color: #FF5A30;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-top: 8px;
        }

        h1 {
            color: #fff;
            font-size: 1.8rem;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(255, 90, 48, 0.1);
            padding: 12px;
            border-radius: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 90, 48, 0.3);
            color: #FF5A30;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }

        .admin-badge i {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #a0a0a0;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1rem;
        }

        input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            background: #2a2a2a;
            border: 1px solid #333;
            border-radius: 30px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #FF5A30;
            box-shadow: 0 0 0 3px rgba(255, 90, 48, 0.2);
        }

        .error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            padding: 12px 20px;
            border-radius: 30px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            border: 1px solid rgba(244, 67, 54, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        .error i {
            font-size: 1.1rem;
        }

        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #FF5A30 0%, #FF8A00 100%);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 90, 48, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
            position: relative;
            z-index: 1;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: #FF5A30;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-main">ARTOBJECT</div>
            <div class="logo-sub">ADMIN PANEL</div>
        </div>

        <div class="admin-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Только для администраторов</span>
        </div>

        <h1>Вход в панель управления</h1>

        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Логин (FIO)</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="fio" required placeholder="admin">
                </div>
            </div>

            <div class="form-group">
                <label>Пароль</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
            </div>

            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Войти в админку
            </button>
        </form>

        <div class="back-link">
            <a href="/"><i class="fas fa-arrow-left"></i> Вернуться на сайт</a>
        </div>
    </div>
</body>
</html>