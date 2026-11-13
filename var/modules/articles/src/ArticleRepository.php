<?php
declare(strict_types=1);

namespace PortixModule\Articles;

use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/EditorialRepository.php';

final class ArticleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function editorial(): EditorialRepository { return new EditorialRepository($this->pdo); }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
    }

    public function countPublished(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function allForAdministration(): array
    {
        return $this->pdo->query(
            'SELECT article.id, article.title, article.slug, article.category_id, article.status, '
            . 'article.created_by, article.updated_at, article.review_note, category.name AS category_name FROM articles article '
            . 'LEFT JOIN article_categories category ON category.id = article.category_id '
            . 'ORDER BY article.updated_at DESC, article.id DESC'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allForOrganization(): array
    {
        return $this->pdo->query(
            'SELECT article.*, category.name AS category_name FROM articles article '
            . 'LEFT JOIN article_categories category ON category.id = article.category_id '
            . 'ORDER BY article.category_id, article.position, article.title COLLATE NOCASE, article.id'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function relatedArticleCandidates(): array
    {
        return $this->pdo->query(
            'SELECT article.id, article.title, article.category_id, article.status, article.created_by, '
            . 'category.name AS category_name FROM articles article '
            . 'LEFT JOIN article_categories category ON category.id = article.category_id'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM articles WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT article.*, category.name AS category_name, category.slug AS category_slug "
            . 'FROM articles article LEFT JOIN article_categories category ON category.id = article.category_id '
            . "WHERE article.slug = :slug AND article.status = 'published'"
        );
        $statement->execute(['slug' => strtolower($slug)]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param bool $includeHidden Autorise la consultation directe d’une branche retirée de la navigation.
     * @return list<array<string, mixed>>
     */
    public function published(?int $categoryId = null, bool $includeHidden = false, bool $includeBodies = true): array
    {
        $items = $this->publicMixedItems($categoryId, $includeHidden, true, $includeBodies);
        $ordered = [];
        $walk = function (array $nodes) use (&$walk, &$ordered): void {
            foreach ($nodes as $node) {
                if (($node['type'] ?? '') === 'article') {
                    $ordered[] = $node['article'];
                    continue;
                }
                if (($node['type'] ?? '') === 'category') {
                    $walk($node['items'] ?? []);
                }
            }
        };
        $walk($items);
        return $ordered;
    }

    /**
     * Table-of-contents data in editorial order.
     *
     * @return array{items:list<array<string,mixed>>}
     */
    public function publicOutline(?int $categoryId = null, bool $includeHidden = false, bool $includeArticles = true): array
    {
        return ['items' => $this->publicMixedItems($categoryId, $includeHidden, $includeArticles, false)];
    }

    public function publishedCategoryIdBySlug(string $slug): ?int
    {
        $statement = $this->pdo->prepare("SELECT category_id FROM articles WHERE slug = ? AND status = 'published'");
        $statement->execute([strtolower($slug)]);
        $categoryId = $statement->fetchColumn();
        return $categoryId === false || $categoryId === null ? null : (int) $categoryId;
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return $this->pdo->query(
            'SELECT category.*, '
            . '(SELECT COUNT(*) FROM articles article '
            . "WHERE article.category_id = category.id AND article.status = 'published') AS article_count "
            . 'FROM article_categories category ORDER BY category.position, category.name COLLATE NOCASE, category.id'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function visibleCategories(): array
    {
        return array_values(array_filter(
            $this->categories(),
            fn (array $category): bool => $this->isCategoryVisibleOnSite((int) $category['id'])
        ));
    }

    /** @return list<array<string, mixed>> */
    public function publishedForNavigation(): array
    {
        $rows = $this->pdo->query(
            "SELECT category_id, title, slug FROM articles "
            . "WHERE status = 'published' AND category_id IS NOT NULL "
            . 'ORDER BY position, title COLLATE NOCASE, id'
        )->fetchAll();
        return array_values(array_filter(
            $rows,
            fn (array $article): bool => $this->isCategoryVisibleOnSite((int) $article['category_id'])
        ));
    }

    /** @return array{header_enabled:bool,show_articles:bool,navigation_mode:string} */
    public function menuSettings(): array
    {
        $settings = [
            'header_enabled' => true,
            'show_articles' => true,
            'navigation_mode' => 'book',
        ];
        $statement = $this->pdo->prepare(
            'SELECT setting_key, setting_value FROM article_settings '
            . 'WHERE setting_key IN (:header_enabled, :show_articles, :navigation_mode)'
        );
        $statement->execute([
            'header_enabled' => 'header_enabled',
            'show_articles' => 'show_articles',
            'navigation_mode' => 'navigation_mode',
        ]);
        foreach ($statement->fetchAll() as $row) {
            $key = (string) $row['setting_key'];
            if ($key === 'navigation_mode' && in_array($row['setting_value'], ['off', 'category', 'book'], true)) {
                $settings[$key] = (string) $row['setting_value'];
            } elseif ($key !== 'navigation_mode' && array_key_exists($key, $settings)) {
                $settings[$key] = (string) $row['setting_value'] === '1';
            }
        }
        return $settings;
    }

    public function saveMenuSettings(bool $headerEnabled, bool $showArticles, string $navigationMode): void
    {
        if (!in_array($navigationMode, ['off', 'category', 'book'], true)) {
            throw new RuntimeException('articles_invalid_navigation_mode');
        }
        $this->transaction(function () use ($headerEnabled, $showArticles, $navigationMode): void {
            $statement = $this->pdo->prepare(
                'INSERT INTO article_settings (setting_key, setting_value) VALUES (:key, :value) '
                . 'ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
            );
            foreach ([
                'header_enabled' => $headerEnabled ? '1' : '0',
                'show_articles' => $showArticles ? '1' : '0',
                'navigation_mode' => $navigationMode,
            ] as $key => $value) {
                $statement->execute(['key' => $key, 'value' => $value]);
            }
        });
    }

    /** @return array<string, mixed>|null */
    public function findCategory(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM article_categories WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findCategoryBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM article_categories WHERE slug = :slug');
        $statement->execute(['slug' => strtolower($slug)]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param list<string> $reserved */
    public function uniqueArticleSlug(string $base, array $reserved, ?int $exceptId = null): string
    {
        return $this->uniqueSlug('articles', $base, $reserved, $exceptId);
    }

    public function uniqueCategorySlug(string $base, ?int $exceptId = null): string
    {
        return $this->uniqueSlug('article_categories', $base, [], $exceptId);
    }

    public function createArticle(
        ?int $categoryId,
        string $title,
        string $slug,
        string $body,
        string $status,
        string $createdBy,
        ?string $publishedAt,
        string $now,
        string $authorName = '',
        string $featuredImage = '',
        string $summary = ''
    ): int {
        $this->assertCategory($categoryId);
        return $this->transaction(function () use (
            $categoryId,
            $title,
            $slug,
            $body,
            $status,
            $createdBy,
            $publishedAt,
            $now,
            $authorName,
            $featuredImage,
            $summary
        ): int {
            $statement = $this->pdo->prepare(
                'INSERT INTO articles '
                . '(category_id, title, slug, summary, body, status, position, created_by, author_name, featured_image, '
                . 'published_at, created_at, updated_at) '
                . 'VALUES (:category_id, :title, :slug, :summary, :body, :status, :position, :created_by, :author_name, :featured_image, '
                . ':published_at, :created_at, :updated_at)'
            );
            $statement->execute([
                'category_id' => $categoryId,
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary,
                'body' => $body,
                'status' => $status,
                'position' => $this->nextMixedPosition($categoryId),
                'created_by' => $createdBy,
                'author_name' => $authorName,
                'featured_image' => $featuredImage,
                'published_at' => $publishedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return (int) $this->pdo->lastInsertId();
        });
    }

    public function updateArticle(
        int $id,
        ?int $categoryId,
        string $title,
        string $slug,
        string $body,
        string $status,
        ?string $publishedAt,
        string $updatedAt,
        string $authorName = '',
        string $featuredImage = '',
        ?string $summary = null
    ): void {
        $this->assertCategory($categoryId);
        $existing = $this->find($id);
        if ($existing === null) {
            throw new RuntimeException('articles_not_found');
        }
        $position = (int) $existing['position'];
        $oldCategoryId = $existing['category_id'] === null ? null : (int) $existing['category_id'];
        if ($oldCategoryId !== $categoryId) {
            $position = $this->nextMixedPosition($categoryId);
        }
        $statement = $this->pdo->prepare(
            'UPDATE articles SET category_id = :category_id, title = :title, slug = :slug, '
            . 'summary = :summary, body = :body, status = :status, author_name = :author_name, '
            . 'featured_image = :featured_image, published_at = :published_at, position = :position, '
            . 'updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary ?? (string) $existing['summary'],
            'body' => $body,
            'status' => $status,
            'author_name' => $authorName,
            'featured_image' => $featuredImage,
            'published_at' => $publishedAt,
            'position' => $position,
            'updated_at' => $updatedAt,
        ]);
    }

    public function deleteArticle(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM articles WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function createCategory(
        string $name,
        string $slug,
        ?int $parentId,
        bool $isVisible = true,
        string $description = ''
    ): int {
        $this->assertCategory($parentId);
        $statement = $this->pdo->prepare(
            'INSERT INTO article_categories (name, slug, parent_id, position, is_visible, description) '
            . 'VALUES (:name, :slug, :parent_id, :position, :is_visible, :description)'
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'position' => $this->nextMixedPosition($parentId),
            'is_visible' => $isVisible ? 1 : 0,
            'description' => $description,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(
        int $id,
        string $name,
        string $slug,
        ?int $parentId,
        bool $isVisible = true,
        ?string $description = null
    ): void {
        $existing = $this->findCategory($id);
        if ($existing === null) {
            throw new RuntimeException('articles_category_not_found');
        }
        $this->assertCategory($parentId);
        if ($parentId === $id || ($parentId !== null && in_array($parentId, $this->descendantCategoryIds($id), true))) {
            throw new RuntimeException('articles_category_cycle');
        }
        $position = (int) $existing['position'];
        $oldParentId = $existing['parent_id'] === null ? null : (int) $existing['parent_id'];
        if ($oldParentId !== $parentId) {
            $position = $this->nextMixedPosition($parentId);
        }
        $statement = $this->pdo->prepare(
            'UPDATE article_categories SET name = :name, slug = :slug, parent_id = :parent_id, '
            . 'position = :position, is_visible = :is_visible, description = :description WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'position' => max(0, $position),
            'is_visible' => $isVisible ? 1 : 0,
            'description' => $description ?? (string) ($existing['description'] ?? ''),
        ]);
    }

    public function updateArticleStatus(int $id, string $status, string $now): void
    {
        if (!in_array($status, ['draft', 'pending', 'published'], true)) {
            throw new RuntimeException('articles_invalid_status');
        }
        $article = $this->find($id);
        if ($article === null) {
            throw new RuntimeException('articles_not_found');
        }
        $publishedAt = $status === 'published'
            ? ((string) ($article['published_at'] ?? '') ?: $now)
            : null;
        $statement = $this->pdo->prepare(
            'UPDATE articles SET status = :status, published_at = :published_at, '
            . 'updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
            'published_at' => $publishedAt,
            'updated_at' => $now,
        ]);
    }

    /** Only the administrator may save an explanatory return-to-author message. */
    public function saveReviewNote(int $id, ?string $note): void
    {
        $statement = $this->pdo->prepare('UPDATE articles SET review_note = :note WHERE id = :id');
        $statement->execute(['id' => $id, 'note' => $note,]);
        if ($statement->rowCount() === 0 && $this->find($id) === null) {
            throw new RuntimeException('articles_not_found');
        }
    }

    public function deleteCategory(int $id): void
    {
        $this->transaction(function () use ($id): void {
            $statement = $this->pdo->prepare('DELETE FROM article_categories WHERE id = :id');
            $statement->execute(['id' => $id]);
        });
    }

    /** @return list<array<string, mixed>> */
    public function flattenedCategories(?int $excludeId = null): array
    {
        return $this->flattenCategories($this->categories(), $excludeId);
    }

    /** @return list<array<string, mixed>> */
    public function flattenedVisibleCategories(): array
    {
        return $this->flattenCategories($this->visibleCategories(), null);
    }

    /**
     * @param list<array<string, mixed>> $categories
     * @return list<array<string, mixed>>
     */
    private function flattenCategories(array $categories, ?int $excludeId): array
    {
        $categories = array_values(array_filter(
            $categories,
            static fn (array $category): bool => (int) $category['id'] !== $excludeId
        ));
        $children = [];
        foreach ($categories as $category) {
            $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
            $children[$parent][] = $category;
        }
        $flattened = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$children, &$flattened): void {
            foreach ($children[$parent] ?? [] as $category) {
                $category['depth'] = $depth;
                $category['indented_name'] = str_repeat('— ', $depth) . (string) $category['name'];
                $flattened[] = $category;
                $walk((int) $category['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $flattened;
    }

    /** @return list<array<string, mixed>> */
    public function categoryTrail(int $categoryId): array
    {
        $byId = [];
        foreach ($this->categories() as $category) {
            $byId[(int) $category['id']] = $category;
        }

        $trail = [];
        $visited = [];
        while (isset($byId[$categoryId]) && !isset($visited[$categoryId])) {
            $visited[$categoryId] = true;
            array_unshift($trail, $byId[$categoryId]);
            $parentId = $byId[$categoryId]['parent_id'];
            if ($parentId === null) {
                break;
            }
            $categoryId = (int) $parentId;
        }
        return $trail;
    }

    /** @return array{previous:?array<string,mixed>,next:?array<string,mixed>} */
    public function publishedNeighbors(int $articleId, string $mode): array
    {
        $empty = ['previous' => null, 'next' => null];
        if ($mode === 'off') {
            return $empty;
        }
        if (!in_array($mode, ['category', 'book'], true)) {
            throw new RuntimeException('articles_invalid_navigation_mode');
        }

        $category = $this->pdo->prepare("SELECT category_id FROM articles WHERE id = ? AND status = 'published'");
        $category->execute([$articleId]);
        $categoryId = (int) $category->fetchColumn();
        if ($categoryId < 1) {
            return $empty;
        }
        if ($mode === 'category') {
            $statement = $this->pdo->prepare(
                "SELECT id, title, slug FROM articles WHERE status = 'published' AND category_id = :category_id "
                . 'ORDER BY position, title COLLATE NOCASE, id'
            );
            $statement->execute(['category_id' => $categoryId]);
            $ordered = $statement->fetchAll();
        } else {
            $rootId = $this->categoryRootId($categoryId);
            $includeHidden = false;
            if (!$this->isCategoryVisibleOnSite($categoryId)) {
                foreach ($this->categoryTrail($categoryId) as $category) {
                    if ((int) ($category['is_visible'] ?? 1) !== 1) {
                        $rootId = (int) $category['id'];
                        $includeHidden = true;
                        break;
                    }
                }
            }
            $ordered = $rootId === null ? [] : $this->published($rootId, $includeHidden, false);
        }

        foreach ($ordered as $index => $candidate) {
            if ((int) $candidate['id'] !== $articleId) {
                continue;
            }
            return [
                'previous' => $index > 0 ? $ordered[$index - 1] : null,
                'next' => isset($ordered[$index + 1]) ? $ordered[$index + 1] : null,
            ];
        }
        return $empty;
    }

    /**
     * @param array<string, list<string>> $groups
     * @param array<string, list<int>>|null $legacyArticleGroups
     */
    public function reorderContent(array $groups, ?array $legacyArticleGroups = null): void
    {
        if ($legacyArticleGroups !== null) {
            $legacyCategoryGroups = $groups;
            $groups = [];
            $groupNames = array_unique(array_merge(array_keys($legacyCategoryGroups), array_keys($legacyArticleGroups)));
            foreach ($groupNames as $group) {
                $tokens = [];
                foreach ($legacyArticleGroups[$group] ?? [] as $id) {
                    $tokens[] = 'article:' . (int) $id;
                }
                foreach ($legacyCategoryGroups[$group] ?? [] as $id) {
                    $tokens[] = 'category:' . (int) $id;
                }
                $groups[(string) $group] = $tokens;
            }
        }

        $current = ['root' => []];
        foreach ($this->allForOrganization() as $article) {
            $group = $article['category_id'] === null ? 'root' : (string) (int) $article['category_id'];
            $current[$group][] = 'article:' . (int) $article['id'];
        }
        foreach ($this->categories() as $category) {
            $group = $category['parent_id'] === null ? 'root' : (string) (int) $category['parent_id'];
            $current[$group][] = 'category:' . (int) $category['id'];
        }

        $ordered = $this->validatedMixedGroupOrder($groups, $current);
        $this->transaction(function () use ($ordered): void {
            $updateCategory = $this->pdo->prepare(
                'UPDATE article_categories SET position = :position WHERE id = :id'
            );
            $updateArticle = $this->pdo->prepare('UPDATE articles SET position = :position WHERE id = :id');

            foreach ($ordered as $tokens) {
                foreach ($tokens as $position => $token) {
                    [$type, $id] = explode(':', $token, 2);
                    $params = ['position' => $position, 'id' => (int) $id];
                    if ($type === 'category') {
                        $updateCategory->execute($params);
                    } else {
                        $updateArticle->execute($params);
                    }
                }
            }
        });
    }

    /** @param list<string> $reserved */
    private function uniqueSlug(string $table, string $base, array $reserved, ?int $exceptId): string
    {
        if (!in_array($table, ['articles', 'article_categories'], true)) {
            throw new RuntimeException('articles_invalid_slug');
        }
        $reserved = array_fill_keys(array_map('strtolower', $reserved), true);
        $candidate = strtolower($base);
        if ($table === 'articles') {
            foreach (array_keys($reserved) as $reservedSlug) {
                if ($candidate === $reservedSlug || str_starts_with($candidate, $reservedSlug . '-')) {
                    $candidate = 'article-' . $candidate;
                    $base = $candidate;
                    break;
                }
            }
        }
        $suffix = 2;
        while (isset($reserved[$candidate]) || $this->slugExists($table, $candidate, $exceptId)) {
            $candidate = substr($base, 0, 170) . '-' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private function slugExists(string $table, string $slug, ?int $exceptId): bool
    {
        $sql = 'SELECT 1 FROM ' . $table . ' WHERE slug = :slug';
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
        }
        $statement = $this->pdo->prepare($sql);
        $parameters = ['slug' => $slug];
        if ($exceptId !== null) {
            $parameters['id'] = $exceptId;
        }
        $statement->execute($parameters);
        if ($statement->fetchColumn() !== false) {
            return true;
        }
        if ($table !== 'articles' || !$this->tableExists('news')) {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM news WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        return $statement->fetchColumn() !== false;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $table]);
        return $statement->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> */
    private function publicMixedItems(?int $categoryId, bool $includeHidden, bool $includeArticles, bool $includeBodies): array
    {
        $categories = $this->categories();
        $byId = [];
        foreach ($categories as $category) {
            $byId[(int) $category['id']] = $category;
        }
        if ($categoryId !== null && !isset($byId[$categoryId])) {
            return [];
        }

        $isVisible = function (int $id) use (&$isVisible, $byId): bool {
            $visited = [];
            while (isset($byId[$id]) && !isset($visited[$id])) {
                $visited[$id] = true;
                if ((int) ($byId[$id]['is_visible'] ?? 1) !== 1) {
                    return false;
                }
                $parent = $byId[$id]['parent_id'];
                if ($parent === null) {
                    return true;
                }
                $id = (int) $parent;
            }
            return false;
        };

        $children = [];
        foreach ($categories as $category) {
            $id = (int) $category['id'];
            if (!$includeHidden && !$isVisible($id)) {
                continue;
            }
            $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
            $children[$parent][] = $category;
        }

        $articles = [];
        if ($includeArticles) {
            $columns = $includeBodies
                ? 'article.*, category.name AS category_name, category.slug AS category_slug'
                : 'article.id, article.category_id, article.position, article.title, article.slug';
            $join = $includeBodies ? 'LEFT JOIN article_categories category ON category.id = article.category_id ' : '';
            $statement = $this->pdo->query(
                'SELECT ' . $columns . ' FROM articles article ' . $join
                . "WHERE article.status = 'published' "
                . 'ORDER BY article.position, article.title COLLATE NOCASE, article.id'
            );
            foreach ($statement as $article) {
                $parent = $article['category_id'] === null ? 0 : (int) $article['category_id'];
                if (!$includeHidden && $parent !== 0 && !$isVisible($parent)) {
                    continue;
                }
                $articles[$parent][] = $article;
            }
        }

        $sortMixed = static function (array &$items, bool $categoriesFirst): void {
            $seenPositions = [];
            $hasCollision = false;
            foreach ($items as $item) {
                $position = (int) $item['position'];
                if (isset($seenPositions[$position])) {
                    $hasCollision = true;
                    break;
                }
                $seenPositions[$position] = true;
            }
            if ($hasCollision) {
                usort($items, static function (array $left, array $right) use ($categoriesFirst): int {
                    if ($left['type'] !== $right['type']) {
                        if ($categoriesFirst) {
                            return $left['type'] === 'category' ? -1 : 1;
                        }
                        return $left['type'] === 'article' ? -1 : 1;
                    }
                    return [$left['position'], $left['label'], $left['id']]
                        <=> [$right['position'], $right['label'], $right['id']];
                });
                return;
            }
            usort($items, static fn (array $left, array $right): int =>
                [$left['position'], $left['type'], $left['label'], $left['id']]
                <=> [$right['position'], $right['type'], $right['label'], $right['id']]
            );
        };

        $build = function (int $parent) use (&$build, &$children, &$articles, $sortMixed): array {
            $items = [];
            foreach ($articles[$parent] ?? [] as $article) {
                $items[] = [
                    'type' => 'article',
                    'id' => (int) $article['id'],
                    'position' => (int) $article['position'],
                    'label' => (string) $article['title'],
                    'article' => $article,
                ];
            }
            foreach ($children[$parent] ?? [] as $category) {
                $id = (int) $category['id'];
                $items[] = [
                    'type' => 'category',
                    'id' => $id,
                    'position' => (int) $category['position'],
                    'label' => (string) $category['name'],
                    'category' => $category,
                    'items' => $build($id),
                ];
            }
            $sortMixed($items, $parent === 0);
            return $items;
        };

        return $build($categoryId ?? 0);
    }

    private function assertCategory(?int $categoryId): void
    {
        if ($categoryId !== null && $this->findCategory($categoryId) === null) {
            throw new RuntimeException('articles_category_not_found');
        }
    }

    /** @return list<int> */
    private function descendantCategoryIds(int $categoryId): array
    {
        return $this->categoryTreeOrder($categoryId);
    }

    /** @return list<int> */
    private function categoryTreeOrder(?int $rootId): array
    {
        $children = [];
        $known = [];
        foreach ($this->categories() as $category) {
            $id = (int) $category['id'];
            $known[$id] = true;
            $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
            $children[$parent][] = $id;
        }

        if ($rootId !== null && !isset($known[$rootId])) {
            return [];
        }
        $ordered = [];
        $visited = [];
        $walk = function (int $id) use (&$walk, &$children, &$ordered, &$visited): void {
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;
            $ordered[] = $id;
            foreach ($children[$id] ?? [] as $childId) {
                $walk($childId);
            }
        };
        if ($rootId !== null) {
            $walk($rootId);
        } else {
            foreach ($children[0] ?? [] as $id) {
                $walk($id);
            }
        }
        return $ordered;
    }

    public function isCategoryVisibleOnSite(int $categoryId): bool
    {
        $categories = [];
        foreach ($this->categories() as $category) {
            $categories[(int) $category['id']] = $category;
        }
        $visited = [];
        while (isset($categories[$categoryId]) && !isset($visited[$categoryId])) {
            $visited[$categoryId] = true;
            if ((int) ($categories[$categoryId]['is_visible'] ?? 1) !== 1) {
                return false;
            }
            $parentId = $categories[$categoryId]['parent_id'];
            if ($parentId === null) {
                return true;
            }
            $categoryId = (int) $parentId;
        }
        return false;
    }

    private function categoryRootId(int $categoryId): ?int
    {
        $trail = $this->categoryTrail($categoryId);
        return $trail === [] ? null : (int) $trail[0]['id'];
    }

    private function nextMixedPosition(?int $groupId): int
    {
        $conditionArticle = $groupId === null ? 'category_id IS NULL' : 'category_id = :group_id';
        $conditionCategory = $groupId === null ? 'parent_id IS NULL' : 'parent_id = :group_id';
        $sql = 'SELECT COALESCE(MAX(position), -1) + 1 FROM ('
            . 'SELECT position FROM articles WHERE ' . $conditionArticle
            . ' UNION ALL SELECT position FROM article_categories WHERE ' . $conditionCategory
            . ') mixed_positions';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($groupId === null ? [] : ['group_id' => $groupId]);
        return max(0, (int) $statement->fetchColumn());
    }

    /**
     * @param array<string, list<string>> $requested
     * @param array<string, list<string>> $current
     * @return array<string, list<string>>
     */
    private function validatedMixedGroupOrder(array $requested, array $current): array
    {
        $ownership = [];
        foreach ($current as $group => $tokens) {
            foreach ($tokens as $token) {
                $ownership[$token] = (string) $group;
            }
        }

        $ordered = $current;
        $seen = [];
        foreach ($requested as $group => $tokens) {
            $group = (string) $group;
            if (!array_key_exists($group, $current) || !is_array($tokens)) {
                throw new RuntimeException('articles_invalid_order');
            }
            $ordered[$group] = [];
            foreach ($tokens as $token) {
                if (!is_string($token)
                    || !preg_match('/^(article|category):[1-9][0-9]*$/D', $token)
                    || ($ownership[$token] ?? null) !== $group
                    || isset($seen[$token])) {
                    throw new RuntimeException('articles_invalid_order');
                }
                $seen[$token] = true;
                $ordered[$group][] = $token;
            }
        }
        if (count($seen) !== count($ownership)) {
            throw new RuntimeException('articles_invalid_order');
        }
        return $ordered;
    }

    public function transaction(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
