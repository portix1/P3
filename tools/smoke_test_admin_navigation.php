<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Portix\Module\ModuleManifest;
use Portix\Navigation\AdminNavigation;
use Portix\Support\Translator;

$root = dirname(__DIR__);
$_LANG = [];
require $root . '/var/lang/fr.php';
$translator = new Translator($_LANG);

function navigationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $modules = [];
    foreach (glob($root . '/var/modules/*/desc.xml') ?: [] as $file) {
        $definition = ModuleManifest::fromXml($file, basename(dirname($file)));
        // Exercise opt-in modules in their enabled state, without changing shipped defaults.
        if (in_array($definition['prefix'], ['home', 'dwl'], true)) { $definition['enabled'] = true; }
        $modules[] = $definition;
    }
    $sections = AdminNavigation::sections($root, $modules, true, false, ['Admin', 'news'], $translator);
    $urls = array_merge(...array_values(array_map(static fn (array $entries): array => array_column($entries, 'url'), $sections)));
    foreach ($modules as $module) {
        $directory = $root . '/var/modules/' . $module['path'];
        $hasController = is_file($directory . '/config.php') || $module['prefix'] === 'news';
        if ($module['enabled'] && $module['admin_access'] !== 'none' && !is_file($directory . '/AUTH') && $hasController) {
            navigationCheck(in_array('Admin-' . $module['prefix'] . '.html', $urls, true), 'Module administrable absent du menu : ' . $module['prefix']);
        }
    }

    navigationCheck(in_array('Admin-dwl.html', array_column($sections['management'], 'url'), true), 'Téléchargements dans Administration');
    navigationCheck(!in_array('Admin-dwl.html', array_column($sections['writing'], 'url'), true), 'Téléchargements absents de Rédaction');

    $moduleManager = AdminNavigation::sections($root, $modules, true, false, ['Admin', 'modmgr'], $translator);
    $managerEntry = array_values(array_filter($moduleManager['configuration'], static fn (array $item): bool => $item['url'] === 'Admin-config-modules.html'));
    navigationCheck(count($managerEntry) === 1 && $managerEntry[0]['current'] === 'is-current', 'Gestion des modules active dans Configuration');
    navigationCheck(!in_array('Admin-modmgr.html', array_column($moduleManager['management'], 'url'), true), 'Ancienne adresse de gestion des modules absente d’Administration');
    navigationCheck(!in_array('Admin-config-modules.html', array_column($moduleManager['management'], 'url'), true), 'Gestion des modules absente d’Administration');
    navigationCheck(in_array('Admin-config-updates.html', array_column($moduleManager['configuration'], 'url'), true),
        'Mises à jour P3 présentes dans Configuration');
    navigationCheck(in_array('Admin-safety.html', array_column($moduleManager['configuration'], 'url'), true), 'Protection des contenus dans Configuration');

    // Article structure belongs to Administration, article menu settings to Configuration.
    foreach (['Admin-articles-categories.html', 'Admin-articles-series.html', 'Admin-articles-order.html'] as $url) {
        navigationCheck(in_array($url, array_column($sections['management'], 'url'), true),
            'Organisation des articles présente en Administration : ' . $url);
        navigationCheck(!in_array($url, array_column($sections['writing'], 'url'), true),
            'Organisation des articles absente de Rédaction : ' . $url);
    }
    navigationCheck(in_array('Admin-articles-menu.html', array_column($sections['configuration'], 'url'), true),
        'Réglages du menu des articles dans Configuration');
    navigationCheck(!in_array('Admin-articles-menu.html', array_column($sections['management'], 'url'), true),
        'Réglages du menu des articles absents d’Administration');
    $articlesMenu = AdminNavigation::sections($root, $modules, true, false, ['Admin', 'articles', 'order'], $translator);
    $activeOrder = array_values(array_filter($articlesMenu['management'],
        static fn (array $item): bool => $item['url'] === 'Admin-articles-order.html' && $item['current'] === 'is-current'));
    navigationCheck(count($activeOrder) === 1, 'Page Organisation active dans Administration');

    $oldContact = tempnam(sys_get_temp_dir(), 'p3-contact-');
    try {
        $contactXml = file_get_contents($root . '/var/modules/contact/desc.xml');
        file_put_contents($oldContact, str_replace('<AdminAccess>admin</AdminAccess>', '<AdminAccess>editor</AdminAccess>', $contactXml));
        navigationCheck(ModuleManifest::fromXml($oldContact, 'contact')['admin_access'] === 'admin',
            'Un ancien manifeste Contact ne donne pas accès aux rédacteurs');
    } finally {
        unlink($oldContact);
    }
    navigationCheck(in_array('Admin-contact.html', array_column($sections['management'], 'url'), true),
        'Messagerie Contact en Administration');

    $news = array_values(array_filter($sections['writing'], static fn (array $item): bool => $item['url'] === 'Admin-news.html'));
    navigationCheck(count($news) === 1 && $news[0]['current'] === 'is-current', 'Actualités doit être présent et actif en Rédaction');
    $editor = AdminNavigation::sections($root, $modules, false, true, ['Admin', 'news'], $translator);
    navigationCheck(in_array('Admin-news.html', array_column($editor['writing'], 'url'), true), 'Actualités doit rester accessible aux rédacteurs');
    navigationCheck($editor['management'] === [], 'Administration masquée au rédacteur');
    navigationCheck(!in_array('Admin-contact.html', array_column($editor['writing'], 'url'), true),
        'Messagerie Contact masquée au rédacteur');
    $editorConfiguration = array_column($editor['configuration'], 'url');
    navigationCheck($editorConfiguration === [], 'Configuration inaccessible au rédacteur');
    navigationCheck(!in_array('Admin-config-updates.html', array_column($editor['writing'], 'url'), true),
        'Mises à jour du noyau invisibles aux rédacteurs');
    navigationCheck(!in_array('Admin-menu.html', array_column($editor['writing'], 'url'), true), 'Menu principal masqué au rédacteur');
    navigationCheck(!in_array('Admin-footer.html', array_column($editor['writing'], 'url'), true), 'Menu de bas de page masqué au rédacteur');

    if (is_dir($root.'/var/modules/home')) {
    foreach (['Admin-home.html', 'Admin-home-report.html'] as $url) {
        navigationCheck(in_array($url, array_column($sections['management'], 'url'), true), 'Accueil quotidien dans Administration');
        navigationCheck(!in_array($url, array_column([...$sections['writing'], ...$sections['configuration']], 'url'), true), 'Accueil quotidien absent des autres espaces');
    }
    navigationCheck(in_array('Admin-home-settings.html', array_column($sections['configuration'], 'url'), true), 'Réglages Accueil dans Configuration');
    navigationCheck(in_array('Admin-affiliation.html', array_column($sections['management'], 'url'), true), 'Affiliation dans Administration');
    navigationCheck(!in_array('Admin-affiliation.html', array_column($sections['writing'], 'url'), true), 'Affiliation absente de Rédaction');
    navigationCheck(in_array('Admin-footer.html', array_column($sections['configuration'], 'url'), true), 'Menu de bas de page dans Configuration');
    navigationCheck(in_array('Admin-menu.html', array_column($sections['configuration'], 'url'), true), 'Menu principal dans Configuration');
    navigationCheck(!in_array('Admin-footer.html', array_column($sections['management'], 'url'), true), 'Menu de bas de page absent d’Administration');
    navigationCheck(!in_array('Admin-menu.html', array_column($sections['writing'], 'url'), true), 'Menu principal exclu de Rédaction');
    navigationCheck(!in_array('Admin-menu.html', array_column($sections['management'], 'url'), true), 'Menu principal absent d’Administration');
    navigationCheck(in_array('Admin-diagnostics.html', array_column($sections['configuration'], 'url'), true), 'Diagnostics dans Configuration');
    $homeDisabled = array_map(static function (array $module): array { if ($module['prefix'] === 'home') { $module['enabled'] = false; } return $module; }, $modules);
    $homeHidden = AdminNavigation::sections($root, $homeDisabled, true, false, ['Admin'], $translator);
    foreach ($homeHidden as $entries) { foreach ($entries as $entry) { navigationCheck(!str_starts_with($entry['url'], 'Admin-home'), 'Accueil désactivé masqué dans tous les espaces'); } }

    }

    $disabled = array_map(static function (array $module): array {
        if ($module['prefix'] === 'news') { $module['enabled'] = false; }
        return $module;
    }, $modules);
    $hidden = AdminNavigation::sections($root, $disabled, true, false, ['Admin'], $translator);
    navigationCheck(!in_array('Admin-news.html', array_column($hidden['writing'], 'url'), true), 'Actualités désactivé doit être masqué');
    $anonymous = AdminNavigation::sections($root, $modules, false, false, ['Admin'], $translator);
    navigationCheck($anonymous === ['writing' => [], 'management' => [], 'configuration' => []], 'Visiteur sans navigation privée');
    navigationCheck(in_array('Admin-config-themes.html', array_column($sections['configuration'], 'url'), true), 'Thèmes dans Configuration');
    navigationCheck(!in_array('Admin-diagnostics.html', array_column($sections['management'], 'url'), true), 'Diagnostics exclus d’Administration');
    navigationCheck($editor['management'] === [] && $editor['configuration'] === [], 'Rédacteur cantonné à Rédaction');
    $groups = AdminNavigation::configurationGroups($sections['configuration'], ['Admin', 'configuration']);
    $owners = array_column($groups, 'module');
    navigationCheck(count($owners) === count(array_unique($owners)), 'Une entrée par composant dans Configuration');
    navigationCheck(count($groups) < count($sections['configuration']), 'Les actions sont regroupées');
    $homeSettings = array_values(array_filter($groups, static fn (array $group): bool => $group['module'] === 'home'))[0];
    navigationCheck($homeSettings['url'] === 'Admin-home-settings.html' && $homeSettings['children'] === [],
        'Réglages accueil directement accessibles dans Configuration');
    $safetyGroup = array_values(array_filter($groups, static fn (array $group): bool => $group['module'] === 'safety'))[0];
    navigationCheck(count($safetyGroup['children']) === 2, 'Protection des contenus regroupée aussi');
    $coreGroup = array_values(array_filter($groups, static fn (array $group): bool => $group['module'] === ''))[0];
    navigationCheck(count($coreGroup['children']) === 7, 'Fonctions du moteur regroupées');
    $single = array_values(array_filter($groups, static fn (array $group): bool => $group['module'] === 'menu'))[0];
    navigationCheck($single['url'] === 'Admin-menu.html' && $single['children'] === [], 'Fonction unique accessible directement');
    $activeGroups = AdminNavigation::configurationGroups(
        AdminNavigation::sections($root, $modules, true, false, ['Admin', 'home', 'settings'], $translator)['configuration'],
        ['Admin', 'home', 'settings']
    );
    $activeHome = array_values(array_filter($activeGroups, static fn (array $group): bool => $group['module'] === 'home'))[0];
    navigationCheck($activeHome['current'] === 'is-current', 'Le composant reste sélectionné sur une fonction interne');
    $hiddenGroups = AdminNavigation::configurationGroups($homeHidden['configuration'], ['Admin', 'configuration']);
    navigationCheck(!in_array('home', array_column($hiddenGroups, 'module'), true), 'Le groupe disparaît à la désactivation');
    navigationCheck(AdminNavigation::configurationGroups($editor['configuration'], ['Admin', 'configuration']) === [],
        'Aucun groupe pour le rédacteur');
    navigationCheck(AdminNavigation::configurationGroups($anonymous['configuration'], ['Admin', 'configuration']) === [],
        'Aucun groupe pour le visiteur');
    $managementGroups = AdminNavigation::workspaceGroups($articlesMenu['management'], ['Admin', 'articles', 'order'], 'management');
    $articleGroup = array_values(array_filter($managementGroups, static fn (array $group): bool => $group['module'] === 'articles'))[0];
    navigationCheck($articleGroup['name'] === 'Gestion des articles', 'Titre du menu de gestion des articles');
    navigationCheck($articleGroup['url'] === 'Admin-management-module-articles.html', 'Route du menu de gestion');
    navigationCheck(array_column($articleGroup['children'], 'url') === ['Admin-articles-categories.html', 'Admin-articles-series.html', 'Admin-articles-order.html'], 'Trois fonctions existantes regroupées');
    navigationCheck($articleGroup['current'] === 'is-current', 'Le parent reste actif sur Organisation');
    navigationCheck(count(array_column($managementGroups, 'module')) === count(array_unique(array_column($managementGroups, 'module'))), 'Une entrée par module en Administration');
    navigationCheck(AdminNavigation::workspaceGroups($editor['management'], ['Admin', 'management'], 'management') === [], 'Pas de gestion pour les rédacteurs');
    $disabledArticles = array_map(static function (array $module): array { if ($module['prefix'] === 'articles') { $module['enabled'] = false; } return $module; }, $modules);
    $withoutArticles = AdminNavigation::sections($root, $disabledArticles, true, false, ['Admin', 'management'], $translator);
    navigationCheck(!in_array('articles', array_column(AdminNavigation::workspaceGroups($withoutArticles['management'], ['Admin', 'management'], 'management'), 'module'), true), 'Groupe retiré si Articles est désactivé');
    echo "Navigation complète des modules, Actualités, rôles et désactivation : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
