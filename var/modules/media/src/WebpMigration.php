<?php
declare(strict_types=1);

namespace Portix\Media;

use PDO;
use RuntimeException;
use Throwable;

/** Migrate one catalogued PNG per request; originals remain publicly readable. */
final class WebpMigration
{
    private const FIELDS = [
        'articles' => ['body', 'summary', 'featured_image'],
        'news' => ['body', 'intro'],
        'content_revisions' => ['snapshot_json'],
        'content_trash' => ['snapshot_json'],
    ];

    public function __construct(private readonly PDO $pdo, private readonly string $root)
    {
    }

    public function candidates(): array
    {
        if (!function_exists('imagewebp') || !defined('IMG_WEBP_LOSSLESS')) {
            throw new RuntimeException('Conversion sans perte indisponible : GD WebP requis.');
        }
        return $this->pdo->query("SELECT id, public_path, size_bytes FROM media_items WHERE mime_type = 'image/png' ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function retained(string $root, string $path): bool
    {
        static $retained = [];
        if (!array_key_exists($root, $retained)) {
            $retained[$root] = [];
            foreach (glob($root . '/var/private/media-webp/*.json') ?: [] as $file) {
                $entry = json_decode((string) file_get_contents($file), true);
                if (!is_array($entry)) { continue; }
                foreach (['old', 'new'] as $kind) {
                    if (isset($entry[$kind . '_path'], $entry[$kind . '_sha256'])) {
                        $retained[$root][$entry[$kind . '_path']] = $entry[$kind . '_sha256'];
                    }
                }
            }
        }
        if (isset($retained[$root][$path]) && is_file($root . '/' . $path)
            && hash_equals($retained[$root][$path], (string) hash_file('sha256', $root . '/' . $path))) {
            return true;
        }
        return false;
    }

    public function migrate(int $id): array
    {
        $this->candidates(); // Check lossless encoder before any mutation.
        $this->assertScope();
        $query = $this->pdo->prepare('SELECT * FROM media_items WHERE id = ?');
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Média introuvable.'); }
        $old = (string) $row['public_path'];
        if ($row['mime_type'] !== 'image/png' || !preg_match('~^var/uploads/images/[a-zA-Z0-9_-]+\.png$~D', $old)) {
            throw new RuntimeException('Ce média ne correspond pas à un PNG migrable.');
        }
        $source = $this->root . '/' . $old;
        if (is_link($source) || !is_file($source) || !hash_equals($row['sha256'], (string) hash_file('sha256', $source))) {
            throw new RuntimeException('Original absent ou modifié : migration refusée.');
        }
        if (glob($this->root . '/var/private/media/' . $id . '/*')) {
            throw new RuntimeException('Une version précédente existe pour ce média : migration refusée pour préserver sa restauration.');
        }
        $new = substr($old, 0, -4) . '.webp';
        $target = $this->root . '/' . $new;
        $collision = $this->pdo->prepare('SELECT id FROM media_items WHERE public_path = ? AND id <> ?');
        $collision->execute([$new, $id]);
        if ($collision->fetchColumn()) { throw new RuntimeException('Le WebP est déjà répertorié sous un autre identifiant.'); }
        $directory = $this->root . '/var/private/media-webp';
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new RuntimeException('Impossible de préparer le journal privé.');
        }
        $journal = $directory . '/' . $id . '.json';
        $prior = is_file($journal) ? json_decode((string) file_get_contents($journal), true) : null;
        if (file_exists($target)) {
            // Only resume a file produced by our own interrupted transaction.
            if (!is_array($prior) || ($prior['new_path'] ?? '') !== $new
                || ($prior['old_sha256'] ?? '') !== $row['sha256']
                || !hash_equals((string) ($prior['new_sha256'] ?? ''), (string) hash_file('sha256', $target))) {
                throw new RuntimeException('Un fichier WebP existe déjà : aucun écrasement effectué.');
            }
        } else {
            $image = imagecreatefrompng($source);
            if ($image === false) { throw new RuntimeException('PNG illisible.'); }
            imagepalettetotruecolor($image);
            imagesavealpha($image, true);
            $stage = tempnam($directory, 'encode-');
            $verify = tempnam($directory, 'verify-');
            $pixels = tempnam($directory, 'pixels-');
            try {
                if (!imagewebp($image, $stage, IMG_WEBP_LOSSLESS)) { throw new RuntimeException('Échec de conversion.'); }
                $decoded = imagecreatefromwebp($stage);
                if ($decoded === false) { throw new RuntimeException('WebP illisible.'); }
                imagesavealpha($decoded, true);
                // Deterministic PNG re-encoding compares decoded pixels, including alpha.
                if (!imagepng($image, $pixels, 0) || !imagepng($decoded, $verify, 0)
                    || !hash_equals((string) hash_file('sha256', $pixels), (string) hash_file('sha256', $verify))) {
                    throw new RuntimeException('Pixels différents : original conservé, migration refusée.');
                }
                unset($decoded);
                $entry = ['old_path' => $old, 'old_sha256' => $row['sha256'], 'new_path' => $new,
                    'new_sha256' => hash_file('sha256', $stage), 'original_row' => $row, 'created_at' => gmdate('c')];
                $this->saveJournal($journal, $entry);
                $output = fopen($target, 'x');
                if ($output === false) { throw new RuntimeException('Impossible de créer le WebP sans écrasement.'); }
                $input = fopen($stage, 'rb');
                try {
                    if ($input === false || stream_copy_to_stream($input, $output) !== filesize($stage)) {
                        throw new RuntimeException('Écriture WebP incomplète.');
                    }
                } finally { if (is_resource($input)) { fclose($input); } fclose($output); }
                chmod($target, 0644);
            } finally {
                unset($image);
                foreach ([$stage, $verify, $pixels] as $temporary) { if ($temporary && is_file($temporary)) { unlink($temporary); } }
            }
        }
        $hash = (string) hash_file('sha256', $target);
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $query->execute([$id]);
            if ($query->fetch(PDO::FETCH_ASSOC) !== $row) { throw new RuntimeException('Catalogue modifié : réessayez.'); }
            $changes = $this->references($old, $new, $hash);
            $entry = json_decode((string) file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
            $entry['changes'] = $changes;
            $this->saveJournal($journal, $entry); // Persist undo values before committing.
            foreach ($changes as $change) {
                $sql = 'UPDATE ' . $this->quote($change['table']) . ' SET ' . $this->quote($change['column']) . ' = ? WHERE id = ?';
                $this->pdo->prepare($sql)->execute([$change['after'], $change['id']]);
            }
            $this->pdo->prepare('UPDATE media_items SET public_path = ?, mime_type = ?, size_bytes = ?, sha256 = ?, updated_at = ? WHERE id = ?')
                ->execute([$new, 'image/webp', filesize($target), $hash, gmdate('Y-m-d H:i:s'), $id]);
            $this->pdo->exec('COMMIT');
        } catch (Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
        return ['id' => $id, 'path' => $new, 'before' => (int) $row['size_bytes'], 'after' => filesize($target), 'references' => count($changes)];
    }

    private function assertScope(): void
    {
        // A custom theme/module may own other references: fail closed rather than guess.
        $paths = $this->pdo->query("SELECT public_path FROM media_items WHERE mime_type = 'image/png'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (in_array($table, ['media_items', 'application_logs', 'audit_logs'], true)) { continue; }
            foreach ($this->pdo->query('PRAGMA table_info(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $name = $column['name'];
                if (!str_contains(strtoupper($column['type']), 'TEXT') || in_array($name, self::FIELDS[$table] ?? [], true)) { continue; }
                foreach ($paths as $path) {
                    $q = $this->pdo->prepare('SELECT 1 FROM ' . $this->quote($table) . ' WHERE instr(' . $this->quote($name) . ', ?) > 0 LIMIT 1');
                    $q->execute([basename($path)]);
                    if ($q->fetchColumn()) { throw new RuntimeException('Référence hors périmètre dans ' . $table . '.' . $name . ' : migration refusée.'); }
                }
            }
        }
        $config = is_file($this->root . '/config.json') ? (string) file_get_contents($this->root . '/config.json') : '';
        foreach ($paths as $path) {
            if (str_contains($config, basename($path))) { throw new RuntimeException('Référence dans config.json : migration refusée.'); }
        }
    }

    private function references(string $old, string $new, string $hash): array
    {
        $changes = [];
        foreach (self::FIELDS as $table => $columns) {
            $exists = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
            $exists->execute([$table]);
            if (!$exists->fetchColumn()) { continue; }
            $actual = array_column($this->pdo->query('PRAGMA table_info(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
            foreach (array_intersect($columns, $actual) as $column) {
                $q = $this->pdo->prepare('SELECT id, ' . $this->quote($column) . ' AS value FROM ' . $this->quote($table) . ' WHERE instr(' . $this->quote($column) . ', ?) > 0');
                $q->execute([basename($old)]);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $record) {
                    $value = (string) $record['value'];
                    if ($column === 'snapshot_json') {
                        $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                        $after = json_encode($this->replaceValue($data, $old, $new, $hash), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    } else { $after = $this->replaceValue($value, $old, $new, $hash); }
                    if (str_contains($after, basename($old))) { throw new RuntimeException('Référence PNG non reconnue : migration refusée.'); }
                    if ($after !== $value) { $changes[] = ['table' => $table, 'column' => $column, 'id' => $record['id'], 'before' => $value, 'after' => $after]; }
                }
            }
        }
        return $changes;
    }

    private function replaceValue(mixed $value, string $old, string $new, string $hash): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) { $value[$key] = $this->replaceValue($item, $old, $new, $hash); }
            return $value;
        }
        if (!is_string($value)) { return $value; }
        $pattern = '~' . preg_quote($old, '~') . '(?:\?p3media=[a-f0-9]{16})?(?=[\s\x22\x27<>?#&)]|$)~';
        return preg_replace_callback($pattern, static fn (array $match): string => $new . (str_contains($match[0], '?p3media=') ? '?p3media=' . substr($hash, 0, 16) : ''), $value);
    }

    private function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function saveJournal(string $path, array $entry): void
    {
        $stage = tempnam(dirname($path), 'journal-');
        if ($stage === false || file_put_contents($stage, json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) === false) {
            throw new RuntimeException('Journal de migration inaccessible.');
        }
        chmod($stage, 0600);
        if (!rename($stage, $path)) { throw new RuntimeException('Impossible de conserver le journal.'); }
    }
}
