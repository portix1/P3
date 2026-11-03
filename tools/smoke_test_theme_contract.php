<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

try {
    $results = \Portix\Theme\ThemeContract::validateAll($root);
    if ($results === []) {
        throw new RuntimeException('Aucun thème public à valider.');
    }
    foreach ($results as $theme => $errors) {
        if ($errors !== []) {
            throw new RuntimeException($theme . ' : ' . implode(' | ', $errors));
        }
    }
    echo count($results) . ' thème(s) : contrat formel v' . \Portix\Theme\ThemeContract::VERSION . " OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test contrat de thème : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
