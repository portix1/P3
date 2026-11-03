<?php
declare(strict_types=1);

namespace Portix\Media;

use PDO;
use RuntimeException;

/** Small optional bridge; resources remain available when the module is disabled. */
final class MediaBridge
{
    private static ?PDO $pdo = null;
    private static string $root = '';
    private static bool $active = false;
    /** @var resource|null */
    private static mixed $lock = null;

    public static function configure(PDO $pdo, string $root, bool $writeRequest): void
    {
        self::$pdo = $pdo;
        self::$root = $root;
        $query = $pdo->prepare("SELECT enabled FROM modules WHERE prefix = :prefix AND path = :path");
        $query->execute(['prefix' => 'media', 'path' => 'media']);
        self::$active = (bool) $query->fetchColumn()
            && is_file($root . '/var/modules/media/src/MediaManager.php');
        // Serialise CMS mutations, including restores, against final usage checks.
        if ($writeRequest && (self::$active || self::hasCatalog())) {
            $directory = $root . '/tmp';
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new RuntimeException('Impossible de verrouiller les médias.');
            }
            self::$lock = fopen($directory . '/media-write.lock', 'c');
            if (self::$lock === false || !flock(self::$lock, LOCK_EX)) {
                throw new RuntimeException('Impossible de verrouiller les médias.');
            }
        }
    }

    public static function release(): void
    {
        if (is_resource(self::$lock)) { flock(self::$lock, LOCK_UN); fclose(self::$lock); }
        self::$lock = null; self::$pdo = null; self::$active = false;
    }

    public static function active(): bool
    {
        return self::$active && self::hasCatalog();
    }

    public static function hasCatalog(): bool
    {
        if (self::$pdo === null) {
            return false;
        }
        $query = self::$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $query->execute(['name' => 'media_items']);
        return (bool) $query->fetchColumn();
    }

    public static function managed(string $path): bool
    {
        if (!self::hasCatalog()) {
            return false;
        }
        $query = self::$pdo->prepare('SELECT 1 FROM media_items WHERE public_path = :path');
        $query->execute(['path' => ltrim($path, '/')]);
        return (bool) $query->fetchColumn();
    }

    public static function registerImage(string $path, string $name): void
    {
        if (!self::active()) {
            return;
        }
        require_once self::$root . '/var/modules/media/src/MediaManager.php';
        (new \Portix\Media\MediaManager(self::$pdo, self::$root))->register($path, $name);
    }

    /** Version only generated links, never rewrite the stored article bodies. */
    public static function versionHtml(string $html): string
    {
        if (!self::hasCatalog()) {
            return $html;
        }
        $versions = self::$pdo->query('SELECT public_path, sha256 FROM media_items')->fetchAll(PDO::FETCH_KEY_PAIR);
        return (string) preg_replace_callback('~\b(src|href)=("|\')([^"\']+)\2~i',
            static function (array $match) use ($versions): string {
                $url = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
                // Relative media URLs only; external URLs must remain untouched.
                if (preg_match('~^[a-z][a-z0-9+.-]*:|^//~i', $url) || !isset($versions[$path])) {
                    return $match[0];
                }
                $query = [];
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $query['p3media'] = substr((string) $versions[$path], 0, 16);
                $fragment = parse_url($url, PHP_URL_FRAGMENT);
                $versioned = (string) parse_url($url, PHP_URL_PATH) . '?' . http_build_query($query)
                    . (is_string($fragment) ? '#' . $fragment : '');
                return $match[1] . '=' . $match[2]
                    . htmlspecialchars($versioned, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $match[2];
            }, $html);
    }
}
