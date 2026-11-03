<?php
declare(strict_types=1);

namespace Portix\Theme;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class ThemeCatalog
{
    public function __construct(private readonly string $root, private readonly string $p3Version, private readonly string $phpVersion = PHP_VERSION) {}

    public function all(): array
    {
        $result = [];
        foreach (glob($this->root . '/var/templates/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (is_link($directory) || (!is_file($directory . '/index.tpl') && basename($directory) !== 'shared')) { continue; }
            $result[] = $this->inspect(basename($directory));
        }
        usort($result, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $result;
    }

    public function inspect(string $id): array
    {
        $row = ['id' => $id, 'name' => $id, 'version' => '', 'kind' => $id === 'shared' ? 'shared' : 'theme', 'variant_of' => '', 'versioned' => false, 'compatible' => null, 'changed' => [], 'missing' => [], 'extra' => [], 'errors' => [], 'requirements' => ''];
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,62}$/D', $id)) { $row['errors'][] = 'Identifiant de dossier invalide.'; return $row; }
        $directory = $this->root . '/var/templates/' . $id;
        if (!is_dir($directory) || is_link($directory)) { $row['errors'][] = 'Dossier de thème absent ou lien symbolique.'; return $row; }
        try {
            $manifest = ThemeManifest::load($directory);
            if ($manifest === null) { return $row; }
            $row['versioned'] = true;
            foreach (['id', 'name', 'version', 'kind'] as $key) { $row[$key] = $manifest[$key]; }
            $row['variant_of'] = $manifest['variant_of'] ?? '';
            $row['requirements'] = 'P3 ≥ ' . $manifest['p3_min'] . (($manifest['p3_max'] ?? null) !== null ? ' et ≤ ' . $manifest['p3_max'] : '') . ' ; PHP ≥ ' . $manifest['php_min'] . ' ; contrat ' . $manifest['contract'];
            $row['compatible'] = version_compare($this->p3Version, $manifest['p3_min'], '>=') && version_compare($this->phpVersion, $manifest['php_min'], '>=')
                && (($manifest['p3_max'] ?? null) === null || version_compare($this->p3Version, $manifest['p3_max'], '<='));
            if ($manifest['kind'] === 'theme') {
                $row['errors'] = ThemeContract::validate($this->root, $id);
            }
            foreach ($manifest['requires'] as $dependency) {
                if (!is_dir($this->root . '/var/templates/' . $dependency) || is_link($this->root . '/var/templates/' . $dependency) || !is_file($this->root . '/var/templates/shared/breadcrumb.tpl')) {
                    $row['errors'][] = 'Ressource commune requise absente : ' . $dependency;
                }
            }
            foreach ($manifest['files'] as $path => $expected) {
                $file = $directory . '/' . $path;
                $real = realpath($file);
                if ($real === false || !is_file($file)) { $row['missing'][] = $path; continue; }
                if (is_link($file) || !str_starts_with($real, realpath($directory) . DIRECTORY_SEPARATOR)) { $row['errors'][] = 'Fichier lié ou hors du thème : ' . $path; continue; }
                if (hash_file('sha256', $file) !== $expected) { $row['changed'][] = $path; }
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink()) { $row['errors'][] = 'Lien symbolique dans le thème.'; continue; }
                if (!$file->isFile()) { continue; }
                $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($directory) + 1));
                if ($path !== 'theme.json' && !array_key_exists($path, $manifest['files'])) { $row['extra'][] = $path; }
                if (count($row['extra']) > 1000) { $row['errors'][] = 'Inventaire trop volumineux.'; break; }
            }
            if ($row['errors'] !== [] || $row['missing'] !== []) { $row['compatible'] = false; }
        } catch (Throwable $error) {
            $row['errors'][] = $error->getMessage(); $row['compatible'] = false;
        }
        return $row;
    }
}
