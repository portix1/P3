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
        $modules[] = ModuleManifest::fromXml($file, basename(dirname($file)));
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

    $news = array_values(array_filter($sections['writing'], static fn (array $item): bool => $item['url'] === 'Admin-news.html'));
    navigationCheck(count($news) === 1 && $news[0]['current'] === 'is-current', 'Actualités doit être présent et actif en Rédaction');
    $editor = AdminNavigation::sections($root, $modules, false, true, ['Admin', 'news'], $translator);
    navigationCheck(in_array('Admin-news.html', array_column($editor['writing'], 'url'), true), 'Actualités doit rester accessible aux rédacteurs');
    navigationCheck($editor['management'] === [] && $editor['configuration'] === [], 'Espaces réservés masqués au rédacteur');

    navigationCheck(in_array('Admin-home.html', array_column($editor['writing'], 'url'), true), 'Accueil accessible en Rédaction');
    navigationCheck(in_array('Admin-home-settings.html', array_column($sections['configuration'], 'url'), true), 'Réglages Accueil en Configuration');
    navigationCheck(in_array('Admin-home-report.html', array_column($sections['management'], 'url'), true), 'Résultats Accueil en Administration');
    $homeDisabled = array_map(static function (array $module): array { if ($module['prefix'] === 'home') { $module['enabled'] = false; } return $module; }, $modules);
    $homeHidden = AdminNavigation::sections($root, $homeDisabled, true, false, ['Admin'], $translator);
    foreach ($homeHidden as $entries) { foreach ($entries as $entry) { navigationCheck(!str_starts_with($entry['url'], 'Admin-home'), 'Accueil désactivé masqué dans tous les espaces'); } }

    $disabled = array_map(static function (array $module): array {
        if ($module['prefix'] === 'news') { $module['enabled'] = false; }
        return $module;
    }, $modules);
    $hidden = AdminNavigation::sections($root, $disabled, true, false, ['Admin'], $translator);
    navigationCheck(!in_array('Admin-news.html', array_column($hidden['writing'], 'url'), true), 'Actualités désactivé doit être masqué');
    $anonymous = AdminNavigation::sections($root, $modules, false, false, ['Admin'], $translator);
    navigationCheck($anonymous === ['writing' => [], 'management' => [], 'configuration' => []], 'Visiteur sans navigation privée');
    navigationCheck(in_array('Admin-config-themes.html', array_column($sections['configuration'], 'url'), true), 'Thèmes dans Configuration');
    echo "Navigation complète des modules, Actualités, rôles et désactivation : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
