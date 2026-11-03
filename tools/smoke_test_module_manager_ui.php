<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);

try {
    $template = (string) file_get_contents($root . '/var/admin/templates/modmgr.tpl');
    $css = (string) file_get_contents($root . '/var/admin/style.css');

    foreach ([
        'module-list-header',
        'module-action-state',
        'module-action-uninstall',
        'button-danger',
        'modules_state_action_column',
    ] as $marker) {
        if (!str_contains($template . $css, $marker)) {
            throw new RuntimeException('Interface modules incomplète : ' . $marker);
        }
    }

    if (str_contains($template, 'class="button-disable">{{ modules_uninstall }}')) {
        throw new RuntimeException('Désinstaller utilise encore la couleur de désactivation.');
    }
    if (!str_contains($css, 'grid-template-columns: 9rem 11.5rem')) {
        throw new RuntimeException('Colonnes d’action modules non alignées.');
    }

    echo "Gestionnaire de modules : couleurs et colonnes d’action OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test interface modules : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
