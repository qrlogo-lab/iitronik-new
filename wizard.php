<?php
require_once __DIR__ . '/auth/config.php';
requireLogin();
$user = getCurrentUser();

// Если визард уже пройден — редирект в чат
if ($user['wizard_completed']) {
    header('Location: /chat.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание Итроника — Визард</title>
    <link rel="icon" type="image/png" href="/img/ii-logo.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Play:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #0a0033;
            color: white;
            overflow-x: hidden;
        }
        
        #app {
            max-width: 600px;
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* ===== ИНДИКАТОР ОБУЧЕННОСТИ (вместо прогресс-бара шагов) ===== */
        .training-bar {
            position: sticky;
            top: 0;
            background: rgba(10,0,51,0.95);
            backdrop-filter: blur(12px);
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            z-index: 100;
            display: none; /* Скрыт на приветственном экране */
        }
        .training-label {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .training-label .percent {
            font-size: 16px;
            font-weight: 700;
            color: #21d4fd;
            transition: all 0.3s;
        }
        .training-track {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
        }
        .training-fill {
            height: 100%;
            background: linear-gradient(90deg, #21d4fd, #842ff3);
            border-radius: 3px;
            transition: width 0.6s ease;
            width: 0%;
        }
        /* Анимация при обновлении процента */
        .training-fill.pulse {
            animation: pulse 0.5s ease;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        
        .content {
            flex: 1;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .step {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        .step.active { display: block; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Шаг 1 */
        .welcome-screen { text-align: center; }
        .welcome-icon { font-size: 64px; margin-bottom: 20px; }
        .welcome-screen h1 {
            font-family: 'Play', sans-serif;
            font-size: 28px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #21d4fd, #ff00c8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .welcome-screen p {
            font-size: 15px;
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .feature-list {
            text-align: left;
            margin: 30px 0;
        }
        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
        }
        .feature-item i {
            font-size: 20px;
            color: #21d4fd;
            margin-top: 2px;
        }
        .feature-item span { font-size: 14px; line-height: 1.5; }
        
        /* Чат */
        .chat-screen {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 100%;
        }
        .chat-message {
            display: flex;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        .chat-message.user { flex-direction: row-reverse; }
        .chat-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }
        .chat-message.assistant .chat-avatar {
            background: linear-gradient(135deg, #21d4fd, #842ff3);
        }
        .chat-message.user .chat-avatar {
            background: rgba(255,255,255,0.1);
        }
        .chat-bubble {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.6;
            word-break: break-word;
        }
        .chat-message.assistant .chat-bubble {
            background: rgba(33,212,253,0.15);
            border: 1px solid rgba(33,212,253,0.3);
        }
        .chat-message.user .chat-bubble {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
        }
        .chat-bubble h1, .chat-bubble h2, .chat-bubble h3 {
            font-size: 1.2em; margin: 0.5em 0;
        }
        .chat-bubble ul, .chat-bubble ol { padding-left: 1.5em; margin: 0.5em 0; }
        .chat-bubble code {
            background: rgba(255,255,255,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .chat-bubble pre {
            background: rgba(0,0,0,0.3);
            padding: 10px;
            border-radius: 8px;
            overflow-x: auto;
        }
        
        /* Ввод */
        .chat-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-top: 20px;
        }
        .chat-input-wrapper textarea {
            flex: 1;
            min-height: 50px;
            max-height: 150px;
            padding: 12px 16px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            resize: none;
            outline: none;
        }
        .chat-input-wrapper textarea:focus {
            border-color: rgba(33,212,253,0.5);
        }
        .chat-input-wrapper .action-btn {
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            transition: 0.2s;
            flex-shrink: 0;
        }
        .chat-input-wrapper .action-btn:hover {
            color: #21d4fd;
        }
        .chat-input-wrapper .send-btn {
            background: linear-gradient(135deg, #21d4fd, #842ff3);
            border: none;
            color: white;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 20px;
            cursor: pointer;
            flex-shrink: 0;
            transition: 0.2s;
        }
        .chat-input-wrapper .send-btn:hover {
            transform: scale(1.05);
        }
        .chat-input-wrapper .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        #fileInputHidden { display: none; }
        
        /* Кнопки */
        .btn-container {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 30px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #21d4fd, #842ff3);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(33,212,253,0.3);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.08);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        /* Loading */
        .loading { text-align: center; padding: 40px 20px; }
        .spinner {
            width: 50px; height: 50px;
            border: 4px solid rgba(255,255,255,0.1);
            border-top-color: #21d4fd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { font-size: 14px; color: rgba(255,255,255,0.6); }
        
        /* Typing */
        .typing-dots {
            display: flex; gap: 6px; align-items: center; height: 20px;
        }
        .typing-dots span {
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(255,255,255,0.6);
            animation: typing 1.4s infinite;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.6; }
            30% { transform: translateY(-10px); opacity: 1; }
        }
        
        /* Список загруженных файлов (мини) */
        .uploaded-files-mini {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .uploaded-file-mini {
            background: rgba(76,175,80,0.2);
            border: 1px solid rgba(76,175,80,0.3);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .uploaded-file-mini .remove {
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            cursor: pointer;
        }
        
        /* ===== ФИНАЛЬНЫЙ ЭКРАН: Большой процент ===== */
        .final-percent {
            font-size: 72px;
            font-weight: 700;
            background: linear-gradient(135deg, #21d4fd, #842ff3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 20px 0;
        }
        .final-percent-label {
            font-size: 14px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
<div id="app">
    <!-- ИНДИКАТОР ОБУЧЕННОСТИ (вместо прогресс-бара шагов) -->
    <div class="training-bar" id="trainingBar">
        <div class="training-label">
            <span>Обученность Итроника</span>
            <span class="percent" id="trainingPercentText">0%</span>
        </div>
        <div class="training-track">
            <div class="training-fill" id="trainingFill"></div>
        </div>
    </div>
    <div class="content" id="content"></div>
</div>

<script>
// ========== КОНФИГ ==========
const USER_ID = <?= $user['id'] ?>;
const USER_NAME = '<?= htmlspecialchars($user['name']) ?>';

// ========== СОСТОЯНИЕ ==========
let wizardConversationHistory = [];
let isWizardComplete = false;
let uploadedFiles = [];
let systemPrompt = '';
let testMessages = [];
let answersCount = 0;       // Количество ответов на вопросы
let filesCount = 0;         // Количество загруженных файлов

// ========== ПРОЦЕНТ ОБУЧЕННОСТИ ==========
function getTrainingPercent() {
    // Формула: каждый ответ +1%, каждый файл +2%
    // Максимум на этапе визарда: ~10-15%
    return Math.min(answersCount * 1 + filesCount * 2, 15);
}

function updateTrainingIndicator() {
    const percent = getTrainingPercent();
    const fill = document.getElementById('trainingFill');
    const text = document.getElementById('trainingPercentText');
    if (fill && text) {
        fill.style.width = percent + '%';
        text.textContent = percent + '%';
        // Анимация пульса при обновлении
        fill.classList.remove('pulse');
        void fill.offsetWidth; // Перезапуск анимации
        fill.classList.add('pulse');
    }
}

function showTrainingBar() {
    document.getElementById('trainingBar').style.display = 'block';
}

function hideTrainingBar() {
    document.getElementById('trainingBar').style.display = 'none';
}

// ========== НАВИГАЦИЯ (без нумерации шагов) ==========
function showScreen(screenId) {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    const el = document.getElementById(screenId);
    if (el) el.classList.add('active');
}

// ========== ЭКРАН 1: ПРИВЕТСТВИЕ ==========
function renderWelcome() {
    hideTrainingBar(); // На приветствии индикатор не нужен
    
    const html = `
        <div class="step active" id="screen-welcome">
            <div class="welcome-screen">
                <div class="welcome-icon">👋</div>
                <h1>Добро пожаловать!</h1>
                <p style="font-size:16px; margin-bottom:10px;">За несколько минут вы создадите своего цифрового двойника — Итроника.</p>
                <p style="font-size:14px; color:rgba(255,255,255,0.6); margin-bottom:20px;">Он сможет:</p>
                <div class="feature-list">
                    <div class="feature-item"><i class="fas fa-comment-dots"></i><span><strong>Говорить вашим голосом</strong> — копировать ваш стиль общения</span></div>
                    <div class="feature-item"><i class="fas fa-pen-fancy"></i><span><strong>Помогать писать</strong> письма, тексты, посты в вашем стиле</span></div>
                    <div class="feature-item"><i class="fas fa-clock"></i><span><strong>Экономить время</strong> — до 5 часов в неделю на рутине</span></div>
                </div>
                <p style="font-size:13px; color:rgba(255,255,255,0.5);">Процесс займёт 10-15 минут. Итроник начнёт обучаться сразу.</p>
                <div class="btn-container">
                    <button class="btn btn-primary" onclick="startWizard()"><i class="fas fa-rocket"></i> Начать</button>
                </div>
            </div>
        </div>
    `;
    document.getElementById('content').innerHTML = html;
}

function startWizard() {
    showTrainingBar(); // Показываем индикатор обученности
    updateTrainingIndicator();
    renderSurvey();
}

// ========== ЭКРАН 2: ОПРОСНИК (с AI-агентом) ==========
function renderSurvey() {
    const html = `
        <div class="step" id="screen-survey">
            <div class="chat-screen" id="chatContainer"></div>
            <div class="chat-input-wrapper" id="wizardInputWrapper" style="display:none;">
                <textarea id="answerInput" placeholder="Введите ваш ответ..." rows="1"></textarea>
                <button class="action-btn" id="attachBtn" onclick="document.getElementById('fileInputHidden').click()" title="Прикрепить файл (txt, docx, pdf)">📎</button>
                <button class="send-btn" id="sendWizardBtn" onclick="submitAnswer()">➤</button>
            </div>
            <div id="wizardUploadedFilesMini" class="uploaded-files-mini"></div>
            <input type="file" id="fileInputHidden" multiple accept=".txt,.docx,.pdf" onchange="handleFileAttach(event)">
        </div>
    `;
    document.getElementById('content').innerHTML = html;
    setTimeout(() => askWizardQuestion(), 300);
}

function handleFileAttach(event) {
    const files = event.target.files;
    for (let file of files) {
        if (file.size > 100 * 1024 * 1024) {
            alert(`Файл ${file.name} слишком большой (макс. 100 МБ)`);
            continue;
        }
        uploadedFiles.push(file);
        filesCount++;
        addFileToMiniList(file);
        uploadFile(file);
        updateTrainingIndicator(); // Обновляем процент
    }
    event.target.value = '';
}

function addFileToMiniList(file) {
    const container = document.getElementById('wizardUploadedFilesMini');
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'uploaded-file-mini';
    div.innerHTML = `
        <span>📄 ${file.name} (${formatFileSize(file.size)})</span>
        <button class="remove" onclick="removeFileFromList('${file.name}')">✕</button>
    `;
    container.appendChild(div);
}

function removeFileFromList(fileName) {
    uploadedFiles = uploadedFiles.filter(f => f.name !== fileName);
    filesCount = Math.max(0, filesCount - 1);
    renderMiniFiles();
    updateTrainingIndicator();
}

function renderMiniFiles() {
    const container = document.getElementById('wizardUploadedFilesMini');
    if (!container) return;
    container.innerHTML = '';
    uploadedFiles.forEach(f => addFileToMiniList(f));
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('user_id', USER_ID);
    try {
        await fetch('/api.php?action=wizard_upload_file', { method: 'POST', body: formData });
    } catch (e) { console.error('Upload error', e); }
}

// ----- Логика опросника -----
async function askWizardQuestion() {
    if (isWizardComplete) return;
    try {
        showTypingIndicator();
        const resp = await fetch('/api.php?action=wizard_chat_question', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: USER_ID, history: wizardConversationHistory })
        });
        const data = await resp.json();
        hideTypingIndicator();
        if (data.question) {
            let text = data.question;
            
            // Проверяем [COMPLETE] ДО отображения
            if (text.includes('[COMPLETE]')) {
                const clean = text.replace(/\[COMPLETE\]/g, '').trim();
                addAssistantMessage(clean);
                isWizardComplete = true;
                document.getElementById('wizardInputWrapper').style.display = 'none';
                setTimeout(() => {
                    renderProcessing();
                }, 1500);
                return;
            }
            
            // Обычный вопрос
            addAssistantMessage(text);
            document.getElementById('wizardInputWrapper').style.display = 'flex';
            document.getElementById('answerInput').focus();
            wizardConversationHistory.push({ role: 'assistant', content: text });
        } else {
            finishWizardSurvey();
        }
    } catch (e) {
        console.error(e);
        hideTypingIndicator();
        alert('Ошибка, переходим к следующему этапу.');
        finishWizardSurvey();
    }
}

async function submitAnswer() {
    const input = document.getElementById('answerInput');
    let answer = input.value.trim();

    // Если есть прикреплённые файлы – читаем их содержимое
    if (uploadedFiles.length > 0) {
        let fileContents = [];
        for (let file of uploadedFiles) {
            if (file.type === 'text/plain' || file.name.endsWith('.txt')) {
                try {
                    const text = await file.text();
                    fileContents.push(`📄 Файл: ${file.name}\n${text}`);
                } catch (e) {
                    console.error('Ошибка чтения файла:', e);
                    fileContents.push(`⚠️ Не удалось прочитать файл: ${file.name}`);
                }
            } else {
                fileContents.push(`📎 Файл: ${file.name} (загружен на сервер, будет использован при обучении)`);
            }
        }
        if (fileContents.length > 0) {
            answer += '\n--- Прикреплённые файлы ---\n' + fileContents.join('\n');
        }
        uploadedFiles = [];
        renderMiniFiles();
    }

    if (!answer || !answer.trim()) {
        alert('Введите сообщение или прикрепите файл');
        return;
    }

    addUserMessage(answer);
    input.value = '';
    document.getElementById('wizardInputWrapper').style.display = 'none';
    
    // Увеличиваем счётчик ответов и обновляем процент
    answersCount++;
    updateTrainingIndicator();
    
    wizardConversationHistory.push({ role: 'user', content: answer });
    await saveAnswer(wizardConversationHistory.length, answer);
    setTimeout(() => askWizardQuestion(), 500);
}

function finishWizardSurvey() {
    if (isWizardComplete) return;
    isWizardComplete = true;
    document.getElementById('wizardInputWrapper').style.display = 'none';
    addAssistantMessage('✅ Отлично, я понял Ваш стиль! Переходим к созданию Итроника.');
    setTimeout(() => {
        renderProcessing();
    }, 1500);
}

async function saveAnswer(qid, text) {
    try {
        await fetch('/api.php?action=wizard_save_answer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: USER_ID, question_id: qid, answer_text: text })
        });
    } catch (e) { console.error(e); }
}

// ----- Вспомогательные функции чата -----
function addAssistantMessage(text) {
    const container = document.getElementById('chatContainer');
    if (!container) return;
    const msg = document.createElement('div');
    msg.className = 'chat-message assistant';
    const htmlContent = marked.parse(text);
    msg.innerHTML = `
        <div class="chat-avatar">🤖</div>
        <div class="chat-bubble">${htmlContent}</div>
    `;
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

function addUserMessage(text) {
    const container = document.getElementById('chatContainer');
    if (!container) return;
    const msg = document.createElement('div');
    msg.className = 'chat-message user';
    msg.innerHTML = `
        <div class="chat-avatar">👤</div>
        <div class="chat-bubble">${text}</div>
    `;
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

function showTypingIndicator() {
    const container = document.getElementById('chatContainer');
    if (!container) return;
    const indicator = document.createElement('div');
    indicator.id = 'typingIndicator';
    indicator.className = 'chat-message assistant';
    indicator.innerHTML = `
        <div class="chat-avatar">🤖</div>
        <div class="chat-bubble" style="padding: 15px 20px;"><div class="typing-dots"><span></span><span></span><span></span></div></div>
    `;
    container.appendChild(indicator);
    container.scrollTop = container.scrollHeight;
}
function hideTypingIndicator() {
    const el = document.getElementById('typingIndicator');
    if (el) el.remove();
}

// ========== ЭКРАН 3: ОБРАБОТКА (генерация промпта) ==========
function renderProcessing() {
    showLoading('Создаём первую версию вашего Итроника...<br><small>Итроник изучает ваш стиль общения. Это займёт 1-2 минуты.</small>');
    processUserData();
}

async function processUserData() {
    try {
        updateLoadingText('Генерация системного промпта...<br><small>Итроник изучает ваш стиль общения</small>');
        await generateSystemPrompt();
        updateLoadingText('Сохранение настроек...');
        await saveSystemPrompt();
        setTimeout(() => {
            renderTestChat();
        }, 1500);
    } catch (e) {
        console.error(e);
        alert('Ошибка при создании Итроника. Попробуйте снова.');
    }
}

function updateLoadingText(html) {
    const el = document.querySelector('.loading-text');
    if (el) el.innerHTML = html;
}

async function generateSystemPrompt() {
    const resp = await fetch('/api.php?action=wizard_generate_prompt', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: USER_ID, user_name: USER_NAME })
    });
    const data = await resp.json();
    if (data.system_prompt) {
        systemPrompt = data.system_prompt;
    } else throw new Error('Не удалось сгенерировать промпт');
}

async function saveSystemPrompt() {
    await fetch('/api.php?action=wizard_save_prompt', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: USER_ID, system_prompt: systemPrompt })
    });
}

// ========== ЭКРАН 4: ТЕСТОВЫЙ ЧАТ ==========
function renderTestChat() {
    const html = `
        <div class="step" id="screen-test">
            <div class="chat-screen">
                <h2 style="text-align:center; margin-bottom:10px; color:#21d4fd;">✨ Изначальная версия вашего Итроника готова!</h2>
                <p style="text-align:center; font-size:13px; color:rgba(255,255,255,0.6); margin-bottom:20px;">
                    Это первая версия — она будет становиться лучше с каждым обучением.
                </p>
                
                <!-- Кнопки-примеры вопросов -->
                <div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin-bottom:20px;">
                    <button onclick="sendExampleQuestion('Привет! Расскажи о себе')" style="font-size:12px; padding:8px 14px; border-radius:20px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.2); color:white; cursor:pointer;">👋 Расскажи о себе</button>
                    <button onclick="sendExampleQuestion('Чем ты занимаешься?')" style="font-size:12px; padding:8px 14px; border-radius:20px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.2); color:white; cursor:pointer;">💼 Чем занимаешься?</button>
                    <button onclick="sendExampleQuestion('Напиши письмо от моего имени')" style="font-size:12px; padding:8px 14px; border-radius:20px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.2); color:white; cursor:pointer;">✍️ Напиши письмо</button>
                </div>
                
                <div id="testChatContainer" class="chat-screen"></div>
                <div class="chat-input-wrapper">
                    <textarea id="testInput" placeholder="Напишите что-нибудь..." rows="1"></textarea>
                    <button class="send-btn" onclick="sendTestMessage()">➤</button>
                </div>
            </div>
            <div class="btn-container" style="margin-top:30px;">
                <button class="btn btn-primary" onclick="completeWizard()">✅ Всё хорошо</button>
                <button class="btn btn-secondary" onclick="askForRevision()">🔧 Можно ещё улучшить</button>
            </div>
        </div>
    `;
    document.getElementById('content').innerHTML = html;
    
    setTimeout(() => {
        addTestAssistantMessage('Привет! Я ваш Итроник. Я ещё не очень умный, но скоро научусь. Задайте мне вопрос или попросите чего-нибудь написать, чтобы увидеть, что я могу.');
    }, 200);
}

function sendExampleQuestion(question) {
    document.getElementById('testInput').value = question;
    sendTestMessage();
}

function addTestAssistantMessage(text) {
    const container = document.getElementById('testChatContainer');
    if (!container) return;
    const msg = document.createElement('div');
    msg.className = 'chat-message assistant';
    msg.innerHTML = `
        <div class="chat-avatar">🤖</div>
        <div class="chat-bubble">${marked.parse(text)}</div>
    `;
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}
function addTestUserMessage(text) {
    const container = document.getElementById('testChatContainer');
    if (!container) return;
    const msg = document.createElement('div');
    msg.className = 'chat-message user';
    msg.innerHTML = `<div class="chat-bubble">${text}</div>`;
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

async function sendTestMessage() {
    const input = document.getElementById('testInput');
    const message = input.value.trim();
    if (!message) return;
    addTestUserMessage(message);
    input.value = '';
    showTestTypingIndicator();
    try {
        const resp = await fetch('/api.php?action=wizard_test_itronik', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: USER_ID, message, history: testMessages })
        });
        const data = await resp.json();
        hideTestTypingIndicator();
        if (data.response) {
            addTestAssistantMessage(data.response);
            testMessages.push({ role: 'user', content: message });
            testMessages.push({ role: 'assistant', content: data.response });
        }
    } catch (e) {
        hideTestTypingIndicator();
        alert('Ошибка при отправке');
    }
}
function showTestTypingIndicator() {
    const container = document.getElementById('testChatContainer');
    if (!container) return;
    const ind = document.createElement('div');
    ind.id = 'testTyping';
    ind.className = 'chat-message assistant';
    ind.innerHTML = `<div class="chat-avatar">🤖</div><div class="chat-bubble"><div class="typing-dots"><span></span><span></span><span></span></div></div>`;
    container.appendChild(ind);
    container.scrollTop = container.scrollHeight;
}
function hideTestTypingIndicator() {
    const el = document.getElementById('testTyping');
    if (el) el.remove();
}

// ========== ЭКРАН 5: ДОРАБОТКА ==========
function askForRevision() {
    const html = `
        <div class="step" id="screen-revision">
            <h2 style="text-align:center; margin-bottom:20px; color:#21d4fd;">🔧 Доработка Итроника</h2>
            <p style="text-align:center; font-size:13px; color:rgba(255,255,255,0.6); margin-bottom:30px;">Что нужно исправить? Опишите подробно:</p>
            <div style="margin-bottom:20px;">
                <textarea id="revisionText" style="width:100%; min-height:120px; padding:14px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:12px; color:white; font-size:14px; resize:vertical;" placeholder="Например: слишком формально, не использует мои любимые фразы..."></textarea>
            </div>
            <div class="btn-container">
                <button class="btn btn-secondary" onclick="renderTestChat()">← Назад</button>
                <button class="btn btn-primary" onclick="updatePrompt()"><i class="fas fa-sync-alt"></i> Обновить</button>
            </div>
        </div>
    `;
    document.getElementById('content').innerHTML = html;
}

async function updatePrompt() {
    const text = document.getElementById('revisionText').value.trim();
    if (!text) { alert('Опишите, что нужно исправить'); return; }
    showLoading('Обновляем Итроника...');
    try {
        const resp = await fetch('/api.php?action=wizard_update_prompt', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: USER_ID, feedback: text, current_prompt: systemPrompt })
        });
        const data = await resp.json();
        if (data.system_prompt) {
            systemPrompt = data.system_prompt;
            await saveSystemPrompt();
            testMessages = [];
            renderTestChat();
        }
    } catch (e) { alert('Ошибка при обновлении'); }
}

// ========== ЭКРАН 6: ЗАВЕРШЕНИЕ ==========
async function completeWizard() {
    showLoading('Завершаем настройку...');
    try {
        const finalPercent = getTrainingPercent() + 5; // +5% база за завершение визарда
        
        const resp = await fetch('/api.php?action=wizard_complete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                user_id: USER_ID,
                training_percent: finalPercent  // ← Передаём процент на сервер
            })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            renderFinal(finalPercent);
        }
    } catch (e) { alert('Ошибка при завершении'); }
}

function renderFinal(percent) {
    hideTrainingBar(); // Скрываем индикатор на финальном экране
    
    const html = `
        <div class="step" id="screen-final">
            <div class="welcome-screen">
                <div class="welcome-icon">🎉</div>
                <h1>Первая версия готова!</h1>
                
                <div class="final-percent">${percent}%</div>
                <div class="final-percent-label">Обученность вашего Итроника</div>
                
                <p style="font-size:14px; color:rgba(255,255,255,0.7); line-height:1.6; margin-bottom:20px;">
                    Ваш Итроник создан и готов к работе. С каждым новым диалогом и обучением он будет становиться всё больше похож на вас.
                </p>
                <p style="font-size:13px; color:rgba(255,255,255,0.5); margin-bottom:30px;">
                    Вы всегда сможете дообучить его в настройках.
                </p>
                
                <div class="btn-container">
                    <button class="btn btn-primary" onclick="window.location.href='/dashboard.php?refresh=1'"><i class="fas fa-rocket"></i> Начать пользоваться</button>
                </div>
            </div>
        </div>
    `;
    document.getElementById('content').innerHTML = html;
}

function showLoading(text) {
    document.getElementById('content').innerHTML = `
        <div class="loading"><div class="spinner"></div><div class="loading-text">${text}</div></div>
    `;
}

// ========== ИНИЦИАЛИЗАЦИЯ ==========
renderWelcome();
</script>
</body>
</html>