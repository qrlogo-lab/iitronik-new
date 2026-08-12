<?php
/**
 * Сервис для работы с BotHub OpenAI-шлюзом
 * Дублирует функцию callBothubOpenAI
 */
class BothubService {
    private $apiKey;
    private $baseUrl;
    private $defaultModel;
    private $defaultOptions;

    public function __construct($apiKey, $baseUrl, $defaultModel = 'deepseek-chat', $defaultOptions = []) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultModel = $defaultModel;
        $this->defaultOptions = array_merge([
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'stream' => false
        ], $defaultOptions);
    }

    public function chat($messages, $options = []) {
        $options = array_merge($this->defaultOptions, $options);
        $model = $options['model'] ?? $this->defaultModel;
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'stream' => $options['stream']
        ];
        try {
            $ch = curl_init($this->baseUrl . '/chat/completions');
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
                return ['success' => false, 'error' => 'cURL error: ' . $curlError];
            }
            if ($httpCode !== 200) {
                return ['success' => false, 'error' => 'HTTP Error: ' . $httpCode];
            }
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'JSON parse error: ' . json_last_error_msg()];
            }
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (!$content) {
                return ['success' => false, 'error' => 'No content in response'];
            }
            return [
                'success' => true,
                'response' => $content,
                'conversation_id' => $data['id'] ?? 'conv_' . uniqid(),
                'usage' => $data['usage'] ?? null
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
        }
    }
}