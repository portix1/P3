<?php
declare(strict_types=1);

namespace Portix\Media;

use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/MediaUsage.php';
require_once __DIR__ . '/WebpMigration.php';

final class MediaManager
{
    public const DOCUMENT_MAX_BYTES = 20000000;
    private readonly string $privateDirectory;

    public function __construct(private readonly PDO $pdo, private readonly string $root)
    {
        $this->privateDirectory = $root . '/var/private/media';
    }

    /** @return list<array<string,mixed>> */
    public function listing(string $family, string $search): array
    {
        $query = $this->pdo->prepare('SELECT * FROM media_items WHERE (:family = \'\' OR family = :family) '
            . 'AND instr(lower(original_name), lower(:search)) > 0 ORDER BY id DESC LIMIT 300');
        $query->execute(['family' => $family, 'search' => mb_substr($search, 0, 200)]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function item(int $id): array
    {
        $query = $this->pdo->prepare('SELECT * FROM media_items WHERE id = :id');
        $query->execute(['id' => $id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Média introuvable.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    public function register(string $path, string $name, string $actor = ''): array
    {
        $file = $this->path($path);
        $meta = $this->metadata($file);
        $query = $this->pdo->prepare('INSERT INTO media_items '
            . '(public_path, original_name, family, mime_type, size_bytes, sha256, width, height, created_at, created_by, updated_at) '
            . 'VALUES (:path,:name,:family,:mime,:size,:sha,:width,:height,:created,:actor,:updated) '
            . 'ON CONFLICT(public_path) DO UPDATE SET original_name = CASE WHEN media_items.created_by = \'\' AND excluded.created_by != \'\' THEN excluded.original_name ELSE media_items.original_name END, created_by = CASE WHEN media_items.created_by = \'\' THEN excluded.created_by ELSE media_items.created_by END');
        $query->execute([
            'path' => $path, 'name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 240),
            'family' => $meta['family'], 'mime' => $meta['mime'], 'size' => $meta['size'], 'sha' => $meta['sha'],
            'width' => $meta['width'], 'height' => $meta['height'], 'created' => gmdate('Y-m-d H:i:s'),
            'actor' => $actor, 'updated' => gmdate('Y-m-d H:i:s'),
        ]);
        $query = $this->pdo->prepare('SELECT * FROM media_items WHERE public_path = :path');
        $query->execute(['path' => $path]);
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    /** Save reusable editorial defaults, without altering image bytes or articles. */
    public function setAccessibility(int $id, string $altText, bool $decorative): array
    {
        $item = $this->item($id);
        if ($item['family'] !== 'image') {
            throw new RuntimeException('Les métadonnées d’accessibilité ne concernent que les images.');
        }
        $altText = trim($altText);
        if (mb_strlen($altText, 'UTF-8') > 250) {
            throw new RuntimeException('Le texte alternatif dépasse 250 caractères.');
        }
        if ($decorative) {
            $altText = '';
        } elseif ($altText === '') {
            throw new RuntimeException('Renseignez un texte alternatif ou marquez l’image comme décorative.');
        }
        $query = $this->pdo->prepare(
            'UPDATE media_items SET alt_text = :alt, is_decorative = :decorative, updated_at = :updated WHERE id = :id'
        );
        $query->execute([
            'alt' => $altText, 'decorative' => $decorative ? 1 : 0,
            'updated' => gmdate('Y-m-d H:i:s'), 'id' => $id,
        ]);
        return $this->item($id);
    }

    /** @return array{added:int,unknown:list<string>} */
    public function inventory(): array
    {
        $result = ['added' => 0, 'unknown' => []];
        foreach (['var/uploads/images', 'var/uploads/media'] as $directory) {
            foreach (glob($this->root . '/' . $directory . '/*') ?: [] as $file) {
                if (is_link($file) || !is_file($file)) {
                    continue;
                }
                $path = $directory . '/' . basename($file);
                if (WebpMigration::retained($this->root, $path) || MediaBridge::managed($path)) {
                    continue;
                }
                try {
                    $this->register($path, basename($file));
                    $result['added']++;
                } catch (Throwable $error) {
                    $result['unknown'][] = basename($file) . ' — ' . str_replace($this->root, '[site]', $error->getMessage());
                }
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $file @return array<string,mixed> */
    public function upload(array $file, string $actor): array
    {
        $this->assertUpload($file);
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            require_once $this->root . '/includes/class/image_storage.class.php';
            $name = \PortixImageStorage::storeUploadedFile($file, $this->root . '/var/uploads/images');
            return $this->register('var/uploads/images/' . $name, (string) $file['name'], $actor);
        }
        if (!in_array($extension, ['pdf', 'zip'], true)) {
            throw new RuntimeException('Formats acceptés : JPEG, PNG, WebP, PDF et ZIP.');
        }
        $this->validateDocument((string) $file['tmp_name'], $extension);
        $this->directory($this->root . '/var/uploads/media', 0755);
        $this->protectDocuments();
        $path = 'var/uploads/media/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $this->root . '/' . $path)) {
            throw new RuntimeException('Impossible de stocker le document.');
        }
        chmod($this->root . '/' . $path, 0644);
        try {
            return $this->register($path, (string) $file['name'], $actor);
        } catch (Throwable $error) {
            unlink($this->root . '/' . $path);
            throw $error;
        }
    }

    /** @return array{current:list<string>,history:list<string>,errors:list<string>} */
    public function usages(int $id): array
    {
        return $this->verifiedUsage($this->item($id), new MediaUsage($this->pdo, $this->root));
    }

    /** @param array<string,mixed> $row @return array{current:list<string>,history:list<string>,errors:list<string>} */
    private function verifiedUsage(array $row, MediaUsage $scanner): array
    {
        $result = $scanner->inspect((string) $row['public_path']);
        try {
            $file = $this->path((string) $row['public_path']);
            if (!hash_equals((string) $row['sha256'], (string) hash_file('sha256', $file))) {
                $result['errors'][] = 'Fichier modifié hors de la médiathèque : contrôle manuel requis.';
            }
        } catch (Throwable $error) { $result['errors'][] = $error->getMessage(); }
        $result['identical_current'] = [];
        $duplicates = $this->pdo->prepare('SELECT public_path FROM media_items WHERE sha256 = :sha AND id != :id');
        $duplicates->execute(['sha' => $row['sha256'], 'id' => $row['id']]);
        foreach ($duplicates->fetchAll(PDO::FETCH_COLUMN) as $duplicatePath) {
            try {
                $duplicateFile = $this->path((string) $duplicatePath);
                if (!hash_equals((string) $row['sha256'], (string) hash_file('sha256', $duplicateFile))) { continue; }
                $duplicateUses = $scanner->inspect((string) $duplicatePath);
                if ($duplicateUses['current'] !== []) {
                    $result['identical_current'][] = ['path' => $duplicatePath, 'uses' => $duplicateUses['current']];
                }
            } catch (Throwable $error) {
                // An unreadable copy cannot be advertised as byte-identical.
            }
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function analyse(): array
    {
        $result = [];
        // Explicit user action only. Full scan, never an automatic deletion.
        $scanner = new MediaUsage($this->pdo, $this->root);
        foreach ($this->pdo->query('SELECT id, public_path FROM media_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string) $row['id']] = $this->verifiedUsage($this->item((int) $row['id']), $scanner);
        }
        return $result;
    }

    public function delete(int $id, bool $allowHistory): void
    {
        $row = $this->item($id);
        $uses = $this->usages($id);
        if ($uses['errors'] !== [] || $uses['current'] !== []) {
            throw new RuntimeException('Suppression bloquée : média utilisé ou analyse incomplète.');
        }
        if ($uses['history'] !== [] && !$allowHistory) {
            throw new RuntimeException('Ce média est utilisé dans l’historique. Confirmation spécifique requise.');
        }
        $this->directory($this->privateDirectory, 0700);
        $file = $this->path((string) $row['public_path']);
        $directory = $this->privateDirectory . '/' . $id;
        $this->directory($directory, 0700);
        if (is_file($directory . '/pending.json') || is_file($directory . '/delete.json')) { throw new RuntimeException('Récupération nécessaire avant suppression.'); }
        $quarantine = $this->privateDirectory . '/deleted-' . bin2hex(random_bytes(16));
        $this->writeJson($directory . '/delete.json', ['id' => $id, 'target' => $row['public_path'], 'quarantine' => basename($quarantine)]);
        // Move privately first; a database failure restores the public resource.
        if (!rename($file, $quarantine)) {
            unlink($directory . '/delete.json');
            throw new RuntimeException('Impossible de retirer le média.');
        }
        try {
            $query = $this->pdo->prepare('DELETE FROM media_items WHERE id = :id');
            $query->execute(['id' => $id]);
        } catch (Throwable $error) {
            if (rename($quarantine, $file)) { unlink($directory . '/delete.json'); }
            throw $error;
        }
        if (!unlink($quarantine)) { throw new RuntimeException('Média retiré ; finalisation à reprendre.'); }
        unlink($directory . '/delete.json');
        foreach (['previous.bin', 'previous.json'] as $name) {
            $previous = $this->privateDirectory . '/' . $id . '/' . $name;
            if (is_file($previous)) {
                unlink($previous);
            }
        }
    }

    /** @param array<string,mixed> $file */
    public function replace(int $id, array $file, bool $allowDimensions): void
    {
        $this->assertUpload($file);
        $row = $this->item($id);
        $target = $this->path((string) $row['public_path']);
        $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $inputExtension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($row['family'] !== 'image' && $inputExtension !== $row['family']) {
            throw new RuntimeException('Le remplacement doit conserver le type PDF ou ZIP.');
        }
        $directory = $this->privateDirectory . '/' . $id;
        $this->directory($directory, 0700);
        if (is_file($directory . '/pending.json')) {
            throw new RuntimeException('Une opération interrompue doit être récupérée avant ce remplacement.');
        }
        $stage = dirname($target) . '/.' . bin2hex(random_bytes(16)) . '.tmp';
        try {
            if ($row['family'] === 'image') {
                require_once $this->root . '/includes/class/image_storage.class.php';
                $temporaryName = \PortixImageStorage::storeUploadedFile($file, $directory);
                $temporary = $directory . '/' . $temporaryName;
                $image = imagecreatefromwebp($temporary);
                try {
                    if ($image === false) {
                        throw new RuntimeException('Image invalide.');
                    }
                    $ok = match ($extension) {
                        'webp' => imagewebp($image, $stage, 86),
                        'png' => imagepng($image, $stage),
                        'jpg', 'jpeg' => imagejpeg($image, $stage, 90),
                        default => false,
                    };
                    if (!$ok) {
                        throw new RuntimeException('Conversion incompatible avec le média existant.');
                    }
                } finally {
                    if ($image !== false) { imagedestroy($image); }
                    if (is_file($temporary)) { unlink($temporary); }
                }
            } else {
                $this->validateDocument((string) $file['tmp_name'], $extension);
                if (!copy((string) $file['tmp_name'], $stage)) {
                    throw new RuntimeException('Impossible de préparer le document.');
                }
            }
            $meta = $this->metadata($stage, $extension);
            if ($row['family'] === 'image' && !$allowDimensions
                && ((int) $row['width'] !== $meta['width'] || (int) $row['height'] !== $meta['height'])) {
                throw new RuntimeException('Dimensions différentes : confirmer le changement de dimensions.');
            }
            $this->exchange($row, $stage, $meta);
        } finally {
            if (is_file($stage)) { unlink($stage); }
        }
    }

    public function restore(int $id): void
    {
        $row = $this->item($id);
        $previous = $this->privateDirectory . '/' . $id . '/previous.bin';
        if (!is_file($previous)) {
            throw new RuntimeException('Aucune version précédente.');
        }
        $stage = dirname($this->path((string) $row['public_path'])) . '/.' . bin2hex(random_bytes(16)) . '.tmp';
        try {
            if (!copy($previous, $stage)) {
                throw new RuntimeException('Impossible de préparer le retour arrière.');
            }
            $this->exchange($row, $stage, $this->metadata($stage, pathinfo((string) $row['public_path'], PATHINFO_EXTENSION)));
        } finally {
            if (is_file($stage)) { unlink($stage); }
        }
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $meta */
    private function exchange(array $row, string $stage, array $meta): void
    {
        $target = $this->path((string) $row['public_path']);
        $directory = $this->privateDirectory . '/' . $row['id'];
        $this->directory($directory, 0700);
        if (is_file($directory . '/pending.json')) { throw new RuntimeException('Récupération nécessaire avant une nouvelle opération.'); }
        $backup = $directory . '/before-' . bin2hex(random_bytes(16)) . '.bin';
        if (!copy($target, $backup)) {
            throw new RuntimeException('Impossible de conserver la version précédente.');
        }
        chmod($backup, 0600);
        $intent = ['id' => (int) $row['id'], 'target' => $row['public_path'], 'backup' => basename($backup), 'old' => $row['sha256'], 'new' => $meta['sha']];
        $this->writeJson($directory . '/pending.json', $intent);
        try {
            if (!chmod($stage, 0644) || !rename($stage, $target)) {
                throw new RuntimeException('Impossible de remplacer le fichier.');
            }
            $query = $this->pdo->prepare('UPDATE media_items SET mime_type=:mime,size_bytes=:size,sha256=:sha,width=:width,height=:height,updated_at=:updated WHERE id=:id');
            $query->execute(['mime' => $meta['mime'], 'size' => $meta['size'], 'sha' => $meta['sha'], 'width' => $meta['width'], 'height' => $meta['height'], 'updated' => gmdate('Y-m-d H:i:s'), 'id' => $row['id']]);
        } catch (Throwable $error) {
            if (is_file($backup)) { copy($backup, $target); chmod($target, 0644); }
            throw $error;
        }
        if (!rename($backup, $directory . '/previous.bin')) {
            throw new RuntimeException('Média remplacé ; finalisation de l’historique à reprendre.');
        }
        $this->writeJson($directory . '/previous.json', $row);
        unlink($directory . '/pending.json');
    }

    /** Recover interrupted replacements explicitly, under the same global write lock. */
    public function recover(): void
    {
        foreach (glob($this->privateDirectory . '/*/delete.json') ?: [] as $pending) {
            $intent = json_decode((string) file_get_contents($pending), true, 32, JSON_THROW_ON_ERROR);
            $quarantine = $this->privateDirectory . '/' . basename((string) $intent['quarantine']);
            $query = $this->pdo->prepare('SELECT public_path FROM media_items WHERE id = :id');
            $query->execute(['id' => (int) $intent['id']]);
            $targetPath = $query->fetchColumn();
            if (is_string($targetPath)) {
                if ($targetPath !== $intent['target'] || preg_match('~^var/uploads/(?:images|media)/[a-zA-Z0-9._-]+$~D', $targetPath) !== 1) { throw new RuntimeException('Journal de récupération invalide.'); }
                $target = $this->root . '/' . $targetPath;
                if (is_file($quarantine) && !rename($quarantine, $target)) { throw new RuntimeException('Restauration du média impossible.'); }
                if (!is_file($target)) { throw new RuntimeException('Fichier de récupération manquant.'); }
                chmod($target, 0644);
            } elseif (is_file($quarantine) && !unlink($quarantine)) { throw new RuntimeException('Finalisation de suppression impossible.'); }
            unlink($pending);
        }
        foreach (glob($this->privateDirectory . '/*/pending.json') ?: [] as $pending) {
            $intent = json_decode((string) file_get_contents($pending), true, 32, JSON_THROW_ON_ERROR);
            $row = $this->item((int) $intent['id']);
            $target = $this->path((string) $row['public_path']);
            $backup = dirname($pending) . '/' . basename((string) $intent['backup']);
            if (!is_file($backup) && is_file(dirname($pending) . '/previous.bin')
                && hash_equals((string) $intent['old'], (string) hash_file('sha256', dirname($pending) . '/previous.bin'))
                && hash_equals((string) $intent['new'], (string) $row['sha256'])) {
                unlink($pending); continue;
            }
            if (!is_file($backup)) {
                throw new RuntimeException('Sauvegarde de récupération manquante : intervention requise.');
            }
            if (hash_equals((string) $row['sha256'], (string) $intent['new'])) {
                if (!hash_equals((string) $intent['new'], (string) hash_file('sha256', $target))) {
                    throw new RuntimeException('Fichier incohérent : intervention requise.');
                }
                if (!rename($backup, dirname($pending) . '/previous.bin')) {
                    throw new RuntimeException('Finalisation de l’historique impossible.');
                }
            } else {
                if (!copy($backup, $target)) {
                    throw new RuntimeException('Restauration impossible.');
                }
                chmod($target, 0644);
                unlink($backup);
            }
            unlink($pending);
        }
    }

    /** @return array<string,mixed> */
    private function metadata(string $file, ?string $extension = null): array
    {
        $extension ??= strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        $width = 0; $height = 0;
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $dimensions = @getimagesize($file);
            if ($dimensions === false || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw new RuntimeException('Image non reconnue.');
            }
            [$width, $height] = $dimensions;
            $expected = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            if ($mime !== $expected[$extension]) { throw new RuntimeException('Extension et image incompatibles.'); }
            $family = 'image';
        } elseif (in_array($extension, ['pdf', 'zip'], true)) {
            $this->validateDocument($file, $extension);
            $family = $extension;
            $mime = $extension === 'pdf' ? 'application/pdf' : 'application/zip';
        } else {
            throw new RuntimeException('Format non reconnu.');
        }
        return ['family' => $family, 'mime' => $mime, 'size' => filesize($file), 'sha' => hash_file('sha256', $file), 'width' => $width, 'height' => $height];
    }

    private function validateDocument(string $file, string $extension): void
    {
        $size = filesize($file);
        if ($size === false || $size < 1 || $size > self::DOCUMENT_MAX_BYTES) {
            throw new RuntimeException('Document limité à 20 Mo.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if ($extension === 'pdf') {
            $handle = fopen($file, 'rb');
            $header = $handle === false ? '' : fread($handle, 5);
            if ($handle !== false) { fclose($handle); }
            if ($mime !== 'application/pdf' || $header !== '%PDF-') { throw new RuntimeException('PDF invalide.'); }
        } elseif ($extension === 'zip') {
            if (!in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed'], true)) {
                throw new RuntimeException('ZIP invalide.');
            }
            $zip = new \ZipArchive();
            if ($zip->open($file, \ZipArchive::CHECKCONS) !== true) { throw new RuntimeException('Archive ZIP invalide.'); }
            $zip->close();
        } else {
            throw new RuntimeException('Type de document refusé.');
        }
    }

    /** @param array<string,mixed> $file */
    private function assertUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
            || !is_uploaded_file($file['tmp_name']) || !is_string($file['name'] ?? null)) {
            throw new RuntimeException('Transfert incomplet ou invalide.');
        }
    }

    private function path(string $path): string
    {
        if (preg_match('~^var/uploads/(?:images/(?:[a-f0-9]{32}\.(?:jpe?g|png|webp)|[a-z0-9](?:[a-z0-9-]{0,71}[a-z0-9])?-[a-f0-9]{8}\.webp)|media/[a-f0-9]{32}\.(?:pdf|zip))$~D', $path) !== 1) {
            throw new RuntimeException('Chemin de média refusé.');
        }
        $full = $this->root . '/' . $path;
        $parent = realpath(dirname($full));
        $allowed = realpath($this->root . '/var/uploads/' . (str_contains($path, '/images/') ? 'images' : 'media'));
        // realpath uses native separators on Windows; compare canonical paths consistently.
        $resolvedRoot = realpath($this->root);
        $normalParent = $parent === false ? '' : str_replace('\\', '/', $parent);
        $normalRoot = $resolvedRoot === false ? '' : rtrim(str_replace('\\', '/', $resolvedRoot), '/');
        if (is_link($full) || is_link(dirname($full)) || $parent === false || $allowed === false || $parent !== $allowed
            || $resolvedRoot === false || !str_starts_with($normalParent . '/', $normalRoot . '/var/uploads/') || !is_file($full)) {
            throw new RuntimeException('Fichier absent ou chemin non sûr.');
        }
        return $full;
    }

    private function protectDocuments(): void
    {
        $target = $this->root . '/var/uploads/media/.htaccess';
        $template = __DIR__ . '/../assets/public-media.htaccess';
        if (!is_file($target)) {
            $handle = fopen($target, 'x');
            if ($handle === false) { throw new RuntimeException('Protection des documents indisponible.'); }
            try {
                $contents = file_get_contents($template);
                if ($contents === false || fwrite($handle, $contents) !== strlen($contents)) {
                    throw new RuntimeException('Protection des documents incomplète.');
                }
            } finally { fclose($handle); }
            chmod($target, 0644);
        }
        if (is_link($target) || hash_file('sha256', $target) !== hash_file('sha256', $template)) {
            throw new RuntimeException('Protection existante différente : contrôle administrateur requis, aucun écrasement.');
        }
    }

    private function directory(string $path, int $mode): void
    {
        if (is_link($path) || (!is_dir($path) && !mkdir($path, $mode, true))) {
            throw new RuntimeException('Dossier de stockage indisponible.');
        }
    }

    /** @param array<string,mixed> $value */
    private function writeJson(string $path, array $value): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $bytes = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            throw new RuntimeException('Échec d’écriture du journal de récupération.');
        }
    }
}
