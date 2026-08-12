<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$mode = credentialsConfigured() ? 'reset' : 'setup';
$error = '';
$username = ADMIN_USERNAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? 'admin'));
    $secret = mb_strtolower(trim((string)($_POST['secret_phrase'] ?? '')));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($secret === '') {
        $error = 'Введите секретную фразу.';
    } elseif ($secret !== mb_strtolower(SECRET_PHRASE)) {
        $error = 'Неверная секретная фраза.';
    } elseif ($newPassword === '' || $confirmPassword === '') {
        $error = 'Введите новый пароль и подтверждение.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Пароли не совпадают.';
    } elseif (mb_strlen($newPassword) < 8) {
        $error = 'Пароль должен содержать минимум 8 символов.';
    } else {
        try {
            if ($username === '') {
                $username = 'admin';
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            saveCredentials($username, $hash);
            adminLogout();

            $msg = $mode === 'setup'
                ? 'Пароль успешно создан. Войдите с новыми данными.'
                : 'Пароль обновлён. Войдите с новыми данными.';

            header('Location: auth.php?msg=' . urlencode($msg));
            exit;
        } catch (Throwable $e) {
            $error = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $mode === 'setup' ? 'Создание пароля' : 'Сброс пароля' ?></title>
    <style>
        body {
            font-family: sans-serif;
            background: #f5f6fa;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            width: 400px;
            background: #fff;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(15,23,42,0.12);
        }
        h1 {
            margin: 0 0 20px;
            font-size: 22px;
            text-align: center;
        }
        label {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
            color: #475569;
        }
        input {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #d0d7e2;
            margin-bottom: 16px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            font-size: 15px;
            border-radius: 8px;
            border: none;
            background: #1a73e8;
            color: #fff;
            cursor: pointer;
        }
        button:hover {
            background: #1559b0;
        }
        .hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: -10px;
            margin-bottom: 18px;
        }
        .error {
            background: rgba(217,48,37,0.12);
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="card">
    <h1><?= $mode === 'setup' ? 'Создание пароля' : 'Восстановление пароля' ?></h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_HTML5) ?></div>
    <?php endif; ?>

    <form action="password.php" method="post">
        <label for="username">Логин</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>">

        <label for="secret_phrase">Секретная фраза</label>
        <input type="text" id="secret_phrase" name="secret_phrase" required>
        <div class="hint">Секретная фраза от Димы</div>

        <label for="new_password">Новый пароль</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">

        <label for="confirm_password">Подтвердите пароль</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

        <button type="submit"><?= $mode === 'setup' ? 'Создать пароль' : 'Сбросить пароль' ?></button>
    </form>

    <div style="text-align:center;margin-top:14px;">
        <a href="auth.php" style="font-size:13px;color:#2563eb;">← Вернуться к входу</a>
    </div>
</div>
</body>
</html>
