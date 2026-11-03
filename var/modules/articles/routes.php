<?php
declare(strict_types=1);

use PortixModule\Articles\ArticleRepository;

require_once __DIR__ . '/src/ArticleRepository.php';

return static function (\PDO $pdo, string $slug): ?array {
    $article = (new ArticleRepository($pdo))->findPublishedBySlug($slug);
    return $article === null ? null : ['articles', 'page', $slug];
};
