<?php
namespace local_glossary_ai\glossary;

defined('MOODLE_INTERNAL') || die();

class term_manager {
    
    private $glossary_id;
    private $cm;
    
    public function __construct($glossary_id) {
        global $DB;
        
        $this->glossary_id = $glossary_id;
        $glossary = $DB->get_record('glossary', ['id' => $glossary_id], '*', MUST_EXIST);
        $this->cm = get_coursemodule_from_instance('glossary', $glossary_id, $glossary->course);
        
        $context = \context_module::instance($this->cm->id);
        require_capability('mod/glossary:write', $context);
    }
    
    public function add_term($term, $definition) {
        global $DB, $USER;
        
        if ($this->term_exists($term)) {
            return ['success' => false, 'error' => 'duplicate'];
        }
        
        $entry = new \stdClass();
        $entry->glossaryid = $this->glossary_id;
        $entry->userid = $USER->id;
        $entry->concept = trim($term);
        $entry->definition = trim($definition);
        $entry->definitionformat = FORMAT_HTML;
        $entry->timecreated = time();
        $entry->timemodified = time();
        $entry->approved = 1;
        $entry->teacherentry = 1;
        
        $entry_id = $DB->insert_record('glossary_entries', $entry);
        
        if ($entry_id) {
            return ['success' => true, 'entry_id' => $entry_id];
        }
        
        return ['success' => false, 'error' => 'db_error'];
    }
    
    public function add_terms_batch($terms) {
        $result = ['added' => 0, 'duplicates' => 0, 'errors' => 0];
        
        foreach ($terms as $term_data) {
            $res = $this->add_term($term_data['term'], $term_data['definition']);
            
            if ($res['success']) {
                $result['added']++;
            } elseif ($res['error'] === 'duplicate') {
                $result['duplicates']++;
            } else {
                $result['errors']++;
            }
        }
        
        return $result;
    }
    
    public function term_exists($term) {
        global $DB;
        return $DB->record_exists('glossary_entries', [
            'glossaryid' => $this->glossary_id,
            'concept' => trim($term)
        ]);
    }
}
