<?php
$servername = "localhost";
$username = "root";
$password = "20Sukuna20";
$dbname = "Turfirm";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

$sql = "SELECT * FROM Клиенты";
$result = $conn->query($sql);

echo "<h2>Все заказы</h2>";

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>
            <tr>
                <th>ID</th>
                <th>ФИО клиента</th>
                <th>Телефон</th>
                <th>Дата поездки</th>
                <th>Маршрут</th>
                <th>Кол-во</th>
                <th>Цена</th>
            </tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["id"] . "</td>
                <td>" . $row["fio"] . "</td>
                <td>" . $row["phone"] . "</td>
                <td>" . $row["trip_date"] . "</td>
                <td>" . $row["route_name"] . "</td>
                <td>" . $row["number_of_vouchers"] . "</td>
                <td>" . $row["price"] . "</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "Заказов нет.";
}

echo "<br><a href='index.php'>Добавить новый заказ</a>";

$conn->close();
?>