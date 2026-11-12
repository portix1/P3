<?php
declare(strict_types=1);

use Portix\View\Html;
use Portix\View\Template;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
$temporary = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix3-template-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.tpl';

try {
    if (is_file($root . '/includes/class/template.class.php')) {
        throw new RuntimeException('L’ancien moteur global est encore livré.');
    }
    $templates = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/var', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'tpl') {
            $templates[] = $file->getPathname();
        }
    }
    sort($templates);
    if ($templates === []) {
        throw new RuntimeException('Aucun template à valider.');
    }
    foreach ($templates as $file) {
        (new Template($file))->validate();
        $source = (string) file_get_contents($file);
        if (preg_match('/<IF\s|<ENDIF>|<!--\s*(?:BEGIN|END|CHILD|FILE)\b/', $source) === 1) {
            throw new RuntimeException('Syntaxe historique dans ' . substr($file, strlen($root) + 1));
        }
        if (preg_match_all('/\{%\s*include\s+"([^"]+)"\s*%\}/', $source, $includeMatches) > 0) {
            foreach ($includeMatches[1] as $includedFile) {
                $includedFile = (string) $includedFile;
                $candidate = str_starts_with($includedFile, './') || str_starts_with($includedFile, '../')
                    ? dirname($file) . DIRECTORY_SEPARATOR . $includedFile
                    : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $includedFile);
                if (!is_file($candidate)) {
                    throw new RuntimeException(
                        'Include de template introuvable dans '
                        . substr($file, strlen($root) + 1)
                        . ' : ' . $includedFile
                    );
                }
            }
        }
    }

    $themeDirectories = glob($root . '/var/templates/*', GLOB_ONLYDIR) ?: [];
    $renderedThemes = 0;
    foreach ($themeDirectories as $themeDirectory) {
        $themeName = basename($themeDirectory);
        $themeFile = $themeDirectory . '/index.tpl';
        if (!is_file($themeFile)) {
            continue;
        }
        $theme = new Template($themeFile);
        foreach ([
            'language_code' => 'fr',
            'base_url' => '/P3',
            'document_title' => 'Portix 3',
            'meta_has_description' => '0',
            'meta_description' => '',
            'meta_has_canonical' => '0',
            'meta_canonical' => '',
            'meta_og_type' => 'website',
            'meta_has_image' => '0',
            'meta_image' => '',
            'meta_robots' => 'index,follow',
            'meta_has_json_ld' => '0',
            'meta_json_ld' => '',
            'ui_skip_content' => 'Aller au contenu',
            'ui_home' => 'Accueil',
            'title' => 'Portix 3',
            'ui_powered_by' => 'Propulsé par Portix 3',
            'is_authenticated' => '0',
            'can_access_admin' => '0',
            'is_admin' => '0',
            'ui_menu' => 'Menu',
            'ui_main_navigation' => 'Navigation principale',
            'has_auxiliary_sidebar' => '0',
            'ui_auxiliary_navigation' => 'Navigation secondaire',
            'portix_version' => '0.7.06',
            'asset_version' => '1700000000',
            'ui_version' => 'version',
            'ui_original_project' => 'Projet original',
            'ui_login' => 'Connexion',
        ] as $name => $value) {
            $theme->set($name, $value);
        }
        $theme->addRow('navigation', [
            'name' => 'Accueil',
            'link' => 'accueil.html',
            'current' => 'is-current',
            'item_class' => '',
            'has_submenu' => '0',
            'submenu_id' => '',
            'toggle_label' => '',
            'submenu' => '',
        ]);
        $renderedTheme = $theme->render();
        if (!str_contains($renderedTheme, 'var/templates/' . $themeName . '/style.min.css')) {
            throw new RuntimeException('Le thème ' . $themeName . ' ne charge pas sa feuille publique minifiée.');
        }
        if (!str_contains($renderedTheme, 'style.min.css?v=1700000000')) {
            throw new RuntimeException('Le thème ' . $themeName . ' ne versionne pas sa feuille de style.');
        }
        $isClair = (bool) preg_match('/^six-cordes-ux-clair(?:-v[0-9]{1,3})?$/D', $themeName);
        $expectedCss = $isClair ? 2 : 1;
        if (substr_count($renderedTheme, 'rel="stylesheet"') !== $expectedCss
            || ($isClair && substr_count($renderedTheme, 'var/editor/mobile-header-p1.css?v=20261112') !== 1)
            || (!$isClair && str_contains($renderedTheme, 'var/editor/mobile-header-p1.css'))
            || str_contains($renderedTheme, 'portix-content.css')) {
            throw new RuntimeException('Le thème ' . $themeName . ' charge un jeu de feuilles CSS inattendu.');
        }
        if (!str_contains($renderedTheme, 'href="login.html"')) {
            throw new RuntimeException('Le thème ' . $themeName . ' ne conserve pas le lien de connexion discret.');
        }
        $renderedThemes++;
    }
    if ($renderedThemes < 1) {
        throw new RuntimeException('Aucun thème public complet à rendre.');
    }

    $guitarHeader = $root . '/var/templates/univers-guitare/assets/guitar-fretboard-header-0703.webp';
    if (!is_file($guitarHeader) || filesize($guitarHeader) < 25000 || filesize($guitarHeader) > 300000) {
        throw new RuntimeException('Le fond photographique du thème Univers Guitare est absent ou incomplet.');
    }
    $guitarCss = (string) file_get_contents($root . '/var/templates/univers-guitare/style.css');
    if (!str_contains($guitarCss, 'assets/guitar-fretboard-header-0703.webp')) {
        throw new RuntimeException('Le thème Univers Guitare ne charge pas son fond de header.');
    }

    $articlesForm = (string) file_get_contents($root . '/var/modules/articles/templates/admin-form.tpl');
    $articlesPublic = (string) file_get_contents($root . '/var/modules/articles/templates/index.tpl');
    if (!str_contains($articlesForm, 'name="summary"')
        || !str_contains($articlesForm, 'maxlength="170"')) {
        throw new RuntimeException('La description SEO facultative des Articles doit rester éditable.');
    }
    if (str_contains($articlesPublic, 'showarticle.summary')
        || str_contains($articlesPublic, 'articleitem.summary')) {
        throw new RuntimeException('La description SEO ne doit pas être affichée comme résumé public automatique.');
    }

    $newsController = (string) file_get_contents($root . '/var/modules/news/index.php');
    $newsPublic = (string) file_get_contents($root . '/var/modules/news/templates/index.tpl');
    if (!str_contains($newsController, "set('news_listing', '1')")
        || !str_contains($newsController, "set('news_listing', '0')")
        || substr_count($newsPublic, '<h1>') < 2
        || !str_contains($newsPublic, '{% if news_listing == "1" %}')) {
        throw new RuntimeException('La liste et le détail des Actualités doivent conserver un titre h1 unique.');
    }

    file_put_contents(
        $temporary,
        '{% if visible == "1" %}{% for item in items %}{{ item.name }}:{{ item.html }};{% endfor %}{% endif %}'
    );
    $template = new Template($temporary);
    $template->set('visible', '1');
    $template->addRow('items', ['name' => '<Marc>', 'html' => Html::trusted('<strong>OK</strong>')]);
    $rendered = $template->render();
    if ($rendered !== '&lt;Marc&gt;:<strong>OK</strong>;') {
        throw new RuntimeException('Échappement ou boucle incorrecte : ' . $rendered);
    }

    echo count($templates) . " template(s) compilé(s).\n";
    echo "Moteur de rendu et thèmes publics : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test du moteur de templates : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    @unlink($temporary);
}
