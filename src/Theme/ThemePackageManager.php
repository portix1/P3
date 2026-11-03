<?php
declare(strict_types=1);

namespace Portix\Theme;

use FilesystemIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Local archives only. Never installs shared resources or activates a theme. */
final class ThemePackageManager
{
    private string $private;
    private string $templates;
    public function __construct(private readonly string $root, private readonly string $version, private readonly ?\Closure $activationGuard = null)
    {
        $this->private = $root . '/var/private/theme-packages';
        $this->templates = $root . '/var/templates';
        foreach ([$root . '/var', $root . '/var/private', $this->private, $this->templates] as $directory) {
            if (is_link($directory)) { throw new RuntimeException('Un dossier de gestion des thèmes est un lien symbolique.'); }
            if (!is_dir($directory) && !mkdir($directory, 0750, true)) { throw new RuntimeException('Impossible de préparer les dossiers des thèmes.'); }
        }
        if (!is_file($this->private . '/.htaccess')) { $this->jsonFile($this->private . '/index.json', []); file_put_contents($this->private . '/.htaccess', "Require all denied\nDeny from all\n"); }
    }

    public function stage(string $archive): array
    {
        if (!class_exists(ZipArchive::class) || !is_file($archive) || filesize($archive) > 12 * 1024 * 1024) { throw new RuntimeException('Archive ZIP absente, trop volumineuse ou extension ZIP indisponible.'); }
        $token = bin2hex(random_bytes(16)); $work = $this->private . '/stage-' . $token;
        mkdir($work, 0700);
        try {
            $zip = new ZipArchive(); if ($zip->open($archive, ZipArchive::CHECKCONS) !== true) { throw new RuntimeException('Archive ZIP illisible.'); }
            $names = []; $entries = []; $id = null; $total = 0;
            try {
                if ($zip->numFiles < 1 || $zip->numFiles > 1000) { throw new RuntimeException('Nombre de fichiers invalide.'); }
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i); $stat = $zip->statIndex($i);
                    if (!is_array($stat) || str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/') || str_contains($name, '..')) { throw new RuntimeException('Chemin ZIP dangereux.'); }
                    $parts = explode('/', rtrim($name, '/'));
                    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $parts[0])) { throw new RuntimeException('Dossier racine ZIP invalide.'); }
                    $id ??= $parts[0]; if ($id !== $parts[0] || $id === 'shared') { throw new RuntimeException('Un seul thème par archive ; shared est protégé.'); }
                    $relative = implode('/', array_slice($parts, 1));
                    if (str_ends_with($name, '/')) {
                        foreach ($parts as $part) { if ($part === '' || $part === '.' || !preg_match('/^[A-Za-z0-9_-]+$/D', $part)) { throw new RuntimeException('Dossier ZIP invalide.'); } }
                    } elseif ($relative !== 'theme.json' && !ThemeManifest::validPath($relative)) { throw new RuntimeException('Type de fichier ZIP interdit.'); }
                    $lower = strtolower(rtrim($name, '/')); if (isset($names[$lower])) { throw new RuntimeException('Chemin ZIP dupliqué.'); } $names[$lower] = true;
                    $ops = 0; $attrs = 0;
                    if ($zip->getExternalAttributesIndex($i, $ops, $attrs)) {
                        $type = ($attrs >> 16) & 0170000;
                        if ($type !== 0 && $type !== 0100000 && $type !== 0040000) { throw new RuntimeException('Liens et fichiers spéciaux interdits dans le ZIP.'); }
                    }
                    $size = (int) ($stat['size'] ?? 0); $total += $size;
                    if ($size < 0 || $total > 25 * 1024 * 1024 || ($relative === 'theme.json' && $size > 131072)) { throw new RuntimeException('Archive décompressée trop volumineuse.'); }
                    if (!str_ends_with($name, '/')) { $entries[$relative] = [$i, $size]; }
                }
                if (!isset($entries['theme.json'])) { throw new RuntimeException('theme.json est requis.'); }
                $payload = $work . '/payload/var/templates/' . $id; mkdir($payload, 0700, true);
                foreach ($entries as $relative => [$index, $size]) {
                    $file = $payload . '/' . $relative;
                    if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0700, true)) { throw new RuntimeException('Extraction impossible.'); }
                    $bytes = $zip->getFromIndex($index, $size + 1);
                    if (!is_string($bytes) || strlen($bytes) !== $size || file_put_contents($file, $bytes, LOCK_EX) !== $size) { throw new RuntimeException('Contenu ZIP tronqué ou invalide.'); }
                }
            } finally { $zip->close(); }
            $manifest = ThemeManifest::load($payload); if ($manifest === null || $manifest['kind'] !== 'theme') { throw new RuntimeException('Le paquet doit contenir un thème.'); }
            if (array_diff(array_keys($entries), array_merge(['theme.json'], array_keys($manifest['files']))) !== []) { throw new RuntimeException('Le paquet contient des fichiers non déclarés.'); }
            $this->validatePayload($payload, $manifest);
            $target = $this->templates . '/' . $id;
            $current = (new ThemeCatalog($this->root, $this->version))->inspect($id);
            $conflicts = [];
            if (file_exists($target)) {
                if ($current['errors'] !== []) { throw new RuntimeException('Le thème existant est invalide ou lié : mise à jour refusée.'); }
                $old = ThemeManifest::load($target);
                if ($old === null) { throw new RuntimeException('Le thème existant doit être versionné avant sa mise à jour.'); }
                if (version_compare($manifest['version'], $old['version'], '<=')) { throw new RuntimeException('La version du ZIP doit être supérieure à la version installée.'); }
                if ($current['errors'] !== [] || $current['missing'] !== []) { throw new RuntimeException('Le thème actuel est incomplet ou invalide : mise à jour refusée.'); }
                foreach ($current['changed'] as $path) {
                    if (!isset($manifest['files'][$path]) || hash_file('sha256', $target . '/' . $path) !== $manifest['files'][$path]) { $conflicts[] = $path; }
                }
                foreach ($current['extra'] as $path) {
                    if (isset($manifest['files'][$path]) && hash_file('sha256', $target . '/' . $path) !== $manifest['files'][$path]) { $conflicts[] = $path; }
                }
                foreach ($manifest['files'] as $path => $_) {
                    if (is_dir($target . '/' . $path)) { $conflicts[] = $path; }
                    $parent = dirname($path);
                    while ($parent !== '.') {
                        if (is_file($target . '/' . $parent)) { $conflicts[] = $parent; }
                        $parent = dirname($parent);
                    }
                }
            }
            $plan = ['token' => $token, 'id' => $id, 'name' => $manifest['name'], 'version' => $manifest['version'], 'previous' => $current['version'], 'conflicts' => array_values(array_unique($conflicts)), 'created' => time(), 'before' => is_dir($target) ? $this->fingerprint($target) : null, 'payload' => $this->fingerprint($payload)];
            $this->jsonFile($work . '/plan.json', $plan);
            return $plan;
        } catch (Throwable $error) { $this->remove($work); throw $error; }
    }

    public function cancel(string $token): void { $this->remove($this->stagePath($token)); }
    public function plan(string $token): array {
        $plan = $this->readJson($this->stagePath($token) . '/plan.json');
        if (($plan['created'] ?? 0) < time() - 1800) { throw new RuntimeException('Analyse expirée ; analysez à nouveau le ZIP.'); }
        return $plan;
    }

    public function install(string $token): array
    {
        return $this->locked(function () use ($token): array {
            $work = $this->stagePath($token); $plan = $this->readJson($work . '/plan.json');
            if ($plan['created'] < time() - 1800 || $plan['conflicts'] !== []) { throw new RuntimeException('Analyse expirée ou fichiers locaux en conflit. Relancez une analyse après résolution.'); }
            $id = $this->safeId((string) $plan['id']); $target = $this->templates . '/' . $id;
            if ((is_dir($target) ? $this->fingerprint($target) : null) !== $plan['before']) { throw new RuntimeException('Le thème a changé depuis l’analyse : aucune installation effectuée.'); }
            $payload = $work . '/payload/var/templates/' . $id;
            if ($this->fingerprint($payload) !== $plan['payload']) { throw new RuntimeException('Le paquet analysé a changé.'); }
            $manifest = ThemeManifest::load($payload); $this->validatePayload($payload, $manifest ?? []);
            $candidate = $work . '/candidate';
            if (is_dir($candidate)) { $this->remove($candidate); }
            if (is_dir($target)) {
                $this->copy($target, $candidate);
                $old = ThemeManifest::load($target);
                foreach (array_diff(array_keys($old['files']), array_keys($manifest['files'])) as $removed) { if (is_file($candidate . '/' . $removed)) { unlink($candidate . '/' . $removed); } }
                $this->copy($payload, $candidate);
            } else { $this->copy($payload, $candidate); }
            $this->validatePayload($candidate, $manifest, $id);
            if ((is_dir($target) ? $this->fingerprint($target) : null) !== $plan['before']) { throw new RuntimeException('Le thème a changé pendant la préparation.'); }
            $receipt = $this->switch($id, $candidate, 'install');
            try { $this->remove($work); } catch (Throwable) { /* Installation committed; retain temporary files if cleanup fails. */ }
            return $receipt;
        });
    }

    public function backups(): array
    {
        $result = [];
        foreach (glob($this->private . '/backup-*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (is_link($directory) || !is_file($directory . '/receipt.json')) { continue; }
            try { $row = $this->readJson($directory . '/receipt.json'); if (!empty($row['had_previous'])) { $result[] = $row; } } catch (Throwable) {}
        }
        usort($result, static fn (array $a, array $b): int => $b['created'] <=> $a['created']); return $result;
    }

    public function restore(string $token): array
    {
        return $this->locked(function () use ($token): array {
            $backup = $this->backupPath($token); $receipt = $this->readJson($backup . '/receipt.json');
            $id = $this->safeId((string) $receipt['id']); $target = $this->templates . '/' . $id;
            if (empty($receipt['had_previous']) || !is_dir($backup . '/previous')) { throw new RuntimeException('Aucune version précédente à restaurer.'); }
            if (!is_dir($target) || $this->fingerprint($target) !== $receipt['installed']) { throw new RuntimeException('L’état courant diffère de celui installé (modifications ou autre mise à jour) : restauration automatique refusée pour le préserver.'); }
            $work = $this->private . '/restore-' . bin2hex(random_bytes(16));
            mkdir($work, 0700); $candidate = $work . '/' . $id;
            try {
                $this->copy($backup . '/previous', $candidate);
                $manifest = ThemeManifest::load($candidate);
                if ($this->fingerprint($backup . '/previous') !== $receipt['previous'] || $this->fingerprint($candidate) !== $receipt['previous']) { throw new RuntimeException('Sauvegarde modifiée : restauration refusée.'); }
                $this->validatePayload($candidate, $manifest ?? [], null, false);
                return $this->switch($id, $candidate, 'restore');
            } finally { $this->remove($work); }
        });
    }

    private function validatePayload(string $directory, array $manifest, ?string $id = null, bool $verifyHashes = true): void
    {
        $id ??= $manifest['id'] ?? '';
        if (($manifest['kind'] ?? '') !== 'theme' || !isset($manifest['files'])) { throw new RuntimeException('Manifeste de thème invalide.'); }
        if (version_compare($this->version, $manifest['p3_min'], '<') || version_compare(PHP_VERSION, $manifest['php_min'], '<') || (($manifest['p3_max'] ?? null) !== null && version_compare($this->version, $manifest['p3_max'], '>'))) { throw new RuntimeException('Le paquet est incompatible avec cette version de P3 ou de PHP.'); }
        foreach ($manifest['requires'] as $_) { if (!is_file($this->templates . '/shared/breadcrumb.tpl') || is_link($this->templates . '/shared')) { throw new RuntimeException('Les ressources communes shared sont requises.'); } }
        foreach ($manifest['files'] as $path => $hash) { if (!is_file($directory . '/' . $path) || is_link($directory . '/' . $path) || ($verifyHashes && hash_file('sha256', $directory . '/' . $path) !== $hash)) { throw new RuntimeException('Empreinte de fichier non conforme : ' . $path); } }
        // ThemeContract expects var/templates/<id>; use a small isolated validation root.
        $validationRoot = $this->private . '/validate-' . bin2hex(random_bytes(8));
        try {
            $this->copy($directory, $validationRoot . '/var/templates/' . $id);
            $errors = ThemeContract::validate($validationRoot, $id);
            if ($errors !== []) { throw new RuntimeException(implode(' ; ', $errors)); }
        } finally { $this->remove($validationRoot); }
    }

    private function switch(string $id, string $candidate, string $operation): array
    {
        $target = $this->templates . '/' . $id; $token = bin2hex(random_bytes(16)); $backup = $this->backupPath($token);
        mkdir($backup, 0700); $hadPrevious = is_dir($target);
        $previous = $hadPrevious ? $this->fingerprint($target) : null;
        if ($hadPrevious) { $this->copy($target, $backup . '/previous'); if ($this->fingerprint($target) !== $previous || $this->fingerprint($backup . '/previous') !== $previous) { throw new RuntimeException('Le thème a changé pendant sa sauvegarde.'); } }
        $held = $backup . '/held'; $moved = false; $installed = false;
        try {
            if ($hadPrevious && !rename($target, $held)) { throw new RuntimeException('Impossible de préparer le remplacement du thème.'); }
            $moved = $hadPrevious;
            if ($this->activationGuard !== null) { ($this->activationGuard)(); }
            if (!rename($candidate, $target)) { throw new RuntimeException('Impossible d’installer le thème.'); }
            $installed = true;
            chmod($target, 0755);
            $receipt = ['token' => $token, 'id' => $id, 'created' => time(), 'operation' => $operation, 'had_previous' => $hadPrevious, 'previous' => $previous, 'installed' => $this->fingerprint($target), 'version' => ThemeManifest::load($target)['version']];
            $this->jsonFile($backup . '/receipt.json', $receipt);
            if ($moved) { try { $this->remove($held); } catch (Throwable) { /* Keep the redundant copy; the committed theme and immutable backup remain valid. */ } }
            return $receipt;
        } catch (Throwable $error) {
            if ($moved) { if (is_dir($target)) { $this->remove($target); } if (!rename($held, $target)) { throw new RuntimeException('Restauration automatique impossible ; sauvegarde conservée dans les données privées.', 0, $error); } }
            elseif (!$hadPrevious && $installed && is_dir($target)) { $this->remove($target); }
            throw $error;
        }
    }

    private function fingerprint(string $directory): string
    {
        if (is_link($directory)) { throw new RuntimeException('Lien symbolique interdit.'); }
        $entries = [];
        $visit = function (string $path, string $relative = '') use (&$visit, &$entries): void {
            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $file) {
                $name = $relative . $file->getFilename();
                if ($file->isLink()) { throw new RuntimeException('Lien symbolique interdit.'); }
                if ($file->isDir()) { $entries[$name . '/'] = 'directory'; $visit($file->getPathname(), $name . '/'); }
                elseif ($file->isFile()) { $entries[$name] = hash_file('sha256', $file->getPathname()); }
                else { throw new RuntimeException('Fichier spécial interdit.'); }
                if (count($entries) > 2000) { throw new RuntimeException('Thème trop volumineux.'); }
            }
        };
        $visit($directory); ksort($entries); return hash('sha256', json_encode($entries, JSON_THROW_ON_ERROR));
    }
    private function copy(string $source, string $target): void
    {
        if (is_link($source) || is_link($target)) { throw new RuntimeException('Lien symbolique interdit.'); }
        if (!is_dir($target) && !mkdir($target, 0755, true)) { throw new RuntimeException('Copie du thème impossible.'); }
        foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $file) {
            if ($file->isLink()) { throw new RuntimeException('Lien symbolique interdit.'); }
            $destination = $target . '/' . $file->getFilename();
            if ($file->isDir()) { $this->copy($file->getPathname(), $destination); }
            elseif ($file->isFile() && copy($file->getPathname(), $destination)) { chmod($destination, 0644); }
            else { throw new RuntimeException('Copie de fichier impossible.'); }
        }
    }
    private function remove(string $directory): void
    {
        if (is_link($directory)) { throw new RuntimeException('Lien symbolique interdit.'); }
        if (!is_dir($directory)) { return; }
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
            if ($file->isDir() && !$file->isLink()) { $this->remove($file->getPathname()); } else { if (!unlink($file->getPathname())) { throw new RuntimeException('Nettoyage de fichier impossible.'); } }
        }
        if (!rmdir($directory)) { throw new RuntimeException('Nettoyage du dossier impossible.'); }
    }
    private function stagePath(string $token): string { $this->validToken($token); return $this->private . '/stage-' . $token; }
    private function backupPath(string $token): string { $this->validToken($token); return $this->private . '/backup-' . $token; }
    private function validToken(string $token): void { if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { throw new RuntimeException('Identifiant de paquet invalide.'); } }
    private function safeId(string $id): string { if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $id) || $id === 'shared') { throw new RuntimeException('Identifiant de thème invalide.'); } return $id; }
    private function readJson(string $file): array { if (!is_file($file) || is_link($file)) { throw new RuntimeException('Analyse ou sauvegarde introuvable.'); } return json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR); }
    private function jsonFile(string $file, array $value): void { if (file_put_contents($file, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) { throw new RuntimeException('Écriture des métadonnées impossible.'); } }
    private function locked(callable $action): mixed
    {
        $file = fopen($this->private . '/install.lock', 'c');
        if ($file === false || !flock($file, LOCK_EX | LOCK_NB)) { if (is_resource($file)) { fclose($file); } throw new RuntimeException('Une installation de thème est déjà en cours.'); }
        try { return $action(); } finally { flock($file, LOCK_UN); fclose($file); }
    }
}
