<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
$modulesRoot = $root . '/var/modules';

try {
    $directories = glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [];
    $count = 0;

    foreach ($directories as $directory) {
        $manifest = $directory . '/desc.xml';
        if (!is_file($manifest)) {
            continue;
        }
        $count++;
        $xml = (string) file_get_contents($manifest);
        foreach (['Name', 'Prefix', 'Type', 'Path', 'Enabled', 'Version'] as $tag) {
            if (preg_match('#<' . $tag . '>\s*[^<]+\s*</' . $tag . '>#s', $xml) !== 1) {
                throw new RuntimeException(basename($directory) . ' : balise ' . $tag . ' absente ou vide.');
            }
        }

        preg_match('#<Version>\s*([^<]+)\s*</Version>#s', $xml, $versionMatch);
        $version = trim((string) ($versionMatch[1] ?? ''));
        $versionFile = $directory . '/MODULE-VERSION';
        if (!is_file($versionFile) || trim((string) file_get_contents($versionFile)) !== $version) {
            throw new RuntimeException(basename($directory) . ' : version incohérente.');
        }

        foreach (glob($directory . '/database/*/desc.csv') ?: [] as $schema) {
            if (trim((string) file_get_contents($schema)) === '') {
                throw new RuntimeException(basename($directory) . ' : schéma vide ' . basename(dirname($schema)) . '.');
            }
        }
    }

    if ($count < 1) {
        throw new RuntimeException('Aucun module installable trouvé.');
    }

    echo $count . " module(s) : manifest, version et schémas statiques OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test packages modules : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
