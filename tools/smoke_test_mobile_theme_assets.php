<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/bootstrap/autoload.php';

use Portix\View\MobileThemeAssets;

function mobileAssetAssert(bool $condition, string $detail): void
{
    if (!$condition) {
        throw new RuntimeException('Thème mobile : ' . $detail);
    }
}
$root = dirname(__DIR__);
$markup = '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
    . '<title>Univers Guitare</title></head><body class="theme-six-cordes theme-six-cordes-clair">'
    . '<h1>Univers Guitare</h1></body></html>';
$tpl = './var/templates/six-cordes-ux-clair/index.tpl';
$originalImage = $root . '/var/templates/six-cordes-ux-clair/assets/guitar-header.webp';
$before = is_file($originalImage) ? hash_file('sha256', $originalImage) : null;

$rendered = MobileThemeAssets::inject($markup, $tpl, $root);
mobileAssetAssert(str_contains($rendered, 'var/editor/mobile-header-p1.css?v=20261112'), 'feuille CSS non injectée');
mobileAssetAssert(str_contains($rendered, 'var/editor/mobile-header-p1.js?v=20261112'), 'script de navigation non injecté');
mobileAssetAssert(!str_contains($rendered, 'reading-desktop-p2.css'),
    'l’accueil ne doit pas télécharger le CSS des articles');
$articleMarkup = str_replace('<h1>Univers Guitare</h1>',
    '<article class="news-detail article-reading"><div class="news-body news-rich-text">Texte</div></article>', $markup);
$articleRendered = MobileThemeAssets::inject($articleMarkup, $tpl, $root);
mobileAssetAssert(str_contains($articleRendered,
    'var/editor/reading-desktop-p2.css?v=20261113'),
    'correction de lecture desktop non injectée sur les articles');
mobileAssetAssert(str_contains($articleRendered, 'media="screen and (min-width: 1025px)"'),
    'feuille de lecture desktop non limitée au grand écran');
mobileAssetAssert(MobileThemeAssets::inject($articleRendered, $tpl, $root) === $articleRendered,
    'feuille de lecture desktop injectée deux fois');
mobileAssetAssert(MobileThemeAssets::inject($articleMarkup,
    './var/templates/modern/index.tpl', $root) === $articleMarkup,
    'correction desktop injectée dans un autre thème');
mobileAssetAssert(str_contains($rendered, 'theme-color'), 'barre de navigateur non configurée');
mobileAssetAssert(str_contains($rendered, 'work-sans-latin-wght-normal.woff2'), 'préchargement Work Sans manquant');
mobileAssetAssert(str_contains($rendered, 'literata-latin-wght-normal.woff2'), 'préchargement Literata manquant');
mobileAssetAssert(MobileThemeAssets::inject($rendered, $tpl, $root) === $rendered,
    'injections répétées');
mobileAssetAssert(MobileThemeAssets::inject($markup, './var/templates/modern/index.tpl', $root) === $markup,
    'autre thème modifié');
mobileAssetAssert(MobileThemeAssets::inject($markup, './var/admin/templates/login.tpl', $root) === $markup,
    'admin modifié');
mobileAssetAssert(MobileThemeAssets::inject('<html><head></head><body>Autre page</body></html>', $tpl, $root)
    === '<html><head></head><body>Autre page</body></html>', 'page sans thème modifiée');
mobileAssetAssert(MobileThemeAssets::inject($markup, './var/templates/six-cordes-ux-clair-v018/index.tpl', $root)
    !== $markup, 'variante personnalisée non prise en charge');

if ($before !== null) {
    mobileAssetAssert(hash_file('sha256', $originalImage) === $before, 'image originale modifiée');
}
if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')
    && is_file($originalImage) && is_file($root . '/var/templates/six-cordes-ux-clair/style.min.css')) {
    $info = getimagesize($originalImage);
    if (is_array($info) && (int) $info[0] > 800 && (int) $info[0] * (int) $info[1] <= 6000000) {
        mobileAssetAssert(str_contains($rendered, 'imagesrcset=') && str_contains($rendered, 'image-set('),
            'variantes WebP non servies');
        mobileAssetAssert(
            str_contains($rendered, '.site-header.six-cordes-masthead{')
                && str_contains($rendered, '.site-footer.six-cordes-footer{')
                && substr_count($rendered, 'image-set(') === 2
                && substr_count($rendered, 'linear-gradient(') >= 2,
            'en-tête et pied de page doivent partager les dérivés tout en gardant un dégradé'
        );
        mobileAssetAssert(preg_match('/var\/uploads\/images\/responsive\/[a-f0-9]{32}\.webp/', $rendered) === 1,
            'URL de dérivé invalide');
        preg_match_all('~var/uploads/images/responsive/([a-f0-9]{32}\.webp)~', $rendered, $matches);
        $files = array_unique($matches[1]);
        mobileAssetAssert(count($files) === 2, 'exactement deux variantes attendues');
        foreach ($files as $file) {
            $file = $root . '/var/uploads/images/responsive/' . $file;
            mobileAssetAssert(is_file($file), 'dérivé absent');
            $meta = @getimagesize($file);
            mobileAssetAssert(is_array($meta) && ($meta['mime'] ?? '') === 'image/webp'
                && in_array((int) $meta[0], [400, 800], true), 'dimensions des dérivés');
        }
    }
}
echo "Polices préchargées, variantes header, filtrage des thèmes, rendu idempotent : OK\n";
