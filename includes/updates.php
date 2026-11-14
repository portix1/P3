<?php
PortixSecurity::requireRole($user, 'admin');
$themeInventory = (new \Portix\Theme\ThemeCatalog(dirname(__DIR__), trim((string) file_get_contents(dirname(__DIR__) . '/VERSION'))))->all();
$themeById = array_column($themeInventory, null, 'id');
$updateStatus = '';
$root = dirname(__DIR__);
$installedVersion = trim((string) file_get_contents($root . '/VERSION'));
$coreVersion = \Portix\Maintenance\ExtensionUpdates::core($installedVersion);
$coreFailed = \Portix\Maintenance\ExtensionUpdates::coreCheckFailed();
$coreMessage = '';

// Core updates are explicitly requested by an administrator: never on page load.
if (isset($_POST['install_core_update'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        if (($_POST['confirm_core_update'] ?? '') !== '1') {
            throw new RuntimeException('Confirmez la sauvegarde et la mise à jour avant de continuer.');
        }
        $requested = $_POST['core_target_version'] ?? '';
        if (!is_string($requested) || $requested === '' || $requested !== $coreVersion) {
            throw new RuntimeException('La version annoncée a changé : rechargez la page.');
        }
        $rollback = \Portix\Maintenance\CoreUpdater::installLatest($root, $requested);
        $_SESSION['p3_core_update_result'] = [
            'version' => $requested,
            'rollback' => $rollback,
        ];
        unset($_SESSION['p3_core_release']);
        header('Location: Admin-config-updates.html?installed=1', true, 303);
        exit;
    } catch (\Throwable $error) {
        error_log('P3 core update: ' . (string)$error);
        $coreFailed = true;
        $coreMessage = $error instanceof RuntimeException
            ? $error->getMessage()
            : 'La mise à jour n’a pas abouti. Les fichiers précédents ont été conservés ou restaurés.';
    }
}
if (($_GET['installed'] ?? '') === '1' && isset($_SESSION['p3_core_update_result'])) {
    $completed = $_SESSION['p3_core_update_result'];
    unset($_SESSION['p3_core_update_result']);
    if (is_array($completed)) {
        $coreMessage = 'P3 ' . (string)($completed['version'] ?? '')
            . ' a été mis à jour. Point de restauration : '
            . (string)($completed['rollback'] ?? 'inconnu') . '.';
    }
}
$template->set('core_update_target', $coreVersion);
$template->set('core_update_installed', $installedVersion);
$template->set('core_update_message', $coreMessage);
$template->set('core_update_failed', $coreFailed ? '1' : '0');
foreach (['heading', 'action', 'confirm', 'help', 'current', 'available', 'none', 'unavailable'] as $name) {
    $template->set('core_update_' . $name, $translator->text('core_update_' . $name));
}
if (isset($_POST['check_extension_updates'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        $catalogue = \Portix\Maintenance\ExtensionUpdates::fetchCatalogue();
        $installed = ['module' => [], 'theme' => $themeById];
        foreach ($repositories->modules()->allOrdered() as $module) {
            $path = basename((string) $module['path']);
            $manifestFile = dirname(__DIR__) . '/var/modules/' . $path . '/desc.xml';
            if (!is_readable($manifestFile)) {
                continue;
            }
            try {
                $manifest = \Portix\Module\ModuleManifest::fromXml($manifestFile, $path);
                $installed['module'][$manifest['prefix']] = $manifest;
            } catch (RuntimeException) {
                continue;
            }
        }
        $updates = \Portix\Maintenance\ExtensionUpdates::available($catalogue, $installed);
        $_SESSION['p3_update_catalogue'] = ['json' => $catalogue, 'expires' => time() + 300];
        unset($_SESSION['p3_core_release']);
        foreach ($updates as $update) {
            $template->addRow('extensionupdate', $update);
        }
        $updateStatus = $updates === [] ? 'extension_updates_none' : 'extension_updates_found';
    } catch (RuntimeException | JsonException) {
        $updateStatus = 'extension_updates_failed';
    }
}
$template->set('extension_updates_status', $updateStatus === '' ? '' : $translator->text($updateStatus));
foreach (['heading', 'check', 'download'] as $label) {
    $key = 'extension_updates_' . $label;
    $template->set($key, $translator->text($key));
}

$template->setSlot('main', './var/admin/templates/updates.tpl');
