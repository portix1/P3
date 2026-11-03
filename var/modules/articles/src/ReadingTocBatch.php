<?php
declare(strict_types=1);
namespace PortixModule\Articles;
use Portix\Infrastructure\Database;
use PDO;
use RuntimeException;

/** One-off, administrator-only activation; subsequent manual choices are retained. */
final class ReadingTocBatch
{
    private const MARKER = 'toc_four_headings_batch_1';

    public static function headingCount(string $body): int
    {
        if (!preg_match('/<h[2-6]\b/i', $body)) { return 0; }
        $html = \PortixHtmlSanitizer::clean($body);
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadHTML('<?xml encoding="UTF-8"><div id="p3-toc-count">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $root = $dom->getElementById('p3-toc-count');
            if ($root === null) { return 0; }
            $count = 0;
            foreach ((new \DOMXPath($dom))->query('.//h2 | .//h3 | .//h4 | .//h5 | .//h6', $root) as $heading) {
                if (preg_match('/\S/u', str_replace("\xc2\xa0", ' ', $heading->textContent))) { $count++; }
            }
            return $count;
        } finally {
            libxml_clear_errors(); libxml_use_internal_errors($previous);
        }
    }

    /** @return array{enabled:int,backup:string}|null */
    public static function applyOnce(Database $database, string $root): ?array
    {
        return $database->transaction(static function (PDO $pdo) use ($root): ?array {
            $query = $pdo->prepare('SELECT setting_value FROM article_settings WHERE setting_key=?');
            $query->execute([self::MARKER]);
            if ($query->fetchColumn() !== false) { return null; }
            $rows = $pdo->query('SELECT a.id,a.body,o.toc_enabled FROM articles a LEFT JOIN article_reading_options o ON o.article_id=a.id WHERE COALESCE(o.toc_enabled,0)=0 ORDER BY a.id')->fetchAll();
            $changes = [];
            foreach ($rows as $row) {
                if (self::headingCount((string)$row['body']) > 3) {
                    $changes[] = ['article_id'=>(int)$row['id'], 'previous'=>$row['toc_enabled'] === null ? null : (int)$row['toc_enabled']];
                }
            }
            // Only the affected preferences are backed up; article content and relations are never written.
            $relative = '';
            if ($changes !== []) {
                $directory = rtrim($root, '/\\').'/var/private/reading-toc-backups';
                if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) { throw new RuntimeException('articles_toc_batch_backup_failed'); }
                if (is_link($directory)) { throw new RuntimeException('articles_toc_batch_backup_failed'); }
                $relative='var/private/reading-toc-backups/before-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json';
                $json=json_encode(['created_at'=>gmdate('c'),'threshold'=>4,'changes'=>$changes],JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (file_put_contents(rtrim($root,'/\\').'/'.$relative,$json,LOCK_EX)===false) { throw new RuntimeException('articles_toc_batch_backup_failed'); }
                chmod(rtrim($root,'/\\').'/'.$relative,0600);
            }
            $update=$pdo->prepare('INSERT INTO article_reading_options(article_id,toc_enabled) VALUES(?,1) ON CONFLICT(article_id) DO UPDATE SET toc_enabled=1');
            foreach ($changes as $change) { $update->execute([$change['article_id']]); }
            $result=['enabled'=>count($changes),'backup'=>$relative];
            $marker=$pdo->prepare('INSERT INTO article_settings(setting_key,setting_value) VALUES(?,?)');
            $marker->execute([self::MARKER,json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        });
    }
}
