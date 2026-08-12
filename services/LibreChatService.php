<?php
/**
 * Сервис для работы с LibreChat API
 * Дублирует функции callLibreChatAgent, extractAIMessageFromResponse и др.
 */
class LibreChatService {
    private $apiUrl;
    private $apiKey;
    private $defaultOptions;

    public function __construct($apiUrl, $apiKey, $defaultOptions = []) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->defaultOptions = array_merge([
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'stream' => false
        ], $defaultOptions);
    }

    public function callAgent($agentId, $messages, $conversationId = null, $options = [], $system = null) {
        $options = array_merge($this->defaultOptions, $options);
        $payload = [
            'model' => $agentId,
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'stream' => $options['stream']
        ];
        if ($system) $payload['system'] = $system;
        if ($conversationId) $payload['conversation_id'] = $conversationId;

        error_log('LibreChat request: agent=' . $agentId . ', messages=' . count($messages));
        try {
            $ch = curl_init($this->apiUrl . '/api/agents/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey
                ],
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!empty($curlError)) {
                return ['success' => false, 'error' => 'Ошибка подключения: ' . $curlError];
            }
            if ($httpCode !== 200) {
                return ['success' => false, 'error' => 'HTTP Error: ' . $httpCode];
            }
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Ошибка парсинга JSON'];
            }
            $content = $this->extractMessage($data);
            if (!$content) {
                return ['success' => false, 'error' => 'Не удалось получить ответ от AI'];
            }
            return [
                'success' => true,
                'response' => $content,
                'conversation_id' => $data['conversation_id'] ?? $conversationId ?? 'conv_' . uniqid()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
        }
    }

    private function extractMessage($response) {
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }
        if (isset($response['message']['content'])) {
            return $response['message']['content'];
        }
        if (isset($response['text'])) {
            return $response['text'];
        }
        if (isset($response['response'])) {
            return $response['response'];
        }
        return null;
    }

    // Удобные методы для каждого агента
    public function callTovCreator($messages, $conversationId = null, $options = []) {
        return $this->callAgent(AGENT_TOV_CREATOR['id'], $messages, $conversationId, $options);
    }
    public function callItronikUniversal($messages, $conversationId = null, $options = []) {
        return $this->callAgent(AGENT_ITRONIK_UNIVERSAL['id'], $messages, $conversationId, $options);
    }
    public function callWizardAssistant($messages, $conversationId = null, $options = []) {
        return $this->callAgent(AGENT_WIZARD_ASSISTANT['id'], $messages, $conversationId, $options);
    }
    public function callTrumpWidget($messages, $conversationId = null, $options = []) {
        return $this->callAgent(AGENT_TRUMP_WIDGET['id'], $messages, $conversationId, $options);
    }
}