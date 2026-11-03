<?php
declare(strict_types=1);

namespace Portix\Theme;

use RuntimeException;

/** Local package metadata, not a publisher signature or a remote update source. */
final class ThemeManifest
{
    public static function load(string $directory): ?array
    {
        $file = $directory . '/theme.json';
        if (!is_file($file)) { return null; }
        if (is_link($file) || filesize($file) > 131072) { throw new RuntimeException('Manifeste de thème invalide.'); }
        $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema'] ?? null) !== 1) { throw new RuntimeException('Format de manifeste inconnu.'); }
        foreach (['id', 'name', 'version', 'kind', 'p3_min', 'php_min'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '' || strlen($data[$key]) > 160) {
                throw new RuntimeException('Champ de manifeste invalide : ' . $key);
            }
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $data['id']) || $data['id'] !== basename($directory)
            || !in_array($data['kind'], ['theme', 'shared'], true)) {
            throw new RuntimeException('Identifiant ou type de thème invalide.');
        }
        foreach (['version', 'p3_min', 'php_min'] as $key) {
            if (!self::validVersion($data[$key])) { throw new RuntimeException('Version invalide : ' . $key); }
        }
        if (($data['p3_max'] ?? null) !== null && (!is_string($data['p3_max']) || !self::validVersion($data['p3_max']) || version_compare($data['p3_max'], $data['p3_min'], '<'))) {
            throw new RuntimeException('Version maximale P3 invalide.');
        }
        if (($data['contract'] ?? null) !== ThemeContract::VERSION) { throw new RuntimeException('Contrat de thème non pris en charge.'); }
        if (!is_array($data['requires'] ?? null) || !array_is_list($data['requires'])) { throw new RuntimeException('Dépendances invalides.'); }
        foreach ($data['requires'] as $dependency) {
            if ($dependency !== 'shared' || $data['id'] === 'shared') { throw new RuntimeException('Dépendance non prise en charge.'); }
        }
        if (($data['variant_of'] ?? null) !== null && (!is_string($data['variant_of']) || !preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $data['variant_of']) || $data['variant_of'] === $data['id'])) {
            throw new RuntimeException('Variante de thème invalide.');
        }
        if (!is_array($data['files'] ?? null) || $data['files'] === [] || count($data['files']) > 1000) { throw new RuntimeException('Inventaire de fichiers absent ou invalide.'); }
        foreach ($data['files'] as $path => $hash) {
            if (!is_string($path) || !self::validPath($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                throw new RuntimeException('Empreinte ou chemin de fichier invalide.');
            }
        }
        if ($data['kind'] === 'theme') {
            foreach (['index.tpl', 'menu.tpl', 'not-found.tpl', 'style.css', 'style.min.css'] as $required) {
                if (!isset($data['files'][$required])) { throw new RuntimeException('Fichier contractuel non suivi : ' . $required); }
            }
        } elseif ($data['id'] !== 'shared' || !isset($data['files']['breadcrumb.tpl'])) {
            throw new RuntimeException('Inventaire des ressources communes invalide.');
        }
        return $data;
    }

    public static function validVersion(string $version): bool
    {
        return preg_match('/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/D', $version) === 1;
    }

    public static function validPath(string $path): bool
    {
        return strlen($path) <= 240 && preg_match('#^[A-Za-z0-9_-]+(?:[./-][A-Za-z0-9_-]+)*\.[A-Za-z0-9]+$#D', $path) === 1
            && !str_contains($path, '..') && !str_contains($path, '\\')
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['tpl', 'css', 'js', 'svg', 'webp', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'woff', 'woff2', 'ttf'], true);
    }
}
