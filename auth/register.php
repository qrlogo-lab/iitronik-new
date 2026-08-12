<?php
require_once __DIR__ . '/config.php';

// Если уже авторизован — редирект в ЛК
if (isUserLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Валидация
    if (empty($name)) {
        $error = 'Введите ваше имя';
    } elseif (empty($email)) {
        $error = 'Введите email';
    } elseif (!isValidEmail($email)) {
        $error = 'Некорректный email';
    } elseif (empty($password)) {
        $error = 'Введите пароль';
    } elseif (!isValidPassword($password)) {
        $error = 'Пароль должен быть не менее 8 символов';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Пароли не совпадают';
    } else {
        // Проверка существования email
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Этот email уже зарегистрирован';
        } else {
            // Создание пользователя
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sessionId = bin2hex(random_bytes(16)); // Для совместимости с виджетом
            
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password_hash, session_id, wizard_completed) 
                VALUES (?, ?, ?, ?, 0)
            ");
            
            try {
                $stmt->execute([$name, $email, $passwordHash, $sessionId]);
                
                // Автоматический вход после регистрации
                $userId = $db->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                
                header('Location: /wizard.php'); // Сразу в визард
                exit;
                
            } catch (Exception $e) {
                $error = 'Ошибка регистрации. Попробуйте позже.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация — Итроник</title>
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
        .success {
            background: rgba(76,175,80,0.15);
            border: 1px solid rgba(76,175,80,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #4CAF50;
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
        .password-strength {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            margin-top: 4px;
        }
        .password-strength.weak { color: #f44336; }
        .password-strength.medium { color: #FF9800; }
        .password-strength.strong { color: #4CAF50; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="logo">
            <span class="ii">ii</span><span class="tronik">троник</span>
        </div>
        
        <h1>Создать аккаунт</h1>
        <p class="subtitle">Зарегистрируйтесь, чтобы создать своего цифрового двойника</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="post" autocomplete="off">
            <div class="field">
                <label for="name">Ваше имя</label>
                <input type="text" id="name" name="name" placeholder="Иван Иванов" required autofocus value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="example@mail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="field">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" placeholder="Минимум 8 символов" required>
                <div class="password-strength" id="strength"></div>
            </div>
            
            <div class="field">
                <label for="password_confirm">Повторите пароль</label>
                <input type="password" id="password_confirm" name="password_confirm" placeholder="Введите пароль ещё раз" required>
            </div>
            
            <button type="submit">🚀 Создать аккаунт</button>
        </form>
        
        <div class="links">
            Уже есть аккаунт? <a href="/auth/login.php">Войти</a>
        </div>
    </div>

    <script>
        // Индикатор силы пароля
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.getElementById('strength');
        
        passwordInput.addEventListener('input', () => {
            const pwd = passwordInput.value;
            let strength = '';
            let className = '';
            
            if (pwd.length === 0) {
                strength = '';
            } else if (pwd.length < 8) {
                strength = '⚠️ Слишком короткий пароль';
                className = 'weak';
            } else if (pwd.length < 12) {
                strength = '⚡ Средний пароль';
                className = 'medium';
            } else {
                strength = '🔒 Надёжный пароль';
                className = 'strong';
            }
            
            strengthDiv.textContent = strength;
            strengthDiv.className = 'password-strength ' + className;
        });
    </script>
</body>
</html>
