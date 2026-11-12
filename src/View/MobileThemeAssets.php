<?php
declare(strict_types=1);

namespace Portix\View;

/**
 * Progressive mobile header optimisations for the Six Cordes Clair family.
 * Render-only: does not modify custom theme templates, CSS or media originals.
 */
final class MobileThemeAssets
{
    private const CSS_URL = 'var/editor/mobile-header-p1.css?v=20261112';
    private const JS_URL = 'var/editor/mobile-header-p1.js?v=20261112';
    private const MOBILE_MEDIA = '(max-width: 640px), (max-width: 900px) and (max-height: 500px)';

    public static function inject(string $html, string $templateFile, ?string $projectRoot = null): string
    {
        $name = str_replace('\\', '/', $templateFile);
        if (preg_match('~(?:^|/)var/templates/(six-cordes-ux-clair(?:-v[0-9]{1,3})?)/index\.tpl$~D', $name, $match) !== 1
            || !str_contains($html, 'theme-six-cordes-clair')
            || stripos($html, '</head>') === false) {
            return $html;
        }
        if (str_contains($html, self::CSS_URL)) {
            return $html;
        }
        $root = $projectRoot ?? dirname(__DIR__, 2);
        if (!is_file($root . '/var/editor/mobile-header-p1.css')
            || !is_file($root . '/var/editor/mobile-header-p1.js')) {
            return $html;
        }
        $theme = $match[1];
        $head = "\n<!-- P3 / Mobile : chargement indépendant des personnalisations du thème. -->\n";
        $head .= '<meta name="theme-color" content="#241a15">' . "\n";
        foreach (['work-sans-latin-wght-normal.woff2', 'literata-latin-wght-normal.woff2'] as $font) {
            if (is_file($root . '/var/templates/' . $theme . '/assets/fonts/' . $font)) {
                $head .= '<link rel="preload" href="var/templates/' . $theme . '/assets/fonts/' . $font
                    . '" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
            }
        }
        $head .= '<link rel="stylesheet" href="' . self::CSS_URL . '" media="screen">' . "\n";
        $head .= '<script src="' . self::JS_URL . '" defer></script>' . "\n";
        try {
            $images = self::headerImages($root, $theme);
            if (count($images) === 2) {
                // The header image is derived from the active theme's own background.
                $one = $images[400];
                $two = $images[800];
                $head .= '<link rel="preload" as="image" href="' . $two . '" imagesrcset="' . $one
                    . ' 1x, ' . $two . ' 2x" media="' . self::MOBILE_MEDIA . '">' . "\n";
                $head .= '<style id="p3-mobile-header-image">@media screen and (max-width: 640px),'
                    . ' screen and (max-width: 900px) and (max-height: 500px) {'
                    . 'body.theme-six-cordes-clair .site-header.six-cordes-masthead{'
                    . 'background-image:image-set(url("' . $one . '") 1x,url("' . $two
                    . '") 2x)!important;}}</style>' . "\n";
            }
        } catch (\Throwable) {
            // File system permissions and GD availability must never break public pages.
        }
        return (string) preg_replace('~</head>~i', $head . '</head>', $html, 1);
    }

    /** @return array<int, string> */
    private static function headerImages(string $root, string $theme): array
    {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
            return [];
        }
        $source = $root . '/var/templates/' . $theme . '/assets/guitar-header.webp';
        $style = $root . '/var/templates/' . $theme . '/style.min.css';
        if (is_link($source) || !is_file($source) || !is_readable($source)
            || !is_file($style) || !is_readable($style)) {
            return [];
        }
        // Never substitute a different customised header background.
        $css = file_get_contents($style);
        if ($css === false || !str_contains($css, 'guitar-header.webp')) {
            return [];
        }
        $size = filesize($source);
        $info = @getimagesize($source);
        if (!is_int($size) || $size < 1 || $size > 4194304 || !is_array($info)
            || ($info['mime'] ?? '') !== 'image/webp') {
            return [];
        }
        $sourceWidth = (int) ($info[0] ?? 0);
        $sourceHeight = (int) ($info[1] ?? 0);
        if ($sourceWidth <= 800 || $sourceHeight < 1 || $sourceWidth * $sourceHeight > 6000000) {
            return [];
        }
        $digest = hash_file('sha256', $source);
        if (!is_string($digest)) {
            return [];
        }
        $directory = $root . '/var/uploads/images/responsive';
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0755, true))) {
            return [];
        }
        $originals = realpath($root . '/var/uploads/images');
        if ($originals === false || realpath($directory) !== $originals . '/responsive') {
            return [];
        }
        $variants = [];
        $original = null;
        foreach ([400, 800] as $width) {
            $name = substr(hash('sha256', 'p3-theme-header-v1:' . $digest . ':' . $width), 0, 32) . '.webp';
            $file = $directory . '/' . $name;
            if (is_link($file)) {
                continue;
            }
            if (!is_file($file) || filesize($file) < 1) {
                $original ??= @imagecreatefromwebp($source);
                if (!$original instanceof \GdImage) {
                    break;
                }
                $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));
                $small = imagecreatetruecolor($width, $height);
                if (!$small instanceof \GdImage) {
                    break;
                }
                try {
                    if (!imagecopyresampled($small, $original, 0, 0, 0, 0,
                            $width, $height, $sourceWidth, $sourceHeight)) {
                        continue;
                    }
                    $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
                    try {
                        if (!@imagewebp($small, $tmp, 76) || !is_file($tmp)
                            || filesize($tmp) < 1 || !@rename($tmp, $file)) {
                            continue;
                        }
                        @chmod($file, 0644);
                    } finally {
                        if (is_file($tmp)) {
                            @unlink($tmp);
                        }
                    }
                } finally {
                    imagedestroy($small);
                }
            }
            if (is_file($file) && !is_link($file) && filesize($file) > 0) {
                $variants[$width] = 'var/uploads/images/responsive/' . $name;
            }
        }
        if ($original instanceof \GdImage) {
            imagedestroy($original);
        }
        return count($variants) === 2 ? $variants : [];
    }
}
