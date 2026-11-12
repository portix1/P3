<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/bootstrap/autoload.php';

use Portix\Media\ResponsiveImages;

function responsiveCheck(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('Images responsives : ' . $label);
    }
}
function responsiveImage(string $path, int $width, int $height, int $seed): void
{
    $im = imagecreatetruecolor($width, $height);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    for ($y = 0; $y < $height; $y += 16) {
        $color = imagecolorallocatealpha($im, ($y + $seed) % 255, ($seed * 17 + $y) % 255, 70, 0);
        imagefilledrectangle($im, 0, $y, $width - 1, min($height - 1, $y + 15), $color);
    }
    if (pathinfo($path, PATHINFO_EXTENSION) === 'png') {
        imagepng($im, $path);
    } else {
        imagewebp($im, $path, 88);
    }
    imagedestroy($im);
}
function responsiveImages(string $html): array
{
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    return iterator_to_array($doc->getElementsByTagName('img'));
}

if (!extension_loaded('gd') || !extension_loaded('dom') || !function_exists('imagewebp')) {
    throw new RuntimeException('Extensions GD WebP et DOM requises pour le test.');
}
$root = sys_get_temp_dir() . '/p3-responsive-images-' . bin2hex(random_bytes(8));
$images = $root . '/var/uploads/images';
mkdir($images, 0755, true);
try {
    $first = str_repeat('a', 32) . '.webp';
    $second = str_repeat('b', 32) . '.png';
    $icon = str_repeat('c', 32) . '.webp';
    responsiveImage($images . '/' . $first, 1448, 900, 33);
    responsiveImage($images . '/' . $second, 1448, 900, 87);
    responsiveImage($images . '/' . $icon, 48, 48, 19);
    $hashBefore = hash_file('sha256', $images . '/' . $first);
    $html = '<figure class="portix-image-figure" data-width="25">'
        . '<img src="var/uploads/images/' . $first . '" alt="Gabarit"><figcaption>Vue du gabarit</figcaption></figure>'
        . '<figure class="portix-image-figure" data-width="33">'
        . '<img src="/var/uploads/images/' . $second . '" alt="Détail"><figcaption>Vue détaillée</figcaption></figure>'
        . '<img src="/var/uploads/images/' . $icon . '" alt="Icône">'
        . '<img src="https://example.org/nonlocal.webp" alt="Externe">'
        . '<img src="/var/uploads/images/../private.webp" alt="Interdit">';

    $output = ResponsiveImages::enhance($html, $root);
    $items = responsiveImages($output);
    responsiveCheck(count($items) === 5, 'aucune image perdue');
    responsiveCheck(str_contains($output, '<figcaption>Vue du gabarit</figcaption>'), 'légende conservée');
    responsiveCheck($items[0]->getAttribute('width') === '1448' && $items[0]->getAttribute('height') === '900', 'dimensions intrinsèques correctes');
    responsiveCheck($items[0]->hasAttribute('srcset') && str_contains($items[0]->getAttribute('srcset'), '400w')
        && str_contains($items[0]->getAttribute('srcset'), '1448w'), 'variantes et original accessibles');
    responsiveCheck(str_contains($items[0]->getAttribute('sizes'), '25vw'), 'taille de figure 25 % prise en compte');
    responsiveCheck(!$items[0]->hasAttribute('loading'), 'première image non différée');
    responsiveCheck($items[1]->getAttribute('loading') === 'lazy', 'deuxième image différée');
    responsiveCheck(str_contains($items[1]->getAttribute('sizes'), '33vw'), 'taille de figure 33 % prise en compte');
    responsiveCheck(str_contains($items[1]->getAttribute('srcset'), '/var/uploads/images/responsive/'), 'URL absolue conservée pour les dérivés');
    responsiveCheck(!$items[2]->hasAttribute('srcset') && $items[2]->getAttribute('width') === '48', 'petite icône non agrandie');
    responsiveCheck(!$items[3]->hasAttribute('srcset') && !$items[3]->hasAttribute('loading'), 'image externe non modifiée');
    responsiveCheck(!$items[4]->hasAttribute('srcset'), 'traversée de chemin ignorée');
    responsiveCheck(str_contains($items[0]->getAttribute('src'), '?p3image='), 'source versionnée pour éviter le cache ancien');
    responsiveCheck(hash_file('sha256', $images . '/' . $first) === $hashBefore, 'original non modifié');

    $directory = $images . '/responsive';
    $newFiles = glob($directory . '/*.webp') ?: [];
    responsiveCheck(count($newFiles) === 4, 'quatre nouveaux fichiers maximum par rendu');
    foreach ($newFiles as $file) {
        responsiveCheck((bool) preg_match('/^[a-f0-9]{32}\.webp$/D', basename($file)), 'nom compatible avec FilesMatch Apache');
        $dimensions = getimagesize($file);
        responsiveCheck(is_array($dimensions) && in_array($dimensions[0], [400, 800, 1200], true)
            && ($dimensions['mime'] ?? '') === 'image/webp', 'variante WebP redimensionnée');
    }

    $again = ResponsiveImages::enhance($html, $root);
    $all = glob($directory . '/*.webp') ?: [];
    responsiveCheck(count($all) === 6, 'les dérivés manquants sont créés au rendu suivant');
    responsiveCheck(str_contains(responsiveImages($again)[1]->getAttribute('srcset'), '1200w'), 'second dérivé complété');
    $twice = ResponsiveImages::enhance($again, $root);
    responsiveCheck(count(glob($directory . '/*.webp') ?: []) === 6 && count(responsiveImages($twice)) === 5,
        'seconde transformation sans duplication');

    responsiveImage($images . '/' . $first, 1448, 900, 59);
    $changed = ResponsiveImages::enhance('<img src="var/uploads/images/' . $first . '">', $root);
    responsiveCheck(str_contains($changed, 'p3image=') && $changed !== $output
        && count(glob($directory . '/*.webp') ?: []) > 6, 'remplacement de l’original donne de nouvelles URL');

    $protected = $root . '/protected.txt';
    file_put_contents($protected, 'secret');
    @symlink($protected, $images . '/' . str_repeat('d', 32) . '.webp');
    $unsafe = ResponsiveImages::enhance('<img src="var/uploads/images/' . str_repeat('d', 32) . '.webp">', $root);
    responsiveCheck(!str_contains($unsafe, 'srcset='), 'lien symbolique interdit');

    echo "Images responsives : variantes 400/800/1200, srcset, largeur réelle, cache, lazy, sécurité et originaux : OK\n";
} finally {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        ($file->isDir() && !$file->isLink()) ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($root);
}
