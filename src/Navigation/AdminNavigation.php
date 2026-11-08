<?php
declare(strict_types=1);

namespace Portix\Navigation;

use Portix\Module\ModuleManifest;
use Portix\Support\Translator;
use Throwable;

final class AdminNavigation
{
    public static function sections(string $root, array $modules, bool $isAdmin, bool $isRedactor, array $arguments, Translator $translator, string $language = 'fr'): array
    {
        $sections = ['writing' => [], 'management' => [], 'configuration' => []];
        if (!$isAdmin && !$isRedactor) {
            return $sections;
        }
        $add = static function (string $section, string $label, string $url, string $icon, string $match = '') use (&$sections, $translator): void {
            $sections[$section][] = [
                'name' => $translator->text($label, $label),
                'url' => $url,
                'icon' => $icon,
                'match' => $match !== '' ? $match : basename($url, '.html'),
                'current' => '',
            ];
        };
        if ($isAdmin) {
            $add('management', 'ux_accounts', 'Admin-users.html', 'users');
            $add('management', 'ux_modules', 'Admin-modmgr.html', 'boxes');
            $add('management', 'diagnostics_nav', 'Admin-diagnostics.html', 'activity');
            $add('configuration', 'ux_identity', 'Admin-config.html', 'settings', 'Admin-config');
            $add('configuration', 'ux_themes', 'Admin-config-themes.html', 'images');
            $add('configuration', 'ux_seo', 'Admin-config-seo.html', 'search');
            $add('configuration', 'ux_mail', 'Admin-config-mail.html', 'mail');
        }

        $places = [
            'articles' => ['writing', 'file-text'], 'news' => ['writing', 'newspaper'],
            'media' => ['writing', 'images'], 'menu' => ['writing', 'menu'],
            'footer' => ['writing', 'panel-bottom'], 'dwl' => ['writing', 'download'],
            'affiliation' => ['writing', 'dollar-sign'], 'contact' => ['management', 'mail'],
            'insights' => ['management', 'chart-bar'], 'search' => ['management', 'search'],
            'safety' => ['management', 'triangle-alert'],
        ];
        $settings = ['contact', 'affiliation', 'insights', 'safety'];
        foreach ($modules as $module) {
            $path = (string) $module['path'];
            if ($path !== basename($path) || $path === '.' || $path === '..') {
                continue;
            }
            $directory = $root . '/var/modules/' . $path;
            if (empty($module['enabled']) || is_file($directory . '/AUTH')
                || ((string) $module['prefix'] !== 'news' && !is_file($directory . '/config.php'))) {
                continue;
            }
            try {
                $manifest = ModuleManifest::fromXml($directory . '/desc.xml', $path);
            } catch (Throwable) {
                continue;
            }
            $access = $manifest['prefix'] === 'news' ? 'editor' : $manifest['admin_access'];
            if ($access === 'none' || (!$isAdmin && $access !== 'editor')) {
                continue;
            }
            $prefix = $manifest['prefix'];
            if ($manifest['admin_navigation'] !== []) {
                $moduleMessages = (static function (string $directory, string $language, string $fallback): array {
                    $_LANG = [];
                    $file = $directory . '/lang/' . basename($language) . '.php';
                    if (!is_file($file)) { $file = $directory . '/lang/' . basename($fallback) . '.php'; }
                    if (is_file($file) && !is_link($file)) { include $file; }
                    return $_LANG;
                })($directory, $language, $manifest['default_language']);
                $moduleTranslator = new Translator($moduleMessages);
                foreach ($manifest['admin_navigation'] as $item) {
                    if (!$isAdmin && $item['role'] !== 'editor') { continue; }
                    $url = 'Admin-' . $prefix . ($item['action'] !== '' ? '-' . $item['action'] : '') . '.html';
                    $add($item['area'], $moduleTranslator->text($item['label'], $translator->text($item['label'], $manifest['name'])), $url, $item['icon']);
                }
                continue;
            }
            [$section, $icon] = $places[$prefix] ?? [$access === 'editor' ? 'writing' : 'management', 'boxes'];
            // Existing editor permissions continue to expose the Contact inbox.
            if (!$isAdmin && $section === 'management') {
                $section = 'writing';
            }
            $add($section, $manifest['name'], 'Admin-' . $prefix . '.html', $icon);
            if ($isAdmin && $prefix === 'articles') {
                $add('configuration', 'ux_settings_articles', 'Admin-articles-menu.html', 'menu');
            }
            if ($isAdmin && in_array($prefix, $settings, true)) {
                $add('configuration', 'ux_settings_' . $prefix, 'Admin-' . $prefix . '-settings.html', 'settings');
            }
        }

        $route = implode('-', $arguments);
        $active = null;
        $longest = -1;
        foreach ($sections as $section => $entries) {
            foreach ($entries as $index => $entry) {
                $match = $entry['match'];
                if (($route === $match || str_starts_with($route, $match . '-')) && strlen($match) > $longest) {
                    $active = [$section, $index];
                    $longest = strlen($match);
                }
            }
        }
        if ($active !== null) {
            $sections[$active[0]][$active[1]]['current'] = 'is-current';
        }
        return $sections;
    }
}
