<?php
declare(strict_types=1);

use Portix\Seo\PageMetadata;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

try {
    $description = PageMetadata::descriptionText('<p>Premier paragraphe.</p><p>Deuxième paragraphe.</p>');
    if ($description !== 'Premier paragraphe. Deuxième paragraphe.') {
        throw new RuntimeException('Les paragraphes HTML sont collés dans la meta description.');
    }

    $metadata = new PageMetadata(
        'Univers Guitare',
        'Description générale',
        'https://www.universguitare.be',
        'Marc',
        'Univers Guitare',
        '',
        'var/templates/univers-guitare/assets/guitar-fretboard-header-0703.webp'
    );
    $metadata->define('Actualités', '', '/');
    if ($metadata->canonicalUrl() !== 'https://www.universguitare.be/') {
        throw new RuntimeException('Canonical racine invalide.');
    }
    if ($metadata->imageUrl() !== 'https://www.universguitare.be/var/templates/univers-guitare/assets/guitar-fretboard-header-0703.webp') {
        throw new RuntimeException('Image sociale par défaut invalide.');
    }
    $long = str_repeat('mot ', 60);
    $short = PageMetadata::descriptionText($long);
    $shortLength = function_exists('iconv_strlen') ? iconv_strlen($short, 'UTF-8') : strlen($short);
    if ($shortLength === false || $shortLength > 171 || !str_ends_with($short, '…')) {
        throw new RuntimeException('Troncature SEO autour de 170 caractères invalide.');
    }
    echo "Canonical, descriptions et image sociale SEO : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test SEO : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
