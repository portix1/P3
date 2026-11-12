<?php
declare(strict_types=1);

namespace Portix\Media;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Add responsive candidates to rendered article HTML, without editing stored content. */
final class ResponsiveImages
{
    private const WIDTHS = [400, 800, 1200];
    private const MAX_SOURCE_PIXELS = 6000000;
    private const MAX_SOURCE_BYTES = 12582912;
    private const MAX_NEW_FILES_PER_RENDER = 4;
    private const SUBDIRECTORY = '/var/uploads/images/responsive';

    public static function enhance(string $html, string $root, string $theme = ''): string
    {
        if (stripos($html, '<img') === false || !class_exists(DOMDocument::class)) {
            return $html;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            if (!@$dom->loadHTML('<?xml encoding="UTF-8"><div id="p3-responsive-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
                return $html;
            }
            $container = $dom->getElementById('p3-responsive-root');
            if (!$container instanceof DOMElement) {
                return $html;
            }
            $images = (new DOMXPath($dom))->query('.//img', $container);
            if ($images === false) {
                return $html;
            }
            $count = 0;
            $budget = self::MAX_NEW_FILES_PER_RENDER;
            $cache = [];
            foreach ($images as $image) {
                if (!$image instanceof DOMElement) {
                    continue;
                }
                $source = self::inspect($image->getAttribute('src'), $root);
                if ($source === null) {
                    continue;
                }
                $count++;
                // A 400px+ original may fill a narrow editorial figure on mobile.
                // Mark it explicitly so thumbnail-sized icons are never enlarged.
                if ($source['width'] >= 400 && self::isClairTheme($theme)
                    && self::isNarrowFigure($image)) {
                    $classes = trim($image->getAttribute('class') . ' p3-mobile-figure-expand');
                    if (!str_contains(' ' . $image->getAttribute('class') . ' ', ' p3-mobile-figure-expand ')) {
                        $image->setAttribute('class', $classes);
                    }
                }
                if (!$image->hasAttribute('width')) {
                    $image->setAttribute('width', (string) $source['width']);
                }
                if (!$image->hasAttribute('height')) {
                    $image->setAttribute('height', (string) $source['height']);
                }
                if (!$image->hasAttribute('decoding')) {
                    $image->setAttribute('decoding', 'async');
                }
                // The first image may appear above the fold or become the LCP image.
                if ($count > 1 && !$image->hasAttribute('loading')) {
                    $image->setAttribute('loading', 'lazy');
                }
                if ($image->hasAttribute('srcset') || $source['width'] <= self::WIDTHS[0]
                    || !function_exists('imagewebp')) {
                    continue;
                }
                $key = $source['file'];
                if (!isset($cache[$key])) {
                    try {
                        $cache[$key] = self::candidates($source, $root, $budget);
                    } catch (\Throwable) {
                        // File-system or encoder failures must not break article reading.
                        $cache[$key] = [];
                    }
                }
                $candidates = $cache[$key];
                if ($candidates === []) {
                    continue;
                }
                $versioned = self::withVersion($image->getAttribute('src'), $source['sha256']);
                $originalUrl = $image->getAttribute('src');
                $prefix = str_starts_with($originalUrl, '/') ? '/'
                    : (str_starts_with($originalUrl, './') ? './' : '');
                $parts = [];
                foreach ($candidates as $width => $url) {
                    $parts[] = $prefix . $url . ' ' . $width . 'w';
                }
                $parts[] = $versioned . ' ' . $source['width'] . 'w';
                $image->setAttribute('src', $versioned);
                $image->setAttribute('srcset', implode(', ', $parts));
                if (!$image->hasAttribute('sizes')) {
                    $image->setAttribute('sizes', self::sizes($image, $theme));
                }
            }
            $output = '';
            foreach ($container->childNodes as $child) {
                $output .= (string) $dom->saveHTML($child);
            }
            return $output;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array{file:string,width:int,height:int,mime:string,sha256:string}|null */
    private static function inspect(string $url, string $root): ?array
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//') || str_contains($url, '\\')) {
            return null;
        }
        $parsed = parse_url($url);
        if (!is_array($parsed) || isset($parsed['scheme']) || isset($parsed['host'])
            || isset($parsed['user'])) {
            return null;
        }
        $relative = ltrim((string) ($parsed['path'] ?? ''), '/');
        if (str_starts_with($relative, './')) {
            $relative = substr($relative, 2);
        }
        if (preg_match('~^var/uploads/images/(?:[a-f0-9]{32}\.(?:jpe?g|png|webp)|[a-z0-9](?:[a-z0-9-]{0,71}[a-z0-9])?-[a-f0-9]{8}\.webp)$~D', $relative) !== 1) {
            return null;
        }
        $imagesDirectory = rtrim($root, '/\\') . '/var/uploads/images';
        if (is_link($imagesDirectory)) {
            return null;
        }
        $originals = realpath($imagesDirectory);
        if (!is_string($originals)) {
            return null;
        }
        $source = $originals . '/' . basename($relative);
        if (is_link($source) || !is_file($source) || !is_readable($source)) {
            return null;
        }
        $length = filesize($source);
        if (!is_int($length) || $length < 1 || $length > self::MAX_SOURCE_BYTES) {
            return null;
        }
        $info = @getimagesize($source);
        if (!is_array($info)) {
            return null;
        }
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $mime = (string) ($info['mime'] ?? '');
        $extensions = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $ext = pathinfo($relative, PATHINFO_EXTENSION);
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_SOURCE_PIXELS
            || !isset($extensions[$ext]) || $extensions[$ext] !== $mime) {
            return null;
        }
        $digest = hash_file('sha256', $source);
        if (!is_string($digest)) {
            return null;
        }
        return ['file' => $source, 'width' => $width, 'height' => $height, 'mime' => $mime, 'sha256' => $digest];
    }

    /** @param array{file:string,width:int,height:int,mime:string,sha256:string} $source
     *  @return array<int,string> */
    private static function candidates(array $source, string $root, int &$budget): array
    {
        $directory = rtrim($root, '/\\') . self::SUBDIRECTORY;
        if (is_link($directory)) {
            return [];
        }
        if (!is_dir($directory) && (!@mkdir($directory, 0755, true) && !is_dir($directory))) {
            return [];
        }
        $parent = realpath(rtrim($root, '/\\') . '/var/uploads/images');
        if ($parent === false || realpath($directory) !== $parent . '/responsive') {
            return [];
        }
        $candidates = [];
        $original = null;
        foreach (self::WIDTHS as $width) {
            if ($width >= $source['width']) {
                continue;
            }
            $name = substr(hash('sha256', 'p3-responsive-v1:' . $source['sha256'] . ':' . $width), 0, 32) . '.webp';
            $file = $directory . '/' . $name;
            if (is_link($file)) {
                continue;
            }
            if (!is_file($file) || filesize($file) < 1) {
                if ($budget < 1) {
                    continue;
                }
                if (!$original) {
                    $original = self::decode($source);
                    if (!$original) {
                        break;
                    }
                }
                if (!self::writeVariant($original, $source['width'], $source['height'], $width, $file)) {
                    continue;
                }
                $budget--;
            }
            if (is_file($file) && !is_link($file) && filesize($file) > 0) {
                $candidates[$width] = 'var/uploads/images/responsive/' . $name;
            }
        }
        if ($original instanceof \GdImage) {
            imagedestroy($original);
        }
        return $candidates;
    }

    /** @param array{file:string,width:int,height:int,mime:string,sha256:string} $source */
    private static function decode(array $source): \GdImage|false
    {
        return match ($source['mime']) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source['file']) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source['file']) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source['file']) : false,
            default => false,
        };
    }

    private static function writeVariant(\GdImage $source, int $sourceWidth, int $sourceHeight, int $width, string $path): bool
    {
        $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));
        $image = imagecreatetruecolor($width, $height);
        if (!$image instanceof \GdImage) {
            return false;
        }
        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
            if (!imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
                return false;
            }
            $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
            try {
                if (!@imagewebp($image, $tmp, 84) || !is_file($tmp) || filesize($tmp) < 1) {
                    return false;
                }
                if (!@rename($tmp, $path)) {
                    return false;
                }
                @chmod($path, 0644);
                return true;
            } finally {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        } finally {
            imagedestroy($image);
        }
    }

    private static function withVersion(string $url, string $digest): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['p3image'] = substr($digest, 0, 16);
        return (string) ($parts['path'] ?? '') . '?' . http_build_query($query)
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    private static function isClairTheme(string $theme): bool
    {
        return preg_match('/^six-cordes-ux-clair(?:-v[0-9]{1,3})?$/D', $theme) === 1;
    }

    private static function isNarrowFigure(DOMElement $image): bool
    {
        $parent = $image->parentNode;
        for ($i = 0; $i < 4 && $parent instanceof DOMElement; $i++) {
            if (strtolower($parent->tagName) === 'figure') {
                return in_array($parent->getAttribute('data-width'), ['25','33','50'], true)
                    || preg_match('/(?:^|\s)portix-image-size-(?:25|33|50)(?:\s|$)/',
                        $parent->getAttribute('class')) === 1;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    private static function sizes(DOMElement $image, string $theme): string
    {
        $parent = $image;
        for ($i = 0; $i < 4 && $parent !== null; $i++) {
            $parent = $parent->parentNode instanceof DOMElement ? $parent->parentNode : null;
            if ($parent === null || strtolower($parent->tagName) !== 'figure') {
                continue;
            }
            $value = $parent->getAttribute('data-width');
            if (preg_match('/^(?:[1-9]|[1-9][0-9]|100)$/D', $value) === 1) {
                $percent = (int) $value;
                $desktop = max(20, (int) round(760 * $percent / 100));
                $mobile = self::isClairTheme($theme) && in_array($percent, [25, 33, 50], true)
                    ? 'min(94vw, 432px)' : "{$percent}vw";
                return "(max-width: 640px) {$mobile}, (max-width: 1024px) {$percent}vw, {$desktop}px";
            }
        }
        $value = $image->getAttribute('data-width');
        if ($value === '') {
            // Legacy images can encode the editor's width as a CSS class.
            $figure = $image->parentNode instanceof DOMElement ? $image->parentNode : null;
            $classes = $image->getAttribute('class') . ' ' .
                (($figure !== null && strtolower($figure->tagName) === 'figure')
                    ? $figure->getAttribute('class') : '');
            if (preg_match('/(?:^|\s)portix-image-size-(25|33|50)(?:\s|$)/', $classes, $match) === 1) {
                $value = $match[1];
            }
        }
        if (preg_match('/^(?:[1-9]|[1-9][0-9]|100)$/D', $value) === 1) {
            $percent = (int) $value;
            $desktop = max(20, (int) round(760 * $percent / 100));
            $mobile = self::isClairTheme($theme) && in_array($percent, [25, 33, 50], true)
                    ? 'min(94vw, 432px)' : "{$percent}vw";
                return "(max-width: 640px) {$mobile}, (max-width: 1024px) {$percent}vw, {$desktop}px";
        }
        return '(max-width: 640px) 90vw, (max-width: 1024px) 78vw, 760px';
    }
}
