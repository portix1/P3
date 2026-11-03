<?php
/** End-to-end smoke test for secure image decoding and local storage. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/class/image_storage.class.php';

if (!extension_loaded('gd') || !function_exists('imagewebp')) {
    fwrite(STDERR, "L’extension GD avec support WebP est requise pour ce test.\n");
    exit(1);
}

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
    . 'portix2-image-test-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0700, true)) {
    fwrite(STDERR, "Impossible de créer le dossier temporaire.\n");
    exit(1);
}

$source = $directory . DIRECTORY_SEPARATOR . 'source.png';
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    true
);
if ($png === false || file_put_contents($source, $png) === false) {
    fwrite(STDERR, "Impossible de créer l’image de test.\n");
    exit(1);
}

try {
    $name = PortixImageStorage::storeUploadedFile(array(
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $source,
        'size' => strlen($png),
        'name' => 'source.png',
    ), $directory);
    $stored = $directory . DIRECTORY_SEPARATOR . $name;
    $description = getimagesize($stored);
    if (!preg_match('/^[a-f0-9]{32}\.webp$/', $name)
        || !is_file($stored)
        || !is_array($description)
        || ($description['mime'] ?? '') !== 'image/webp') {
        throw new RuntimeException('Le fichier optimisé est invalide.');
    }
    $html = '<figure><img src="var/uploads/images/' . $name
        . '" alt="Chevalet Fender Telecaster vintage"><figcaption>Légende de secours</figcaption></figure>';
    $renamedHtml = PortixImageStorage::optimiseReferencedFilenames($html, $directory);
    $suffix = substr(pathinfo($name, PATHINFO_FILENAME), 0, 8);
    $seoName = 'chevalet-fender-telecaster-vintage-' . $suffix . '.webp';
    if (!str_contains($renamedHtml, 'var/uploads/images/' . $seoName)
        || !is_file($directory . DIRECTORY_SEPARATOR . $seoName)
        || !is_file($stored)) {
        throw new RuntimeException('Le nom SEO dérivé du texte alternatif est invalide.');
    }
    echo "Stockage sécurisé, conversion WebP et nommage SEO des images : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: array() as $file) {
        @unlink($file);
    }
    @rmdir($directory);
}
