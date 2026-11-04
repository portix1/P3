<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté avec PHP CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$loadLanguage = static function (string $file): array {
    $_LANG = array();
    include $file;
    return $_LANG;
};

try {
    $french = $loadLanguage($root . '/var/lang/fr.php');
    $english = $loadLanguage($root . '/var/lang/en.php');
    $frenchKeys = array_keys($french);
    $englishKeys = array_keys($english);
    sort($frenchKeys);
    sort($englishKeys);
    if ($frenchKeys !== $englishKeys) {
        $missingInEnglish = array_values(array_diff($frenchKeys, $englishKeys));
        $missingInFrench = array_values(array_diff($englishKeys, $frenchKeys));
        throw new RuntimeException(
            'Clés absentes en anglais : ' . implode(', ', $missingInEnglish)
            . '; clés absentes en français : ' . implode(', ', $missingInFrench)
        );
    }
    $combinedFrench = $french;
    $combinedEnglish = $english;
    foreach (glob($root . '/var/modules/*/lang/fr.php') ?: [] as $moduleFrenchFile) {
        $moduleEnglishFile = dirname($moduleFrenchFile) . '/en.php';
        if (!is_file($moduleEnglishFile)) {
            continue;
        }
        $moduleFrench = $loadLanguage($moduleFrenchFile);
        $moduleEnglish = $loadLanguage($moduleEnglishFile);
        $moduleFrenchKeys = array_keys($moduleFrench);
        $moduleEnglishKeys = array_keys($moduleEnglish);
        sort($moduleFrenchKeys);
        sort($moduleEnglishKeys);
        if ($moduleFrenchKeys !== $moduleEnglishKeys) {
            throw new RuntimeException(
                'Catalogues de module différents : ' . basename(dirname($moduleFrenchFile, 2))
            );
        }
        $combinedFrench = array_merge($combinedFrench, $moduleFrench);
        $combinedEnglish = array_merge($combinedEnglish, $moduleEnglish);
    }

    foreach (array('fr' => $french, 'en' => $english) as $language => $messages) {
        foreach ($messages as $key => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') {
                throw new RuntimeException('Traduction vide ou invalide : ' . $language . '.' . $key);
            }
        }
    }

    require_once $root . '/src/Support/Translator.php';
    $translator = new Portix\Support\Translator($english);
    if ($translator->text('ui_save') !== 'Save'
        || $translator->exception(new RuntimeException('Accès refusé.')) !== 'Access denied.') {
        throw new RuntimeException('Le service de traduction ne restitue pas la langue active.');
    }

    $prefixes = array(
        'ui_', 'error_', 'page_', 'back_', 'admin_', 'config_', 'dashboard_', 'ux_',
        'modules_', 'news_', 'articles_', 'menu_', 'users_', 'security_', 'editor_', 'login_',
        'profile_', 'contact_', 'downloads_', 'insights_', 'meta_',
    );
    $dynamicPlaceholders = array(
        'admin_module_updates', 'admin_theme_updates', 'admin_core_update', 'admin_core_status',
        'admin_module_has_updates', 'admin_theme_has_updates', 'admin_public_theme',
        'ux_show_writing', 'ux_show_management', 'ux_show_configuration',
        'ux_writing_current', 'ux_management_current', 'ux_configuration_current',
        'ux_active_area', 'ux_active_label', 'ux_breadcrumb_leaf', 'ux_area_title', 'ux_area_help',
        'extension_updates_status', 'ux_home_current',
        'ux_has_writing', 'ux_has_management', 'ux_has_configuration',

        'admin_area_class', 'admin_home_current', 'admin_news_current', 'admin_users_current', 'admin_config_current',
        'admin_modules_current', 'admin_diagnostics_current', 'admin_module_count', 'admin_enabled_module_count',
        'admin_news_count', 'admin_published_news_count', 'diagnostics_message', 'diagnostics_mode', 'diagnostics_has_result', 'diagnostics_total', 'diagnostics_pass', 'diagnostics_fail', 'diagnostics_report', 'diagnostics_last_summary', 'news_total', 'news_published',
        'news_form_title', 'news_form_action', 'news_message', 'news_title', 'news_slug',
        'news_intro', 'news_content', 'news_preview_action', 'news_draft_selected', 'news_published_selected', 'news_pinned_checked',
        'articles_asset_version', 'articles_can_configure_menu', 'articles_listing', 'articles_list_title', 'articles_has_items',
        'articles_has_breadcrumb', 'articles_has_navigation',
        'articles_total', 'articles_published', 'articles_form_title',
        'articles_form_action', 'articles_message', 'articles_title_value',
        'articles_slug_value', 'articles_body_value', 'articles_author_value', 'articles_featured_image_value',
        'articles_summary_value',
        'articles_preview_action', 'articles_toc',
        'articles_draft_selected', 'articles_published_selected',
        'articles_category_form_title', 'articles_category_form_action',
        'articles_category_name_value', 'articles_category_slug_value', 'articles_category_description_value',
        'articles_category_visible_checked',
        'articles_menu_message', 'articles_menu_header_checked',
        'articles_menu_articles_checked',
        'articles_menu_navigation_off_selected',
        'articles_menu_navigation_category_selected',
        'articles_menu_navigation_book_selected',
        'articles_order_message', 'articles_order_tree',
        'users_permissions_action', 'users_message', 'users_query', 'users_total', 'users_admin_count',
        'users_redactor_count', 'users_has_items', 'users_has_query',
        'users_role_filter', 'users_filter_all_active', 'users_filter_admin_active',
        'users_filter_redactor_active', 'users_filter_all_current',
        'users_filter_admin_current', 'users_filter_redactor_current',
        'users_list_title', 'users_clear_search_url',
        'users_is_new', 'users_is_edit', 'users_can_delete', 'users_is_self',
        'users_form_title', 'users_form_intro', 'users_form_action',
        'users_password_action', 'users_delete_action', 'users_pseudo_value',
        'users_first_name_value', 'users_last_name_value', 'users_email_value',
        'users_admin_checked', 'users_redactor_checked',
        'login_message', 'menu_message',
        'meta_canonical', 'meta_description', 'meta_has_canonical',
        'meta_has_description', 'meta_has_image', 'meta_image', 'meta_has_json_ld', 'meta_json_ld', 'meta_og_type', 'meta_robots',
        'contact_admin_message', 'contact_can_configure', 'contact_conversation_subject',
        'contact_error', 'contact_id', 'contact_inbox_current', 'contact_is_open',
        'contact_notification_email', 'contact_recipient_name', 'contact_retention_days',
        'contact_thread_subject', 'contact_thread_token', 'contact_thread_error', 'contact_thread_notice', 'contact_conversation_live', 'contact_settings_current', 'contact_folder', 'contact_is_trash', 'contact_can_reply',
        'contact_visitor_email', 'contact_visitor_name',
        'downloads_categories_current', 'downloads_category_description',
        'downloads_category_description_value', 'downloads_category_form_action',
        'downloads_category_form_title', 'downloads_category_id',
        'downloads_category_name_value', 'downloads_category_slug_value',
        'downloads_category_title', 'downloads_current_file', 'downloads_description_value',
        'downloads_file_id', 'downloads_file_required', 'downloads_form_action',
        'downloads_form_title', 'downloads_has_files', 'downloads_is_edit',
        'downloads_list_current', 'downloads_listing', 'downloads_message',
        'downloads_new_current', 'downloads_status_draft_selected',
        'downloads_status_published_selected', 'downloads_title_value',
        'insights_confirmed_pages', 'insights_unconfirmed_pages', 'insights_previous_audience_note',
        'insights_analytics_days', 'insights_application_days', 'insights_audit_current',
        'insights_audit_days', 'insights_bots', 'insights_dashboard_current',
        'insights_measurement_note', 'insights_legacy_note', 'insights_days', 'insights_has_visits', 'insights_logs_current',
        'insights_message', 'insights_mode_estimated', 'insights_mode_minimal', 'insights_mode_off',
        'insights_page_views', 'insights_settings_current', 'insights_visits',
        'insights_unique_visitors', 'insights_new_visitors', 'insights_returning_visitors',
        'insights_pages_per_visitor', 'insights_avg_unique_day', 'insights_avg_views_day',
        'insights_period_7_selected', 'insights_period_30_selected',
        'insights_period_90_selected', 'insights_period_365_selected',
    );
    $templateDirectories = array(
        $root . '/var/admin',
        $root . '/var/templates',
        $root . '/var/modules',
    );
    foreach ($templateDirectories as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'tpl') {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            preg_match_all('/\{\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}\}/', $content, $matches);
            foreach (array_unique($matches[1]) as $placeholder) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($placeholder, $prefix)
                        && !in_array($placeholder, $dynamicPlaceholders, true)
                        && !array_key_exists($placeholder, $combinedFrench)) {
                        throw new RuntimeException(
                            'Clé globale absente pour ' . $placeholder . ' dans '
                            . substr($file->getPathname(), strlen($root) + 1)
                        );
                    }
                }
            }
        }
    }

    $editorSource = (string) file_get_contents($root . '/var/editor/portix-editor.js');
    $uiSource = (string) file_get_contents($root . '/var/editor/portix-ui.js');
    foreach (array(
        'Format refusé', 'Cette image dépasse', 'Envoi et optimisation',
        'Attendez la fin', 'Supprimer définitivement',
    ) as $hardCodedMessage) {
        if (str_contains($editorSource . $uiSource, $hardCodedMessage)) {
            throw new RuntimeException('Message JavaScript codé en dur : ' . $hardCodedMessage);
        }
    }

    $engine = (string) file_get_contents($root . '/includes/engine.php');
    $languagePosition = strpos($engine, '$globalLanguageFile');
    $pluginPosition = strpos($engine, "if (\$currentModule['type'] === 'plugin')");
    if ($languagePosition === false || $pluginPosition === false || $languagePosition > $pluginPosition) {
        throw new RuntimeException('Le catalogue global doit être chargé avant les modules.');
    }

    echo "Localisation française/anglaise : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test de localisation : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
