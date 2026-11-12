<?php
use Portix\Module\ModuleInstaller;
use Portix\Module\ModuleManifest;

if (!$isAdmin) {
    return;
}

$modulesDatabase = $repositories->modules();
$message = '';
$removeModuleTree = static function (string $directory): void {
    if (is_link($directory)) {
        if (!unlink($directory)) {
            throw new RuntimeException('modules_cleanup_failed');
        }
        return;
    }
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink() || $entry->isFile()) {
            if (!unlink($entry->getPathname())) {
                throw new RuntimeException('modules_cleanup_failed');
            }
        } elseif (!rmdir($entry->getPathname())) {
            throw new RuntimeException('modules_cleanup_failed');
        }
    }
    if (!rmdir($directory)) {
        throw new RuntimeException('modules_cleanup_failed');
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        PortixSecurity::requirePostWithCsrf();

        $action = (string) ($_POST['module_action'] ?? '');
        if (in_array($action, array('enable', 'disable'), true)) {
            $prefix = trim((string) ($_POST['prefix'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
                throw new RuntimeException($translator->text('modules_invalid_prefix'));
            }
            $modulesDatabase->setEnabled($prefix, $action === 'enable');
            $message = $translator->text('modules_state_updated');
        } elseif ($action === 'uninstall') {
            $prefix = trim((string) ($_POST['prefix'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
                throw new RuntimeException($translator->text('modules_invalid_prefix'));
            }
            $module = $modulesDatabase->findByPrefix($prefix);
            if (!is_array($module) || (string) ($module['type'] ?? '') !== 'plugin') {
                throw new RuntimeException($translator->text('modules_uninstall_unavailable'));
            }
            $modulePath = (string) ($module['path'] ?? '');
            $moduleFolder = basename($modulePath);
            $moduleDirectory = './var/modules/' . $moduleFolder;
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$/', $modulePath)
                || $moduleFolder !== $modulePath
                || is_file($moduleDirectory . '/AUTH')) {
                throw new RuntimeException($translator->text('modules_uninstall_unavailable'));
            }
            if (is_file($moduleDirectory . '/desc.xml')) {
                $manifest = ModuleManifest::fromXml($moduleDirectory . '/desc.xml', $moduleFolder);
                if (strcasecmp((string) $manifest['prefix'], $prefix) !== 0
                    || $manifest['type'] !== 'plugin') {
                    throw new RuntimeException($translator->text('modules_uninstall_unavailable'));
                }
            }
            $modulesDatabase->setEnabled($prefix, false);
            $removeModuleTree($moduleDirectory);
            $modulesDatabase->deleteByPrefix($prefix);
            $message = $translator->text('modules_uninstalled_data_kept');
        } elseif ($action === 'upload') {
            if (!isset($_FILES['userfile']) || $_FILES['userfile']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException($translator->text('modules_upload_failed'));
            }
            if ((int) $_FILES['userfile']['size'] > 5 * 1024 * 1024
                || strtolower((string) pathinfo($_FILES['userfile']['name'], PATHINFO_EXTENSION)) !== 'zip') {
                throw new RuntimeException($translator->text('modules_zip_required'));
            }

            if (!is_dir('./tmp') && !mkdir('./tmp', 0775, true) && !is_dir('./tmp')) {
                throw new RuntimeException($translator->text('modules_temp_failed'));
            }
            $temporaryArchive = './tmp/module-' . bin2hex(random_bytes(12)) . '.zip';
            if (!move_uploaded_file($_FILES['userfile']['tmp_name'], $temporaryArchive)) {
                throw new RuntimeException($translator->text('modules_move_failed'));
            }

            $catalogueVerified = \Portix\Maintenance\ExtensionUpdates::verifyUploadedArchive(
                'module', (string) $_FILES['userfile']['name'], $temporaryArchive
            );
            $stagingDirectory = './tmp/module-stage-' . bin2hex(random_bytes(12));
            $backupDirectory = '';
            $moduleDirectory = '';
            try {
                if (!mkdir($stagingDirectory, 0770, true)) {
                    throw new RuntimeException('modules_temp_failed');
                }
                $rootFolder = PortixZip::extractSingleRoot($temporaryArchive, $stagingDirectory);
                $stagedModule = $stagingDirectory . '/' . $rootFolder;
                $descriptionFile = $stagedModule . '/desc.xml';
                if (!is_file($descriptionFile)) {
                    throw new RuntimeException('modules_manifest_missing');
                }
                $stagedManifest = ModuleManifest::fromXml($descriptionFile, $rootFolder);
                ModuleManifest::assertCompatible($stagedManifest, trim((string) file_get_contents('./VERSION')));
                $moduleDirectory = './var/modules/' . $rootFolder;
                if (is_link($moduleDirectory)) {
                    throw new RuntimeException('modules_unsafe_target');
                }
                if (is_dir($moduleDirectory)) {
                    $currentManifestFile = $moduleDirectory . '/desc.xml';
                    if (!is_file($currentManifestFile)) {
                        throw new RuntimeException('modules_installed_manifest_missing');
                    }
                    $currentManifest = ModuleManifest::fromXml($currentManifestFile, $rootFolder);
                    ModuleManifest::assertUpgrade($stagedManifest, $currentManifest);
                    $backupDirectory = './tmp/module-backup-' . bin2hex(random_bytes(12));
                    if (!rename($moduleDirectory, $backupDirectory)) {
                        throw new RuntimeException('modules_backup_failed');
                    }
                }

                if (!rename($stagedModule, $moduleDirectory)) {
                    if ($backupDirectory !== '') {
                        rename($backupDirectory, $moduleDirectory);
                    }
                    throw new RuntimeException('modules_activate_failed');
                }
                try {
                    (new ModuleInstaller($database, $modulesDatabase))->installDirectory($moduleDirectory);
                } catch (Throwable $installationError) {
                    $removeModuleTree($moduleDirectory);
                    if ($backupDirectory !== '' && !rename($backupDirectory, $moduleDirectory)) {
                        throw new RuntimeException('modules_restore_failed', 0, $installationError);
                    }
                    throw $installationError;
                }
                if ($backupDirectory !== '') {
                    $removeModuleTree($backupDirectory);
                    $backupDirectory = '';
                }
                $message = $translator->text('modules_installed_success') . ' '
                    . $translator->text($catalogueVerified
                        ? 'extension_updates_verified' : 'extension_updates_manual_unverified');
            } finally {
                if (is_file($temporaryArchive)) {
                    unlink($temporaryArchive);
                }
                if (is_dir($stagingDirectory)) {
                    $removeModuleTree($stagingDirectory);
                }
            }
        }
    } catch (Throwable $error) {
        $message = $translator->exception($error);
    }
}

$template->setSlot('main', './var/admin/templates/modmgr.tpl');
$template->set('extension_updates_download', $translator->text('extension_updates_download'));
$template->set('modmgr_message', $message);

$installedModules = $modulesDatabase->allOrdered();
$updates = \Portix\Maintenance\ExtensionUpdates::dashboard(dirname(__DIR__), $installedModules, []);
foreach ($installedModules as $description) {
    $modulePath = basename((string) $description['path']);
    $moduleDirectory = './var/modules/' . $modulePath;
    if (is_dir($moduleDirectory) && !is_file($moduleDirectory . '/AUTH')) {
        $moduleVersion = '';
        $manifestFile = $moduleDirectory . '/desc.xml';
        if (is_file($manifestFile)) {
            try {
                $installedManifest = ModuleManifest::fromXml($manifestFile, $modulePath);
                $moduleVersion = (string) ($installedManifest['version'] ?? '');
            } catch (Throwable) {
                // Keep the module list available if an older manifest cannot be parsed.
            }
        }
        $isConfigurable = !empty($description['enabled']) && is_file($moduleDirectory . '/config.php');
        $template->addRow('checkmod', array(
            'name' => $description['name'],
            'update_url' => $updates['module'][$description['prefix']]['url'] ?? '',
            'update_version' => $updates['module'][$description['prefix']]['version'] ?? '',
            'version' => $moduleVersion !== '' ? $moduleVersion : $translator->text('modules_version_missing'),
            'enabled' => $description['enabled'],
            'prefix' => $description['prefix'],
            'configurable' => $isConfigurable ? '1' : '0',
            'action' => !empty($description['enabled']) ? 'disable' : 'enable',
            'action_label' => $translator->text(
                !empty($description['enabled']) ? 'modules_disable' : 'modules_enable'
            ),
            'action_class' => !empty($description['enabled']) ? 'button-disable' : 'button-enable',
            'uninstallable' => (strtolower((string) ($description['type'] ?? '')) === 'plugin' && !is_file($moduleDirectory . '/AUTH')) ? '1' : '0',
        ));
    }
}
