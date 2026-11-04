<?php
PortixSecurity::requireRole($user, 'admin');
$originalThemeArguments = $argument ?? [];
$argument[2] = 'appearance';
require __DIR__ . '/config.php';
$argument = $originalThemeArguments;
$appearanceMessage = $configurationMessage;
$themeCatalog = new \Portix\Theme\ThemeCatalog(dirname(__DIR__), trim((string) file_get_contents(dirname(__DIR__) . '/VERSION')));
$themeInventory = $themeCatalog->all();
$configurationMessage = (string) ($_SESSION['themes_flash'] ?? $appearanceMessage);
unset($_SESSION['themes_flash']);
$themePackages = null;
try { $themePackages = new \Portix\Theme\ThemePackageManager(dirname(__DIR__), trim((string) file_get_contents(dirname(__DIR__) . '/VERSION'))); } catch (Throwable $error) { $configurationMessage = $translator->exception($error); }
if (isset($_POST['themes_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        if ($themePackages === null) { throw new RuntimeException($translator->text('themes_upload_invalid')); }
        $action = (string) $_POST['themes_action'];
        if ($action === 'analyse') {
            $upload = $_FILES['theme_archive'] ?? null;
            if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) { throw new RuntimeException($translator->text('themes_upload_invalid')); }
            if (isset($_SESSION['theme_package_token'])) { $themePackages->cancel((string) $_SESSION['theme_package_token']); unset($_SESSION['theme_package_token']); }
            $plan = $themePackages->stage((string) $upload['tmp_name']);
            $_SESSION['theme_package_token'] = $plan['token'];
            if ($plan['conflicts'] === []) {
                $themePackages->install($plan['token']);
                unset($_SESSION['theme_package_token']);
                $configurationMessage = $translator->text('themes_install_done');
            } else {
                $configurationMessage = $translator->text('themes_analysis_done');
            }
        } elseif (in_array($action, ['install', 'cancel'], true)) {
            $token = (string) ($_POST['theme_token'] ?? '');
            if (!isset($_SESSION['theme_package_token']) || !hash_equals((string) $_SESSION['theme_package_token'], $token)) { throw new RuntimeException($translator->text('themes_plan_invalid')); }
            if ($action === 'install') {
                if (($_POST['themes_confirm'] ?? '') !== 'yes') { throw new RuntimeException($translator->text('themes_confirmation_required')); }
                $themePackages->install($token);
                $configurationMessage = $translator->text('themes_install_done');
            } else { $themePackages->cancel($token); $configurationMessage = $translator->text('themes_cancel_done'); }
            unset($_SESSION['theme_package_token']);
        } elseif ($action === 'restore') {
            if (($_POST['themes_confirm'] ?? '') !== 'yes') { throw new RuntimeException($translator->text('themes_confirmation_required')); }
            $themePackages->restore((string) ($_POST['backup_token'] ?? ''));
            $configurationMessage = $translator->text('themes_restore_done');
        } else { throw new RuntimeException($translator->text('themes_plan_invalid')); }
        if (PHP_SAPI !== 'cli') { $_SESSION['themes_flash'] = $configurationMessage; header('Location: Admin-config-themes.html#theme-inventory-title', true, 303); exit; }
    } catch (Throwable $error) { $configurationMessage = $translator->exception($error); }
}

$template->setSlot('main', './var/admin/templates/themes.tpl');
$template->set('extension_updates_download', $translator->text('extension_updates_download'));
$template->set('confmessage', $configurationMessage);
$updates = \Portix\Maintenance\ExtensionUpdates::dashboard(dirname(__DIR__), [], $themeInventory);
foreach ($themeInventory as $entry) {
    $fileStatus = $translator->text('themes_not_measured');
    if ($entry['versioned']) {
        $fileStatus = $entry['changed'] === [] && $entry['missing'] === [] && $entry['errors'] === [] ? $translator->text('themes_files_match') : $translator->text('themes_files_changed');
    }
    $details = $entry['requirements'];
    if ($entry['variant_of'] !== '') { $details .= ' ; ' . $translator->text('themes_variant_of') . ' ' . $entry['variant_of']; }
    foreach (['changed', 'missing', 'extra', 'errors'] as $state) {
        if ($entry[$state] !== []) { $details .= ' ; ' . $translator->text('themes_' . $state) . ' : ' . implode(', ', $entry[$state]); }
    }
    if ($entry['kind'] === 'shared') { $details .= ' ; ' . $translator->text('themes_shared_help'); }
    $template->addRow('themerow', [
        'id' => $entry['id'], 'name' => $entry['name'],
        'update_url' => $updates['theme'][$entry['id']]['url'] ?? '',
        'update_version' => $updates['theme'][$entry['id']]['version'] ?? '',
        'version' => $entry['version'] !== '' ? $entry['version'] : $translator->text('themes_not_versioned'),
        'type' => $translator->text($entry['kind'] === 'shared' ? 'themes_shared' : ($entry['variant_of'] !== '' ? 'themes_variant' : 'themes_theme')),
        'compatibility' => $translator->text($entry['compatible'] === null ? 'themes_not_declared' : ($entry['compatible'] ? 'themes_compatible' : 'themes_incompatible')),
        'files' => $fileStatus, 'details' => trim($details, ' ;'),
        'active' => $entry['kind'] === 'theme' && $entry['id'] === (string) $CONFIG['template'] ? $translator->text('themes_active') : '',
    ]);
}

$template->set('themes_have_plan', '0');
$template->set('themes_plan_ready', '0');
if ($themePackages !== null && isset($_SESSION['theme_package_token'])) {
    try {
        $plan = $themePackages->plan((string) $_SESSION['theme_package_token']);
        $template->set('themes_have_plan', '1');
        $template->set('themes_plan_ready', $plan['conflicts'] === [] ? '1' : '0');
        foreach (['token', 'name', 'version', 'previous'] as $key) { $template->set('themes_plan_' . $key, (string) $plan[$key]); }
        $template->set('themes_plan_conflicts', implode(', ', $plan['conflicts']));
    } catch (Throwable) { unset($_SESSION['theme_package_token']); }
}
foreach ($themePackages?->backups() ?? [] as $backup) {
    $template->addRow('themebackup', ['token' => $backup['token'], 'id' => $backup['id'], 'version' => $backup['version'], 'date' => gmdate('Y-m-d H:i:s', $backup['created']) . ' UTC']);
}
