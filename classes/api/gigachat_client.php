<?php
/**
 * Клиент для работы с API GigaChat
 *
 * @package    local_glossary_ai
 * @author     Смолий Алена
 * @copyright  2026 Алтайский государственный университет
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_glossary_ai\api;

defined('MOODLE_INTERNAL') || die();

class gigachat_client {
    
    private $api_url = 'https://gigachat.devices.sberbank.ru';
    private $client_id;
    private $client_secret;
    private $scope;
    private $model;
    private $temperature;
    private $timeout;
    private $access_token = null;
    private $token_expires = 0;
    
    public function __construct() {
        $this->load_config();
    }
    
    /**
     * Загрузка конфигурации из файла
     */
    private function load_config() {
        // Пути к файлу конфигурации
        $paths = [
            __DIR__ . '/../../gigachat_config.php',
            '/etc/moodle/gigachat_config.php',
            '/var/www/moodle/local/glossary_ai/gigachat_config.php',
        ];
        
        $config = null;
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $config = include($path);
                if ($config && is_array($config)) {
                    break;
                }
            }
        }
        
        if ($config && is_array($config)) {
            $this->client_id = $config['client_id'] ?? '';
            $this->client_secret = $config['client_secret'] ?? '';
            $this->scope = $config['scope'] ?? 'GIGACHAT_API_B2B';
            $this->model = $config['model'] ?? 'GigaChat-2-Pro';
            $this->temperature = (float)($config['temperature'] ?? 0.7);
            $this->timeout = (int)($config['timeout'] ?? 120);
        }
    }
    
    /**
     * Проверка наличия конфигурации API
     */
    public function is_configured() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }
    
    /**
     * Получение токена доступа
     */
    private function get_access_token() {
        if (!$this->is_configured()) {
            return false;
        }
        
        // Проверяем, не истёк ли текущий токен
        if ($this->access_token && time() < $this->token_expires) {
            return $this->access_token;
        }
        
        $auth_key = base64_encode($this->client_id . ':' . $this->client_secret);
        
        $ch = curl_init('https://ngw.devices.sberbank.ru:9443/api/v2/oauth');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic {$auth_key}",
                "RqUID: " . $this->generate_uuid(),
                "Accept: application/json",
                "Content-Type: application/x-www-form-urlencoded"
            ],
            CURLOPT_POSTFIELDS => http_build_query(['scope' => $this->scope]),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            error_log("GigaChat OAuth error: HTTP {$http_code}, Response: {$response}");
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->token_expires = time() + ($data['expires_in'] ?? 3600) - 60;
            return $this->access_token;
        }
        
        error_log("GigaChat OAuth error: No access_token in response");
        return false;
    }
    
    /**
     * Генерация UUID для RqUID
     */
    private function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Извлечение текста из DOCX файла
     */
    public function extract_docx_text($filepath) {
        if (!file_exists($filepath)) {
            return '';
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($filepath) !== true) {
            return '';
        }
        
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xml === false) {
            return '';
        }
        
        // Удаляем XML теги
        $xml = str_replace('</w:p>', "\n", $xml);
        $text = strip_tags($xml);
        
        // Очищаем текст
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        
        return trim($text);
    }
    
    /**
     * Формирование промпта для GigaChat
     */
    private function build_prompt($topic, $count, $language, $context = '', $custom_prompt = '') {
        $lang_text = ($language === 'ru') ? 'на русском языке' : 'in English';
        
        $prompt = "Ты - эксперт по созданию глоссариев. Создай глоссарий по теме '{$topic}'.\n\n";
        $prompt .= "Требования:\n";
        $prompt .= "- Сгенерируй ровно {$count} терминов {$lang_text}\n";
        $prompt .= "- Каждый термин должен иметь чёткое, понятное определение\n";
        $prompt .= "- Определения должны быть краткими, но информативными\n";
        $prompt .= "- Термины должны быть релевантны теме\n\n";
        
        if (!empty($custom_prompt)) {
            $prompt .= "Дополнительные требования: {$custom_prompt}\n\n";
        }
        
        if (!empty($context)) {
            $context = mb_substr($context, 0, 3000);
            $prompt .= "Используй следующий текст как источник для терминов:\n";
            $prompt .= "---\n{$context}\n---\n\n";
        }
        
        $prompt .= "Формат вывода - ТОЛЬКО JSON массив. Пример правильного формата:\n";
        $prompt .= '[{"term": "Пример термина", "definition": "Пример определения"}, {"term": "Второй термин", "definition": "Второе определение"}]\n\n';
        $prompt .= "Важно: Не добавляй никаких пояснений, комментариев или дополнительного текста. Только JSON массив. Убедись, что JSON валидный.";
        
        return $prompt;
    }
    
    /**
     * Парсинг ответа от GigaChat
     */
    private function parse_response($response) {
        // Ищем JSON массив в ответе
        $json_start = strpos($response, '[');
        $json_end = strrpos($response, ']');
        
        if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
            $json_string = substr($response, $json_start, $json_end - $json_start + 1);
            
            // Очищаем JSON от возможных ошибок
            $json_string = preg_replace('/,\s*]/', ']', $json_string);
            $json_string = preg_replace('/,\s*}/', '}', $json_string);
            $json_string = preg_replace('/[\x00-\x1F\x7F]/', '', $json_string);
            
            $terms = json_decode($json_string, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($terms)) {
                $valid_terms = [];
                foreach ($terms as $item) {
                    if (isset($item['term']) && isset($item['definition'])) {
                        $valid_terms[] = [
                            'term' => trim($item['term']),
                            'definition' => trim($item['definition'])
                        ];
                    } elseif (isset($item['Термин']) && isset($item['Определение'])) {
                        $valid_terms[] = [
                            'term' => trim($item['Термин']),
                            'definition' => trim($item['Определение'])
                        ];
                    }
                }
                
                if (!empty($valid_terms)) {
                    return $valid_terms;
                }
            }
        }
        
        // Альтернативный парсинг для нестандартного JSON
        $pattern = '/["\']?(?:term|Термин)["\']?\s*:\s*["\']([^"\']+)["\']\s*,\s*["\']?(?:definition|Определение)["\']?\s*:\s*["\']([^"\']+)["\']/ui';
        
        if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
            $valid_terms = [];
            foreach ($matches as $match) {
                $valid_terms[] = [
                    'term' => trim($match[1]),
                    'definition' => trim($match[2])
                ];
            }
            if (!empty($valid_terms)) {
                return $valid_terms;
            }
        }
        
        error_log("GigaChat parse error: Could not parse response - " . substr($response, 0, 500));
        return false;
    }
    
    /**
     * Генерация терминов через GigaChat API
     */
    public function generate_terms($topic, $count, $language = 'ru', $context = '', $custom_prompt = '') {
        if (!$this->is_configured()) {
            error_log("GigaChat API not configured");
            return ['error' => 'api_not_configured'];
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            error_log("GigaChat: Failed to get access token");
            return ['error' => 'token_failed'];
        }
        
        $prompt = $this->build_prompt($topic, $count, $language, $context, $custom_prompt);
        
        $headers = [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ];
        
        $body = json_encode([
            "model" => $this->model,
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => $this->temperature,
            "max_tokens" => 4000
        ]);
        
        $ch = curl_init($this->api_url . '/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $this->timeout
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("GigaChat cURL error: {$curl_error}");
            return ['error' => 'curl_error', 'message' => $curl_error];
        }
        
        if ($http_code !== 200) {
            error_log("GigaChat API error: HTTP {$http_code}, Response: {$response}");
            return ['error' => 'api_error', 'http_code' => $http_code];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            $terms = $this->parse_response($content);
            
            if ($terms && is_array($terms) && !empty($terms)) {
                // Обрезаем до нужного количества, если API вернул больше
                if (count($terms) > $count) {
                    $terms = array_slice($terms, 0, $count);
                }
                return $terms;
            }
            
            error_log("GigaChat: Failed to parse terms from response");
            return ['error' => 'parse_error'];
        }
        
        error_log("GigaChat: Unexpected response structure - " . print_r($data, true));
        return ['error' => 'invalid_response'];
    }
    
    /**
     * Простая генерация для тестирования (без реального API)
     */
    public function generate_mock_terms($topic, $count, $language = 'ru') {
        $terms = [];
        $lang_suffix = $language === 'ru' ? '' : ' (EN)';
        
        for ($i = 1; $i <= $count; $i++) {
            $terms[] = [
                'term' => "Термин {$i}: {$topic}{$lang_suffix}",
                'definition' => "Это подробное определение для термина {$i} по теме '{$topic}'. Термин объясняется в контексте современной науки и практики."
            ];
        }
        
        return $terms;
    }
}
