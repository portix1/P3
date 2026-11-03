<?php
declare(strict_types=1);

namespace Portix\Theme;

final class ThemeContract
{
    public const VERSION = '1';

    /** @return list<string> */
    public static function validate(string $root, string $theme): array
    {
        $errors = [];
        $directory = rtrim($root, '/\\') . '/var/templates/' . $theme;
        if (!is_dir($directory)) {
            return ['Thème introuvable : ' . $theme];
        }
        foreach (['index.tpl', 'menu.tpl', 'not-found.tpl', 'style.css', 'style.min.css'] as $file) {
            if (!is_file($directory . '/' . $file)) {
                $errors[] = 'Fichier requis absent : ' . $file;
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        $index = (string) file_get_contents($directory . '/index.tpl');
        $menu = (string) file_get_contents($directory . '/menu.tpl');
        $notFound = (string) file_get_contents($directory . '/not-found.tpl');
        $style = (string) file_get_contents($directory . '/style.css');
        $minified = (string) file_get_contents($directory . '/style.min.css');

        if (!str_contains(strtolower($index), '<!doctype html>')) {
            $errors[] = 'index.tpl : doctype HTML5 absent.';
        }
        foreach ([
            '{{ language_code }}' => 'langue du document',
            '{{ base_url }}' => 'URL de base',
            '{{ document_title }}' => 'titre du document',
            '{{ meta_robots }}' => 'directive robots',
            '{{ title }}' => 'nom du site',
            '{{ asset_version }}' => 'version des ressources',
            '{{ ui_skip_content }}' => 'lien d’évitement',
            '{{ ui_main_navigation }}' => 'libellé navigation principale',
            '{% for navigation in navigation %}' => 'navigation principale',
            '{% slot main %}' => 'contenu principal',
            'id="main-content"' => 'ancre du contenu principal',
            'var/editor/portix-ui.js?v={{ asset_version }}' => 'script UI versionné',
        ] as $marker => $label) {
            if (!str_contains($index, $marker)) {
                $errors[] = 'index.tpl : élément contractuel absent (' . $label . ').';
            }
        }

        $expectedCss = 'var/templates/' . $theme . '/style.min.css?v={{ asset_version }}';
        if (!str_contains($index, $expectedCss)) {
            $errors[] = 'index.tpl : feuille CSS publique contractuelle absente ou mal référencée.';
        }
        if (preg_match('/<link\b[^>]*(?:rel=["\']stylesheet["\'][^>]*href=["\']https?:\/\/|href=["\']https?:\/\/[^"\']+["\'][^>]*rel=["\']stylesheet["\'])/i', $index) === 1) {
            $errors[] = 'index.tpl : feuille CSS distante interdite par le contrat de thème.';
        }

        foreach (['{% for menu in menu %}', '{{ menu.link }}', '{{ menu.title }}'] as $marker) {
            if (!str_contains($menu, $marker)) {
                $errors[] = 'menu.tpl : structure de menu auxiliaire incomplète (' . $marker . ').';
            }
        }
        foreach (['<h1', '{{ error_404 }}', '{{ page_not_found_title }}', '{{ page_not_found_description }}', '{{ back_to_home }}'] as $marker) {
            if (!str_contains($notFound, $marker)) {
                $errors[] = 'not-found.tpl : élément 404 contractuel absent (' . $marker . ').';
            }
        }
        if (trim($style) === '') {
            $errors[] = 'style.css : feuille source vide.';
        }
        if (trim($minified) === '') {
            $errors[] = 'style.min.css : feuille compilée vide.';
        }
        if (str_contains($minified, '/*') || str_contains(trim($minified), "\n")) {
            $errors[] = 'style.min.css : feuille non minifiée.';
        }

        return $errors;
    }

    /** @return array<string,list<string>> */
    public static function validateAll(string $root): array
    {
        $base = rtrim($root, '/\\') . '/var/templates';
        $results = [];
        foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $theme = basename($directory);
            if ($theme === 'shared' || !is_file($directory . '/index.tpl')) {
                continue;
            }
            $results[$theme] = self::validate($root, $theme);
        }
        ksort($results);
        return $results;
    }
}
