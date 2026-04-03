<?php
/**
 * Main functions for AI Glossary plugin
 *
 * @package    local_glossary_ai
 * @author     Смолий Алена
 * @copyright  2026 Алтайский государственный университет
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation node to course settings
 */
function local_glossary_ai_extend_navigation_course($navigation, $course, $context) {
    // Check if capability exists and user has it
    if (get_capability_info('local/glossary_ai:use') && has_capability('local/glossary_ai:use', $context)) {
        $url = new moodle_url('/local/glossary_ai/index.php', ['id' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'local_glossary_ai'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'glossary_ai',
            new pix_icon('i/calendar', '')
        );
    }
}
