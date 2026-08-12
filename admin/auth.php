<?php
require_once __DIR__ . '/config.php';

if (!credentialsConfigured()) {
    header('Location: password.php?setup=1');
    exit;
}

$error = '';
$message = '';

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } elseif (attemptLogin($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в админку</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f4;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            width: 320px;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.08);
        }
        .auth-card h1 {
            margin-top: 0;
            font-size: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        label {
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
            color: #555;
        }
        input {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            font-size: 15px;
            border: none;
            border-radius: 6px;
            background: #1a73e8;
            color: #fff;
            cursor: pointer;
        }
        button:hover {
            background: #1559b0;
        }
        .error {
            color: #d93025;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
        }
        .notice {
            background: rgba(16,185,129,0.12);
            color: #166534;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="auth-card">
    <h1>Админ-панель Итроник</h1>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= htmlspecialchars($message, ENT_QUOTES | ENT_HTML5) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_HTML5) ?></div>
    <?php endif; ?>

    <form action="auth.php" method="post" autocomplete="off">
        <label for="username">Логин</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Пароль</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Войти</button>
    </form>

    <div style="text-align:center;margin-top:16px;">
        <a href="password.php" style="font-size:13px;color:#2563eb;">Забыли пароль?</a>
    </div>
</div>
</body>
</html>
