<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавление заказа</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        input, button { padding: 8px; margin: 5px 0; width: 300px; }
    </style>
</head>
<body>
    <h2>Добавить новый заказ в БД</h2>
    <form action="add_order.php" method="post">
        <label>ФИО клиента *</label><br>
        <input type="text" name="fio" required><br>

        <label>Телефон клиента *</label><br>
        <input type="text" name="phone" required><br>

        <label>Дата поездки *</label><br>
        <input type="date" name="date" required><br>

        <label>Название маршрута *</label><br>
        <input type="text" name="route" required><br>

        <label>Количество путевок *</label><br>
        <input type="number" name="quantity" min="1" required><br>

        <label>Цена путевки *</label><br>
        <input type="number" step="0.01" name="price" required><br><br>

        <button type="submit">Добавить заказ</button>
    </form>

    <br>
    <a href="show_clients.php">Посмотреть все заказы</a>
</body>
</html>