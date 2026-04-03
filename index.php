<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/generation_form.php');

$course_id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
$context = \context_course::instance($course_id);

require_login($course);
require_capability('local/glossary_ai:use', $context);

$PAGE->set_url('/local/glossary_ai/index.php', ['id' => $course_id]);
$PAGE->set_title(get_string('pluginname', 'local_glossary_ai'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/local/glossary_ai/styles.css');
$PAGE->requires->js('/local/glossary_ai/script.js');

// Очищаем сессию
unset($SESSION->glossary_ai_terms);

$mform = new \local_glossary_ai\form\generation_form(null, ['course_id' => $course_id]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course_id]));
} else if ($data = $mform->get_data()) {
    $SESSION->glossary_ai_data = [
        'glossary_id' => $data->glossary_id,
        'topic' => $data->topic,
        'terms_count' => $data->terms_count,
        'language' => $data->language
    ];
    redirect(new moodle_url('/local/glossary_ai/generate.php', ['id' => $course_id]));
}

echo $OUTPUT->header();
?>

<div class="glossary-ai-container">
    <div class="glossary-ai-header">
        <div class="glossary-ai-logo">
            🤖
        </div>
        <div class="glossary-ai-title">
            <h1>Глоссарий ИИ</h1>
            <p>Автоматическая генерация терминов с помощью нейросети GigaChat</p>
        </div>
    </div>
    
    <div class="glossary-ai-card">
        <?php $mform->display(); ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
