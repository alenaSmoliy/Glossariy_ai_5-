<?php
namespace local_glossary_ai\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class generation_form extends \moodleform {
    
    protected function definition() {
        global $COURSE, $DB;
        
        $mform = $this->_form;
        
        $mform->addElement('hidden', 'course_id');
        $mform->setType('course_id', PARAM_INT);
        $mform->setDefault('course_id', $COURSE->id);
        
        // Глоссарий
        $glossaries = $DB->get_records_select_menu(
            'glossary',
            'course = ?',
            [$COURSE->id],
            'name',
            'id, name'
        );
        
        if (empty($glossaries)) {
            $mform->addElement('html', '<div class="alert alert-warning">⚠️ ' . 
                get_string('error_no_glossary', 'local_glossary_ai') . '</div>');
            return;
        }
        
        $mform->addElement('select', 'glossary_id', '📖 ' . get_string('select_glossary', 'local_glossary_ai'), $glossaries);
        $mform->addRule('glossary_id', null, 'required');
        
        // Тема
        $mform->addElement('text', 'topic', '🏷️ ' . get_string('topic', 'local_glossary_ai'));
        $mform->setType('topic', PARAM_TEXT);
        $mform->addRule('topic', null, 'required');
        
        // Количество
        $mform->addElement('select', 'terms_count', '📊 ' . get_string('terms_count', 'local_glossary_ai'), [
            10 => '10 терминов',
            25 => '25 терминов',
            50 => '50 терминов'
        ]);
        $mform->setDefault('terms_count', 10);
        
        // Язык
        $mform->addElement('select', 'language', '🌐 ' . get_string('language', 'local_glossary_ai'), [
            'ru' => 'Русский',
            'en' => 'English'
        ]);
        $mform->setDefault('language', 'ru');
        
        $this->add_action_buttons(false, get_string('generate_btn', 'local_glossary_ai'));
    }
}
