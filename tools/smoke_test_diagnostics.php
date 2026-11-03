<?php
declare(strict_types=1);

use Portix\Diagnostics\DiagnosticRunner;
use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

try {
    $source = (string) file_get_contents($root . '/src/Diagnostics/DiagnosticRunner.php');
    foreach ([
        "['quick', 'full']",
        'ThemeContract::validateAll',
        'PRAGMA integrity_check',
        'addWriteCheck',
    ] as $marker) {
        if (!str_contains($source, $marker)) {
            throw new RuntimeException('Runner incomplet : ' . $marker);
        }
    }

    $engine = (string) file_get_contents($root . '/includes/engine.php');
    $template = (string) file_get_contents($root . '/var/admin/templates/diagnostics.tpl');
    $dashboard = (string) file_get_contents($root . '/var/admin/templates/dashboard.tpl');
    foreach ([
        'DiagnosticAdminController',
        'Admin-diagnostics.html',
    ] as $marker) {
        if (!str_contains($engine . $template . $dashboard, $marker)) {
            throw new RuntimeException('Intégration Diagnostics incomplète : ' . $marker);
        }
    }

    if (!str_contains((string) file_get_contents($root . '/src/Controller/Admin/DiagnosticAdminController.php'), 'PortixSecurity::requirePostWithCsrf')) {
        throw new RuntimeException('Protection CSRF Diagnostics absente.');
    }
    if (str_contains($source, 'exec(') || str_contains($source, 'shell_exec(') || str_contains($source, 'proc_open(')) {
        throw new RuntimeException('Le diagnostic Web ne doit pas lancer de commande système.');
    }

    echo "Diagnostics admin : intégration, liste fermée et sécurité statique OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test diagnostics : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
