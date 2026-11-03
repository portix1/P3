<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/tools/build_public_css.php';

try {
    $themes = portixPublicThemeNames($root);
    if ($themes === []) {
        throw new RuntimeException('Aucun thème public complet à contrôler.');
    }

    foreach ($themes as $theme) {
        $templateFile = $root . '/var/templates/' . $theme . '/index.tpl';
        $template = (string) file_get_contents($templateFile);
        preg_match_all(
            '/<link\b[^>]*\brel="stylesheet"[^>]*\bhref="([^"]+)"[^>]*>/i',
            $template,
            $styleLinks
        );
        if (count($styleLinks[1]) !== 1) {
            throw new RuntimeException('Le thème ' . $theme . ' doit charger exactement une feuille CSS publique.');
        }
        $expectedUrl = 'var/templates/' . $theme . '/style.min.css?v={{ asset_version }}';
        if ($styleLinks[1][0] !== $expectedUrl) {
            throw new RuntimeException('Feuille publique inattendue pour le thème ' . $theme . '.');
        }
        if (str_contains($template, 'portix-content.css')) {
            throw new RuntimeException('Le thème ' . $theme . ' charge encore portix-content.css séparément.');
        }

        $compiledFile = $root . '/var/templates/' . $theme . '/style.min.css';
        if (!is_file($compiledFile)) {
            throw new RuntimeException('Feuille publique compilée absente pour le thème ' . $theme . '.');
        }
        $compiled = (string) file_get_contents($compiledFile);
        if ($compiled !== portixExpectedPublicCss($root, $theme)) {
            throw new RuntimeException('Feuille publique obsolète pour le thème ' . $theme . '.');
        }
        if (str_contains($compiled, '/*') || str_contains(trim($compiled), "\n")) {
            throw new RuntimeException('Feuille publique non minifiée pour le thème ' . $theme . '.');
        }
        foreach (['.news-rich-text{', '.portix-content-image{'] as $marker) {
            if (!str_contains($compiled, $marker)) {
                throw new RuntimeException('Styles de contenu riche absents du thème ' . $theme . ' : ' . $marker);
            }
        }
    }

    $application = (string) file_get_contents($root . '/src/Core/Application.php');
    if (!str_contains($application, "'/style.min.css'")) {
        throw new RuntimeException('Le versionnement ne suit pas la feuille publique compilée.');
    }

    echo count($themes) . " thème(s) : feuille CSS publique unique, minifiée et à jour.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test des CSS publics : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
