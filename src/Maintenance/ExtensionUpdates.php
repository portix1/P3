<?php
declare(strict_types=1);

namespace Portix\Maintenance;

use RuntimeException;

final class ExtensionUpdates
{
    private const BASE_URL = 'https://raw.githubusercontent.com/portix1/P3/main/distribution/';
    private const MAX_BYTES = 131072;

    public static function fetchCatalogue(): string
    {
        $context = stream_context_create(['http' => [
            'timeout' => 3,
            'follow_location' => 0,
        ]]);
        $json = @file_get_contents(self::BASE_URL . 'catalogue.json', false, $context, 0, self::MAX_BYTES + 1);
        if ($json === false || strlen($json) > self::MAX_BYTES) {
            throw new RuntimeException('Extension catalogue unavailable');
        }
        return $json;
    }

    public static function dashboard(string $root, array $modules, array $themes): array
    {
        $cached = $_SESSION['p3_update_catalogue'] ?? [];
        if (($cached['expires'] ?? 0) <= time()) {
            try {
                $json = self::fetchCatalogue();
                self::available($json, []);
                $cached = ['json' => $json, 'expires' => time() + 300];
            } catch (RuntimeException | \JsonException) {
                $cached = ['json' => null, 'expires' => time() + 60];
            }
            $_SESSION['p3_update_catalogue'] = $cached;
        }
        if ($cached['json'] === null) {
            return [];
        }

        $installed = ['theme' => array_column($themes, null, 'id'), 'module' => []];
        foreach ($modules as $module) {
            $path = basename((string) $module['path']);
            if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]{0,62}\z/', $path)) {
                continue;
            }
            $directory = $root . '/var/modules/' . $path;
            if (is_link($directory) || is_file($directory . '/AUTH') || !is_readable($directory . '/desc.xml')) {
                continue;
            }
            try {
                $manifest = \Portix\Module\ModuleManifest::fromXml($directory . '/desc.xml', $path);
                $installed['module'][$manifest['prefix']] = $manifest;
            } catch (RuntimeException) {
                continue;
            }
        }
        $updates = [];
        foreach (self::available($cached['json'], $installed) as $update) {
            $updates[$update['kind']][$update['id']] = $update;
        }
        return $updates;
    }

    public static function core(string $installed): string
    {
        $cached = $_SESSION['p3_core_release'] ?? [];
        if (($cached['expires'] ?? 0) <= time()) {
            $context = stream_context_create(['http' => [
                'timeout' => 3, 'follow_location' => 0, 'user_agent' => 'Portix-P3',
            ]]);
            $json = @file_get_contents('https://api.github.com/repos/portix1/P3/releases/latest', false, $context, 0, self::MAX_BYTES);
            $release = $json === false ? null : json_decode($json, true, 32);
            $tag = $release['tag_name'] ?? '';
            $version = is_string($tag) && preg_match('/\Av?[0-9]+\.[0-9]+\.[0-9]+\z/', $tag) ? ltrim($tag, 'v') : '';
            $cached = ['version' => $version, 'expires' => time() + ($version === '' ? 60 : 300)];
            $_SESSION['p3_core_release'] = $cached;
        }
        return $cached['version'] !== '' && version_compare($cached['version'], $installed, '>') ? $cached['version'] : '';
    }

    /**
     * A matching catalogue ZIP is checked before staging. Other archives remain
     * manual imports and are never represented as verified packages.
     * This checks integrity against the current catalogue, not authenticity.
     */
    public static function verifyUploadedArchive(
        string $kind,
        string $filename,
        string $archive,
        ?string $catalogueJson = null
    ): bool {
        if (!in_array($kind, ['module', 'theme'], true) || !is_file($archive)) {
            throw new RuntimeException('Invalid extension archive');
        }
        $catalogueJson ??= self::catalogueForUpload();
        if ($catalogueJson === null) {
            return false;
        }
        foreach (self::packages($catalogueJson) as $package) {
            if ($package['file'] !== $filename) {
                continue;
            }
            if ($package['kind'] !== $kind) {
                throw new RuntimeException('extension_updates_package_type_mismatch');
            }
            $actual = hash_file('sha256', $archive);
            if (!is_string($actual) || !hash_equals($package['sha256'], $actual)) {
                throw new RuntimeException('extension_updates_checksum_failed');
            }
            return true;
        }
        return false;
    }

    private static function catalogueForUpload(): ?string
    {
        $cached = $_SESSION['p3_update_catalogue'] ?? [];
        if (($cached['expires'] ?? 0) > time() && is_string($cached['json'] ?? null)) {
            return $cached['json'];
        }
        try {
            $json = self::fetchCatalogue();
            self::packages($json);
            $_SESSION['p3_update_catalogue'] = ['json' => $json, 'expires' => time() + 300];
            return $json;
        } catch (RuntimeException | \JsonException) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    private static function packages(string $json): array
    {
        $catalogue = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (($catalogue['schema'] ?? null) !== 1 || !is_array($catalogue['packages'] ?? null)
            || !array_is_list($catalogue['packages'])) {
            throw new RuntimeException('Invalid extension catalogue');
        }
        $seen = [];
        foreach ($catalogue['packages'] as $package) {
            if (!is_array($package)) {
                throw new RuntimeException('Invalid extension catalogue entry');
            }
            $kind = $package['kind'] ?? null;
            $id = $package['id'] ?? null;
            $version = $package['version'] ?? null;
            $file = $package['file'] ?? null;
            $sha256 = $package['sha256'] ?? null;
            if (!in_array($kind, ['module', 'theme'], true)
                || !is_string($id) || !preg_match('/\A[A-Za-z0-9_-]+\z/', $id)
                || !is_string($version) || !preg_match('/\A[0-9]+(?:\.[0-9]+){1,3}(?:[-+][A-Za-z0-9.-]+)?\z/', $version)
                || !is_string($file) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.zip\z/', $file)
                || !is_string($sha256) || !preg_match('/\A[a-f0-9]{64}\z/D', $sha256)
                || isset($seen[$file])) {
                throw new RuntimeException('Invalid extension catalogue entry');
            }
            $seen[$file] = true;
        }
        return $catalogue['packages'];
    }

    public static function available(string $json, array $installed): array
    {
        $updates = [];
        foreach (self::packages($json) as $package) {
            $kind = $package['kind'] ?? null;
            $id = $package['id'] ?? null;
            $version = $package['version'] ?? null;
            $file = $package['file'] ?? null;
            if (!in_array($kind, ['module', 'theme'], true) || !is_string($id)
                || !preg_match('/\A[A-Za-z0-9_-]+\z/', $id)
                || !is_string($version) || !preg_match('/\A[0-9]+(?:\.[0-9]+){1,3}(?:[-+][A-Za-z0-9.-]+)?\z/', $version)
                || !is_string($file) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.zip\z/', $file)) {
                throw new RuntimeException('Invalid extension catalogue entry');
            }

            $local = $installed[$kind][$id] ?? null;
            if ($local === null || $local['version'] === '' || !version_compare($version, $local['version'], '>')) {
                continue;
            }
            if (isset($updates[$kind][$id]) && !version_compare($version, $updates[$kind][$id]['version'], '>')) {
                continue;
            }
            $updates[$kind][$id] = [
                'kind' => $kind,
                'id' => $id,
                'name' => $local['name'],
                'installed' => $local['version'],
                'version' => $version,
                'sha256' => $package['sha256'],
                'url' => self::BASE_URL . rawurlencode($file),
            ];
        }

        return array_merge(array_values($updates['module'] ?? []), array_values($updates['theme'] ?? []));
    }
}
