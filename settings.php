<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings_page = new admin_settingpage('local_glossary_ai', get_string('pluginname', 'local_glossary_ai'));
    
    $settings_page->add(new admin_setting_configtext(
        'local_glossary_ai/instruction_link',
        'Ссылка на инструкцию',
        'URL страницы с инструкцией по использованию плагина',
        '',
        PARAM_URL
    ));
    
    $ADMIN->add('localplugins', $settings_page);
}
