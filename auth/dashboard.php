<?php
require_once __DIR__ . '/auth/config.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет — Итроник</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #0a0033;
            color: white;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }
        h1 {
            font-size: 24px;
            color: #21d4fd;
        }
        .user-info {
            text-align: right;
        }
        .user-info .name {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .user-info .email {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #21d4fd;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #21d4fd, #842ff3);
            color: white;
            text-decoration: none;
            border-radius: 24px;
            font-weight: 700;
            font-size: 14px;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>💼 Личный кабинет</h1>
            <div class="user-info">
                <div class="name">👤 <?= htmlspecialchars($user['name']) ?></div>
                <div class="email"><?= htmlspecialchars($user['email']) ?></div>
                <a href="/auth/logout.php" style="font-size: 12px; color: #21d4fd; text-decoration: none;">Выйти →</a>
            </div>
        </header>
        
        <div class="card">
            <h2>🎉 Добро пожаловать!</h2>
            <p style="margin-bottom: 20px; line-height: 1.6;">
                Ваш аккаунт создан. Теперь можно создать своего цифрового двойника — Итроника.
            </p>
            
            <?php if (!$user['wizard_completed']): ?>
                <a href="/wizard.php" class="btn">🚀 Создать Итроника</a>
            <?php else: ?>
                <a href="/chat.php" class="btn">💬 Перейти в чат с Итроником</a>
                <a href="/settings.php" class="btn btn-outline" style="margin-left: 10px;">⚙️ Настройки</a>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>📊 Статистика</h2>
            <p style="color: rgba(255,255,255,0.6);">
                Здесь будет статистика использования вашего Итроника.
            </p>
        </div>
    </div>
</body>
</html>
