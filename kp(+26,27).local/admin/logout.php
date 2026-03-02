<?php
// ============================================
// admin/logout.php - ВЫХОД ИЗ АДМИНКИ
// ============================================
session_start();

// Очищаем все данные сессии
$_SESSION = array();

// Уничтожаем сессию
session_destroy();

header('Location: /admin/login.php');
exit;