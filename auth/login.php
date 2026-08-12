<?php
require_once __DIR__ . '/config.php';

// Если уже авторизован — редирект в ЛК
if (isUserLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Введите email и пароль';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = 'Пользователь с таким email не найден';
        } elseif (empty($user['password_hash'])) {
            $error = 'Этот email зарегистрирован через гостевой доступ. Восстановите пароль.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Неверный пароль';
        } else {
            // Успешный вход
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            
            // Обновляем last_seen
            $db->exec("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = {$user['id']}");
            
            // Редирект в ЛК или визард
            if ($user['wizard_completed']) {
                header('Location: /dashboard.php');
            } else {
                header('Location: /wizard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Итроник</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #0a0033;
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 40px 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo .ii {
            font-family: 'Pacifico', cursive;
            font-size: 36px;
            color: white;
            letter-spacing: 2px;
        }
        .logo .tronik {
            font-family: 'Montserrat', sans-serif;
            font-style: italic;
            font-weight: 500;
            font-size: 28px;
            color: white;
        }
        h1 {
            font-size: 22px;
            text-align: center;
            margin-bottom: 10px;
            color: #21d4fd;
        }
        .subtitle {
            text-align: center;
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 30px;
        }
        .field {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            outline: none;
        }
        input:focus {
            border-color: rgba(33,212,253,0.5);
        }
        input::placeholder {
            color: rgba(255,255,255,0.35);
        }
        .error {
            background: rgba(244,67,54,0.15);
            border: 1px solid rgba(244,67,54,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #f44336;
            margin-bottom: 20px;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #21d4fd, #842ff3);
            border: none;
            border-radius: 30px;
            color: white;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        button:active {
            transform: translateY(0);
        }
        .links {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .links a {
            color: #21d4fd;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            margin-bottom: 20px;
        }
        .remember input {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="logo">
            <span class="ii">ii</span><span class="tronik">троник</span>
        </div>
        
        <h1>Вход в аккаунт</h1>
        <p class="subtitle">Войдите, чтобы продолжить работу с вашим итроником</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post" autocomplete="off">
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="example@mail.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="field">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" placeholder="Введите пароль" required>
            </div>
            
            <div class="remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember" style="margin: 0; cursor: pointer;">Запомнить меня</label>
            </div>
            
            <button type="submit">🚀 Войти</button>
        </form>
        
        <div class="links">
            Нет аккаунта? <a href="/auth/register.php">Зарегистрироваться</a><br>
            <a href="/auth/reset.php">Забыли пароль?</a>
        </div>
    </div>
</body>
</html>
