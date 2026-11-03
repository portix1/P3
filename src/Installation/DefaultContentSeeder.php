<?php
declare(strict_types=1);

namespace Portix\Installation;

use JsonException;
use PDO;
use RuntimeException;

final class DefaultContentSeeder
{
    public function __construct(
        private readonly string $contentFile = __DIR__ . '/default-content.json'
    ) {
    }

    public function seed(PDO $pdo, string $author, string $now): void
    {
        $content = $this->load();
        $categoryIds = $this->seedCategories($pdo, $content['categories']);
        $this->seedArticles($pdo, $content['articles'], $categoryIds, $author, $now);
        $this->seedNews($pdo, $content['news'], $author, $now);
    }

    /** @return array{categories:list<array<string,mixed>>,articles:list<array<string,mixed>>,news:list<array<string,mixed>>} */
    private function load(): array
    {
        $json = @file_get_contents($this->contentFile);
        if (!is_string($json) || $json === '') {
            throw new RuntimeException('Le contenu initial de Portix 3 est introuvable.');
        }

        try {
            $content = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Le contenu initial de Portix 3 est invalide.', 0, $exception);
        }

        if (!is_array($content)
            || !isset($content['categories'], $content['articles'], $content['news'])
            || !is_array($content['categories'])
            || !is_array($content['articles'])
            || !is_array($content['news'])) {
            throw new RuntimeException('La structure du contenu initial de Portix 3 est invalide.');
        }

        return [
            'categories' => array_values($content['categories']),
            'articles' => array_values($content['articles']),
            'news' => array_values($content['news']),
        ];
    }

    /**
     * @param list<array<string,mixed>> $categories
     * @return array<string,int>
     */
    private function seedCategories(PDO $pdo, array $categories): array
    {
        $insert = $pdo->prepare(
            'INSERT INTO article_categories (name, slug, parent_id, position) '
            . 'VALUES (:name, :slug, :parent_id, :position) '
            . 'ON CONFLICT(slug) DO NOTHING'
        );
        $find = $pdo->prepare('SELECT id FROM article_categories WHERE slug = :slug');
        $ids = [];

        foreach ($categories as $category) {
            $slug = trim((string) ($category['slug'] ?? ''));
            $name = trim((string) ($category['name'] ?? ''));
            $parentSlug = trim((string) ($category['parent_slug'] ?? ''));
            if ($slug === '' || $name === '') {
                throw new RuntimeException('Une catégorie du contenu initial est invalide.');
            }
            $parentId = null;
            if ($parentSlug !== '') {
                if (!isset($ids[$parentSlug])) {
                    throw new RuntimeException('Catégorie parente introuvable dans le contenu initial : ' . $parentSlug);
                }
                $parentId = $ids[$parentSlug];
            }
            $insert->execute([
                'name' => $name,
                'slug' => $slug,
                'parent_id' => $parentId,
                'position' => max(0, (int) ($category['position'] ?? 0)),
            ]);
            $find->execute(['slug' => $slug]);
            $id = $find->fetchColumn();
            if ($id === false) {
                throw new RuntimeException('Impossible de retrouver la catégorie initiale : ' . $slug);
            }
            $ids[$slug] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $articles
     * @param array<string,int> $categoryIds
     */
    private function seedArticles(
        PDO $pdo,
        array $articles,
        array $categoryIds,
        string $author,
        string $now
    ): void {
        $insert = $pdo->prepare(
            'INSERT INTO articles '
            . '(category_id, title, slug, summary, body, status, position, created_by, published_at, created_at, updated_at) '
            . 'VALUES (:category_id, :title, :slug, :summary, :body, :status, :position, :created_by, '
            . ':published_at, :created_at, :updated_at) '
            . 'ON CONFLICT(slug) DO NOTHING'
        );

        foreach ($articles as $article) {
            $slug = trim((string) ($article['slug'] ?? ''));
            $title = trim((string) ($article['title'] ?? ''));
            $categorySlug = trim((string) ($article['category_slug'] ?? ''));
            if ($slug === '' || $title === '') {
                throw new RuntimeException('Un article du contenu initial est invalide.');
            }
            $categoryId = null;
            if ($categorySlug !== '') {
                if (!isset($categoryIds[$categorySlug])) {
                    throw new RuntimeException('Catégorie d’article initiale introuvable : ' . $categorySlug);
                }
                $categoryId = $categoryIds[$categorySlug];
            }
            $insert->execute([
                'category_id' => $categoryId,
                'title' => $title,
                'slug' => $slug,
                'summary' => '',
                'body' => (string) ($article['body'] ?? ''),
                'status' => 'published',
                'position' => max(0, (int) ($article['position'] ?? 0)),
                'created_by' => $author,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param list<array<string,mixed>> $news */
    private function seedNews(PDO $pdo, array $news, string $author, string $now): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO news '
            . '(title, slug, created_by, intro, body, status, published_at, created_at, updated_at) '
            . 'VALUES (:title, :slug, :created_by, :intro, :body, :status, :published_at, :created_at, :updated_at) '
            . 'ON CONFLICT(slug) DO NOTHING'
        );

        foreach ($news as $item) {
            $slug = trim((string) ($item['slug'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            if ($slug === '' || $title === '') {
                throw new RuntimeException('Une actualité du contenu initial est invalide.');
            }
            $insert->execute([
                'title' => $title,
                'slug' => $slug,
                'created_by' => $author,
                'intro' => (string) ($item['intro'] ?? ''),
                'body' => (string) ($item['body'] ?? ''),
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
