<?php
declare(strict_types=1);

namespace PortixModule\Search;

use PDO;
use PDOException;
use RuntimeException;

final class SearchRepository
{
    private ?PDO $indexPdo = null;
    private ?bool $fts5Available = null;
    private string $suggestedQuery = "";

    public function __construct(
        private readonly PDO $sourcePdo,
        private readonly string $indexFile
    ) {
    }

    public function fts5Available(): bool
    {
        if ($this->fts5Available !== null) {
            return $this->fts5Available;
        }

        try {
            $pdo = $this->indexConnection();
            $pdo->exec('CREATE VIRTUAL TABLE IF NOT EXISTS search_fts USING fts5('
                . 'title, summary, category, body, '
                . 'kind UNINDEXED, source_id UNINDEXED, slug UNINDEXED, url UNINDEXED, '
                . "tokenize='unicode61 remove_diacritics 2'"
                . ')');
            $this->fts5Available = true;
        } catch (PDOException $error) {
            if (stripos($error->getMessage(), 'fts5') === false) {
                throw $error;
            }
            $this->fts5Available = false;
        }

        return $this->fts5Available;
    }

    /**
     * Synchronise l'index seulement lorsque les contenus publiés ont changé.
     *
     * @return array{mode:string,indexed:int,articles:int,news:int,reindexed:bool}
     */
    public function sync(bool $force = false): array
    {
        $documents = $this->collectDocuments();
        $articleCount = 0;
        $newsCount = 0;
        foreach ($documents as $document) {
            if ($document['kind'] === 'article') {
                $articleCount++;
            } elseif ($document['kind'] === 'news') {
                $newsCount++;
            }
        }

        if (!$this->fts5Available()) {
            return [
                'mode' => 'fallback',
                'indexed' => 0,
                'articles' => $articleCount,
                'news' => $newsCount,
                'reindexed' => false,
            ];
        }

        $fingerprint = $this->sourceFingerprint();
        $pdo = $this->indexConnection();
        $storedFingerprint = (string) ($this->metaValue('source_fingerprint') ?? '');
        $indexedCount = (int) $pdo->query('SELECT COUNT(*) FROM search_fts')->fetchColumn();

        if (!$force && $storedFingerprint === $fingerprint && $indexedCount === count($documents)) {
            return [
                'mode' => 'fts5',
                'indexed' => $indexedCount,
                'articles' => $articleCount,
                'news' => $newsCount,
                'reindexed' => false,
            ];
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM search_fts');
            $statement = $pdo->prepare(
                'INSERT INTO search_fts '
                . '(title, summary, category, body, kind, source_id, slug, url) '
                . 'VALUES (:title, :summary, :category, :body, :kind, :source_id, :slug, :url)'
            );
            foreach ($documents as $document) {
                $statement->execute($document);
            }
            $this->setMetaValue('source_fingerprint', $fingerprint);
            $this->setMetaValue('last_sync', date('c'));
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        return [
            'mode' => 'fts5',
            'indexed' => count($documents),
            'articles' => $articleCount,
            'news' => $newsCount,
            'reindexed' => true,
        ];
    }

    /**
     * @return list<array{kind:string,source_id:string,slug:string,url:string,title:string,summary:string,category:string,excerpt:string}>
     */
    public function search(string $query, string $kind = 'all', int $limit = 50): array
    {
        $this->suggestedQuery = '';
        $query = mb_substr(trim($query), 0, 120);
        $kind = in_array($kind, ['all', 'article', 'news'], true) ? $kind : 'all';
        $results = $this->exactSearch($query, $kind, $limit);
        if ($results !== [] || $query === '') { return $results; }
        $corrected = $this->correctedQuery($query, $kind);
        if ($corrected === '' || $corrected === $query) { return []; }
        $results = $this->exactSearch($corrected, $kind, $limit);
        if ($results !== []) { $this->suggestedQuery = $corrected; }
        return $results;
    }

    public function suggestedQuery(): string
    {
        return $this->suggestedQuery;
    }

    /** Limited typo recovery from words actually present in published content. */
    private function correctedQuery(string $query, string $kind): string
    {
        preg_match_all('/[\p{L}\p{N}]{2,}/u', mb_strtolower($query), $matches);
        $tokens = array_slice($matches[0] ?? [], 0, 12);
        if ($tokens === []) { return ''; }
        $vocabulary = [];
        foreach ($this->collectDocuments() as $document) {
            if ($kind !== 'all' && $document['kind'] !== $kind) { continue; }
            foreach (['title' => 10, 'summary' => 4, 'category' => 3, 'body' => 1] as $field => $weight) {
                preg_match_all('/[\p{L}\p{N}]{2,48}/u', mb_strtolower($document[$field]), $words);
                foreach (array_unique($words[0] ?? []) as $word) {
                    $key = self::foldWord($word);
                    if (!isset($vocabulary[$key])) { $vocabulary[$key] = ['word' => $word, 'score' => 0]; }
                    $vocabulary[$key]['score'] += $weight;
                }
            }
        }
        $corrected = [];
        foreach ($tokens as $token) {
            $folded = self::foldWord($token);
            if (isset($vocabulary[$folded])) { $corrected[] = $vocabulary[$folded]['word']; continue; }
            $length = mb_strlen($folded);
            if ($length < 4 || $length > 48 || preg_match('/\p{N}/u', $token)) { $corrected[] = $token; continue; }
            // Keep existing prefix matching (e.g. "fend"), including in compatibility mode.
            $prefix = false;
            foreach ($vocabulary as $word => $entry) {
                if (str_starts_with((string) $word, $folded)) { $prefix = true; break; }
            }
            if ($prefix) { $corrected[] = $token; continue; }
            $maximum = $length >= 8 ? 2 : 1;
            $bestDistance = $maximum + 1; $bestScore = -1; $best = $token;
            foreach ($vocabulary as $word => $entry) {
                $word = (string) $word;
                if (abs(mb_strlen($word) - $length) > $maximum) { continue; }
                $distance = self::wordDistance($folded, $word);
                if ($distance <= $maximum && ($distance < $bestDistance
                    || ($distance === $bestDistance && $entry['score'] > $bestScore))) {
                    $best = $entry['word']; $bestDistance = $distance; $bestScore = $entry['score'];
                }
            }
            $corrected[] = $best;
        }
        return implode(' ', $corrected);
    }

    private static function foldWord(string $word): string
    {
        return strtr(mb_strtolower($word), [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','æ'=>'ae',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i','ì'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','ò'=>'o','õ'=>'o','œ'=>'oe',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u','ç'=>'c','ÿ'=>'y',
        ]);
    }

    /** Optimal-string-alignment distance: an adjacent transposition is one error. */
    private static function wordDistance(string $left, string $right): int
    {
        $a = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $previous = range(0, count($b)); $beforePrevious = [];
        foreach ($a as $i => $character) {
            $current = [$i + 1];
            foreach ($b as $j => $other) {
                $current[$j + 1] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($character === $other ? 0 : 1));
                if ($i > 0 && $j > 0 && $character === $b[$j - 1] && $a[$i - 1] === $other) {
                    $current[$j + 1] = min($current[$j + 1], $beforePrevious[$j - 1] + 1);
                }
            }
            $beforePrevious = $previous; $previous = $current;
        }
        return $previous[count($b)];
    }

    private function exactSearch(string $query, string $kind = 'all', int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $kind = in_array($kind, ['all', 'article', 'news'], true) ? $kind : 'all';
        $limit = max(1, min(100, $limit));

        if (!$this->fts5Available()) {
            return $this->fallbackSearch($query, $kind, $limit);
        }

        $this->sync();
        $match = $this->ftsQuery($query);
        if ($match === '') {
            return [];
        }

        $sql = 'SELECT kind, source_id, slug, url, title, summary, category, '
            . 'snippet(search_fts, 3, char(1), char(2), char(3), 30) AS excerpt, '
            . 'snippet(search_fts, 1, char(1), char(2), char(3), 30) AS summary_excerpt, '
            . 'bm25(search_fts, 10.0, 4.0, 3.0, 1.0) AS rank '
            . 'FROM search_fts WHERE search_fts MATCH :match ';
        if ($kind !== 'all') {
            $sql .= 'AND kind = :kind ';
        }
        $sql .= 'ORDER BY rank, title COLLATE NOCASE LIMIT :limit';

        $statement = $this->indexConnection()->prepare($sql);
        $statement->bindValue(':match', $match, PDO::PARAM_STR);
        if ($kind !== 'all') {
            $statement->bindValue(':kind', $kind, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $results = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $markedExcerpt = (string) ($row['excerpt'] ?? '');
            $markedFragment = $markedExcerpt;
            // L'introduction d'une actualité est affichée sur sa page ; le
            // résumé d'un article ne l'est pas nécessairement.
            if ($row['kind'] === 'news' && !str_contains($markedFragment, "\x01")) {
                $markedFragment = (string) ($row['summary_excerpt'] ?? '');
            }
            $excerpt = trim(str_replace(
                ["\x01", "\x02", "\x03"],
                ['', '', ' … '],
                $markedExcerpt
            ));
            if ($excerpt === '') {
                $excerpt = $this->excerpt(
                    (string) (($row['summary'] ?? '') !== '' ? $row['summary'] : '')
                );
            }
            $results[] = [
                'kind' => (string) $row['kind'],
                'source_id' => (string) $row['source_id'],
                'slug' => (string) $row['slug'],
                'url' => $this->textFragmentUrl(
                    (string) $row['url'],
                    $markedFragment,
                    (string) $row['title']
                ),
                'title' => (string) $row['title'],
                'summary' => (string) $row['summary'],
                'category' => (string) $row['category'],
                'excerpt' => $this->excerpt($excerpt),
            ];
        }

        return $results;
    }

    /** @return array{mode:string,indexed:int,articles:int,news:int,last_sync:string} */
    public function stats(): array
    {
        $documents = $this->collectDocuments();
        $articles = 0;
        $news = 0;
        foreach ($documents as $document) {
            $document['kind'] === 'article' ? $articles++ : $news++;
        }

        if (!$this->fts5Available()) {
            return [
                'mode' => 'fallback',
                'indexed' => 0,
                'articles' => $articles,
                'news' => $news,
                'last_sync' => '',
            ];
        }

        return [
            'mode' => 'fts5',
            'indexed' => (int) $this->indexConnection()->query('SELECT COUNT(*) FROM search_fts')->fetchColumn(),
            'articles' => $articles,
            'news' => $news,
            'last_sync' => (string) ($this->metaValue('last_sync') ?? ''),
        ];
    }

    /** @return list<array<string,string>> */
    private function collectDocuments(): array
    {
        $documents = [];

        $articleStatement = $this->sourcePdo->query(
            "SELECT a.id, a.title, a.slug, a.summary, a.body, COALESCE(c.name, '') AS category "
            . 'FROM articles a '
            . 'LEFT JOIN article_categories c ON c.id = a.category_id '
            . "WHERE a.status = 'published' "
            . 'ORDER BY a.id'
        );
        foreach ($articleStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $slug = trim((string) $row['slug']);
            if ($slug === '') {
                continue;
            }
            $documents[] = [
                'title' => $this->normaliseText((string) $row['title']),
                'summary' => $this->normaliseText((string) $row['summary']),
                'category' => $this->normaliseText((string) $row['category']),
                'body' => $this->normaliseText((string) $row['body'], true),
                'kind' => 'article',
                'source_id' => (string) $row['id'],
                'slug' => $slug,
                'url' => $slug . '.html',
            ];
        }

        $newsStatement = $this->sourcePdo->query(
            "SELECT id, title, slug, intro, body FROM news "
            . "WHERE status = 'published' ORDER BY id"
        );
        foreach ($newsStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $slug = trim((string) $row['slug']);
            if ($slug === '') {
                continue;
            }
            $documents[] = [
                'title' => $this->normaliseText((string) $row['title']),
                'summary' => $this->normaliseText((string) $row['intro'], true),
                'category' => '',
                'body' => $this->normaliseText((string) $row['body'], true),
                'kind' => 'news',
                'source_id' => (string) $row['id'],
                'slug' => $slug,
                'url' => $slug . '.html',
            ];
        }

        return $documents;
    }

    private function sourceFingerprint(): string
    {
        // Reconstruire aussi les index 1.0.1 pour conserver les frontières
        // entre blocs HTML utilisées par les liens vers le texte.
        $parts = ['text_format' => 'block-text-1'];
        foreach (['articles', 'news'] as $table) {
            $statement = $this->sourcePdo->query(
                'SELECT COUNT(*) AS item_count, COALESCE(MAX(id), 0) AS max_id, '
                . "COALESCE(MAX(updated_at), '') AS max_updated "
                . 'FROM ' . $table . " WHERE status = 'published'"
            );
            $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
            $parts[$table] = [
                'count' => (int) ($row['item_count'] ?? 0),
                'max_id' => (int) ($row['max_id'] ?? 0),
                'updated' => (string) ($row['max_updated'] ?? ''),
            ];
        }

        return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function indexConnection(): PDO
    {
        if ($this->indexPdo instanceof PDO) {
            return $this->indexPdo;
        }

        $directory = dirname($this->indexFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('search_index_directory_failed');
        }

        $this->indexPdo = new PDO('sqlite:' . $this->indexFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->indexPdo->exec('PRAGMA busy_timeout = 3000');
        $this->indexPdo->exec(
            'CREATE TABLE IF NOT EXISTS search_meta ('
            . 'meta_key TEXT PRIMARY KEY, meta_value TEXT NOT NULL'
            . ')'
        );

        return $this->indexPdo;
    }

    private function metaValue(string $key): ?string
    {
        $statement = $this->indexConnection()->prepare(
            'SELECT meta_value FROM search_meta WHERE meta_key = :key'
        );
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    private function setMetaValue(string $key, string $value): void
    {
        $statement = $this->indexConnection()->prepare(
            'INSERT INTO search_meta (meta_key, meta_value) VALUES (:key, :value) '
            . 'ON CONFLICT(meta_key) DO UPDATE SET meta_value = excluded.meta_value'
        );
        $statement->execute(['key' => $key, 'value' => $value]);
    }

    private function ftsQuery(string $query): string
    {
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $query, $matches);
        $tokens = array_values(array_unique(array_slice($matches[0] ?? [], 0, 12)));
        $parts = [];
        foreach ($tokens as $token) {
            $parts[] = '"' . str_replace('"', '""', $token) . '"*';
        }
        return implode(' AND ', $parts);
    }

    /**
     * @return list<array{kind:string,source_id:string,slug:string,url:string,title:string,summary:string,category:string,excerpt:string}>
     */
    private function fallbackSearch(string $query, string $kind, int $limit): array
    {
        preg_match_all('/[\p{L}\p{N}]{2,}/u', mb_strtolower($query), $matches);
        $tokens = array_values(array_unique(array_slice($matches[0] ?? [], 0, 12)));
        if ($tokens === []) {
            return [];
        }

        $ranked = [];
        foreach ($this->collectDocuments() as $document) {
            if ($kind !== 'all' && $document['kind'] !== $kind) {
                continue;
            }

            $fields = [
                'title' => mb_strtolower($document['title']),
                'summary' => mb_strtolower($document['summary']),
                'category' => mb_strtolower($document['category']),
                'body' => mb_strtolower($document['body']),
            ];
            $score = 0;
            $matchesAll = true;
            foreach ($tokens as $token) {
                $tokenFound = false;
                foreach (['title' => 10, 'summary' => 4, 'category' => 3, 'body' => 1] as $field => $weight) {
                    if (mb_strpos($fields[$field], $token) !== false) {
                        $score += $weight;
                        $tokenFound = true;
                    }
                }
                if (!$tokenFound) {
                    $matchesAll = false;
                    break;
                }
            }
            if (!$matchesAll) {
                continue;
            }

            $markedFragment = $this->markFirstMatch($document['body'], $tokens);
            if ($markedFragment === '' && $document['kind'] === 'news') {
                $markedFragment = $this->markFirstMatch($document['summary'], $tokens);
            }

            $ranked[] = [
                'score' => $score,
                'document' => [
                    'kind' => $document['kind'],
                    'source_id' => $document['source_id'],
                    'slug' => $document['slug'],
                    'url' => $this->textFragmentUrl(
                        $document['url'],
                        $markedFragment,
                        $document['title']
                    ),
                    'title' => $document['title'],
                    'summary' => $document['summary'],
                    'category' => $document['category'],
                    'excerpt' => $this->excerpt(
                        $document['summary'] !== '' ? $document['summary'] : $document['body']
                    ),
                ],
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            $scoreCompare = (int) $right['score'] <=> (int) $left['score'];
            return $scoreCompare !== 0
                ? $scoreCompare
                : strcasecmp((string) $left['document']['title'], (string) $right['document']['title']);
        });

        return array_map(
            static fn (array $entry): array => $entry['document'],
            array_slice($ranked, 0, $limit)
        );
    }

    /** @param list<string> $tokens */
    private function markFirstMatch(string $text, array $tokens): string
    {
        preg_match_all('/[\p{L}\p{N}\p{M}]+/u', $text, $words, PREG_OFFSET_CAPTURE);
        foreach ($words[0] ?? [] as [$word, $offset]) {
            foreach ($tokens as $token) {
                if (mb_stripos($word, $token) !== false) {
                    return substr($text, 0, $offset) . "\x01" . $word . "\x02"
                        . substr($text, $offset + strlen($word));
                }
            }
        }
        return '';
    }

    private function textFragmentUrl(string $url, string $markedText, string $title): string
    {
        $fragment = trim($title);
        // Un texte exact ne doit pas traverser deux blocs HTML. Le caractère
        // 3 délimite les coupures ajoutées par snippet(), pas le contenu réel.
        foreach (preg_split('/[\r\n\x03]+/u', $markedText) ?: [] as $block) {
            if (preg_match('/\x01([^\x01\x02]+)\x02/u', $block, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $text = str_replace(["\x01", "\x02"], '', $block);
            $matchStart = strlen(str_replace(
                ["\x01", "\x02"],
                '',
                substr($block, 0, (int) $match[0][1])
            ));
            $matchEnd = $matchStart + strlen((string) $match[1][0]);
            preg_match_all('/\S+/u', $text, $words, PREG_OFFSET_CAPTURE);
            $words = $words[0] ?? [];
            $firstMatch = null;
            $lastMatch = null;
            foreach ($words as $position => [$word, $offset]) {
                if ($offset < $matchEnd && $offset + strlen($word) > $matchStart) {
                    $firstMatch ??= $position;
                    $lastMatch = $position;
                }
            }
            if ($firstMatch === null || $lastMatch === null) {
                continue;
            }
            $first = max(0, $firstMatch - 2);
            $last = min(count($words) - 1, $lastMatch + 3);
            $start = (int) $words[$first][1];
            $end = (int) $words[$last][1] + strlen((string) $words[$last][0]);
            $fragment = substr($text, $start, $end - $start);
            break;
        }

        if ($fragment === '') {
            return $url;
        }
        // rawurlencode() conserve le tiret, qui est réservé à la syntaxe des
        // fragments de texte ; il faut donc l'encoder explicitement.
        $encoded = str_replace('-', '%2D', rawurlencode($fragment));
        $highlightUrl = $url
            . (str_contains($url, '?') ? '&' : '?')
            . 'p3hl=' . rawurlencode($fragment);
        return $highlightUrl
            . (str_contains($highlightUrl, '#') ? ':~:text=' : '#:~:text=')
            . $encoded;
    }

    private function normaliseText(string $html, bool $preserveBlocks = false): string
    {
        if ($preserveBlocks) {
            $html = preg_replace(
                '/<\/?(?:address|article|aside|blockquote|br|dd|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|table|tbody|td|tfoot|th|thead|tr|ul)\b[^>]*>/iu',
                "\n",
                $html
            ) ?? $html;
        } else {
            $html = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/iu', ' ', $html) ?? $html;
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\x01", "\x02", "\x03"], '', $text);
        if ($preserveBlocks) {
            $text = str_replace("\r", "\n", $text);
            $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
            return trim(preg_replace('/ *\n+ */u', "\n", $text) ?? $text);
        }
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function excerpt(string $text, int $length = 280): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $excerpt = mb_substr($text, 0, $length);
        $space = mb_strrpos($excerpt, ' ');
        if ($space !== false && $space > (int) ($length * 0.65)) {
            $excerpt = mb_substr($excerpt, 0, $space);
        }
        return rtrim($excerpt, " \t\n\r\0\x0B.,;:") . '…';
    }
}
