<?php
/**
 * КОНФИГУРАЦИЯ AI АГЕНТОВ (LibreChat)
 * 
 * Все агенты работают через LibreChat API
 * Каждый агент имеет свою специализацию
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Загрузка переменных окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required(['LIBRECHAT_URL', 'LIBRECHAT_API_KEY']);

// ============= LibreChat API =============
define('LIBRECHAT_URL', $_ENV['LIBRECHAT_URL']);
define('LIBRECHAT_API_KEY', $_ENV['LIBRECHAT_API_KEY']);

// ============= АГЕНТЫ =============
// (без изменений, поскольку ID агентов - константы)
define('AGENT_TOV_CREATOR', [
    'id' => 'agent_EuB2m0H_rG6gVjs4MQLYH',
    'name' => 'TOV Creator',
    'description' => 'Эксперт по анализу стиля общения и созданию системных промптов'
]);

define('AGENT_ITRONIK_UNIVERSAL', [
    'id' => 'agent_zZUwnI1SSTTaA4YreeTxt',
    'name' => 'Itronik Universal',
    'description' => 'AI-двойник пользователя, работает с кастомным промптом'
]);

define('AGENT_WIZARD_ASSISTANT', [
    'id' => 'agent_Stw6G0rFh6Hxw_PzDXR6F',
    'name' => 'Wizard Assistant',
    'description' => 'Помощник для сбора информации о стиле пользователя'
]);

define('AGENT_TRUMP_WIDGET', [
    'id' => 'agent_KtAEbBRT3mHG_KpP-L8na',
    'name' => 'Trump Widget',
    'description' => 'Дональд Трамп для демонстрации возможностей'
]);

// ============= УЛУЧШЕННЫЕ ФУНКЦИИ LIBRECHAT =============

/**
 * Универсальная функция для вызова LibreChat API с улучшенной обработкой ошибок
 */
function callLibreChatAgent($agentId, $messages, $conversationId = null, $options = [], $system = null) {
    if (!isValidAgentId($agentId)) {
        return [
            'success' => false,
            'error' => 'Неверный ID агента'
        ];
    }

    $defaults = [
        'temperature' => 0.7,
        'max_tokens' => 2000,
        'stream' => false,
        'timeout' => 120
    ];
    
    $options = array_merge($defaults, $options);

    $payload = [
        'model' => $agentId,
        'messages' => $messages,
        'temperature' => $options['temperature'],
        'max_tokens' => $options['max_tokens'],
        'stream' => $options['stream']
    ];

    if ($system) $payload['system'] = $system;
    if ($conversationId) $payload['conversation_id'] = $conversationId;

    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => LIBRECHAT_URL . '/api/agents/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . LIBRECHAT_API_KEY,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => $options['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error) {
            throw new RuntimeException('CURL Error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('HTTP Error: ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON Error: ' . json_last_error_msg());
        }

        $content = extractAIMessageFromResponse($data);
        if (!$content) {
            throw new RuntimeException('Invalid response format');
        }

        return [
            'success' => true,
            'response' => $content,
            'conversation_id' => $data['conversation_id'] ?? $conversationId ?? generateConversationId(),
            'usage' => $data['usage'] ?? null
        ];

    } catch (Exception $e) {
        error_log('LibreChat API Error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'API Error: ' . $e->getMessage(),
            'http_code' => $httpCode ?? null
        ];
    }
}

// ============= ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ =============
// (остаются без изменений, но вынесены в отдельные файлы)

// Загрузка сервиса (если требуется)
if (file_exists(__DIR__ . '/../services/LibreChatService.php')) {
    require_once __DIR__ . '/../services/LibreChatService.php';
    if (class_exists('LibreChatService')) {
        $GLOBALS['libreChatService'] = new LibreChatService(
            LIBRECHAT_URL,
            LIBRECHAT_API_KEY,
            ['temperature' => 0.7, 'max_tokens' => 2000]
        );
    }
}