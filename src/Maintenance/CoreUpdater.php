<?php
declare(strict_types=1);

namespace Portix\Maintenance;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Manual, administrator-triggered P3 core update from an official GitHub
 * release. Runtime data, site-specific routing and themes are never replaced.
 */
final class CoreUpdater
{
    private const RELEASE_API = 'https://api.github.com/repos/portix1/P3/releases/latest';
    private const RELEASE_BASE = 'https://github.com/portix1/P3/releases/download/';
    private const MAX_ZIP_BYTES = 62914560; // 60 MiB, abort before extraction.
    private const MAX_FILES = 4000;

    public static function latest(): string
    {
        $context = stream_context_create(['http' => [
            'timeout' => 8, 'follow_location' => 0,
            'header' => "User-Agent: Portix-P3\r\nAccept: application/vnd.github+json\r\n",
        ]]);
        $bytes = @file_get_contents(self::RELEASE_API, false, $context, 0, 131073);
        if (!is_string($bytes) || strlen($bytes) > 131072) {
            throw new RuntimeException('Impossible de vérifier la dernière version officielle.');
        }
        $release = json_decode($bytes, true);
        $tag = $release['tag_name'] ?? null;
        if (!is_array($release) || !is_string($tag)
            || ($release['draft'] ?? true) !== false
            || ($release['prerelease'] ?? true) !== false
            || preg_match('/\Av([0-9]+\.[0-9]+\.[0-9]+)\z/D', $tag, $m) !== 1) {
            throw new RuntimeException('La version officielle annoncée est invalide.');
        }
        return $m[1];
    }

    public static function installLatest(string $root, string $requestedVersion): string
    {
        if (preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $requestedVersion) !== 1) {
            throw new RuntimeException('Numéro de version invalide.');
        }
        $root = rtrim($root, '/\\');
        $current = trim((string) @file_get_contents($root . '/VERSION'));
        if (version_compare($requestedVersion, $current, '<=')) {
            throw new RuntimeException('Cette version de P3 est déjà installée.');
        }
        if (self::latest() !== $requestedVersion) {
            throw new RuntimeException('Une nouvelle version est disponible : rechargez la page.');
        }
        $lockFile = $root . '/var/sys/core-update.lock';
        if (is_link($lockFile)) {
            throw new RuntimeException('Verrou de mise à jour invalide.');
        }
        $lock = @fopen($lockFile, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) { fclose($lock); }
            throw new RuntimeException('Une mise à jour est déjà en cours.');
        }
        $directory = $root . '/var/private/core-update-staging';
        $work = $directory . '/' . bin2hex(random_bytes(8));
        try {
            UpdateSafety::assertInstalled($root);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Impossible de préparer le téléchargement de P3.');
            }
            if (is_link($directory) || !mkdir($work, 0700)) {
                throw new RuntimeException('Dossier temporaire de mise à jour invalide.');
            }
            $name = 'P3-' . $requestedVersion . '.zip';
            $url = self::RELEASE_BASE . 'v' . $requestedVersion . '/' . $name;
            $checksum = self::fetchChecksum($url, $name);
            $archive = $work . '/' . $name;
            self::download($url, $archive);
            $actual = hash_file('sha256', $archive);
            if (!is_string($actual) || !hash_equals($checksum, $actual)) {
                throw new RuntimeException('Archive refusée : empreinte SHA-256 incorrecte.');
            }
            return self::applyVerifiedArchive($root, $archive, $requestedVersion, $work);
        } finally {
            self::removeTemporary($work);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function fetchChecksum(string $url, string $name): string
    {
        $checksumUrl = substr($url, 0, -4) . '.sha256';
        $context = stream_context_create(['http' => [
            'timeout' => 20, 'follow_location' => 1, 'max_redirects' => 5,
            'header' => "User-Agent: Portix-P3\r\n",
        ]]);
        $value = @file_get_contents($checksumUrl, false, $context, 0, 512);
        if (!is_string($value)
            || preg_match('/\A([a-f0-9]{64})  ' . preg_quote($name, '/') . '\r?\n?\z/D', $value, $matches) !== 1) {
            throw new RuntimeException('Empreinte officielle SHA-256 absente ou incorrecte.');
        }
        return $matches[1];
    }

    private static function download(string $url, string $target): void
    {
        $context = stream_context_create(['http' => [
            'timeout' => 40, 'follow_location' => 1, 'max_redirects' => 5,
            'header' => "User-Agent: Portix-P3\r\n",
        ]]);
        $source = @fopen($url, 'rb', false, $context);
        if ($source === false) {
            throw new RuntimeException('Impossible de télécharger la version officielle.');
        }
        $destination = @fopen($target, 'wb');
        if ($destination === false) { fclose($source); throw new RuntimeException('Stockage temporaire indisponible.'); }
        try {
            $bytes = stream_copy_to_stream($source, $destination, self::MAX_ZIP_BYTES + 1);
            if ($bytes === false || $bytes < 100 || $bytes > self::MAX_ZIP_BYTES) {
                throw new RuntimeException('Taille de l’archive P3 invalide.');
            }
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    /**
     * Called only after the ZIP has been downloaded from the fixed official
     * release endpoint and verified against its independent checksum file.
     * Exposed for isolated fixture tests using a local test ZIP.
     */
    public static function applyVerifiedArchive(
        string $root,
        string $archive,
        string $version,
        string $work
    ): string {
        $root = rtrim($root, '/\\');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Archive P3 illisible.');
        }
        $files = [];
        $seen = [];
        try {
            if ($zip->numFiles < 3 || $zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException('Nombre de fichiers inattendu dans l’archive.');
            }
            $totalSize = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat) || ($stat['size'] ?? 0) > 12582912 || ($stat['size'] ?? 0) < 0) {
                    throw new RuntimeException('Un fichier de l’archive est trop volumineux.');
                }
                $totalSize += (int) $stat['size'];
                if ($totalSize > 100663296) { throw new RuntimeException('Archive décompressée trop volumineuse.'); }
                $entry = (string) ($stat['name'] ?? '');
                if (!str_starts_with($entry, 'P3/')) {
                    throw new RuntimeException('Archive P3 : racine inattendue.');
                }
                if (str_ends_with($entry, '/')) { continue; }
                $relative = substr($entry, 3);
                if (!self::safePath($relative) || isset($seen[$relative])) {
                    throw new RuntimeException('Chemin dangereux ou en double dans l’archive.');
                }
                $seen[$relative] = true;
                $opsys = 0; $attributes = 0;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if ($opsys === ZipArchive::OPSYS_UNIX
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Lien symbolique refusé dans l’archive.');
                }
                if (self::isApplicationFile($relative)) {
                    $files[$relative] = $i;
                }
            }
            foreach (['VERSION', 'index.php', 'src/Core/Application.php', 'includes/engine.php'] as $required) {
                if (!isset($files[$required])) {
                    throw new RuntimeException('L’archive ne contient pas tous les fichiers du moteur.');
                }
            }
            if (trim((string) $zip->getFromIndex($files['VERSION'])) !== $version) {
                throw new RuntimeException('La version interne de l’archive ne correspond pas.');
            }

            // Extract and validate all files before touching the site.
            $staged = $work . '/staged-' . bin2hex(random_bytes(5));
            if (!mkdir($staged, 0700)) {
                throw new RuntimeException('Impossible de préparer les fichiers de mise à jour.');
            }
            foreach ($files as $relative => $index) {
                $to = $staged . '/' . $relative;
                if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0700, true) && !is_dir(dirname($to))) {
                    throw new RuntimeException('Dossier temporaire inaccessible.');
                }
                $source = $zip->getStream($zip->getNameIndex($index));
                $dest = @fopen($to, 'wb');
                if ($source === false || $dest === false) {
                    if (is_resource($source)) { fclose($source); }
                    if (is_resource($dest)) { fclose($dest); }
                    throw new RuntimeException('Extraction impossible.');
                }
                $copied = stream_copy_to_stream($source, $dest);
                fclose($source); fclose($dest);
                if ($copied === false || $copied !== (int) $zip->statIndex($index)['size']) {
                    throw new RuntimeException('Extraction incomplète.');
                }
            }

            // Complete private rollback point, including DB and existing application.
            $rollbackPath = UpdateSafety::createRollbackPoint($root);
            $rollbackName = basename($rollbackPath);
            $previous = $work . '/previous-' . bin2hex(random_bytes(5));
            if (!mkdir($previous, 0700)) {
                throw new RuntimeException('Sauvegarde temporaire indisponible.');
            }
            $changed = [];
            $maintenance = false;
            try {
                UpdateSafety::enableMaintenance($root, $version);
                $maintenance = true;
                // VERSION last, so an interrupted update never advertises a new version.
                unset($files['VERSION']);
                $files['VERSION'] = -1;
                foreach (array_keys($files) as $relative) {
                    self::assertDestinationSafe($root, $relative);
                    $destination = $root . '/' . $relative;
                    $from = $staged . '/' . $relative;
                    $old = $previous . '/' . $relative;
                    if (!is_dir(dirname($destination))
                        && !mkdir(dirname($destination), 0755, true)
                        && !is_dir(dirname($destination))) {
                        throw new RuntimeException('Impossible de créer le dossier ' . dirname($relative));
                    }
                    $existed = is_file($destination);
                    if ($existed) {
                        if (!is_dir(dirname($old)) && !mkdir(dirname($old), 0700, true)
                            && !is_dir(dirname($old))) {
                            throw new RuntimeException('Sauvegarde temporaire impossible.');
                        }
                        if (!copy($destination, $old)) {
                            throw new RuntimeException('Impossible de protéger le fichier ' . $relative);
                        }
                    }
                    $tmp = $destination . '.p3-update-' . bin2hex(random_bytes(5));
                    if (!copy($from, $tmp)) { throw new RuntimeException('Écriture impossible : ' . $relative); }
                    if (!rename($tmp, $destination)) {
                        @unlink($tmp);
                        throw new RuntimeException('Remplacement impossible : ' . $relative);
                    }
                    $changed[] = [$destination, $old, $existed];
                }
                UpdateSafety::markRelease($root, $version, 'update');
                if (function_exists('opcache_reset')) { @opcache_reset(); }
            } catch (Throwable $error) {
                // Roll back only files changed in this request; the comprehensive
                // restoration point remains available in Configuration.
                foreach (array_reverse($changed) as [$destination, $old, $existed]) {
                    if ($existed) { @copy($old, $destination); }
                    else { @unlink($destination); }
                }
                throw $error;
            } finally {
                if ($maintenance) { UpdateSafety::disableMaintenance($root); }
            }
            return $rollbackName;
        } finally {
            $zip->close();
        }
    }

    private static function safePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 260 || str_contains($path, '\\')
            || str_contains($path, "\0") || str_starts_with($path, '/')) { return false; }
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..') { return false; }
        }
        return true;
    }

    private static function isApplicationFile(string $relative): bool
    {
        if (in_array($relative, ['VERSION', 'index.php', 'install.php',
            'README.md', 'RELEASE-NOTES.md', 'LICENSE'], true)) { return true; }
        foreach (['bootstrap/', 'includes/', 'src/', 'tools/',
            'var/admin/', 'var/editor/', 'var/lang/', 'var/modules/'] as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                // Never overwrite an existing module's runtime data.
                if (preg_match('~(?:^|/)(?:uploads|private|cache|sessions)/~', $relative)) {
                    return false;
                }
                return !str_ends_with($relative, '/.htaccess');
            }
        }
        return false;
    }

    private static function assertDestinationSafe(string $root, string $relative): void
    {
        $parts = explode('/', $relative);
        $path = $root;
        foreach ($parts as $part) {
            $path .= '/' . $part;
            if (is_link($path)) {
                throw new RuntimeException('Lien symbolique refusé : ' . $relative);
            }
        }
        if (is_dir($path)) {
            throw new RuntimeException('Un dossier remplace un fichier : ' . $relative);
        }
    }

    private static function removeTemporary(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) { return; }
        $children = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($children as $child) {
            $name = $child->getPathname();
            if ($child->isLink() || $child->isFile()) { @unlink($name); }
            elseif ($child->isDir()) { @rmdir($name); }
        }
        @rmdir($dir);
    }
}
