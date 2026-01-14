<?php
// Подключение к базе данных
$dbc = mysqli_connect('localhost', 'root', '', 'form');

// Проверка соединения
if (!$dbc) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Ошибка соединения с базой данных']);
    exit;
}

// Получение и декодирование JSON-данных
$data = json_decode(file_get_contents('php://input'), true);

// Проверка наличия необходимых данных
if (!isset($data['first_name']) || !isset($data['last_name']) || !isset($data['email'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Недостающие данные']);
    mysqli_close($dbc);
    exit;
}

$first_name = trim($data['first_name']);
$last_name = trim($data['last_name']);
$email = trim($data['email']);

// Проверка, что поля не пустые
if (empty($first_name) || empty($last_name) || empty($email)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Пожалуйста, заполните все поля']);
    mysqli_close($dbc);
    exit;
}

// Экранирование для защиты от SQL-инъекций
$first_name = mysqli_real_escape_string($dbc, $first_name);
$last_name = mysqli_real_escape_string($dbc, $last_name);
$email = mysqli_real_escape_string($dbc, $email);

// SQL-запрос с использованием CURRENT_TIMESTAMP по умолчанию
$query = "INSERT INTO form (first_name, last_name, email) VALUES ('$first_name', '$last_name', '$email')";

// Выполнение запроса
$result = mysqli_query($dbc, $query);

if ($result) {
    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Данные успешно сохранены']);
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Ошибка при сохранении данных']);
}

// Закрытие соединения
mysqli_close($dbc);
?>
