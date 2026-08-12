<?php
session_start();
require_once __DIR__ . '/auth/config.php';
require_once __DIR__ . '/config/database.php';

// Проверка авторизации
if (!isUserLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

$user = getCurrentUser();

// Проверка wizard_completed
if (!$user['wizard_completed']) {
    header('Location: /wizard.php');
    exit;
}

// Проверка системного промпта
if (empty($user['system_prompt'])) {
    header('Location: /dashboard.php?error=no_prompt');
    exit;
}

$db = getDB();

// Получаем список диалогов пользователя
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_id,
        COALESCE(c.custom_name, c.title, 'Новый диалог') as display_title,
        c.started_at,
        c.ended_at,
        (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
    FROM conversations c
    WHERE c.user_id = ?
    ORDER BY c.started_at DESC
    LIMIT 50
");
$stmt->execute([$user['id']]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Текущий диалог (из URL или последний)
$currentConvId = $_GET['conv'] ?? ($conversations[0]['id'] ?? null);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат с Итроником — Iitronik</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Marked.js для Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Highlight.js для подсветки кода -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif;
        }
        
        /* Кастомный скроллбар */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Анимация typing indicator */
        .typing-indicator {
            display: inline-flex;
            gap: 4px;
        }
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #6b7280;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes typing {
            0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
            30% { opacity: 1; transform: translateY(-10px); }
        }
        
        /* Markdown стили */
        .markdown-content {
            line-height: 1.6;
        }
        .markdown-content h1 { font-size: 1.5em; font-weight: bold; margin: 1em 0 0.5em; }
        .markdown-content h2 { font-size: 1.3em; font-weight: bold; margin: 1em 0 0.5em; }
        .markdown-content h3 { font-size: 1.1em; font-weight: bold; margin: 0.8em 0 0.4em; }
        .markdown-content ul, .markdown-content ol { margin: 0.5em 0; padding-left: 2em; }
        .markdown-content li { margin: 0.3em 0; }
        .markdown-content code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .markdown-content pre {
            background: #1f2937;
            color: #e5e7eb;
            padding: 1em;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1em 0;
        }
        .markdown-content pre code {
            background: none;
            padding: 0;
            color: inherit;
        }
        .markdown-content blockquote {
            border-left: 4px solid #e5e7eb;
            padding-left: 1em;
            color: #6b7280;
            margin: 1em 0;
        }
        
        /* Сообщения */
        .message-user {
            background: #3b82f6;
            color: white;
            border-radius: 18px;
            padding: 12px 18px;
            max-width: 70%;
            margin-left: auto;
            word-wrap: break-word;
        }
        .message-assistant {
            background: #f3f4f6;
            color: #111827;
            border-radius: 18px;
            padding: 12px 18px;
            max-width: 85%;
            margin-right: auto;
            word-wrap: break-word;
        }
        
        /* Активный диалог */
        .conversation-item {
            cursor: pointer;
            transition: all 0.2s;
        }
        .conversation-item:hover {
            background: #f3f4f6;
        }
        .conversation-item.active {
            background: #e0e7ff;
            border-left: 4px solid #3b82f6;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            #sidebar {
                position: fixed;
                left: -100%;
                transition: left 0.3s;
                z-index: 50;
            }
            #sidebar.show {
                left: 0;
            }
            #sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 40;
            }
            #sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        
        <!-- SIDEBAR (Левая панель) -->
        <div id="sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <!-- Header -->
            <div class="p-4 border-b border-gray-200">
                <button 
                    id="newChatBtn"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Новый диалог
                </button>
            </div>
            
            <!-- Search -->
            <div class="p-3 border-b border-gray-200">
                <input 
                    type="text" 
                    id="searchInput"
                    placeholder="🔍 Поиск по диалогам..." 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
            </div>
            
            <!-- Conversations List -->
            <div id="conversationsList" class="flex-1 overflow-y-auto p-2">
                <?php if (empty($conversations)): ?>
                    <div class="text-center text-gray-500 mt-8 text-sm">
                        <p>Нет диалогов</p>
                        <p class="mt-2">Начните новый диалог ☝️</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div 
                            class="conversation-item p-3 rounded-lg mb-1 <?= ($currentConvId == $conv['id']) ? 'active' : '' ?>"
                            data-conv-id="<?= $conv['id'] ?>"
                            data-conversation-id="<?= htmlspecialchars($conv['conversation_id']) ?>">
                            
                            <div class="font-medium text-sm text-gray-900 truncate">
                                <?= htmlspecialchars($conv['display_title']) ?>
                            </div>
                            
                            <?php if ($conv['last_message']): ?>
                                <div class="text-xs text-gray-500 truncate mt-1">
                                    <?= htmlspecialchars(substr($conv['last_message'], 0, 50)) ?>...
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-xs text-gray-400 mt-1">
                                <?= date('d.m.Y H:i', strtotime($conv['started_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <div class="p-4 border-t border-gray-200 space-y-2">
                <a href="/settings.php" class="flex items-center gap-2 text-gray-700 hover:text-blue-600 text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Настройки
                </a>
                <a href="/dashboard.php" class="flex items-center gap-2 text-gray-700 hover:text-blue-600 text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
                <a href="/auth/logout.php" class="flex items-center gap-2 text-red-600 hover:text-red-700 text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Выйти
                </a>
            </div>
        </div>
        
        <!-- MAIN CHAT AREA -->
        <div class="flex-1 flex flex-col">
            
            <!-- Top Bar -->
            <div class="bg-white border-b border-gray-200 p-4 flex items-center justify-between">
                <!-- Mobile menu button -->
                <button id="mobileMenuBtn" class="md:hidden text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-blue-600 font-bold text-lg">I</span>
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900" id="chatTitle">Мой Итроник</h1>
                        <p class="text-xs text-gray-500">Готов помочь с вашими задачами</p>
                    </div>
                </div>
                
                <div class="text-sm text-gray-600">
                    👤 <?= htmlspecialchars($user['name']) ?>
                </div>
            </div>
            
            <!-- Messages Container -->
            <div id="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4">
                <!-- Сообщения загружаются динамически -->
                <div class="text-center text-gray-400 mt-20">
                    <p class="text-lg">👋 Начните диалог с вашим Итроником</p>
                    <p class="text-sm mt-2">Он знает ваш стиль и готов помочь</p>
                </div>
            </div>
            
            <!-- Input Area -->
            <div class="bg-white border-t border-gray-200 p-4">
                <form id="chatForm" class="max-w-4xl mx-auto">
                    <div class="flex gap-3">
                        <textarea 
                            id="messageInput"
                            placeholder="Введите сообщение... (Shift+Enter для новой строки)"
                            class="flex-1 resize-none border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 min-h-[50px] max-h-[200px]"
                            rows="1"></textarea>
                        
                        <button 
                            type="submit"
                            id="sendBtn"
                            class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            <span class="hidden md:inline">Отправить</span>
                        </button>
                    </div>
                    
                    <div class="text-xs text-gray-500 mt-2 text-center">
                        Итроник может ошибаться. Проверяйте важную информацию.
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Mobile sidebar overlay -->
    <div id="sidebar-overlay"></div>
    
    <script>
        // ============================================
        // ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ
        // ============================================
        
        const userId = <?= $user['id'] ?>;
        let currentConversationId = <?= $currentConvId ? $currentConvId : 'null' ?>;
        let currentLibreChatConvId = null; // ID диалога в LibreChat
        let messageHistory = []; // История сообщений текущего диалога
        let isLoading = false;
        
        // ============================================
        // ЭЛЕМЕНТЫ DOM
        // ============================================
        
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const chatForm = document.getElementById('chatForm');
        const sendBtn = document.getElementById('sendBtn');
        const newChatBtn = document.getElementById('newChatBtn');
        const searchInput = document.getElementById('searchInput');
        const chatTitle = document.getElementById('chatTitle');
        
        // ============================================
        // ИНИЦИАЛИЗАЦИЯ
        // ============================================
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Chat initialized, user_id:', userId);
            
            // Загружаем текущий диалог если есть
            if (currentConversationId) {
                loadConversation(currentConversationId);
            }
            
            // Автоматическое изменение высоты textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 200) + 'px';
            });
            
            // Enter для отправки (Shift+Enter для новой строки)
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit'));
                }
            });
            
            // Mobile menu
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            mobileMenuBtn?.addEventListener('click', () => {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });
            
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });
        });
        
        // ============================================
        // ОТПРАВКА СООБЩЕНИЯ
        // ============================================
        
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (isLoading) return;
            
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Отключаем ввод
            isLoading = true;
            sendBtn.disabled = true;
            messageInput.disabled = true;
            
            // Добавляем сообщение пользователя
            addMessage('user', message);
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            // Показываем typing indicator
            const typingId = showTypingIndicator();
            
            try {
                const response = await fetch('/api.php?action=chat_send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        conversation_id: currentConversationId,
                        librechat_conv_id: currentLibreChatConvId,
                        message: message,
                        history: messageHistory
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Chat response:', data);
                
                if (data.status === 'success') {
                    // Удаляем typing indicator
                    removeTypingIndicator(typingId);
                    
                    // Добавляем ответ ассистента
                    addMessage('assistant', data.response);
                    
                    // Обновляем ID диалогов
                    if (data.conversation_id && !currentConversationId) {
                        currentConversationId = data.conversation_id;
                        
                        // Обновляем URL
                        const newUrl = `/chat.php?conv=${data.conversation_id}`;
                        window.history.pushState({}, '', newUrl);
                        
                        // Перезагружаем список диалогов
                        loadConversationsList();
                    }
                    
                    if (data.librechat_conv_id) {
                        currentLibreChatConvId = data.librechat_conv_id;
                    }
                    
                    // Обновляем заголовок если есть
                    if (data.title) {
                        chatTitle.textContent = data.title;
                    }
                    
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
                
            } catch (error) {
                console.error('Send message error:', error);
                removeTypingIndicator(typingId);
                addMessage('system', '❌ Ошибка отправки сообщения: ' + error.message);
            } finally {
                // Включаем ввод
                isLoading = false;
                sendBtn.disabled = false;
                messageInput.disabled = false;
                messageInput.focus();
            }
        });
        
        // ============================================
        // ДОБАВЛЕНИЕ СООБЩЕНИЯ В ЧАТ
        // ============================================
        
        function addMessage(role, content) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex';
            
            if (role === 'user') {
                messageDiv.innerHTML = `
                    <div class="message-user">
                        ${escapeHtml(content)}
                    </div>
                `;
                
                // Сохраняем в историю
                messageHistory.push({ role: 'user', content: content });
                
            } else if (role === 'assistant') {
                const htmlContent = marked.parse(content);
                
                messageDiv.innerHTML = `
                    <div class="message-assistant markdown-content">
                        ${htmlContent}
                    </div>
                `;
                
                // Подсветка кода
                messageDiv.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightElement(block);
                });
                
                // Сохраняем в историю
                messageHistory.push({ role: 'assistant', content: content });
                
            } else if (role === 'system') {
                messageDiv.innerHTML = `
                    <div class="mx-auto text-center text-gray-500 text-sm">
                        ${content}
                    </div>
                `;
            }
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // ============================================
        // TYPING INDICATOR
        // ============================================
        
        function showTypingIndicator() {
            const id = 'typing-' + Date.now();
            const typingDiv = document.createElement('div');
            typingDiv.id = id;
            typingDiv.className = 'flex';
            typingDiv.innerHTML = `
                <div class="message-assistant">
                    <div class="typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            `;
            messagesContainer.appendChild(typingDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            return id;
        }
        
        function removeTypingIndicator(id) {
            const element = document.getElementById(id);
            if (element) {
                element.remove();
            }
        }
        
        // ============================================
        // ЗАГРУЗКА ДИАЛОГА
        // ============================================
        
        async function loadConversation(convId) {
            console.log('Loading conversation:', convId);
            
            try {
                const response = await fetch(`/api.php?action=chat_messages&conversation_id=${convId}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Очищаем чат
                    messagesContainer.innerHTML = '';
                    messageHistory = [];
                    
                    // Обновляем заголовок
                    if (data.title) {
                        chatTitle.textContent = data.title;
                    }
                    
                    // Загружаем сообщения
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            addMessage(msg.role, msg.content);
                        });
                    } else {
                        messagesContainer.innerHTML = `
                            <div class="text-center text-gray-400 mt-20">
                                <p class="text-lg">👋 Продолжите диалог</p>
                            </div>
                        `;
                    }
                    
                    // Сохраняем LibreChat conversation_id
                    if (data.librechat_conv_id) {
                        currentLibreChatConvId = data.librechat_conv_id;
                    }
                    
                    // Обновляем активный пункт в sidebar
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        item.classList.remove('active');
                        if (item.dataset.convId == convId) {
                            item.classList.add('active');
                        }
                    });
                    
                } else {
                    console.error('Load conversation error:', data.error);
                }
                
            } catch (error) {
                console.error('Load conversation error:', error);
            }
        }
        
        // ============================================
        // НОВЫЙ ДИАЛОГ
        // ============================================
        
        newChatBtn.addEventListener('click', function() {
            currentConversationId = null;
            currentLibreChatConvId = null;
            messageHistory = [];
            
            messagesContainer.innerHTML = `
                <div class="text-center text-gray-400 mt-20">
                    <p class="text-lg">👋 Начните новый диалог</p>
                    <p class="text-sm mt-2">Ваш Итроник готов помочь</p>
                </div>
            `;
            
            chatTitle.textContent = 'Новый диалог';
            
            // Обновляем активный пункт
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Обновляем URL
            window.history.pushState({}, '', '/chat.php');
            
            messageInput.focus();
        });
        
        // ============================================
        // ПЕРЕКЛЮЧЕНИЕ ДИАЛОГОВ
        // ============================================
        
        document.addEventListener('click', function(e) {
            const convItem = e.target.closest('.conversation-item');
            if (convItem) {
                const convId = convItem.dataset.convId;
                const libreChatConvId = convItem.dataset.conversationId;
                
                currentConversationId = convId;
                currentLibreChatConvId = libreChatConvId;
                
                loadConversation(convId);
                
                // Обновляем URL
                const newUrl = `/chat.php?conv=${convId}`;
                window.history.pushState({}, '', newUrl);
                
                // Закрываем sidebar на мобильных
                if (window.innerWidth < 768) {
                    document.getElementById('sidebar').classList.remove('show');
                    document.getElementById('sidebar-overlay').classList.remove('show');
                }
            }
        });
        
        // ============================================
        // ПОИСК ПО ДИАЛОГАМ
        // ============================================
        
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim();
                if (query.length >= 2) {
                    searchConversations(query);
                } else {
                    loadConversationsList();
                }
            }, 300);
        });
        
        async function searchConversations(query) {
            try {
                const response = await fetch(`/api.php?action=chat_search&query=${encodeURIComponent(query)}&user_id=${userId}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    renderConversationsList(data.conversations);
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }
        
        // ============================================
        // ЗАГРУЗКА СПИСКА ДИАЛОГОВ
        // ============================================
        
        async function loadConversationsList() {
            try {
                const response = await fetch(`/api.php?action=chat_history&user_id=${userId}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    renderConversationsList(data.conversations);
                }
            } catch (error) {
                console.error('Load conversations error:', error);
            }
        }
        
        function renderConversationsList(conversations) {
            const listContainer = document.getElementById('conversationsList');
            
            if (conversations.length === 0) {
                listContainer.innerHTML = `
                    <div class="text-center text-gray-500 mt-8 text-sm">
                        <p>Нет диалогов</p>
                    </div>
                `;
                return;
            }
            
            listContainer.innerHTML = conversations.map(conv => `
                <div 
                    class="conversation-item p-3 rounded-lg mb-1 ${conv.id == currentConversationId ? 'active' : ''}"
                    data-conv-id="${conv.id}"
                    data-conversation-id="${conv.conversation_id || ''}">
                    
                    <div class="font-medium text-sm text-gray-900 truncate">
                        ${escapeHtml(conv.display_title)}
                    </div>
                    
                    ${conv.last_message ? `
                        <div class="text-xs text-gray-500 truncate mt-1">
                            ${escapeHtml(conv.last_message.substring(0, 50))}...
                        </div>
                    ` : ''}
                    
                    <div class="text-xs text-gray-400 mt-1">
                        ${formatDate(conv.started_at)}
                    </div>
                </div>
            `).join('');
        }
        
        // ============================================
        // ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
        // ============================================
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // ============================================
        // ОБРАБОТКА ИСТОРИИ БРАУЗЕРА
        // ============================================
        
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const convId = urlParams.get('conv');
            
            if (convId) {
                currentConversationId = convId;
                loadConversation(convId);
            } else {
                newChatBtn.click();
            }
        });
    </script>
</body>
</html>
