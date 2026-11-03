<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;

final class DownloadRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return $this->pdo->query(
            'SELECT c.*, (SELECT COUNT(*) FROM downloads d WHERE d.category_id = c.id) AS file_count, '
            . "(SELECT COUNT(*) FROM downloads d WHERE d.category_id = c.id AND d.status = 'published') AS published_count "
            . 'FROM download_categories c ORDER BY c.position, c.name, c.id'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findCategory(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM download_categories WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findCategoryBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM download_categories WHERE slug = :slug');
        $statement->execute(['slug' => strtolower($slug)]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function createCategory(string $name, string $slug, string $description): int
    {
        $position = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM download_categories'
        )->fetchColumn();
        $statement = $this->pdo->prepare(
            'INSERT INTO download_categories (name, slug, description, position) '
            . 'VALUES (:name, :slug, :description, :position)'
        );
        $statement->execute(compact('name', 'slug', 'description', 'position'));
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(int $id, string $name, string $slug, string $description): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE download_categories SET name = :name, slug = :slug, description = :description WHERE id = :id'
        );
        $statement->execute(compact('id', 'name', 'slug', 'description'));
    }

    public function deleteCategory(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM download_categories WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function uniqueCategorySlug(string $base, ?int $exceptId = null): string
    {
        $candidate = $base;
        $suffix = 2;
        while ($this->categorySlugExists($candidate, $exceptId)) {
            $candidate = mb_substr($base, 0, 170) . '-' . $suffix++;
        }
        return $candidate;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT d.*, c.name AS category_name, c.slug AS category_slug FROM downloads d '
            . 'LEFT JOIN download_categories c ON c.id = d.category_id '
            . 'ORDER BY c.position, d.position, d.id DESC'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function publishedForCategory(int $categoryId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*, c.name AS category_name, c.slug AS category_slug FROM downloads d '
            . 'JOIN download_categories c ON c.id = d.category_id '
            . "WHERE d.category_id = :category_id AND d.status = 'published' "
            . 'ORDER BY d.position, d.title, d.id'
        );
        $statement->execute(['category_id' => $categoryId]);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, bool $publishedOnly = false): ?array
    {
        $sql = 'SELECT d.*, c.name AS category_name, c.slug AS category_slug FROM downloads d '
            . 'LEFT JOIN download_categories c ON c.id = d.category_id WHERE d.id = :id';
        if ($publishedOnly) {
            $sql .= " AND d.status = 'published'";
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $file */
    public function create(array $file): int
    {
        $positionStatement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM downloads WHERE category_id = :category_id'
        );
        $positionStatement->execute(['category_id' => $file['category_id']]);
        $position = (int) $positionStatement->fetchColumn();
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO downloads '
            . '(category_id, title, original_name, stored_name, mime_type, size_bytes, sha256, '
            . 'description, status, position, download_count, created_at, updated_at) '
            . 'VALUES (:category_id, :title, :original_name, :stored_name, :mime_type, :size_bytes, :sha256, '
            . ':description, :status, :position, 0, :created_at, :updated_at)'
        );
        $statement->execute([
            'category_id' => $file['category_id'],
            'title' => $file['title'],
            'original_name' => $file['original_name'],
            'stored_name' => $file['stored_name'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
            'sha256' => $file['sha256'],
            'description' => $file['description'],
            'status' => $file['status'],
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE downloads SET category_id = :category_id, title = :title, description = :description, '
            . 'status = :status, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'category_id' => $values['category_id'],
            'title' => $values['title'],
            'description' => $values['description'],
            'status' => $values['status'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $file */
    public function replaceFile(int $id, array $file): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE downloads SET original_name = :original_name, stored_name = :stored_name, '
            . 'mime_type = :mime_type, size_bytes = :size_bytes, sha256 = :sha256, updated_at = :updated_at '
            . 'WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'original_name' => $file['original_name'],
            'stored_name' => $file['stored_name'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
            'sha256' => $file['sha256'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function incrementCounter(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE downloads SET download_count = download_count + 1 WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM downloads WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function categorySlugExists(string $slug, ?int $exceptId): bool
    {
        $sql = 'SELECT 1 FROM download_categories WHERE slug = :slug';
        $parameters = ['slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchColumn() !== false;
    }
}
