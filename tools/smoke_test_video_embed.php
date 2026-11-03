<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
$editor = (string) file_get_contents($root . '/var/editor/portix-editor.js');
$publicUi = (string) file_get_contents($root . '/var/editor/portix-ui.js');
$sanitizer = (string) file_get_contents($root . '/includes/class/html_sanitizer.class.php');
$contentCss = (string) file_get_contents($root . '/var/editor/portix-content.css');
$admin = (string) file_get_contents($root . '/var/admin/index.tpl');

try {
    foreach ([
        'youtube.com',
        'youtu.be',
        "segments[0] === 'shorts'",
        'vimeo.com',
        'dailymotion.com',
        'dai.ly',
        'youtube-nocookie.com',
        'registerVideoFormat',
        'createVideoTools',
        "data-width",
        "'portix-video'",
    ] as $marker) {
        if (!str_contains($editor, $marker)) {
            throw new RuntimeException('Éditeur vidéo incomplet : ' . $marker);
        }
    }

    foreach ([
        'renderPortixVideos',
        'youtube-nocookie.com',
        'player.vimeo.com/video/',
        'dailymotion.com/embed/video/',
    ] as $marker) {
        if (!str_contains($publicUi, $marker)) {
            throw new RuntimeException('Rendu public vidéo incomplet : ' . $marker);
        }
    }

    if (!str_contains($sanitizer, "'iframe'") || !str_contains($sanitizer, 'isAllowedVideo')) {
        throw new RuntimeException('Sanitisation vidéo incomplète.');
    }
    if (!str_contains($contentCss, '.portix-video iframe')
        || !str_contains($contentCss, '.portix-video[data-width="50"]')
        || !str_contains($admin, 'data-editor-video=')) {
        throw new RuntimeException('Styles ou traductions vidéo incomplets.');
    }

    echo "Vidéo WYSIWYG : providers, sanitisation et rendu public OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test vidéo WYSIWYG : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
