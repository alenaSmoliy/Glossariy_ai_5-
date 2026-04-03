<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/glossary/term_manager.php');

$action = required_param('action', PARAM_ALPHA);
$course_id = required_param('course_id', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

$course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
$context = \context_course::instance($course_id);

require_login($course);
require_sesskey();
require_capability('local/glossary_ai:use', $context);

$response = ['success' => false];

switch ($action) {
    case 'add_to_glossary':
        $glossary_id = required_param('glossary_id', PARAM_INT);
        $term = required_param('term', PARAM_TEXT);
        $definition = required_param('definition', PARAM_RAW);
        
        try {
            $manager = new \local_glossary_ai\glossary\term_manager($glossary_id);
            $result = $manager->add_term($term, $definition);
            $response = $result;
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => $e->getMessage()];
        }
        break;
        
    case 'add_all_terms':
        $glossary_id = required_param('glossary_id', PARAM_INT);
        $terms_json = required_param('terms', PARAM_RAW);
        $terms = json_decode($terms_json, true);
        
        if ($terms && is_array($terms)) {
            try {
                $manager = new \local_glossary_ai\glossary\term_manager($glossary_id);
                $result = $manager->add_terms_batch($terms);
                $response = ['success' => true, 'added' => $result['added'], 'duplicates' => $result['duplicates']];
                
                if ($result['added'] > 0) {
                    unset($SESSION->glossary_ai_terms);
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'error' => 'invalid_terms'];
        }
        break;
        
    case 'update_term':
        $index = required_param('index', PARAM_INT);
        $term = required_param('term', PARAM_TEXT);
        $definition = required_param('definition', PARAM_RAW);
        
        if (isset($SESSION->glossary_ai_terms['terms'][$index])) {
            $SESSION->glossary_ai_terms['terms'][$index] = [
                'term' => $term,
                'definition' => $definition
            ];
            $response['success'] = true;
        }
        break;
        
    case 'delete_term':
        $index = required_param('index', PARAM_INT);
        
        if (isset($SESSION->glossary_ai_terms['terms'][$index])) {
            array_splice($SESSION->glossary_ai_terms['terms'], $index, 1);
            $response['success'] = true;
        }
        break;
        
    case 'add_term':
        $term = required_param('term', PARAM_TEXT);
        $definition = required_param('definition', PARAM_RAW);
        
        if (isset($SESSION->glossary_ai_terms['terms'])) {
            $SESSION->glossary_ai_terms['terms'][] = [
                'term' => $term,
                'definition' => $definition
            ];
            $response['success'] = true;
            $response['index'] = count($SESSION->glossary_ai_terms['terms']) - 1;
        }
        break;
}

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
