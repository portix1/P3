<?php
require_once dirname(__DIR__) . '/includes/class/zip.class.php';

$root = sys_get_temp_dir() . '/portix2-zip-' . bin2hex(random_bytes(6));
$source = $root . '/source';
$archive = $root . '/test.zip';
try {
    if (!mkdir($source, 0770, true)) {
        throw new RuntimeException('Impossible de créer le dossier temporaire.');
    }
    file_put_contents($source . '/empty.log', '');
    file_put_contents($source . '/filled.log', "test\n");
    PortixZip::createFromDirectory($archive, $source);
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true || $zip->numFiles !== 2) {
        throw new RuntimeException('Archive de test invalide.');
    }
    $zip->close();
    if (!PortixZip::isReadableArchive($archive)) {
        throw new RuntimeException('Le contrôle d’intégrité refuse une archive valide.');
    }
    $invalidArchive = $root . '/invalid.zip';
    file_put_contents($invalidArchive, "PK\x03\x04archive interrompue");
    if (PortixZip::isReadableArchive($invalidArchive)) {
        throw new RuntimeException('Le contrôle d’intégrité accepte une archive interrompue.');
    }
    echo "ZipArchive : fichiers ordinaires et vides archivés avec succès.\n";
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
}
