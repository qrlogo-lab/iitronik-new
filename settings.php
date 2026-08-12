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
    <title>Настройки — Итроник</title>
	<link rel="icon" type="image/png"  href="/img/ii-logo.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #0a0033;
            color: white;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 24px;
            color: #21d4fd;
        }
        .header a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
        }
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #21d4fd;
        }
        .card p {
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            font-size: 14px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: rgba(255,255,255,0.5);
            font-size: 13px;
        }
        .info-value {
            color: white;
            font-size: 14px;
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
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-outline:hover {
            background: rgba(255,255,255,0.05);
        }
        .btn-danger {
            background: linear-gradient(135deg, #f44336, #e91e63);
        }
        
        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #1a0f3a;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .modal-content h3 {
            color: #f44336;
            margin-bottom: 15px;
            font-size: 22px;
        }
        .modal-content p {
            margin-bottom: 25px;
            line-height: 1.6;
            color: rgba(255,255,255,0.8);
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Настройки</h1>
            <a href="/dashboard.php">← Назад в кабинет</a>
        </div>
        
        <div class="card">
            <h2>👤 Профиль</h2>
            <div class="info-row">
                <span class="info-label">Имя:</span>
                <span class="info-value"><?= htmlspecialchars($user['name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Дата регистрации:</span>
                <span class="info-value"><?= date('d.m.Y', strtotime($user['created_at'])) ?></span>
            </div>
        </div>
        
        <div class="card">
            <h2>🤖 Итроник</h2>
            <?php if ($user['wizard_completed']): ?>
                <p style="margin-bottom: 20px;">
                    Твой Итроник создан и готов к работе.
                </p>
                <button class="btn btn-outline" onclick="openResetModal()">
                    <i class="fas fa-sync-alt"></i> Перенастроить Итроника
                </button>
                <p style="font-size: 12px; color: rgba(255,255,255,0.4); margin-top: 10px;">
                    Это удалит текущие настройки и перезапустит визард
                </p>
            <?php else: ?>
                <p>Итроник ещё не создан.</p>
                <a href="/wizard.php" class="btn">Создать Итроника</a>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>💳 Тарифный план</h2>
            <div class="info-row">
                <span class="info-label">Текущий план:</span>
                <span class="info-value"><?= ucfirst($user['plan'] ?? 'free') ?></span>
            </div>
            <p style="margin-top: 15px; font-size: 13px;">
                Управление тарифами пока недоступно.
            </p>
        </div>
        
        <div class="card">
            <h2>🔒 Безопасность</h2>
            <button class="btn btn-outline" disabled>
                <i class="fas fa-key"></i> Сменить пароль
            </button>
            <p style="font-size: 12px; color: rgba(255,255,255,0.4); margin-top: 10px;">
                (Функция в разработке)
            </p>
        </div>
    </div>
    
    <!-- Модальное окно подтверждения -->
    <div class="modal" id="resetModal">
        <div class="modal-content">
            <h3>⚠️ Подтверди действие</h3>
            <p>
                Ты уверен, что хочешь перенастроить Итроника?
            </p>
            <p style="color: #f44336; font-size: 13px;">
                <strong>Внимание:</strong> Это удалит:
            </p>
            <ul style="margin: 10px 0 20px 20px; color: rgba(255,255,255,0.7); font-size: 13px;">
                <li>Текущий системный промпт</li>
                <li>Ответы на опросник</li>
                <li>Загруженные файлы</li>
            </ul>
            <p style="font-size: 13px;">
                Придётся пройти визард заново.
            </p>
            <div class="modal-buttons">
                <button class="btn btn-outline" onclick="closeResetModal()">
                    Отмена
                </button>
                <button class="btn btn-danger" onclick="resetWizard()">
                    <i class="fas fa-trash-alt"></i> Да, сбросить
                </button>
            </div>
        </div>
    </div>
    
    <script>
        const USER_ID = <?= $user['id'] ?>;
        
        function openResetModal() {
            document.getElementById('resetModal').classList.add('active');
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
        }
        
        async function resetWizard() {
            try {
                const response = await fetch('/api.php?action=wizard_reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: USER_ID })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert('✅ Итроник сброшен. Сейчас откроется визард.');
                    window.location.href = '/wizard.php';
                } else {
                    alert('❌ Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            } catch (error) {
                console.error('Reset error:', error);
                alert('❌ Ошибка сброса. Попробуй позже.');
            }
        }
        
        // Закрытие модалки по клику вне её
        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetModal();
            }
        });
    </script>
</body>
</html>
