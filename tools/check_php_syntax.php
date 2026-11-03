<?php
/** Parse every PHP file with the running PHP engine (no subprocess needed). */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Cet outil doit être exécuté en ligne de commande.\n");
}

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
$files = array();
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$failures = array();
foreach ($files as $file) {
    try {
        token_get_all((string) file_get_contents($file), TOKEN_PARSE);
    } catch (ParseError $error) {
        $failures[] = substr($file, strlen($root) + 1) . ': ' . $error->getMessage();
    }
}

echo count($files) . " fichier(s) PHP analysé(s).\n";
if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    exit(1);
}

echo "Aucune erreur de syntaxe.\n";
