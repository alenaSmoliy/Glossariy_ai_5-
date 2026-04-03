<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/api/gigachat_client.php');

$course_id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
$context = \context_course::instance($course_id);

require_login($course);
require_capability('local/glossary_ai:use', $context);

$PAGE->set_pagelayout('embedded');
$PAGE->set_url('/local/glossary_ai/generate.php', ['id' => $course_id]);
$PAGE->set_title(get_string('edit_terms', 'local_glossary_ai'));
$PAGE->requires->css('/local/glossary_ai/styles.css');
$PAGE->requires->js('/local/glossary_ai/script.js');

$generation_data = $SESSION->glossary_ai_data ?? null;
$session_terms = $SESSION->glossary_ai_terms ?? null;

if (!$generation_data && !$session_terms) {
    redirect(new moodle_url('/local/glossary_ai/index.php', ['id' => $course_id]));
}

$terms = null;
$glossary_id = null;

if ($session_terms) {
    $terms = $session_terms['terms'];
    $glossary_id = $session_terms['glossary_id'];
} else if ($generation_data) {
    $client = new \local_glossary_ai\api\gigachat_client();
    $result = $client->generate_terms(
        $generation_data['topic'],
        $generation_data['terms_count'],
        $generation_data['language']
    );
    
    if (is_array($result) && !isset($result['error'])) {
        $terms = $result;
        $glossary_id = $generation_data['glossary_id'];
        $SESSION->glossary_ai_terms = [
            'terms' => $terms,
            'glossary_id' => $glossary_id
        ];
    }
    unset($SESSION->glossary_ai_data);
}

echo $OUTPUT->header();
?>

<div class="glossary-ai-container">
    <div class="glossary-ai-header">
        <div class="glossary-ai-logo">
            ✏️
        </div>
        <div class="glossary-ai-title">
            <h1>Редактирование терминов</h1>
            <p>Проверьте и отредактируйте сгенерированные термины перед добавлением в глоссарий</p>
        </div>
    </div>
    
    <?php if (!$terms || empty($terms)): ?>
        <div class="glossary-ai-card">
            <div class="alert alert-danger"><?php echo get_string('error_generation', 'local_glossary_ai'); ?></div>
            <a href="<?php echo new moodle_url('/local/glossary_ai/index.php', ['id' => $course_id]); ?>" 
               class="glossary-ai-btn glossary-ai-btn-secondary">← Попробовать снова</a>
        </div>
    <?php else: ?>
    
    <div class="glossary-ai-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <div>
                <strong>Всего терминов:</strong> <?php echo count($terms); ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <button id="add-new-term" class="glossary-ai-btn glossary-ai-btn-primary">
                    + Добавить термин
                </button>
                <button id="add-all-terms" class="glossary-ai-btn glossary-ai-btn-success">
                    ✓ Добавить все в глоссарий
                </button>
                <a href="<?php echo new moodle_url('/local/glossary_ai/index.php', ['id' => $course_id]); ?>" 
                   class="glossary-ai-btn glossary-ai-btn-secondary">
                   ← Новая генерация
                </a>
            </div>
        </div>
        
        <div class="glossary-ai-table-wrapper">
            <table class="glossary-ai-table">
                <thead>
                    <tr>
                        <th style="width: 25%">Термин</th>
                        <th style="width: 60%">Определение</th>
                        <th style="width: 15%">Действия</th>
                    </tr>
                </thead>
                <tbody id="terms-tbody">
                    <?php foreach ($terms as $index => $term_data): ?>
                    <tr data-index="<?php echo $index; ?>" data-added="false">
                        <td><textarea class="term-input" rows="2"><?php echo htmlspecialchars($term_data['term']); ?></textarea></td>
                        <td><textarea class="definition-input" rows="2"><?php echo htmlspecialchars($term_data['definition']); ?></textarea></td>
                        <td class="glossary-ai-action-buttons">
                            <button class="glossary-ai-btn glossary-ai-btn-sm update-term-btn" data-index="<?php echo $index; ?>">
                                ✏️
                            </button>
                            <button class="glossary-ai-btn glossary-ai-btn-sm glossary-ai-btn-danger delete-term-btn" data-index="<?php echo $index; ?>">
                                🗑️
                            </button>
                            <button class="glossary-ai-btn glossary-ai-btn-sm glossary-ai-btn-success add-term-btn" data-index="<?php echo $index; ?>">
                                ➕
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <input type="hidden" id="glossary-id" value="<?php echo $glossary_id; ?>">
    <input type="hidden" id="course-id" value="<?php echo $course_id; ?>">
    <input type="hidden" id="sesskey" value="<?php echo sesskey(); ?>">
    
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
