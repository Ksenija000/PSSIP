<!DOCTYPE html>
<html>
<head>
    <title>Все задания PHP</title>
    <style>
        .task { 
            border: 1px solid #ccccccff; 
            padding: 15px; 
            margin: 10px 0; }
        .result { 
            background: #68858cff; 
            padding: 10px; 
            margin: 10px 0; }
    </style>
</head>


<?php
echo "<div class='task'>";
include 'date.php';      // Задание 2
echo "</div>";

echo "<div class='task'>";
include 'for.php';      // Задание 3
echo "</div>";

echo "<div class='task'>";
include 'array.php';     // Задание 4
echo "</div>";

echo "<div class='task'>";
include 'string.php';    // Задание 5
echo "</div>";

echo "<div class='task'>";
include 'function.php';  // Задание 6
echo "</div>";

echo "</body></html>";
?>