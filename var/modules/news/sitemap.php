<?php
declare(strict_types=1);

return static function (\PDO $pdo): array {
    $entries = [['path' => 'news.html', 'lastmod' => null]];
    $rows = $pdo->query(
        "SELECT slug, COALESCE(updated_at, published_at) AS lastmod FROM news "
        . "WHERE status = 'published' ORDER BY slug"
    )->fetchAll();
    foreach ($rows as $row) {
        $entries[] = [
            'path' => (string) $row['slug'] . '.html',
            'lastmod' => (string) ($row['lastmod'] ?? ''),
        ];
    }
    return $entries;
};
