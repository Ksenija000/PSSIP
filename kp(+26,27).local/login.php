<?php
// ============================================
// login.php - ПРОСТОЙ ВХОД (без БД)
// ============================================
session_start();

// Просто создаём сессию с тестовым пользователем
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Тестовый пользователь';
$_SESSION['user_email'] = 'test@example.com';
$_SESSION['user_role'] = 'buyer';
$_SESSION['logged_in'] = true;

// Перенаправляем на страницу избранного
header('Location: wishlist.php');
exit;
?>