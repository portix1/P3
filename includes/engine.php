<?php
$argument = isset($requestedArguments) && is_array($requestedArguments) ? $requestedArguments : [];
$route = isset($argument[0]) ? (string) $argument[0] : (string) $CONFIG['defaultpage'];
$user = new \Portix\Domain\AnonymousUser();
$Isadmin = false;

$globalLanguage = basename((string) $CONFIG['lang']);
$globalLanguageFile = './var/lang/' . $globalLanguage . '.php';
$globalFallbackFile = './var/lang/fr.php';
if (is_file($globalLanguageFile)) {
    include $globalLanguageFile;
} elseif (is_file($globalFallbackFile)) {
    include $globalFallbackFile;
}
$translator = new \Portix\Support\Translator($_LANG);
$breadcrumbTrail = new \Portix\Navigation\BreadcrumbTrail($translator->text('ui_home'));
$moduleAdminAccess = static function (array $module): string {
    $modulePath = basename((string) ($module['path'] ?? ''));
    $manifestFile = './var/modules/' . $modulePath . '/desc.xml';
    if (!is_file($manifestFile)) {
        return 'admin';
    }
    try {
        $manifest = \Portix\Module\ModuleManifest::fromXml($manifestFile, $modulePath);
        return (string) $manifest['admin_access'];
    } catch (Throwable $error) {
        error_log((string) $error);
        return 'admin';
    }
};

$MODULES = array();
$modules = $repositories->modules()->allOrdered();
foreach ($modules as $record) {
    $template->addRow('module', $record);
    if (!empty($record['enabled'])) {
        $MODULES[] = $record['name'];
    }
}

// Les plugins établissent le contexte transversal (utilisateur, menu,
// métadonnées...) avant l'exécution du module correspondant à la route.
// Leur ordre relatif reste celui configuré dans l'administration.
foreach ($modules as $currentModule) {
    if (empty($currentModule['enabled'])) {
        continue;
    }

    $modulePath = basename((string) $currentModule['path']);
    $language = basename((string) $CONFIG['lang']);
    $defaultLanguage = basename((string) $currentModule['default_language']);

    if ($currentModule['type'] === 'plugin') {
        $languageFile = './var/modules/' . $modulePath . '/lang/' . $language . '.php';
        $fallbackFile = './var/modules/' . $modulePath . '/lang/' . $defaultLanguage . '.php';
        if (is_file($languageFile)) {
            include $languageFile;
        } elseif (is_file($fallbackFile)) {
            include $fallbackFile;
        }
        $translator = new \Portix\Support\Translator($_LANG);
        $entryFile = './var/modules/' . $modulePath . '/index.php';
        if (!is_file($entryFile) || is_link($entryFile)) {
            error_log('Module actif ignoré car son point d’entrée est absent : ' . $modulePath);
            continue;
        }
        require $entryFile;
    }

}

// Un module peut contribuer au menu public par un fichier explicitement
// déclaré dans son manifeste. Le noyau fournit uniquement le point
// d’extension et ne connaît pas la structure du menu produit.
if ($route !== 'Admin') {
    foreach ($modules as $currentModule) {
        if (empty($currentModule['enabled'])) {
            continue;
        }
        $modulePath = basename((string) $currentModule['path']);
        $manifestFile = './var/modules/' . $modulePath . '/desc.xml';
        if (!is_file($manifestFile)) {
            continue;
        }
        try {
            $manifest = \Portix\Module\ModuleManifest::fromXml($manifestFile, $modulePath);
            $navigationName = (string) $manifest['public_navigation'];
            if ($navigationName === '') {
                continue;
            }
            $language = basename((string) $CONFIG['lang']);
            $defaultLanguage = basename((string) $currentModule['default_language']);
            $languageFile = './var/modules/' . $modulePath . '/lang/' . $language . '.php';
            $fallbackFile = './var/modules/' . $modulePath . '/lang/' . $defaultLanguage . '.php';
            if (is_file($languageFile)) {
                include $languageFile;
            } elseif (is_file($fallbackFile)) {
                include $fallbackFile;
            }
            $translator = new \Portix\Support\Translator($_LANG);
            $navigationFile = './var/modules/' . $modulePath . '/' . $navigationName;
            if (is_file($navigationFile) && !is_link($navigationFile)) {
                include $navigationFile;
            }
        } catch (Throwable $error) {
            error_log((string) $error);
        }
    }
}

foreach ($modules as $currentModule) {
    if (empty($currentModule['enabled'])
        || $currentModule['type'] !== 'module'
        || (string) $currentModule['prefix'] !== $route) {
        continue;
    }

    $modulePath = basename((string) $currentModule['path']);
    $language = basename((string) $CONFIG['lang']);
    $defaultLanguage = basename((string) $currentModule['default_language']);
    $languageFile = './var/modules/' . $modulePath . '/lang/' . $language . '.php';
    $fallbackFile = './var/modules/' . $modulePath . '/lang/' . $defaultLanguage . '.php';
    if (is_file($languageFile)) {
        include $languageFile;
    } elseif (is_file($fallbackFile)) {
        include $fallbackFile;
    }
    $translator = new \Portix\Support\Translator($_LANG);
    $entryFile = './var/modules/' . $modulePath . '/index.php';
    if (!is_file($entryFile) || is_link($entryFile)) {
        error_log('Module actif ignoré car son point d’entrée est absent : ' . $modulePath);
        continue;
    }
    require $entryFile;
    break;
}

// The SimpleMenu plugin supplies the site navigation when it is enabled.
// Fall back to the enabled page modules for installations without that plugin.
if ($template->rows('navigation') === []) {
    foreach ($modules as $currentModule) {
        if (!empty($currentModule['enabled']) && (string) $currentModule['type'] === 'module') {
            $prefix = trim((string) $currentModule['prefix']);
            $template->addRow('navigation', array(
                'name' => trim((string) $currentModule['name']),
                'link' => $prefix . '.html',
                'current' => $prefix === $route ? 'is-current' : '',
                'item_class' => '',
                'has_submenu' => '0',
                'submenu_id' => '',
                'toggle_label' => '',
                'submenu' => '',
            ));
        }
    }
}

$isAdmin = isset($user) && is_object($user) && method_exists($user, 'isAdmin') && (bool) $user->isAdmin();
$isRedactor = isset($user) && is_object($user) && method_exists($user, 'isRedactor') && (bool) $user->isRedactor();
$canAccessAdmin = $isAdmin || $isRedactor;
$template->set('is_admin', $isAdmin ? '1' : '0');
$template->set('is_redactor', $isRedactor ? '1' : '0');
$template->set('can_access_admin', $canAccessAdmin ? '1' : '0');
$isAuthenticated = !($user instanceof \Portix\Domain\AnonymousUser);
$template->set('is_authenticated', $isAuthenticated ? '1' : '0');
$template->set('has_auxiliary_sidebar', ($template->rows('menu1') !== [] || $template->rows('menu2') !== []) ? '1' : '0');
if ($isAdmin) {
    $template->addRow('adminonly', ['visible' => '1']);
}
$adminRoute = $route === 'Admin' && isset($argument[1]) ? (string) $argument[1] : '';
$adminAreaClass = 'admin-area-dashboard';
if ($route === 'Admin' && $adminRoute !== '') {
    if ($adminRoute === 'news') {
        $adminAreaClass = 'admin-area-content';
    } elseif (in_array($adminRoute, ['config', 'users', 'modmgr', 'diagnostics'], true)) {
        $adminAreaClass = 'admin-area-config';
    } else {
        $adminAreaModule = $repositories->modules()->findByPrefix($adminRoute);
        if (is_array($adminAreaModule)) {
            $adminAreaAccess = $moduleAdminAccess($adminAreaModule);
            $adminSubRoute = (string) ($argument[2] ?? '');
            $isConfigurationSubRoute = ($adminRoute === 'articles' && $adminSubRoute === 'menu')
                || ($adminRoute === 'contact' && $adminSubRoute === 'settings');
            $adminAreaClass = !$isConfigurationSubRoute
                && ($adminAreaAccess === 'editor' || $adminRoute === 'dwl')
                ? 'admin-area-content'
                : 'admin-area-config';
        }
    }
}
$template->set('admin_area_class', $adminAreaClass);

// ActualitesAdminFix 1: follow the module enabled state.
$adminNewsModule = $repositories->modules()->findByPrefix('news');
$adminNewsEnabled = is_array($adminNewsModule) && !empty($adminNewsModule['enabled']);
$template->set('admin_news_enabled', $adminNewsEnabled ? '1' : '0');

if ($route === 'Admin') {
    $template->set('admin_home_current', $adminRoute === '' ? 'is-current' : '');
    $template->set('admin_news_current', $adminRoute === 'news' ? 'is-current' : '');
    $template->set('admin_users_current', $adminRoute === 'users' ? 'is-current' : '');
    $template->set('admin_config_current', $adminRoute === 'config' ? 'is-current' : '');
    $template->set('admin_modules_current', $adminRoute === 'modmgr' ? 'is-current' : '');
$template->set('admin_diagnostics_current', $adminRoute === 'diagnostics' ? 'is-current' : '');
}

if (!$canAccessAdmin && $route === 'Admin') {
    $template->setSlot('main', './var/admin/templates/login-required.tpl');
}

if ($canAccessAdmin && $route === 'Admin') {
    switch ($adminRoute) {
        case '':
            $visibleModuleCount = 0;
            $enabledModuleCount = 0;
            foreach ($modules as $currentModule) {
                $modulePath = basename((string) $currentModule['path']);
                if (is_file('./var/modules/' . $modulePath . '/AUTH')) {
                    continue;
                }
                $visibleModuleCount++;
                if (!empty($currentModule['enabled'])) {
                    $enabledModuleCount++;
                }
            }
            $updates = [];
            $coreUpdate = '';
            if ($isAdmin) {
                $root = dirname(__DIR__);
                $version = trim((string) file_get_contents($root . '/VERSION'));
                $themes = (new \Portix\Theme\ThemeCatalog($root, $version))->all();
                $updates = \Portix\Maintenance\ExtensionUpdates::dashboard($root, $modules, $themes);
                $coreUpdate = \Portix\Maintenance\ExtensionUpdates::core($version);
            }
            foreach (['module', 'theme'] as $kind) {
                $count = count($updates[$kind] ?? []);
                $template->set('admin_' . $kind . '_updates', $count > 0
                    ? sprintf($translator->text($count === 1 ? 'dashboard_update_one' : 'dashboard_update_many'), $count)
                    : '');
            }
            $template->set('extension_updates_download', $translator->text('extension_updates_download'));
            $template->set('admin_core_update', $coreUpdate);
            $template->set('admin_module_count', (string) $visibleModuleCount);
            $template->set('admin_enabled_module_count', (string) $enabledModuleCount);
            $template->set('admin_news_count', $adminNewsEnabled ? (string) $repositories->news()->countAll() : '0');
            $template->set('admin_published_news_count', $adminNewsEnabled ? (string) $repositories->news()->countPublished() : '0');
            $template->set('admin_public_theme', basename((string) $CONFIG['template']));
            $template->setSlot('main', './var/admin/templates/dashboard.tpl');
            break;

        case 'news':
            if (!$adminNewsEnabled) {
                http_response_code(404);
                $template->setSlot('main', './var/admin/templates/not-found.tpl');
                break;
            }
            $reservedSlugs = [
                'accueil', 'admin', 'install', 'index', 'news',
                'disconnect', 'login', 'personnal', 'forgot-password', 'reset-password', 'usrmgr',
            ];
            foreach ($modules as $currentModule) {
                $reservedSlugs[] = strtolower(trim((string) $currentModule['prefix']));
            }
            (new \Portix\Controller\Admin\NewsAdminController(
                $repositories->news(),
                dirname(__DIR__),
                array_values(array_unique($reservedSlugs)),
                $translator,
                $repositories->contentSafety()
            ))->handle($template, $argument, $user);
            break;

        case 'users':
            if (!$isAdmin) {
                http_response_code(403);
                $template->setSlot('main', './var/admin/templates/access-denied.tpl');
                break;
            }
            (new \Portix\Controller\Admin\UserAdminController(
                $repositories->users(),
                new \Portix\Observability\EventLogger($database->connection()),
                $translator,
                $requestId
            ))->handle($template, $argument, $user);
            break;

        case 'modmgr':
            if ($isAdmin) {
                include './includes/modmgr.php';
            } else {
                http_response_code(403);
                $template->setSlot('main', './var/admin/templates/not-found.tpl');
            }
            break;

        case 'config':
            if ($isAdmin) {
                include ($argument[2] ?? '') === 'themes' ? './includes/themes.php' : './includes/config.php';
            } else {
                http_response_code(403);
                $template->setSlot('main', './var/admin/templates/not-found.tpl');
            }
            break;

        case 'diagnostics':
            if (!$isAdmin) {
                http_response_code(403);
                $template->setSlot('main', './var/admin/templates/access-denied.tpl');
                break;
            }
            (new \Portix\Controller\Admin\DiagnosticAdminController(
                new \Portix\Diagnostics\DiagnosticRunner(dirname(__DIR__), $database->connection()),
                new \Portix\Observability\EventLogger($database->connection()),
                $translator,
                $requestId,
                dirname(__DIR__)
            ))->handle($template, $user);
            break;

        default:
            if ($canAccessAdmin && $adminRoute !== '') {
                $description = $repositories->modules()->findByPrefix($adminRoute);
                $adminAccess = is_array($description) ? $moduleAdminAccess($description) : 'none';
                $canConfigureModule = $isAdmin || ($isRedactor && $adminAccess === 'editor');
                if ($canConfigureModule && is_array($description) && !empty($description['enabled'])) {
                    // Les fichiers de configuration des modules reçoivent ce contexte.
                    $ModAdmin = $description;
                    $modulePath = basename((string) $description['path']);
                    $configFile = './var/modules/' . $modulePath . '/config.php';
                    if (is_file($configFile)) {
                        $language = basename((string) $CONFIG['lang']);
                        $defaultLanguage = basename((string) $description['default_language']);
                        $languageFile = './var/modules/' . $modulePath . '/lang/' . $language . '.php';
                        $fallbackFile = './var/modules/' . $modulePath . '/lang/' . $defaultLanguage . '.php';
                        if (is_file($languageFile)) {
                            include $languageFile;
                        } elseif (is_file($fallbackFile)) {
                            include $fallbackFile;
                        }
                        $translator = new \Portix\Support\Translator($_LANG);
                        include $configFile;
                    } else {
                        $template->setSlot('main', './var/admin/templates/not-found.tpl');
                    }
                } else {
                    http_response_code(403);
                    $template->setSlot('main', './var/admin/templates/not-found.tpl');
                }
            } else {
                http_response_code(403);
                $template->setSlot('main', './var/admin/templates/not-found.tpl');
            }
    }
}

foreach ($modules as $currentModule) {
    if (!empty($currentModule['enabled'])
        && is_file('./var/modules/' . basename((string) $currentModule['path']) . '/config.php')) {
        $adminAccess = $moduleAdminAccess($currentModule);
        if ($adminAccess === 'editor' && $canAccessAdmin) {
            $template->addRow('contentmodules', array(
                'name' => trim((string) $currentModule['name']),
                'prefix' => trim((string) $currentModule['prefix']),
                'icon' => trim((string) $currentModule['prefix']) === 'affiliation' ? 'dollar-sign' : '',
                'current' => $route === 'Admin'
                    && $adminRoute === trim((string) $currentModule['prefix'])
                    ? 'is-current'
                    : '',
            ));
        } elseif ($isAdmin && $adminAccess === 'admin') {
            $targetCollection = trim((string) $currentModule['prefix']) === 'dwl'
                ? 'admincontentmodules'
                : 'modulesadmin';
            $template->addRow($targetCollection, array(
                'name' => trim((string) $currentModule['name']),
                'prefix' => trim((string) $currentModule['prefix']),
                'icon' => trim((string) $currentModule['prefix']) === 'affiliation' ? 'dollar-sign' : '',
                'current' => $route === 'Admin'
                    && $adminRoute === trim((string) $currentModule['prefix'])
                    ? 'is-current'
                    : '',
            ));
        }
    }
}

if ($route === '__not_found') {
    http_response_code(404);
    $pageMetadata->noIndex();
    $activeTheme = basename((string) ($CONFIG['template'] ?? 'modern'));
    $notFoundTemplate = './var/templates/' . $activeTheme . '/not-found.tpl';
    if (!is_file($notFoundTemplate)) {
        $notFoundTemplate = './var/templates/modern/not-found.tpl';
    }
    $template->setSlot('main', $notFoundTemplate);
}

include './includes/lang.php';

if (isset($insightsTracker) && is_callable($insightsTracker)) {
    $insightsTracker();
}

if (isset($user)) {
    unset($user);
}
