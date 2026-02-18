<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $servername = "localhost";
    $username = "root";
    $password = "20Sukuna20";
    $dbname = "Turfirm";


    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Ошибка подключения: " . $conn->connect_error);
    }


    $fio = $conn->real_escape_string($_POST['fio']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $date = $conn->real_escape_string($_POST['date']);
    $route = $conn->real_escape_string($_POST['route']);
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];

    $sql = "INSERT INTO Клиенты (fio, phone, trip_date, route_name, number_of_vouchers, price)
            VALUES ('$fio', '$phone', '$date', '$route', $quantity, $price)";

    if ($conn->query($sql) === TRUE) {
        echo "<h3 style='color:green;'>Заказ успешно добавлен!</h3>";
        echo "<a href='add_order_form.html'>Добавить ещё</a> | ";
        echo "<a href='show_clients.php'>Посмотреть все заказы</a>";
    } else {
        echo "Ошибка: " . $conn->error;
    }

    $conn->close();
} else {
    echo "Доступ запрещён. Используйте форму.";
}
?>