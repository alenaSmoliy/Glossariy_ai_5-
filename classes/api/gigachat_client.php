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
    
    private function load_config() {
        $paths = [
            __DIR__ . '/../../gigachat_config.php',
            '/etc/moodle/gigachat_config.php',
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
    
    public function is_configured() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }
    
    private function get_access_token() {
        if (!$this->is_configured()) {
            return false;
        }
        
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
            error_log("GigaChat OAuth error: HTTP {$http_code}");
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->token_expires = time() + ($data['expires_in'] ?? 3600) - 60;
            return $this->access_token;
        }
        
        return false;
    }
    
    private function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
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
        
        $xml = str_replace('</w:p>', "\n", $xml);
        $text = strip_tags($xml);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        
        return trim($text);
    }
    
    private function build_prompt($topic, $count, $language, $context = '', $custom_prompt = '') {
        if ($language === 'en') {
            $prompt = "You are a glossary creation expert. Create a glossary on the topic '{$topic}'.\n\n";
            $prompt .= "Requirements:\n";
            $prompt .= "- Generate exactly {$count} terms in English\n";
            $prompt .= "- Each term must have a clear, understandable definition\n";
            $prompt .= "- Definitions should be concise but informative\n";
            $prompt .= "- Terms must be relevant to the topic\n\n";
            
            if (!empty($custom_prompt)) {
                $prompt .= "Additional requirements: {$custom_prompt}\n\n";
            }
            
            if (!empty($context)) {
                $context = mb_substr($context, 0, 3000);
                $prompt .= "Use the following text as a source for terms:\n";
                $prompt .= "---\n{$context}\n---\n\n";
            }
            
            $prompt .= "Output format - ONLY JSON array. Example:\n";
            $prompt .= '[{"term": "Example term", "definition": "Example definition"}]\n\n';
            $prompt .= "Important: No explanations, only valid JSON array.";
        } else {
            $prompt = "Ты - эксперт по созданию глоссариев. Создай глоссарий по теме '{$topic}'.\n\n";
            $prompt .= "Требования:\n";
            $prompt .= "- Сгенерируй ровно {$count} терминов на русском языке\n";
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
            
            $prompt .= "Формат вывода - ТОЛЬКО JSON массив. Пример:\n";
            $prompt .= '[{"term": "Пример термина", "definition": "Пример определения"}]\n\n';
            $prompt .= "Важно: Без пояснений, только JSON массив.";
        }
        
        return $prompt;
    }
    
    private function parse_response($response) {
        $json_start = strpos($response, '[');
        $json_end = strrpos($response, ']');
        
        if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
            $json_string = substr($response, $json_start, $json_end - $json_start + 1);
            $json_string = preg_replace('/,\s*]/', ']', $json_string);
            $json_string = preg_replace('/,\s*}/', '}', $json_string);
            
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
        
        return false;
    }
    
    public function generate_terms($topic, $count, $language = 'ru', $context = '', $custom_prompt = '') {
        if (!$this->is_configured()) {
            return ['error' => 'api_not_configured'];
        }
        
        $token = $this->get_access_token();
        if (!$token) {
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
        curl_close($ch);
        
        if ($http_code !== 200) {
            return ['error' => 'api_error', 'http_code' => $http_code];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            $terms = $this->parse_response($content);
            
            if ($terms && is_array($terms) && !empty($terms)) {
                if (count($terms) > $count) {
                    $terms = array_slice($terms, 0, $count);
                }
                return $terms;
            }
            
            return ['error' => 'parse_error'];
        }
        
        return ['error' => 'invalid_response'];
    }
}
