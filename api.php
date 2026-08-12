<?php
/**
 * ИТРОНИК API - С LIBRECHAT АГЕНТАМИ
 */

// Принудительное логирование для диагностики
file_put_contents(__DIR__ . '/logs/startup.log', date('Y-m-d H:i:s') . " api.php started\n", FILE_APPEND);

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


// ============= БАЗОВАЯ ЗАЩИТА =============

// 1. Защита от прямого доступа к файлу
if (empty($_GET['action']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    die('Access Denied');
}

// 2. Простой rate limiting (без внешних зависимостей)
function simpleRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $cacheFile = sys_get_temp_dir() . '/rate_' . md5($ip) . '.txt';
    
    $now = time();
    $window = 60; // 60 секунд
    $maxRequests = 100; // 100 запросов в минуту
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        
        // Очищаем старые записи
        $data = array_filter($data, function($ts) use ($now, $window) {
            return ($now - $ts) < $window;
        });
        
        // Проверяем лимит
        if (count($data) >= $maxRequests) {
            http_response_code(429);
            header('Retry-After: ' . $window);
            die(json_encode(['error' => 'Too many requests. Please try again later.']));
        }
        
        $data[] = $now;
    } else {
        $data = [$now];
    }
    
    file_put_contents($cacheFile, json_encode($data));
}

simpleRateLimit(); // Запускаем проверку

// 3. Базовая санитизация входных данных
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ⚠️ НЕ САНИТИЗИРУЕМ POST для wizard эндпоинтов
$action = $_GET['action'] ?? '';
if (!in_array($action, [
    'wizard_update_prompt', 
    'wizard_save_prompt', 
    'wizard_generate_prompt', 
    'wizard_test_itronik',
    'wizard_complete'  // ← ДОБАВЬ ЭТО!
])) {
    $_GET = sanitizeInput($_GET);
    $_POST = sanitizeInput($_POST);
} else {
    // Только GET санитизируем
    $_GET = sanitizeInput($_GET);
}

// $_GET = sanitizeInput($_GET);
// $_POST = sanitizeInput($_POST);

// 4. Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// 5. CORS (только для твоего домена)
$allowedOrigins = [
    'https://iitronik.ru',
    'https://www.iitronik.ru',
    'http://localhost' // Для разработки
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://iitronik.ru");
}

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============= КОНФИГУРАЦИЯ =============
// require_once __DIR__ . '/config/database.php';
// require_once __DIR__ . '/config/ai_config.php';  // ← НОВЫЙ КОНФИГ С АГЕНТАМИ
// require_once __DIR__ . '/auth/config.php';
// После подключения конфигов
require_once __DIR__ . '/config/database.php';
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " database.php loaded\n", FILE_APPEND);

require_once __DIR__ . '/config/ai_config.php';
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " ai_config.php loaded\n", FILE_APPEND);

require_once __DIR__ . '/auth/config.php';
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " auth/config.php loaded\n", FILE_APPEND);

$db = getDB();
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " DB connection established\n", FILE_APPEND);

// После объявления функций (например, после функции handleWizardComplete)
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " Functions defined\n", FILE_APPEND);

// В начале обработчика `chat_send`
if ($action === 'chat_send') {
    file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " chat_send action triggered\n", FILE_APPEND);
    handleChatSend($db);
    exit;
}
// $db = getDB();
// $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

define('DEBUG_MODE', false); // Отключаем в продакшене

// Автоочистка событий (каждый 50-й запрос)
if (rand(1, 50) === 1) {
    cleanupOldEvents($db);
}

// Роутинг
$action = $_GET['action'] ?? '';
header('Content-Type: application/json; charset=utf-8');

// Логирование
function debugLog($message, $data = null) {
    if (!DEBUG_MODE) return;
    $log = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents('debug.log', $log . PHP_EOL, FILE_APPEND);
}

// ============= РОУТЫ =============

if ($action === 'widget_send') {
    handleWidgetMessage($db);
    exit;
}

if ($action === 'widget_poll') {
    pollMessages($db);
    exit;
}

if ($action === 'widget_history') {
    getWidgetHistory($db);
    exit;
}

if ($action === 'admin_conversations') {
    getConversations($db);
    exit;
}

if ($action === 'admin_messages') {
    getMessages($db);
    exit;
}

if ($action === 'admin_intercept') {
    interceptMessage($db);
    exit;
}

if ($action === 'admin_pause') {
    pauseConversation($db);
    exit;
}

if ($action === 'admin_resume') {
    resumeConversation($db);
    exit;
}

if ($action === 'admin_flag_user') {
    flagUser($db);
    exit;
}

if ($action === 'admin_rename') {
    renameConversation($db);
    exit;
}

if ($action === 'admin_add_tag') {
    addTag($db);
    exit;
}

if ($action === 'admin_remove_tag') {
    removeTag($db);
    exit;
}

if ($action === 'admin_get_tags') {
    getAllTags($db);
    exit;
}

if ($action === 'admin_stats') {
    getStats($db);
    exit;
}

if ($action === 'admin_send_message') {
    sendOperatorMessage($db);
    exit;
}

if ($action === 'admin_stream') {
    streamEvents($db);
    exit;
}

// ============= ЭНДПОИНТЫ ВИЗАРДА =============

if ($action === 'wizard_save_answer') {
    handleWizardSaveAnswer($db);
    exit;
}

if ($action === 'wizard_upload_file') {
    handleWizardUploadFile($db);
    exit;
}

if ($action === 'wizard_transcribe') {
    handleWizardTranscribe($db);
    exit;
}

if ($action === 'wizard_generate_prompt') {
    handleWizardGeneratePrompt($db);
    exit;
}

if ($action === 'wizard_save_prompt') {
    handleWizardSavePrompt($db);
    exit;
}

if ($action === 'wizard_test_itronik') {
    handleWizardTestItronik($db);
    exit;
}

if ($action === 'wizard_update_prompt') {
    handleWizardUpdatePrompt($db);
    exit;
}

if ($action === 'wizard_reset') {
    handleWizardReset($db);
    exit;
}

// ✅ ДОБАВЛЯЕМ ЗАВЕРШЕНИЕ ВИЗАРДА
if ($action === 'wizard_complete') {
    handleWizardComplete($db);
    exit;
}

// ============= ЭНДПОИНТЫ ЧАТА =============

if ($action === 'chat_send') {
    handleChatSend($db);
    exit;
}

if ($action === 'chat_messages') {
    handleChatMessages($db);
    exit;
}

if ($action === 'chat_history') {
    handleChatHistory($db);
    exit;
}

if ($action === 'chat_search') {
    handleChatSearch($db);
    exit;
}

// ============= ФУНКЦИИ ВИДЖЕТА =============

function getWidgetHistory($db) {
    try {
        $sessionId = $_GET['session_id'] ?? '';
        $agentId = $_GET['agent_id'] ?? '';
        
        if (empty($sessionId)) {
            echo json_encode(['messages' => []]);
            return;
        }
        
        $stmt = $db->prepare("SELECT id FROM users WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['messages' => []]);
            return;
        }
        
        $stmt = $db->prepare("
            SELECT id, conversation_id FROM conversations 
            WHERE user_id = ? AND agent_id = ? AND ended_at IS NULL
            ORDER BY started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user['id'], $agentId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            echo json_encode(['messages' => []]);
            return;
        }
        
        $stmt = $db->prepare("
            SELECT id, role, content, created_at
            FROM messages
            WHERE conversation_id = ? AND delivered_at IS NOT NULL
            ORDER BY created_at ASC
        ");
        $stmt->execute([$conversation['id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'conversation_id' => $conversation['conversation_id'],
            'messages' => $messages
        ]);
        
    } catch (Exception $e) {
        debugLog('Exception in getWidgetHistory', ['message' => $e->getMessage()]);
        echo json_encode(['messages' => [], 'error' => $e->getMessage()]);
    }
}

function pollMessages($db) {
    try {
        $sessionId = $_GET['session_id'] ?? '';
        $lastMessageId = (int)($_GET['last_message_id'] ?? 0);
        $agentId = $_GET['agent_id'] ?? '';
        
        if (empty($sessionId)) {
            echo json_encode(['messages' => []]);
            return;
        }
        
        $stmt = $db->prepare("SELECT id FROM users WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['messages' => []]);
            return;
        }
        
        $stmt = $db->prepare("
            SELECT id FROM conversations 
            WHERE user_id = ? AND agent_id = ? AND ended_at IS NULL
            ORDER BY started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user['id'], $agentId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            echo json_encode(['messages' => []]);
            return;
        }
        
        $stmt = $db->prepare("
            SELECT id, role, content, created_at
            FROM messages
            WHERE conversation_id = ? 
            AND id > ?
            AND delivered_at IS NOT NULL
            ORDER BY created_at ASC
        ");
        $stmt->execute([$conversation['id'], $lastMessageId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['messages' => $messages]);
        
    } catch (Exception $e) {
        debugLog('Exception in pollMessages', ['message' => $e->getMessage()]);
        echo json_encode(['messages' => [], 'error' => $e->getMessage()]);
    }
}

function handleWidgetMessage($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        debugLog('Widget message received', $input);
        
        $sessionId = $input['session_id'] ?? 'unknown';
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? null;
        $agentId = $input['agent_id'] ?? AGENT_TRUMP_WIDGET['id']; // ← По умолчанию Трамп
        $agentName = $input['agent_name'] ?? 'Итроник';
        
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Сообщение пустое']);
            return;
        }
        
        $user = getOrCreateUser($db, $sessionId);
        $conversation = getOrCreateConversation($db, $user['id'], $conversationId, $agentId, $agentName);
        
        $userMessageId = saveMessage($db, $conversation['id'], 'user', $message);
        markMessageDelivered($db, $userMessageId);
        
        addEvent($db, 'new_message', [
            'conversation_id' => $conversation['id'],
            'user_id' => $user['id'],
            'message' => $message,
            'agent_name' => $agentName
        ]);
        
        if ($conversation['status'] === 'paused' || !$conversation['agent_enabled']) {
            addEvent($db, 'manual_response_required', [
                'conversation_id' => $conversation['id'],
                'message' => $message,
                'agent_name' => $agentName
            ]);
            
            echo json_encode([
                'status' => 'pending',
                'response' => 'Оператор скоро ответит вам.',
                'conversation_id' => $conversation['conversation_id']
            ]);
            return;
        }
        
        // ← ИСПОЛЬЗУЕМ LIBRECHAT АГЕНТА
        $messages = [['role' => 'user', 'content' => $message]];
        $result = callLibreChatAgent($agentId, $messages, $conversation['conversation_id']);
        
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
            return;
        }
        
        $aiMessage = $result['response'];
        
        $messageId = saveMessage($db, $conversation['id'], 'assistant', $aiMessage, [
            'original_ai_response' => $aiMessage,
            'pending_review' => 0
        ]);
        
        markMessageDelivered($db, $messageId);
        
        addEvent($db, 'ai_response_sent', [
            'message_id' => $messageId,
            'conversation_id' => $conversation['id'],
            'content' => $aiMessage,
            'agent_name' => $agentName
        ]);
        
        echo json_encode([
            'status' => 'success',
            'response' => $aiMessage,
            'conversation_id' => $result['conversation_id'],
            'message_id' => $messageId
        ]);
        
    } catch (Exception $e) {
        debugLog('Exception in handleWidgetMessage', [
            'message' => $e->getMessage()
        ]);
        
        http_response_code(500);
        echo json_encode(['error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()]);
    }
}

function sendOperatorMessage($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $conversationId = $input['conversation_id'] ?? 0;
        $message = $input['message'] ?? '';
        
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Сообщение пустое']);
            return;
        }
        
        $messageId = saveMessage($db, $conversationId, 'assistant', $message);
        markMessageDelivered($db, $messageId);
        
        addEvent($db, 'operator_message_sent', [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'content' => $message
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message_id' => $messageId
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ============= ФУНКЦИИ АДМИНКИ =============

function getConversations($db) {
    $filterTag = $_GET['tag'] ?? '';
    
    $sql = "
        SELECT 
            c.id,
            c.user_id,
            c.conversation_id,
            c.agent_id,
            c.agent_name,
            c.custom_name,
            c.status,
            c.agent_enabled,
            c.started_at,
            u.session_id,
            u.name as user_name,
            u.is_flagged,
            COUNT(m.id) as message_count,
            MAX(m.created_at) as last_message_at
        FROM conversations c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE c.ended_at IS NULL
    ";
    
    if ($filterTag) {
        $sql .= " AND c.id IN (
            SELECT ct.conversation_id FROM conversation_tags ct
            JOIN tags t ON ct.tag_id = t.id
            WHERE t.name = ?
        )";
    }
    
    $sql .= " GROUP BY c.id ORDER BY last_message_at DESC LIMIT 100";
    
    if ($filterTag) {
        $stmt = $db->prepare($sql);
        $stmt->execute([$filterTag]);
    } else {
        $stmt = $db->query($sql);
    }
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($conversations)) {
        $ids = array_column($conversations, 'id');
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        // Получаем последние сообщения
        $stmt = $db->prepare("
            SELECT 
                m1.conversation_id,
                m1.content as last_message
            FROM messages m1
            INNER JOIN (
                SELECT conversation_id, MAX(created_at) as max_created
                FROM messages
                WHERE conversation_id IN ($placeholders)
                GROUP BY conversation_id
            ) m2 ON m1.conversation_id = m2.conversation_id 
                AND m1.created_at = m2.max_created
        ");
        $stmt->execute($ids);
        $lastMessages = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Получаем теги
        $stmt = $db->prepare("
            SELECT 
                ct.conversation_id,
                t.id,
                t.name,
                t.color
            FROM conversation_tags ct
            JOIN tags t ON ct.tag_id = t.id
            WHERE ct.conversation_id IN ($placeholders)
        ");
        $stmt->execute($ids);
        
        $tagsByConv = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $convId = $row['conversation_id'];
            unset($row['conversation_id']);
            if (!isset($tagsByConv[$convId])) {
                $tagsByConv[$convId] = [];
            }
            $tagsByConv[$convId][] = $row;
        }
        
        foreach ($conversations as &$conv) {
            $conv['last_message'] = $lastMessages[$conv['id']] ?? null;
            $conv['parsed_tags'] = $tagsByConv[$conv['id']] ?? [];
        }
    }
    
    echo json_encode($conversations);
}

function getMessages($db) {
    $conversationId = $_GET['conversation_id'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT * FROM messages
        WHERE conversation_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversationId]);
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function interceptMessage($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $messageId = $input['message_id'] ?? 0;
    $editedContent = $input['edited_content'] ?? null;
    
    $stmt = $db->prepare("
        UPDATE messages 
        SET was_intercepted = 1,
            content = COALESCE(?, content),
            edited_response = ?,
            was_edited = CASE WHEN ? IS NOT NULL THEN 1 ELSE 0 END,
            delivered_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([$editedContent, $editedContent, $editedContent, $messageId]);
    
    echo json_encode(['status' => 'success']);
}

function pauseConversation($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = $input['conversation_id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE conversations SET status = 'paused', agent_enabled = 0 WHERE id = ?");
    $stmt->execute([$conversationId]);
    
    addEvent($db, 'conversation_paused', ['conversation_id' => $conversationId]);
    
    echo json_encode(['status' => 'success']);
}

function resumeConversation($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = $input['conversation_id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE conversations SET status = 'active', agent_enabled = 1 WHERE id = ?");
    $stmt->execute([$conversationId]);
    
    addEvent($db, 'conversation_resumed', ['conversation_id' => $conversationId]);
    
    echo json_encode(['status' => 'success']);
}

function flagUser($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? 0;
    $reason = $input['reason'] ?? '';
    
    $stmt = $db->prepare("UPDATE users SET is_flagged = 1, flag_reason = ? WHERE id = ?");
    $stmt->execute([$reason, $userId]);
    
    addEvent($db, 'user_flagged', ['user_id' => $userId, 'reason' => $reason]);
    
    echo json_encode(['status' => 'success']);
}

function renameConversation($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $conversationId = $input['conversation_id'] ?? 0;
        $newName = trim($input['new_name'] ?? '');
        
        if (empty($newName)) {
            http_response_code(400);
            echo json_encode(['error' => 'Имя не может быть пустым']);
            return;
        }
        
        $stmt = $db->prepare("UPDATE conversations SET custom_name = ? WHERE id = ?");
        $stmt->execute([$newName, $conversationId]);
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function addTag($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $conversationId = $input['conversation_id'] ?? 0;
        $tagName = trim($input['tag_name'] ?? '');
        
        if (empty($tagName)) {
            http_response_code(400);
            echo json_encode(['error' => 'Имя тега не может быть пустым']);
            return;
        }
        
        $stmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stmt->execute([$tagName]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tag) {
            $colors = ['#f44336', '#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#00BCD4'];
            $color = $colors[array_rand($colors)];
            
            $stmt = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stmt->execute([$tagName, $color]);
            $tagId = $db->lastInsertId();
        } else {
            $tagId = $tag['id'];
        }
        
        $stmt = $db->prepare("INSERT IGNORE INTO conversation_tags (conversation_id, tag_id) VALUES (?, ?)");
        $stmt->execute([$conversationId, $tagId]);
        
        echo json_encode(['status' => 'success', 'tag_id' => $tagId]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function removeTag($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $conversationId = $input['conversation_id'] ?? 0;
        $tagId = $input['tag_id'] ?? 0;
        
        $stmt = $db->prepare("DELETE FROM conversation_tags WHERE conversation_id = ? AND tag_id = ?");
        $stmt->execute([$conversationId, $tagId]);
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getAllTags($db) {
    $stmt = $db->query("SELECT * FROM tags ORDER BY name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getStats($db) {
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeConversations = $db->query("SELECT COUNT(*) FROM conversations WHERE status = 'active' AND ended_at IS NULL")->fetchColumn();
    $totalMessages = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $flaggedUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_flagged = 1")->fetchColumn();
    $aiResponses = $db->query("SELECT COUNT(*) FROM messages WHERE role = 'assistant'")->fetchColumn();
    
    echo json_encode([
        'total_users' => $totalUsers,
        'active_conversations' => $activeConversations,
        'total_messages' => $totalMessages,
        'flagged_users' => $flaggedUsers,
        'ai_responses' => $aiResponses
    ]);
}

function streamEvents($db) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    
    $lastEventId = $_GET['last_event_id'] ?? 0;
    $checkInterval = 5;
    $cleanupCounter = 0;
    
    while (true) {
        $stmt = $db->prepare("
            SELECT * FROM events 
            WHERE id > :last_id AND is_read = 0 
            ORDER BY id ASC 
            LIMIT 20
        ");
        $stmt->execute([':last_id' => $lastEventId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($events)) {
            foreach ($events as $event) {
                echo "id: {$event['id']}\n";
                echo "event: {$event['event_type']}\n";
                echo "data: {$event['data']}\n\n";
                
                $lastEventId = $event['id'];
            }
            
            $ids = implode(',', array_column($events, 'id'));
            $db->exec("UPDATE events SET is_read = 1 WHERE id IN ($ids)");
            
            ob_flush();
            flush();
        } else {
            echo ": heartbeat\n\n";
            ob_flush();
            flush();
        }
        
        $cleanupCounter++;
        if ($cleanupCounter >= 20) {
            cleanupOldEvents($db);
            $cleanupCounter = 0;
        }
        
        sleep($checkInterval);
        
        if (connection_aborted()) break;
    }
}
// ============= ЭНДПОИНТЫ ВИЗАРДА =============

if ($action === 'wizard_chat_question') {
    handleWizardChatQuestion($db);
    exit;
}
// ============= ФУНКЦИИ ВИЗАРДА =============
/**
 * Диалог с Wizard Assistant (Шаг 2 опросника)
 */
function handleWizardChatQuestion($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = $input['user_id'] ?? 0;
        $userMessage = $input['message'] ?? '';
        $questionNumber = $input['question_number'] ?? 1;
        $conversationHistory = $input['history'] ?? [];
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id required']);
            return;
        }
        
        // Формируем контекст для агента
        $messages = [];
        
        // Добавляем историю диалога
        foreach ($conversationHistory as $msg) {
            $messages[] = $msg;
        }
        
        // Если это первый вопрос - просим агента начать диалог
        if ($questionNumber === 1 && empty($userMessage)) {
            $messages[] = [
                'role' => 'user',
                'content' => 'Начни диалог. Задай первый вопрос о стиле общения пользователя.'
            ];
        } else {
            // Иначе передаём ответ пользователя
            $messages[] = [
                'role' => 'user',
                'content' => $userMessage
            ];
        }
        
        // Вызываем Wizard Assistant через сервис
$result = $GLOBALS['libreChatService']->callWizardAssistant(
    $messages,
    null,
    ['max_tokens' => 500, 'temperature' => 0.8]
);
        
        if (!$result['success']) {
            // Fallback: статичный вопрос
            $fallbackQuestions = [
                "Привет! Давай познакомимся. Как ты обычно общаешься с коллегами — формально или неформально?",
                "Используешь ли ты жаргон, сленг или профессиональные термины в работе?",
                "Ты предпочитаешь короткие ответы или развёрнутые объяснения?",
                "Есть ли слова или фразы, которые ты часто используешь?",
                "Есть ли темы или вопросы, которые тебя раздражают?",
                "Как ты обычно структурируешь мысли — списками, абзацами или свободно?",
                "Ты обращаешься к людям на 'ты' или 'вы'?",
                "Используешь ли эмодзи в рабочей переписке?",
                "Как бы ты описал свой стиль общения одним словом?",
                "Есть ли что-то ещё, что важно знать о твоём стиле общения?"
            ];
            
            $question = $fallbackQuestions[min($questionNumber - 1, 9)] ?? "Расскажи ещё что-нибудь о своём стиле.";
            
            echo json_encode([
                'status' => 'success',
                'question' => $question,
                'fallback' => true
            ]);
            return;
        }
        
        echo json_encode([
            'status' => 'success',
            'question' => $result['response']
        ]);
        
    } catch (Exception $e) {
        error_log('Wizard chat question error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
/**
 * Сохранение ответа на вопрос визарда
 */
function handleWizardSaveAnswer($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? 0;
    $questionId = $input['question_id'] ?? 0;
    $answerText = $input['answer_text'] ?? '';
    
    if (!$userId || !$questionId || !$answerText) {
        http_response_code(400);
        echo json_encode(['error' => 'Неполные данные']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO wizard_answers (user_id, question_id, answer_text) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $questionId, $answerText]);
        
        echo json_encode([
            'status' => 'success',
            'id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
/**
 * Сброс визарда (для перенастройки итроника)
 */
function handleWizardReset($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['user_id'] ?? 0;
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id required']);
            return;
        }
        
        error_log('=== Resetting wizard for user_id=' . $userId . ' ===');
        
        // 1. Сбрасываем флаг wizard_completed и удаляем system_prompt
        $stmt = $db->prepare("
            UPDATE users 
            SET wizard_completed = 0, 
                system_prompt = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        error_log('User wizard_completed reset: ' . $stmt->rowCount() . ' rows');
        
        // 2. Удаляем ответы на опросник
        $stmt = $db->prepare("DELETE FROM wizard_answers WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        error_log('Wizard answers deleted: ' . $stmt->rowCount() . ' rows');
        
        // 3. Удаляем загруженные файлы из БД
        $stmt = $db->prepare("SELECT file_path FROM uploaded_files WHERE user_id = ?");
        $stmt->execute([$userId]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Удаляем файлы с диска
        foreach ($files as $filePath) {
            $fullPath = __DIR__ . $filePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
                error_log('Deleted file: ' . $fullPath);
            }
        }
        
        // Удаляем записи из БД
        $stmt = $db->prepare("DELETE FROM uploaded_files WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        error_log('Uploaded files deleted: ' . $stmt->rowCount() . ' rows');
        
        // 4. Очищаем сессию (чтобы обновились данные пользователя)
        if (isset($_SESSION['user_data'])) {
            unset($_SESSION['user_data']);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Wizard reset successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Wizard reset error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
/**
 * Загрузка файла пользователем
 */
function handleWizardUploadFile($db) {
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Файл не отправлен']);
        return;
    }
    
    $userId = $_POST['user_id'] ?? 0;
    $file = $_FILES['file'];
    
    // Валидация
    $allowedTypes = ['txt', 'docx', 'pdf', 'mp3', 'mp4', 'wav'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Недопустимый тип файла']);
        return;
    }
    
    if ($file['size'] > 100 * 1024 * 1024) { // 100 МБ
        http_response_code(400);
        echo json_encode(['error' => 'Файл слишком большой (макс. 100 МБ)']);
        return;
    }
    
    // Создаём папку для пользователя
    $uploadDir = __DIR__ . "/uploads/{$userId}/materials/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Генерируем безопасное имя файла
    $safeFileName = uniqid() . '_' . basename($file['name']);
    $filePath = $uploadDir . $safeFileName;
    
    // Перемещаем файл
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка загрузки файла']);
        return;
    }
    
    // Сохраняем в БД
    try {
        $fileType = in_array($ext, ['mp3', 'mp4', 'wav']) ? 'audio' : 'text';
        
        $stmt = $db->prepare("
            INSERT INTO uploaded_files (user_id, file_path, file_type, file_size) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            "/uploads/{$userId}/materials/{$safeFileName}",
            $fileType,
            $file['size']
        ]);
        
        echo json_encode([
            'status' => 'success',
            'id' => $db->lastInsertId(),
            'file_name' => $safeFileName,
            'file_size' => $file['size']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Транскрибация аудио файлов через Whisper API
 */
function handleWizardTranscribe($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? 0;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id required']);
        return;
    }
    
    try {
        // Получаем аудио файлы пользователя
        $stmt = $db->prepare("
            SELECT * FROM uploaded_files 
            WHERE user_id = ? AND file_type = 'audio' AND transcription_text IS NULL
        ");
        $stmt->execute([$userId]);
        $audioFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $transcribedCount = 0;
        
        foreach ($audioFiles as $file) {
            $filePath = __DIR__ . $file['file_path'];
            
            if (!file_exists($filePath)) {
                continue;
            }
            
            // Транскрибация через Whisper API
            $transcription = transcribeAudioFile($filePath);
            
            if ($transcription) {
                // Сохраняем результат
                $stmt = $db->prepare("
                    UPDATE uploaded_files 
                    SET transcription_text = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$transcription, $file['id']]);
                $transcribedCount++;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'transcribed' => $transcribedCount
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Транскрибация одного аудио файла через Whisper API
 */
function transcribeAudioFile($filePath) {
    $apiKey = getenv('OPENAI_API_KEY') ?: 'your-openai-api-key'; // ⚠️ Добавь в .env
    
    if ($apiKey === 'your-openai-api-key') {
        // Заглушка для тестирования
        return "[Транскрипция отключена. Добавьте OPENAI_API_KEY для работы]";
    }
    
    try {
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        
        $cfile = new CURLFile($filePath);
        $postData = [
            'file' => $cfile,
            'model' => 'whisper-1'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 120
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['text'] ?? '';
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log('Whisper API error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Генерация системного промпта через LibreChat (TOV_Creator агент)
 */
function handleWizardGeneratePrompt($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? 0;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id required']);
        return;
    }
    
    try {
        error_log('=== Генерация промпта для user_id=' . $userId . ' ===');
        
        // 1. Собираем контекст пользователя
        $context = collectUserContext($db, $userId);
        
        // 2. Формируем запрос для анализа
        $analysisRequest = buildAnalysisRequest($context);
        
        // 3. Отправляем в TOV_Creator агент через LibreChat
        $messages = [
            ['role' => 'user', 'content' => $analysisRequest]
        ];
        
        $result = $GLOBALS['libreChatService']->callTovCreator(
    $messages,
    null,
    ['max_tokens' => 2000, 'temperature' => 0.7]
);
        
        if (!$result['success']) {
            error_log('TOV_Creator error: ' . $result['error']);
            
            // Fallback: генерируем базовый промпт
            $systemPrompt = generateFallbackPrompt($context);
            
            echo json_encode([
                'status' => 'success',
                'system_prompt' => $systemPrompt,
                'fallback' => true
            ]);
            return;
        }
        
        $systemPrompt = $result['response'];
        
        // Проверяем качество промпта
        if (strlen($systemPrompt) < 100) {
            error_log('Промпт слишком короткий, используем fallback');
            $systemPrompt = generateFallbackPrompt($context);
        }
        
        error_log('Промпт сгенерирован успешно: ' . strlen($systemPrompt) . ' символов');
        
        echo json_encode([
            'status' => 'success',
            'system_prompt' => $systemPrompt
        ]);
        
    } catch (Exception $e) {
        error_log('Ошибка handleWizardGeneratePrompt: ' . $e->getMessage());
        
        // В случае ошибки возвращаем fallback промпт
        $context = collectUserContext($db, $userId);
        $systemPrompt = generateFallbackPrompt($context);
        
        echo json_encode([
            'status' => 'success',
            'system_prompt' => $systemPrompt,
            'fallback' => true
        ]);
    }
}

/**
 * Сбор контекста пользователя для генерации промпта
 */
function collectUserContext($db, $userId) {
    $context = [];
    
    // 1. Ответы на опросник
    $stmt = $db->prepare("
        SELECT question_id, answer_text 
        FROM wizard_answers 
        WHERE user_id = ? 
        ORDER BY question_id
    ");
    $stmt->execute([$userId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $context['answers'] = $answers;
    
    // 2. Транскрипты аудио
    $stmt = $db->prepare("
        SELECT transcription_text 
        FROM uploaded_files 
        WHERE user_id = ? AND transcription_text IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $transcripts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $context['transcripts'] = implode("\n\n", $transcripts);
    
    return $context;
}

/**
 * Формирование запроса для анализа
 */
function buildAnalysisRequest($context) {
    $request = "Ты — эксперт по анализу стиля общения.\n\n";
    
    $request .= "ЗАДАЧА: Проанализируй ответы пользователя и создай детальный системный промпт для AI, который будет общаться от лица этого человека.\n\n";
    
    $request .= "ВАЖНО:\n";
    $request .= "- НЕ упоминай имя 'Itronik Universal' в промпте\n";
    $request .= "- НЕ добавляй вводные фразы типа 'Вот системный промпт:'\n";
    $request .= "- Верни ТОЛЬКО текст промпта, готовый к использованию\n\n";
    
    $request .= "ЧТО ВКЛЮЧИТЬ В ПРОМПТ:\n";
    $request .= "1. Тон общения (формальный/неформальный/дружеский/деловой)\n";
    $request .= "2. Типичные слова и фразы, которые использует человек\n";
    $request .= "3. Структура ответов (короткие/развёрнутые, списки/абзацы)\n";
    $request .= "4. Обращение ('ты'/'вы')\n";
    $request .= "5. Использование эмодзи (да/нет/умеренно)\n";
    $request .= "6. Что человека раздражает или триггерит\n";
    $request .= "7. Экспертность и области знаний\n\n";
    
    $request .= "ФОРМАТ ПРОМПТА: 300-600 слов, от первого лица ('Ты — ...')\n\n";
    
    $request .= "=== МАТЕРИАЛЫ ДЛЯ АНАЛИЗА ===\n\n";
    
    // Добавляем ответы на вопросы
    if (!empty($context['answers'])) {
        $request .= "ОТВЕТЫ НА ОПРОСНИК:\n\n";
        foreach ($context['answers'] as $i => $answer) {
            $request .= ($i + 1) . ". " . $answer['answer_text'] . "\n\n";
        }
    }
    
    // Добавляем транскрипты (если есть)
    if (!empty($context['transcripts'])) {
        $request .= "\nТРАНСКРИПТЫ РЕЧИ:\n\n";
        $request .= $context['transcripts'];
    }
    
    $request .= "\n\n=== НАЧНИ ПРОМПТ С ФРАЗЫ 'Ты — персональный AI-ассистент...' ===";
    
    return $request;
}

/**
 * Базовый промпт (fallback)
 */
function generateFallbackPrompt($context) {
    $prompt = "Ты — персональный AI-ассистент пользователя. ";
    
    // Анализируем ответы
    $answers = array_column($context['answers'], 'answer_text');
    $fullText = implode(' ', $answers);
    
    // Простой анализ
    if (stripos($fullText, 'неформально') !== false || stripos($fullText, 'дружеский') !== false) {
        $prompt .= "Общайся неформально, дружелюбно. ";
    } else {
        $prompt .= "Общайся профессионально и вежливо. ";
    }
    
    if (stripos($fullText, 'ты') !== false && stripos($fullText, 'вы') === false) {
        $prompt .= "Обращайся на 'ты'. ";
    } else {
        $prompt .= "Обращайся на 'вы'. ";
    }
    
    if (stripos($fullText, 'эмодзи') !== false && stripos($fullText, 'да') !== false) {
        $prompt .= "Используй эмодзи для выразительности. ";
    }
    
    $prompt .= "Отвечай в стиле пользователя, копируя его манеру общения, лексику и структуру ответов.";
    
    return $prompt;
}

/**
 * Сохранение системного промпта (зашифрованного)
 */
function handleWizardSavePrompt($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? 0;
    $systemPrompt = $input['system_prompt'] ?? '';
    
    if (!$userId || !$systemPrompt) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    try {
        // Шифруем промпт
        $encryptedPrompt = encrypt($systemPrompt);
        
        // Сохраняем в БД
        $stmt = $db->prepare("UPDATE users SET system_prompt = ? WHERE id = ?");
        $stmt->execute([$encryptedPrompt, $userId]);
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Тестирование итроника (через Itronik_Universal агент)
 */
function handleWizardTestItronik($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = $input['user_id'] ?? 0;
        $message = $input['message'] ?? '';
        $history = $input['history'] ?? [];
        
        if (!$userId || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            return;
        }
        
        // Получаем системный промпт
        $stmt = $db->prepare("SELECT system_prompt FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $encryptedPrompt = $stmt->fetchColumn();
        
        if (!$encryptedPrompt) {
            http_response_code(404);
            echo json_encode(['error' => 'Промпт не найден']);
            return;
        }
        
        // Расшифровываем промпт
        $systemPrompt = decrypt($encryptedPrompt);
        
        if (empty($systemPrompt)) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка расшифровки промпта']);
            return;
        }
        
        // Формируем сообщения для модели (с системным промптом)
        $modelMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        
        // Добавляем историю (последние 10 сообщений)
        $historyCount = 0;
        foreach (array_reverse($history) as $msg) {
            if ($historyCount >= 10) break;
            $modelMessages[] = $msg;
            $historyCount++;
        }
        $modelMessages = array_reverse($modelMessages);
        
        // Добавляем текущее сообщение
        $modelMessages[] = ['role' => 'user', 'content' => $message];
        
        // Вызываем BotHub (как в основном чате)
        require_once __DIR__ . '/config/ai_bothub_config.php';
        
        $result = callBothubOpenAI($modelMessages, [
            'model' => 'deepseek-chat',
            'temperature' => 0.7,
            'max_tokens' => 1000
        ]);
        
        if (!$result['success']) {
            throw new Exception($result['error']);
        }
        
        echo json_encode([
            'status' => 'success',
            'response' => $result['response']
        ]);
        
    } catch (Exception $e) {
        error_log('Wizard test itronik error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Обновление промпта на основе фидбека (через TOV_Creator)
 */
function handleWizardUpdatePrompt($db) {
    try {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }
        
        $userId = $input['user_id'] ?? 0;
        $feedback = $input['feedback'] ?? '';
        $currentPrompt = $input['current_prompt'] ?? '';
        
        error_log('Update prompt request: user_id=' . $userId . ', feedback_len=' . strlen($feedback));
        
        if (!$userId || !$feedback) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data: user_id or feedback missing']);
            return;
        }
        
        // Проверяем, что текущий промпт не пустой
        if (empty($currentPrompt) || strlen($currentPrompt) < 50) {
            error_log('Current prompt is empty or too short, trying to get from DB');
            $stmt = $db->prepare("SELECT system_prompt FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $encrypted = $stmt->fetchColumn();
            if ($encrypted) {
                $currentPrompt = decrypt($encrypted);
                error_log('Retrieved prompt from DB, length=' . strlen($currentPrompt));
            }
            if (empty($currentPrompt) || strlen($currentPrompt) < 50) {
                throw new Exception('Системный промпт не найден. Пожалуйста, создайте Итроника заново.');
            }
        }
        
        // Формируем запрос на обновление
        $updateRequest = "Вот текущий системный промпт:\n\n---\n$currentPrompt\n---\n\n";
        $updateRequest .= "Пользователь хочет изменить следующее:\n\n$feedback\n\n";
        $updateRequest .= "ЗАДАЧА: Обновите системный промпт с учётом правок пользователя.\n\n";
        $updateRequest .= "ВАЖНО:\n";
        $updateRequest .= "- НЕ добавляй вводные фразы\n";
        $updateRequest .= "- Верни ТОЛЬКО обновлённый промпт\n";
        $updateRequest .= "- Сохрани общий стиль, но учти правки\n";
        $updateRequest .= "- Сохрани имя пользователя в промпте\n";
        
        $messages = [
            ['role' => 'user', 'content' => $updateRequest]
        ];
        
        error_log('Calling TOV_Creator for prompt update...');
        
        // Используем TOV_Creator для обновления (исправлено с AGENT_ITRONIK_UNIVERSAL на AGENT_TOV_CREATOR)
        $result = callLibreChatAgent(
            AGENT_TOV_CREATOR['id'], // ← ИСПРАВЛЕНО
            $messages,
            null,
            ['max_tokens' => 2000, 'temperature' => 0.7]
        );
        
        if (!$result['success']) {
            error_log('TOV_Creator update error: ' . $result['error']);
            throw new Exception('Ошибка генерации: ' . $result['error']);
        }
        
        $updatedPrompt = $result['response'];
        
        // Проверяем качество промпта
        if (strlen($updatedPrompt) < 100) {
            error_log('Updated prompt too short (' . strlen($updatedPrompt) . ' chars), using current prompt');
            $updatedPrompt = $currentPrompt;
        }
        
        error_log('Prompt updated successfully: ' . strlen($updatedPrompt) . ' characters');
        
        // Сохраняем обновлённый промпт
        $encryptedPrompt = encrypt($updatedPrompt);
        $stmt = $db->prepare("UPDATE users SET system_prompt = ? WHERE id = ?");
        $stmt->execute([$encryptedPrompt, $userId]);
        
        echo json_encode([
            'status' => 'success',
            'system_prompt' => $updatedPrompt
        ]);
        
    } catch (Exception $e) {
        error_log('Wizard update prompt error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Завершение визарда
 */

function handleWizardComplete($db) {
	   // Очищаем все буферы вывода перед отправкой JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    // Получаем raw input БЕЗ санитизации
    $rawInput = file_get_contents('php://input');
    
    error_log('=== handleWizardComplete called ===');
    error_log('Raw input: ' . $rawInput);
    
    if (empty($rawInput)) {
        error_log('Empty input received');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'No data received']);
        exit; // ← ВАЖНО!
    }
    
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit; // ← ВАЖНО!
    }
    
    $userId = $input['user_id'] ?? 0;
    
    if (!$userId) {
        error_log('No user_id provided');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'user_id required']);
        exit; // ← ВАЖНО!
    }
    
    try {
        error_log('Completing wizard for user_id=' . $userId);
        
        // Устанавливаем флаг завершения визарда
        $stmt = $db->prepare("UPDATE users SET wizard_completed = 1 WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if (!$result) {
            throw new Exception('Failed to update user');
        }
        
        $affectedRows = $stmt->rowCount();
        error_log('Update result: success, affected rows: ' . $affectedRows);
        
        // Проверяем что обновилось
        $stmt = $db->prepare("SELECT wizard_completed FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $wizardCompleted = $stmt->fetchColumn();
        
        error_log('Wizard_completed after update: ' . $wizardCompleted);
        
        if ($wizardCompleted != 1) {
            throw new Exception('Wizard completion flag not set correctly');
        }
        
        // ОБЯЗАТЕЛЬНО возвращаем JSON
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'wizard_completed' => true
        ]);
        exit; // ← ВАЖНО! Останавливаем выполнение
        
    } catch (Exception $e) {
    file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    error_log('EXCEPTION in handleChatSend: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
}

// ============= ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ =============

function generateUserName() {
    $animals = ['Лиса', 'Волк', 'Медведь', 'Олень', 'Заяц', 'Енот', 'Белка', 'Сова', 'Орёл', 'Кот'];
    $colors = ['Рыжая', 'Серый', 'Бурый', 'Белый', 'Чёрный', 'Золотой', 'Серебряный', 'Пятнистый', 'Полосатый'];
    
    $animal = $animals[array_rand($animals)];
    $color = $colors[array_rand($colors)];
    
    return $color . ' ' . $animal;
}

function getOrCreateUser($db, $sessionId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $name = generateUserName();
        $stmt = $db->prepare("INSERT INTO users (session_id, name) VALUES (?, ?)");
        $stmt->execute([$sessionId, $name]);
        return getOrCreateUser($db, $sessionId);
    }
    
    $stmt = $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    return $user;
}

function getOrCreateConversation($db, $userId, $conversationId = null, $agentId = null, $agentName = null) {
    if ($conversationId && strpos($conversationId, 'conv_') === 0) {
        $stmt = $db->prepare("SELECT * FROM conversations WHERE conversation_id = ?");
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conv) return $conv;
    }
    
    if ($agentId) {
        $stmt = $db->prepare("
            SELECT * FROM conversations 
            WHERE user_id = ? AND agent_id = ? AND ended_at IS NULL
            ORDER BY started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $agentId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conv) return $conv;
    }
    
    $newConvId = 'conv_' . uniqid();
    $stmt = $db->prepare("
        INSERT INTO conversations (user_id, conversation_id, agent_id, agent_name) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $newConvId, $agentId, $agentName]);
    
    addEvent($db, 'new_conversation', [
        'user_id' => $userId,
        'agent_id' => $agentId,
        'agent_name' => $agentName
    ]);
    
    return getOrCreateConversation($db, $userId, $newConvId, $agentId, $agentName);
}

function saveMessage($db, $conversationId, $role, $content, $extra = []) {
    $stmt = $db->prepare("
        INSERT INTO messages (conversation_id, role, content, original_ai_response, pending_review)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $conversationId,
        $role,
        $content,
        $extra['original_ai_response'] ?? null,
        $extra['pending_review'] ?? 0
    ]);
    
    return $db->lastInsertId();
}

function markMessageDelivered($db, $messageId) {
    $stmt = $db->prepare("UPDATE messages SET delivered_at = CURRENT_TIMESTAMP, pending_review = 0 WHERE id = ?");
    $stmt->execute([$messageId]);
}

function addEvent($db, $eventType, $data) {
    $stmt = $db->prepare("INSERT INTO events (event_type, data) VALUES (?, ?)");
    $stmt->execute([$eventType, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

function cleanupOldEvents($db) {
    try {
        // Удаляем прочитанные старше 1 часа
        $db->exec("DELETE FROM events WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        
        // Удаляем непрочитанные старше 24 часов
        $db->exec("DELETE FROM events WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    } catch (Exception $e) {
        debugLog('Cleanup error', $e->getMessage());
    }
}

// ============================================
// ФУНКЦИИ ДЛЯ ЧАТА
// ============================================

function handleChatSend($db) {
    // 1. Проверка авторизации — user_id берём из сессии, а не из запроса
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
        return;
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $userId = $currentUser['id']; // ← Из сессии, а не из запроса!
        $conversationId = $input['conversation_id'] ?? null;
        $libreChatConvId = $input['librechat_conv_id'] ?? null;
        $message = trim($input['message'] ?? '');
        $history = $input['history'] ?? [];

        // Проверяем message
        if (!$message) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Message is empty']);
            ob_end_flush();
            return;
        }

        error_log("handleChatSend: user_id={$userId}, conv_id={$conversationId}");

        // 1. Получаем системный промпт
        $stmt = $db->prepare("SELECT system_prompt FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $encryptedPrompt = $stmt->fetchColumn();

        // Расшифровываем промпт
        file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " before decrypt\n", FILE_APPEND);
        if (!function_exists('decrypt')) {
            file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " ERROR: decrypt function not found\n", FILE_APPEND);
            $systemPrompt = "Ты — полезный ассистент. Отвечай кратко и по делу.";
        } else {
            try {
                $systemPrompt = decrypt($encryptedPrompt);
                file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " decrypt succeeded, length=" . strlen($systemPrompt) . "\n", FILE_APPEND);
                if (empty($systemPrompt)) {
                    file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " decrypted prompt is empty, using fallback\n", FILE_APPEND);
                    $systemPrompt = "Ты — полезный ассистент. Отвечай кратко и по делу.";
                }
            } catch (Exception $e) {
                file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " decrypt exception: " . $e->getMessage() . "\n", FILE_APPEND);
                $systemPrompt = "Ты — полезный ассистент. Отвечай кратко и по делу.";
            }
        }
        file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " system prompt length: " . strlen($systemPrompt) . "\n", FILE_APPEND);     
// 2. Формируем сообщения для LibreChat
$messages = [];

// Добавляем историю (все сообщения)
foreach ($history as $msg) {
    $messages[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

// Добавляем текущее сообщение
$messages[] = ['role' => 'user', 'content' => $message];
        
/// 3. Вызываем модель через BotHub OpenAI-шлюз
require_once __DIR__ . '/config/ai_bothub_config.php';
error_log("handleChatSend: calling BotHub OpenAI");

// Формируем сообщения для модели (с системным промптом)
$modelMessages = [
    ['role' => 'system', 'content' => $systemPrompt]
];

// Добавляем историю (все сообщения)
foreach ($history as $msg) {
    $modelMessages[] = $msg;
}

// Добавляем текущее сообщение
$modelMessages[] = ['role' => 'user', 'content' => $message];

$result = callBothubOpenAI($modelMessages, [
    'model' => 'deepseek-chat',
    'temperature' => 0.7,
    'max_tokens' => 2000
]);

file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " BotHub returned, success=" . ($result['success'] ? 'true' : 'false') . "\n", FILE_APPEND);
if (!$result['success']) {
    error_log("handleChatSend: BotHub error: " . $result['error']);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'AI service error: ' . $result['error']
    ]);
    ob_end_flush();
    return;
}

$aiResponse = $result['response'];
$newLibreChatConvId = $result['conversation_id'] ?? null;
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " AI response received, length=" . strlen($aiResponse) . ", newConvId=" . ($newLibreChatConvId ?? 'NULL') . "\n", FILE_APPEND);

// 4. Создаём/обновляем диалог в БД
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " Step 4: creating/updating conversation, conversationId={$conversationId}\n", FILE_APPEND);

if (!$conversationId) {
    // Новый диалог
    $convIdForDB = $newLibreChatConvId ?: null;
    $stmt = $db->prepare("
        INSERT INTO conversations 
        (user_id, conversation_id, agent_id, agent_name, title, started_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $convIdForDB,
        AGENT_ITRONIK_UNIVERSAL['id'],
        'Мой Итроник',
        'Новый диалог'
    ]);
    $conversationId = $db->lastInsertId();
    file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " created new conversation id={$conversationId}\n", FILE_APPEND);
} else {
    // Существующий диалог — просто обновляем updated_at
    // Не обновляем conversation_id, чтобы не было конфликтов
    $stmt = $db->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " updated timestamp for conversation {$conversationId}, rows affected: " . $stmt->rowCount() . "\n", FILE_APPEND);
}

// 5. Сохраняем сообщения
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " Step 5: saving messages for conversation_id={$conversationId}\n", FILE_APPEND);
$stmt = $db->prepare("
    INSERT INTO messages (conversation_id, role, content, created_at) 
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$conversationId, 'user', $message]);
$stmt->execute([$conversationId, 'assistant', $aiResponse]);
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " messages saved to DB\n", FILE_APPEND);       
        // 6. Генерируем название (если первое сообщение)
        $dialogTitle = null;
        if (empty($history)) {
            try {
                $titleResult = generateConversationTitle($message, $aiResponse);
                if ($titleResult) {
                    $dialogTitle = $titleResult;
                    $stmt = $db->prepare("UPDATE conversations SET title = ? WHERE id = ?");
                    $stmt->execute([$dialogTitle, $conversationId]);
                    error_log("handleChatSend: generated title: " . $dialogTitle);
                }
            } catch (Exception $e) {
                error_log("handleChatSend: title generation failed: " . $e->getMessage());
            }
        }
        
// 7. Возвращаем ответ
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " about to return response, aiResponse length=" . strlen($aiResponse) . "\n", FILE_APPEND);
echo json_encode([
    'status' => 'success',
    'response' => $aiResponse,
    'conversation_id' => $conversationId,
    'librechat_conv_id' => $newLibreChatConvId,
    'title' => $dialogTitle
]);
file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " response sent\n", FILE_APPEND);
        
    } catch (Exception $e) {
        error_log('EXCEPTION in handleChatSend: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => 'Internal server error: ' . $e->getMessage()
        ]);
    }
    
    ob_end_flush();
}

function handleChatMessages($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // 1. Проверка авторизации
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
        return;
    }
    
    try {
        $conversationId = intval($_GET['conversation_id'] ?? 0);
        if (!$conversationId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Conversation ID required']);
            return;
        }

        // 2. Получаем диалог С ПРОВЕРКОЙ ВЛАДЕЛЬЦА
        $stmt = $db->prepare("
            SELECT
                id,
                user_id,
                conversation_id as librechat_conv_id,
                title,
                custom_name
            FROM conversations
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$conversationId, $currentUser['id']]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conversation) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'Conversation not found']);
            return;
        }

        $stmt = $db->prepare("
            SELECT role, content, created_at
            FROM messages
            WHERE conversation_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'conversation_id' => $conversationId,
            'librechat_conv_id' => $conversation['librechat_conv_id'],
            'title' => $conversation['custom_name'] ?? $conversation['title'] ?? 'Диалог',
            'messages' => $messages
        ]);
    } catch (Exception $e) {
        error_log('EXCEPTION in handleChatMessages: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Internal error']);
    }
}

function handleChatHistory($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Проверка авторизации — user_id берём из сессии, а не из GET!
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
        return;
    }
    
    try {
        // ← Из сессии, не из запроса!
        $userId = $currentUser['id'];

        $stmt = $db->prepare("
            SELECT
                c.id,
                c.conversation_id,
                COALESCE(c.custom_name, c.title, 'Новый диалог') as display_title,
                c.started_at,
                (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM conversations c
            WHERE c.user_id = ?
            ORDER BY c.started_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'conversations' => $conversations
        ]);
    } catch (Exception $e) {
        error_log('EXCEPTION in handleChatHistory: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Internal error']);
    }
}

function handleChatSearch($db) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Проверка авторизации — user_id берём из сессии
    requireLogin();
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
        return;
    }
    
    try {
        // ← Из сессии, не из запроса!
        $userId = $currentUser['id'];
        $query = trim($_GET['query'] ?? '');
        if (!$query) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Query required']);
            return;
        }

        $stmt = $db->prepare("
            SELECT DISTINCT
                c.id,
                c.conversation_id,
                COALESCE(c.custom_name, c.title, 'Новый диалог') as display_title,
                c.started_at,
                (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE c.user_id = ?
            AND (
                c.title LIKE ?
                OR c.custom_name LIKE ?
                OR m.content LIKE ?
            )
            GROUP BY c.id
            ORDER BY c.started_at DESC
            LIMIT 20
        ");
        $searchPattern = '%' . $query . '%';
        $stmt->execute([$userId, $searchPattern, $searchPattern, $searchPattern]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'conversations' => $conversations,
            'query' => $query
        ]);
    } catch (Exception $e) {
        error_log('EXCEPTION in handleChatSearch: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Internal error']);
    }
}

function generateConversationTitle($userMessage, $aiResponse) {
    try {
        require_once __DIR__ . '/config/ai_config.php';
        
        $prompt = "Создай короткое название (максимум 5 слов) для этого диалога на основе первых сообщений. Отвечай ТОЛЬКО названием, без кавычек и точек.\n\nПользователь: {$userMessage}\n\nАссистент: {$aiResponse}";
        
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
require_once __DIR__ . '/config/ai_bothub_config.php';
$result = callBothubOpenAI($messages, [
    'model' => 'deepseek-chat',
    'temperature' => 0.7,
    'max_tokens' => 50  // для короткого названия
]);
        
        if ($result['success']) {
            $title = trim($result['response']);
            $title = preg_replace('/^["\']|["\']$/', '', $title);
            $title = preg_replace('/\.$/', '', $title);
            if (mb_strlen($title) > 60) {
                $title = mb_substr($title, 0, 57) . '...';
            }
            return $title;
        }
        
    } catch (Exception $e) {
        error_log('Title generation error: ' . $e->getMessage());
    }
    
    return null;
}