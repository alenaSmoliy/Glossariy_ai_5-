<?php
/**
 * Russian language pack for "AI Glossary" plugin
 *
 * @package    local_glossary_ai
 * @author     Смолий Алена
 * @copyright  2026 Алтайский государственный университет
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Basic strings
$string['pluginname'] = 'Глоссарий ИИ';
$string['generate_terms'] = 'Генерация терминов';
$string['edit_terms'] = 'Редактирование терминов';

// Form fields
$string['select_glossary'] = 'Выберите глоссарий';
$string['topic'] = 'Тема';
$string['topic_help'] = 'Укажите тему для генерации терминов глоссария';
$string['terms_count'] = 'Количество терминов';
$string['terms_count_help'] = 'Выберите количество терминов (10, 25 или 50)';
$string['language'] = 'Язык';
$string['generate_btn'] = 'Сгенерировать термины';

// Editing interface
$string['term'] = 'Термин';
$string['definition'] = 'Определение';
$string['actions'] = 'Действия';
$string['add_to_glossary'] = 'Добавить в глоссарий';
$string['add_all'] = 'Добавить все в глоссарий';
$string['delete'] = 'Удалить';
$string['edit'] = 'Редактировать';
$string['add_new'] = '+ Добавить термин';
$string['back'] = '← Назад';
$string['new_generation'] = 'Новая генерация';

// Status messages
$string['error_no_glossary'] = 'В этом курсе нет глоссариев. Сначала создайте глоссарий.';
$string['error_generation'] = 'Ошибка при генерации терминов';
$string['error_api'] = 'API GigaChat не настроен. Обратитесь к администратору.';
$string['terms_added'] = 'терминов добавлено';
$string['duplicates_skipped'] = 'дубликатов пропущено';
$string['added_to_glossary'] = 'Добавлено в глоссарий';
$string['term_added'] = 'Термин добавлен в глоссарий';
$string['term_updated'] = 'Термин обновлён';
$string['term_deleted'] = 'Термин удалён';
$string['term_add_failed'] = 'Ошибка при добавлении термина';
$string['term_update_failed'] = 'Ошибка при обновлении термина';
$string['term_delete_failed'] = 'Ошибка при удалении термина';
$string['fill_all_fields'] = 'Заполните и термин, и определение';
$string['confirm_delete'] = 'Удалить этот термин?';
$string['no_terms_to_add'] = 'Нет терминов для добавления';
$string['added_successfully'] = 'Успешно добавлено';
$string['duplicate_term'] = 'Такой термин уже существует в глоссарии';

// Permissions
$string['glossary_ai:use'] = 'Использовать плагин "Глоссарий ИИ"';

// API messages
$string['api_not_configured'] = 'API GigaChat не настроен';
$string['api_token_failed'] = 'Не удалось получить токен API';
$string['api_error'] = 'Произошла ошибка API';
$string['generating'] = 'Генерация терминов...';
$string['please_wait'] = 'Пожалуйста, подождите...';
