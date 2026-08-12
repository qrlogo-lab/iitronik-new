<?php
/**
 * КОНФИГУРАЦИЯ BOTHUB (OpenAI-шлюз)
 * 
 * Используется для прямого вызова моделей через OpenAI-совместимый API.
 * Не зависит от агентов LibreChat.
 */

// ============= API НАСТРОЙКИ =============
define('BOTHUB_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjNjNTk0ODE0LTViMzAtNDJhNS04ODJhLTc2MjVjMzVlZWQxOCIsImlzRGV2ZWxvcGVyIjp0cnVlLCJpYXQiOjE3ODU2MzA3NzQsImV4cCI6MjEwMTIwNjc3NCwianRpIjoiaVZjM1piSm9ReTZycF9VeCJ9.qHld-oFtRsQgm3FmsAdyiIteqQUek4Jdt3r2H_hVH0k');
define('BOTHUB_BASE_URL', 'https://openai.bothub.chat/v1');

// Модель по умолчанию (можно заменить на любую доступную: claude-opus-4.7-fast, gpt-4o и т.д.)
define('BOTHUB_DEFAULT_MODEL', 'deepseek-chat');

// ============= ФУНКЦИИ =============

/**
 * Вызов модели через OpenAI-шлюз BotHub
 * 
 * @param array $messages Массив сообщений [['role' => 'system|user|assistant', 'content' => '...']]
 * @param array $options Дополнительные параметры (model, temperature, max_tokens)
 * @return array ['success' => bool, 'response' => string, 'conversation_id' => string, 'error' => string]
 */
function callBothubOpenAI($messages, $options = []) {
    $defaults = [
        'model' => BOTHUB_DEFAULT_MODEL,
        'temperature' => 0.7,
        'max_tokens' => 2000,
        'stream' => false
    ];
    $options = array_merge($defaults, $options);
    
    $payload = [
        'model' => $options['model'],
        'messages' => $messages,
        'temperature' => $options['temperature'],
        'max_tokens' => $options['max_tokens'],
        'stream' => $options['stream']
    ];
    
    // Логируем запрос (для отладки)
    file_put_contents(__DIR__ . '/../logs/bothub_payload.log', date('Y-m-d H:i:s') . " Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    try {
        $ch = curl_init(BOTHUB_BASE_URL . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . BOTHUB_API_KEY
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if (!empty($curlError)) {
            return [
                'success' => false,
                'error' => 'cURL error: ' . $curlError
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $httpCode . ' | Response: ' . substr($response, 0, 500)
            ];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'JSON parse error: ' . json_last_error_msg()
            ];
        }
        
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!$content) {
            return [
                'success' => false,
                'error' => 'No content in response'
            ];
        }
        
        return [
            'success' => true,
            'response' => $content,
            'conversation_id' => $data['id'] ?? 'conv_' . uniqid(),
            'usage' => $data['usage'] ?? null
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ];
    }
}

/**
 * Вспомогательная функция: получить список доступных моделей (опционально)
 * 
 * @return array|false Массив моделей или false при ошибке
 */
function getBothubModels() {
    try {
        $ch = curl_init(BOTHUB_BASE_URL . '/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . BOTHUB_API_KEY
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['data'] ?? [];
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}