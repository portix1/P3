<?php
declare(strict_types=1);

namespace Portix\Media;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Conservative reference scan; unknown/unreadable sources never mean unused. */
final class MediaUsage
{
    /** @var list<array{bucket:string,label:string,value:string}>|null */
    private ?array $sources = null;
    /** @var list<string> */
    private array $errors = [];

    public function __construct(private readonly PDO $pdo, private readonly string $root)
    {
    }

    /** @return array{current:list<string>,history:list<string>,errors:list<string>} */
    public function inspect(string $path): array
    {
        if ($this->sources === null) { $this->collect(); }
        $result = ['current' => [], 'history' => [], 'errors' => $this->errors];
        foreach ($this->sources ?? [] as $source) {
            if ($this->contains($source['value'], $path)) { $result[$source['bucket']][] = $source['label']; }
        }
        foreach (['current', 'history'] as $bucket) { $result[$bucket] = array_values(array_unique($result[$bucket])); }
        return $result;
    }

    private function collect(): void
    {
        $this->sources = [];
        $bytes = 0;
        try {
            $tables = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                if (!is_string($table) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $table) !== 1) {
                    throw new RuntimeException('Table non analysable.');
                }
                if (preg_match('/^(sqlite_|media_|analytics_)/', $table)
                    || in_array($table, ['application_logs', 'audit_logs', 'rate_limits', 'password_reset_tokens', 'schema_migrations', 'module_schema_tables', 'home_events', 'home_metrics', 'home_cache'], true)) {
                    continue;
                }
                $statement = $this->pdo->query('SELECT * FROM "' . $table . '"');
                $count = 0;
                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    if (++$count > 50000) { throw new RuntimeException('Analyse trop volumineuse : ' . $table); }
                    $values = array_filter($row, 'is_string');
                    $value = implode("\n", $values);
                    $bytes += strlen($value);
                    if ($bytes > 64 * 1024 * 1024) { throw new RuntimeException('Volume d’analyse supérieur à la limite.'); }
                    $this->sources[] = [
                        'bucket' => in_array($table, ['content_revisions', 'content_trash'], true) ? 'history' : 'current',
                        'label' => $table . ' #' . (string) ($row['id'] ?? $count) . (isset($row['title']) ? ' — ' . (string) $row['title'] : ''),
                        'value' => $value,
                    ];
                }
            }
            foreach (['var/templates', 'var/sys', 'var/modules'] as $directory) {
                $base = $this->root . '/' . $directory;
                if (!is_dir($base)) { continue; }
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    $relative = substr($file->getPathname(), strlen($this->root) + 1);
                    if ($file->isLink()) { throw new RuntimeException('Source symbolique non analysée : ' . $relative); }
                    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'tpl', 'css', 'js', 'json', 'xml', 'txt', 'html', 'htm'], true)
                        || str_starts_with($relative, 'var/modules/media/')) { continue; }
                    if ($file->getSize() > 20 * 1024 * 1024 || !is_readable($file->getPathname())) { throw new RuntimeException('Source non lisible : ' . $relative); }
                    $contents = file_get_contents($file->getPathname());
                    if ($contents === false) { throw new RuntimeException('Échec de lecture : ' . $relative); }
                    $bytes += strlen($contents);
                    if ($bytes > 64 * 1024 * 1024) { throw new RuntimeException('Volume d’analyse supérieur à la limite.'); }
                    $this->sources[] = ['bucket' => 'current', 'label' => $relative, 'value' => $contents];
                }
            }
        } catch (\Throwable $error) { $this->errors[] = $error->getMessage(); }
    }

    private function contains(string $value, string $path): bool
    {
        $value = str_replace(['\\/', '/./'], '/', html_entity_decode(rawurldecode($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Includes references in JSON snapshots and CSS; conservative external matches block deletion too.
        return str_contains($value, $path) || str_contains($value, basename($path));
    }
}
