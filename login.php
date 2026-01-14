<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = isset($_POST['login']) ? $_POST['login'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Основные логины
    $correct_login = 'admin';
    $correct_password = '12345';

    // Тренер
    $trainer_login = 'trener';
    $trainer_password = '54321';

    if ($login === $correct_login && $password === $correct_password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = 'admin';
        header('Location: admin.php');
        exit;
    } elseif ($login === $trainer_login && $password === $trainer_password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = 'trainer';
        header('Location: trener.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль!';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Вход в систему</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 300px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #3498db;
            border: none;
            border-radius: 5px;
            color: #fff;
            font-size: 1em;
            cursor: pointer;
        }
        button:hover {
            background-color: #2980b9;
        }
        .error {
            color: red;
            margin-bottom: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="login-container">
    <h2>Вход в систему</h2>
    <?php if (isset($error)) { echo "<div class='error'>$error</div>"; } ?>
    <form method="post" action="">
        <input type="text" name="login" placeholder="Логин" required />
        <input type="password" name="password" placeholder="Пароль" required />
        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>