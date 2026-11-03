<?php
declare(strict_types=1);

return static function (\PDO $pdo): array {
    $entries = [];
    $publishedCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM downloads WHERE status = 'published'"
    )->fetchColumn();
    if ($publishedCount < 1) {
        return $entries;
    }

    $entries[] = ['path' => 'dwl.html', 'lastmod' => null];
    $rows = $pdo->query(
        'SELECT category.slug, MAX(download.updated_at) AS lastmod '
        . 'FROM download_categories category '
        . 'JOIN downloads download ON download.category_id = category.id '
        . "WHERE download.status = 'published' "
        . 'GROUP BY category.id, category.slug ORDER BY category.position, category.id'
    )->fetchAll();
    foreach ($rows as $row) {
        $entries[] = [
            'path' => 'dwl-cat-' . (string) $row['slug'] . '.html',
            'lastmod' => (string) ($row['lastmod'] ?? ''),
        ];
    }
    return $entries;
};
