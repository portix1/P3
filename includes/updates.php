<?php
PortixSecurity::requireRole($user, 'admin');
$themeInventory = (new \Portix\Theme\ThemeCatalog(dirname(__DIR__), trim((string) file_get_contents(dirname(__DIR__) . '/VERSION'))))->all();
$themeById = array_column($themeInventory, null, 'id');
$updateStatus = '';
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
