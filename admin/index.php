<?php
require_once __DIR__ . '/config.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Итроник Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Marked.js для рендеринга Markdown -->
<script src="/assets/js/marked.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        .container { display: flex; height: 100vh; }
        
        .sidebar {
            width: 320px;
            background: #1a1a2e;
            color: white;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .sidebar h2 {
            font-size: 24px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
        }
        
        .stat-card h4 {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 5px;
        }
        
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
        }
        
        /* НОВОЕ: Фильтры по тегам */
        .tag-filters {
            margin-bottom: 15px;
        }
        
        .tag-filters h4 {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 8px;
        }
        
        .tag-filter-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .tag-filter {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.2s;
        }
        
        .tag-filter:hover {
            border-color: rgba(255,255,255,0.5);
        }
        
        .tag-filter.active {
            border-color: white;
            box-shadow: 0 0 8px rgba(255,255,255,0.3);
        }
        
        .tag-filter.clear-filter {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .conversation-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            background: #16213e;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .conversation-item:hover { background: #0f3460; }
        .conversation-item.active { background: linear-gradient(135deg, #667eea, #764ba2); }
        
        .conversation-item .user {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* НОВОЕ: Кнопка редактирования имени */
        .edit-name-btn {
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            cursor: pointer;
            padding: 2px 6px;
            font-size: 12px;
        }
        
        .edit-name-btn:hover {
            color: white;
        }
        
        .conversation-item .preview {
            font-size: 12px;
            opacity: 0.7;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .conversation-item .meta {
            font-size: 11px;
            opacity: 0.5;
            margin-top: 5px;
        }
        
        /* НОВОЕ: Теги на карточке */
        .conversation-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 8px;
        }
        
        .conversation-tag {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            position: relative;
        }
        
        .conversation-tag .remove-tag {
            margin-left: 4px;
            opacity: 0.7;
        }
        
        .conversation-tag:hover .remove-tag {
            opacity: 1;
        }
        
        .agent-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .agent-badge.itronik {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
        }
        
        .agent-badge.astronum {
            background: rgba(255, 0, 200, 0.2);
            color: #ff00c8;
        }
        
        .main {
            flex: 1;
            background: #f5f7fa;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* НОВОЕ: Кнопка добавления тега */
        .add-tag-btn {
            background: transparent;
            border: 1px dashed #ccc;
            color: #666;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .add-tag-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .chat-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-success { background: #4CAF50; color: white; }
        
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .message {
            display: flex;
            margin-bottom: 20px;
        }
        
        .message.user { justify-content: flex-end; }
        
        .message-bubble {
            max-width: 60%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .message.user .message-bubble {
            background: #667eea;
            color: white;
        }
        
        .message.assistant .message-bubble {
            background: white;
            color: #333;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* НОВОЕ: Стили для Markdown в сообщениях */
        .message-bubble h1, .message-bubble h2, .message-bubble h3, .message-bubble h4 {
            margin: 10px 0 8px 0;
            font-weight: 600;
        }
        
        .message-bubble h1 { font-size: 18px; }
        .message-bubble h2 { font-size: 16px; }
        .message-bubble h3 { font-size: 14px; }
        .message-bubble h4 { font-size: 13px; }
        
        .message-bubble p { margin: 8px 0; }
        
        .message-bubble ul, .message-bubble ol {
            margin: 8px 0;
            padding-left: 20px;
        }
        
        .message-bubble li { margin: 4px 0; }
        
        .message-bubble strong { font-weight: 600; }
        .message-bubble em { font-style: italic; }
        
        .message-bubble code {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .message-bubble pre {
            background: rgba(0,0,0,0.05);
            padding: 10px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 8px 0;
        }
        
        .message-bubble pre code {
            background: none;
            padding: 0;
        }
        
        .message-bubble a {
            color: #667eea;
            text-decoration: underline;
        }
        
        .message.user .message-bubble a {
            color: white;
        }
        
        .message-bubble blockquote {
            border-left: 3px solid rgba(0,0,0,0.2);
            padding-left: 12px;
            margin: 8px 0;
            opacity: 0.8;
        }
        
        .message-meta {
            font-size: 11px;
            opacity: 0.6;
            margin-top: 5px;
        }
        
        .manual-reply-form {
            background: white;
            border-top: 1px solid #e0e0e0;
            padding: 15px 20px;
            display: none;
        }
        
        .manual-reply-form.active {
            display: block;
        }
        
        .manual-reply-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .manual-reply-form .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        /* НОВОЕ: Модальное окно */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-bottom: 20px;
        }
        
        .modal-content input,
        .modal-content select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: 40vh; }
            .container { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>📊 Итроник</h2>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Выход
                </button>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Пользователей</h4>
                    <div class="value" id="totalUsers">0</div>
                </div>
                <div class="stat-card">
                    <h4>Активных</h4>
                    <div class="value" id="activeChats">0</div>
                </div>
                <div class="stat-card">
                    <h4>Сообщений</h4>
                    <div class="value" id="totalMessages">0</div>
                </div>
                <div class="stat-card">
                    <h4>Помеченных</h4>
                    <div class="value" id="flaggedUsers">0</div>
                </div>
            </div>
            
            <!-- НОВОЕ: Фильтры по тегам -->
            <div class="tag-filters">
                <h4>Фильтр по тегам</h4>
                <div class="tag-filter-list" id="tagFilterList">
                    <div class="tag-filter clear-filter active" onclick="filterByTag(null)">
                        Все диалоги
                    </div>
                </div>
            </div>
            
            <h3 style="margin: 20px 0 10px;">Диалоги</h3>
            <div class="conversation-list" id="conversationList">
                <p style="opacity: 0.5; text-align: center; padding: 20px;">Загрузка...</p>
            </div>
        </div>
        
        <div class="main">
            <div class="chat-header">
                <div>
                    <h3 id="chatTitle">Выберите диалог</h3>
                    <p id="chatSubtitle" style="font-size: 12px; opacity: 0.6;"></p>
                    
                    <!-- НОВОЕ: Теги диалога -->
                    <div id="chatTags" style="margin-top: 8px; display: none;">
                        <div class="conversation-tags" id="currentChatTags"></div>
                    </div>
                </div>
                <div class="chat-controls" id="chatControls" style="display: none;">
                    <button class="add-tag-btn" onclick="openAddTagModal()">
                        <i class="fas fa-tag"></i> Добавить тег
                    </button>
                    <button class="btn btn-danger" id="pauseBtn" onclick="toggleAI()">
                        <i class="fas fa-pause"></i> Остановить AI
                    </button>
                    <button class="btn btn-success" onclick="flagUser()">
                        <i class="fas fa-flag"></i> Пометить
                    </button>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>Выберите диалог из списка слева</p>
                </div>
            </div>
            
            <div class="manual-reply-form" id="manualReplyForm">
                <textarea id="manualReplyText" placeholder="Введите ваш ответ пользователю..."></textarea>
                <div class="form-actions">
                    <button class="btn btn-primary" onclick="sendManualReply()">
                        <i class="fas fa-paper-plane"></i> Отправить
                    </button>
                    <button class="btn" style="background: #ccc;" onclick="toggleAI()">
                        <i class="fas fa-play"></i> Вернуть AI
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- НОВОЕ: Модальное окно переименования -->
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <h3>Переименовать диалог</h3>
            <input type="text" id="newConversationName" placeholder="Новое имя диалога">
            <div class="modal-actions">
                <button class="btn" style="background: #ccc;" onclick="closeRenameModal()">Отмена</button>
                <button class="btn btn-primary" onclick="saveNewName()">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- НОВОЕ: Модальное окно добавления тега -->
    <div class="modal" id="addTagModal">
        <div class="modal-content">
            <h3>Добавить тег</h3>
            <select id="tagSelect">
                <option value="">Выберите тег...</option>
            </select>
            <input type="text" id="newTagName" placeholder="Или создайте новый тег">
            <div class="modal-actions">
                <button class="btn" style="background: #ccc;" onclick="closeAddTagModal()">Отмена</button>
                <button class="btn btn-primary" onclick="saveTag()">Добавить</button>
            </div>
        </div>
    </div>

    <audio id="notificationSound" src="/sounds/new-message.mp3" preload="auto"></audio>

    <script>
        // Настраиваем marked.js
        marked.setOptions({
            breaks: true,
            gfm: true
        });

        let currentConversation = null;
        let eventSource = null;
        let isAIPaused = false;
        let allTags = [];
        let currentFilter = null;

        function logout() {
            if (confirm('Вы уверены, что хотите выйти?')) {
                window.location.href = 'logout.php';
            }
        }

        // SSE
        function connectSSE() {
            eventSource = new EventSource('../api.php?action=admin_stream');
            
            eventSource.addEventListener('new_message', (e) => {
                const data = JSON.parse(e.data);
                playNotification();
                showBrowserNotification('Новое сообщение', data.message);
                loadConversations();
                
                if (currentConversation && currentConversation.id === data.conversation_id) {
                    loadMessages(currentConversation.id);
                }
            });
            
            eventSource.addEventListener('new_conversation', (e) => {
                playNotification();
                showBrowserNotification('Новый диалог', 'Пользователь начал чат');
                loadConversations();
            });
            
            eventSource.addEventListener('ai_response_sent', (e) => {
                if (currentConversation) {
                    loadMessages(currentConversation.id);
                }
            });
            
            eventSource.addEventListener('operator_message_sent', (e) => {
                if (currentConversation) {
                    loadMessages(currentConversation.id);
                }
            });
        }

        // Загрузить статистику
        async function loadStats() {
            try {
                const res = await fetch('../api.php?action=admin_stats');
                const stats = await res.json();
                
                document.getElementById('totalUsers').textContent = stats.total_users;
                document.getElementById('activeChats').textContent = stats.active_conversations;
                document.getElementById('totalMessages').textContent = stats.total_messages;
                document.getElementById('flaggedUsers').textContent = stats.flagged_users;
            } catch (err) {
                console.error('Failed to load stats:', err);
            }
        }

        // НОВАЯ: Загрузить все теги
        async function loadAllTags() {
            try {
                const res = await fetch('../api.php?action=admin_get_tags');
                allTags = await res.json();
                
                // Обновляем фильтры
                const filterList = document.getElementById('tagFilterList');
                const clearBtn = filterList.querySelector('.clear-filter');
                filterList.innerHTML = '';
                filterList.appendChild(clearBtn);
                
                allTags.forEach(tag => {
                    const tagEl = document.createElement('div');
                    tagEl.className = 'tag-filter';
                    tagEl.style.backgroundColor = tag.color;
                    tagEl.style.color = 'white';
                    tagEl.textContent = tag.name;
                    tagEl.onclick = () => filterByTag(tag.name);
                    filterList.appendChild(tagEl);
                });
                
                // Обновляем select в модалке
                const tagSelect = document.getElementById('tagSelect');
                tagSelect.innerHTML = '<option value="">Выберите тег...</option>';
                allTags.forEach(tag => {
                    const option = document.createElement('option');
                    option.value = tag.name;
                    option.textContent = tag.name;
                    tagSelect.appendChild(option);
                });
                
            } catch (err) {
                console.error('Failed to load tags:', err);
            }
        }

        // НОВАЯ: Фильтрация по тегу
        async function filterByTag(tagName) {
            currentFilter = tagName;
            
            // Обновляем активный фильтр
            document.querySelectorAll('.tag-filter').forEach(el => {
                el.classList.remove('active');
            });
            
            if (tagName === null) {
                document.querySelector('.clear-filter').classList.add('active');
            } else {
                Array.from(document.querySelectorAll('.tag-filter')).forEach(el => {
                    if (el.textContent === tagName) {
                        el.classList.add('active');
                    }
                });
            }
            
            loadConversations();
        }

        // Загрузить диалоги
        async function loadConversations() {
            try {
                const url = currentFilter 
                    ? `../api.php?action=admin_conversations&tag=${encodeURIComponent(currentFilter)}`
                    : '../api.php?action=admin_conversations';
                
                const res = await fetch(url);
                const conversations = await res.json();
                
                const list = document.getElementById('conversationList');
                
                if (conversations.length === 0) {
                    list.innerHTML = '<p style="opacity: 0.5; text-align: center; padding: 20px;">Нет диалогов</p>';
                    return;
                }
                
                list.innerHTML = conversations.map(c => {
                    const agentClass = c.agent_name?.includes('Астронум') ? 'astronum' : 'itronik';
                    const agentBadge = c.agent_name 
                        ? `<span class="agent-badge ${agentClass}">${c.agent_name}</span>`
                        : '';
                    
                    const displayName = c.custom_name || c.user_name || 'Пользователь ' + c.user_id;
                    
                    const tagsHtml = c.parsed_tags && c.parsed_tags.length > 0
                        ? `<div class="conversation-tags">
                            ${c.parsed_tags.map(tag => 
                                `<span class="conversation-tag" style="background-color: ${tag.color}; color: white;">
                                    ${tag.name}
                                </span>`
                            ).join('')}
                           </div>`
                        : '';
                    
                    return `
                        <div class="conversation-item ${currentConversation && currentConversation.id === c.id ? 'active' : ''}" 
                             onclick="selectConversation(${c.id})">
                            <div class="user">
                                ${c.is_flagged ? '🚩 ' : ''}
                                <span onclick="event.stopPropagation(); openRenameModal(${c.id}, '${displayName.replace(/'/g, "\\'")}')">
                                    ${displayName}
                                    <button class="edit-name-btn" title="Переименовать">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </span>
                                ${agentBadge}
                            </div>
                            <div class="preview">${c.last_message || 'Нет сообщений'}</div>
                            <div class="meta">
                                ${c.message_count} сообщ. • 
                                ${c.status === 'paused' ? '⏸️ На паузе' : '✅ Активен'}
                            </div>
                            ${tagsHtml}
                        </div>
                    `;
                }).join('');
            } catch (err) {
                console.error('Failed to load conversations:', err);
            }
        }

        // Выбрать диалог
        async function selectConversation(conversationId) {
            try {
                const res = await fetch('../api.php?action=admin_conversations');
                const conversations = await res.json();
                
                currentConversation = conversations.find(c => c.id === conversationId);
                
                if (!currentConversation) return;
                
                const agentInfo = currentConversation.agent_name 
                    ? ` (${currentConversation.agent_name})`
                    : '';
                
                const displayName = currentConversation.custom_name || 
                    currentConversation.user_name || 
                    'Пользователь ' + currentConversation.user_id;
                
                document.getElementById('chatTitle').textContent = displayName + agentInfo;
                document.getElementById('chatSubtitle').textContent = 
                    `${currentConversation.message_count} сообщений • ${currentConversation.status}`;
                
                // Показываем теги
                const chatTagsDiv = document.getElementById('chatTags');
                const currentChatTags = document.getElementById('currentChatTags');
                
                if (currentConversation.parsed_tags && currentConversation.parsed_tags.length > 0) {
                    chatTagsDiv.style.display = 'block';
                    currentChatTags.innerHTML = currentConversation.parsed_tags.map(tag => 
                        `<span class="conversation-tag" style="background-color: ${tag.color}; color: white;">
                            ${tag.name}
                            <span class="remove-tag" onclick="removeTag(${tag.id})">×</span>
                        </span>`
                    ).join('');
                } else {
                    chatTagsDiv.style.display = 'none';
                }
                
                document.getElementById('chatControls').style.display = 'flex';
                
                isAIPaused = (currentConversation.status === 'paused');
                updateAIState();
                
                loadMessages(conversationId);
                loadConversations();
            } catch (err) {
                console.error('Failed to select conversation:', err);
            }
        }

        // Загрузить сообщения
        async function loadMessages(conversationId) {
            try {
                const res = await fetch(`../api.php?action=admin_messages&conversation_id=${conversationId}`);
                const messages = await res.json();
                
                const container = document.getElementById('chatMessages');
                
                if (messages.length === 0) {
                    container.innerHTML = '<div class="empty-state"><p>Нет сообщений</p></div>';
                    return;
                }
                
                container.innerHTML = messages.map(m => {
                    let content;
                    if (m.role === 'assistant') {
                        // Рендерим Markdown для ответов AI
                        content = marked.parse(m.content);
                    } else {
                        content = m.content;
                    }
                    
                    return `
                        <div class="message ${m.role}">
                            <div class="message-bubble">
                                ${m.role === 'assistant' ? content : `<p>${content}</p>`}
                                ${m.was_edited ? '<div class="message-meta">✏️ Отредактировано</div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
                
                container.scrollTop = container.scrollHeight;
            } catch (err) {
                console.error('Failed to load messages:', err);
            }
        }

        // Переключение AI
        async function toggleAI() {
            if (!currentConversation) return;
            
            const action = isAIPaused ? 'admin_resume' : 'admin_pause';
            const confirmMsg = isAIPaused 
                ? 'Вернуть управление AI агенту?' 
                : 'Остановить AI? Вам придется отвечать вручную.';
            
            if (!confirm(confirmMsg)) return;
            
            try {
                await fetch(`../api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: currentConversation.id })
                });
                
                isAIPaused = !isAIPaused;
                updateAIState();
                loadConversations();
            } catch (err) {
                console.error('Failed to toggle AI:', err);
                alert('Ошибка: ' + err.message);
            }
        }

        function updateAIState() {
            const pauseBtn = document.getElementById('pauseBtn');
            const form = document.getElementById('manualReplyForm');
            
            if (isAIPaused) {
                pauseBtn.innerHTML = '<i class="fas fa-play"></i> Включить AI';
                pauseBtn.className = 'btn btn-success';
                form.classList.add('active');
            } else {
                pauseBtn.innerHTML = '<i class="fas fa-pause"></i> Остановить AI';
                pauseBtn.className = 'btn btn-danger';
                form.classList.remove('active');
            }
        }

        // Отправить сообщение от оператора
        async function sendManualReply() {
            const textarea = document.getElementById('manualReplyText');
            const message = textarea.value.trim();
            
            if (!message) {
                alert('Введите сообщение!');
                return;
            }
            
            if (!currentConversation) return;
            
            try {
                const res = await fetch('../api.php?action=admin_send_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: currentConversation.id,
                        message: message
                    })
                });
                
                const data = await res.json();
                
                if (data.status === 'success') {
                    textarea.value = '';
                    loadMessages(currentConversation.id);
                } else {
                    alert('Ошибка отправки: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Failed to send message:', err);
                alert('Ошибка: ' + err.message);
            }
        }

        // Пометить пользователя
        async function flagUser() {
            if (!currentConversation) return;
            
            const reason = prompt('Причина пометки:');
            if (!reason) return;
            
            try {
                await fetch('../api.php?action=admin_flag_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: currentConversation.user_id,
                        reason: reason
                    })
                });
                
                alert('Пользователь помечен!');
                loadConversations();
                loadStats();
            } catch (err) {
                console.error('Failed to flag user:', err);
            }
        }

        // НОВАЯ: Переименование диалога
        function openRenameModal(conversationId, currentName) {
            document.getElementById('newConversationName').value = currentName;
            document.getElementById('renameModal').classList.add('active');
        }

        function closeRenameModal() {
            document.getElementById('renameModal').classList.remove('active');
        }

        async function saveNewName() {
            const newName = document.getElementById('newConversationName').value.trim();
            
            if (!newName) {
                alert('Введите имя!');
                return;
            }
            
            if (!currentConversation) return;
            
            try {
                await fetch('../api.php?action=admin_rename', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: currentConversation.id,
                        new_name: newName
                    })
                });
                
                closeRenameModal();
                loadConversations();
                
                // Обновляем заголовок
                if (currentConversation) {
                    selectConversation(currentConversation.id);
                }
            } catch (err) {
                console.error('Failed to rename:', err);
                alert('Ошибка: ' + err.message);
            }
        }

        // НОВАЯ: Добавление тега
        function openAddTagModal() {
            if (!currentConversation) return;
            document.getElementById('addTagModal').classList.add('active');
        }

        function closeAddTagModal() {
            document.getElementById('addTagModal').classList.remove('active');
            document.getElementById('tagSelect').value = '';
            document.getElementById('newTagName').value = '';
        }

        async function saveTag() {
            const existingTag = document.getElementById('tagSelect').value;
            const newTag = document.getElementById('newTagName').value.trim();
            
            const tagName = newTag || existingTag;
            
            if (!tagName) {
                alert('Выберите или создайте тег!');
                return;
            }
            
            if (!currentConversation) return;
            
            try {
                await fetch('../api.php?action=admin_add_tag', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: currentConversation.id,
                        tag_name: tagName
                    })
                });
                
                closeAddTagModal();
                await loadAllTags();
                loadConversations();
                selectConversation(currentConversation.id);
            } catch (err) {
                console.error('Failed to add tag:', err);
                alert('Ошибка: ' + err.message);
            }
        }

        // НОВАЯ: Удаление тега
        async function removeTag(tagId) {
            if (!currentConversation) return;
            if (!confirm('Удалить этот тег?')) return;
            
            try {
                await fetch('../api.php?action=admin_remove_tag', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: currentConversation.id,
                        tag_id: tagId
                    })
                });
                
                loadConversations();
                selectConversation(currentConversation.id);
            } catch (err) {
                console.error('Failed to remove tag:', err);
            }
        }

        function playNotification() {
            const audio = document.getElementById('notificationSound');
            audio.play().catch(() => {});
        }

        function showBrowserNotification(title, body) {
            if (Notification.permission === 'granted') {
                new Notification(title, { body, icon: '/favicon.ico' });
            }
        }

        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Инициализация
        loadStats();
        loadAllTags();
        loadConversations();
        connectSSE();

        setInterval(() => {
            loadStats();
            if (!currentConversation) {
                loadConversations();
            }
        }, 30000);
    </script>
</body>
</html>
