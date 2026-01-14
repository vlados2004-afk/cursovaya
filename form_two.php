<?php
// Подключение к базе данных
$host = 'localhost'; // или ваш хост
$db   = 'form'; // замените на название вашей базы данных
$user = 'root'; // замените на ваше имя пользователя
$pass = ''; // замените на ваш пароль

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Получение данных
$height = isset($_POST['height']) ? trim($_POST['height']) : '';
$weight = isset($_POST['weight']) ? trim($_POST['weight']) : '';
$age = isset($_POST['age']) ? trim($_POST['age']) : '';
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

// Проверка обязательных полей
if ($height === '' || $weight === '' || $age === '' || $gender === '') {
    die("Пожалуйста, заполните все обязательные поля.");
}

// Вставка данных
$stmt = $conn->prepare("INSERT INTO form_two (height, weight, age, gender, phone) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ddiss", $height, $weight, $age, $gender, $phone);

if ($stmt->execute()) {
    echo "Данные успешно отправлены!";
} else {
    echo "Ошибка: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>