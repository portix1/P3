<?php
declare(strict_types=1);

return static function (\PDO $pdo): array {
    $entries = [['path' => 'articles.html', 'lastmod' => null]];

    $categories = $pdo->query(
        'SELECT id, slug, parent_id FROM article_categories ORDER BY position, id'
    )->fetchAll();
    $categoriesById = [];
    foreach ($categories as $category) {
        $categoriesById[(int) $category['id']] = $category;
    }

    $categoryIdsWithPublishedContent = [];
    $rows = $pdo->query(
        "SELECT DISTINCT category_id FROM articles WHERE status = 'published' AND category_id IS NOT NULL"
    )->fetchAll();
    foreach ($rows as $row) {
        $categoryId = (int) $row['category_id'];
        $seen = [];
        while ($categoryId > 0 && isset($categoriesById[$categoryId]) && !isset($seen[$categoryId])) {
            $categoryIdsWithPublishedContent[$categoryId] = true;
            $seen[$categoryId] = true;
            $categoryId = (int) ($categoriesById[$categoryId]['parent_id'] ?? 0);
        }
    }

    foreach ($categories as $category) {
        if (!isset($categoryIdsWithPublishedContent[(int) $category['id']])) {
            continue;
        }
        $entries[] = [
            'path' => 'articles-categorie-' . (string) $category['slug'] . '.html',
            'lastmod' => null,
        ];
    }

    $articles = $pdo->query(
        "SELECT slug, COALESCE(updated_at, published_at) AS lastmod FROM articles "
        . "WHERE status = 'published' ORDER BY slug"
    )->fetchAll();
    foreach ($articles as $article) {
        $entries[] = [
            'path' => (string) $article['slug'] . '.html',
            'lastmod' => (string) ($article['lastmod'] ?? ''),
        ];
    }
    return $entries;
};
