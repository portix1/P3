<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;

final class NewsRepository
{
    private const PUBLIC_LIST_FIELDS = 'n.id, n.title, n.slug, n.intro, n.published_at';
    private const ADMIN_LIST_FIELDS = 'n.id, n.title, n.slug, n.created_by, n.status, n.published_at, n.updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM news')->fetchColumn();
    }

    public function countPublished(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM news WHERE status = 'published'")->fetchColumn();
    }

    public function countPublishedUnpinned(): int
    {
        if (!$this->pinTableExists()) {
            return $this->countPublished();
        }
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM news n WHERE n.status = 'published' "
            . "AND NOT EXISTS (SELECT 1 FROM news_pins p WHERE p.news_id = n.id)"
        )->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function pinnedPublished(): array
    {
        if (!$this->pinTableExists()) {
            return [];
        }
        return $this->pdo->query(
            'SELECT ' . self::PUBLIC_LIST_FIELDS . ', 1 AS is_pinned FROM news n '
            . "INNER JOIN news_pins p ON p.news_id = n.id "
            . "WHERE n.status = 'published' "
            . "ORDER BY p.pinned_at DESC, n.published_at DESC, n.id DESC"
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function latestPublished(int $limit = 10, int $offset = 0): array
    {
        $where = "n.status = 'published'";
        if ($this->pinTableExists()) {
            $where .= " AND NOT EXISTS (SELECT 1 FROM news_pins p WHERE p.news_id = n.id)";
        }
        $statement = $this->pdo->prepare(
            'SELECT ' . self::PUBLIC_LIST_FIELDS . ', 0 AS is_pinned FROM news n WHERE ' . $where
            . ' ORDER BY n.published_at DESC, n.id DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allForAdministration(): array
    {
        if (!$this->pinTableExists()) {
            return $this->pdo->query(
                'SELECT ' . self::ADMIN_LIST_FIELDS . ', 0 AS is_pinned FROM news n ORDER BY n.updated_at DESC, n.id DESC'
            )->fetchAll();
        }
        return $this->pdo->query(
            'SELECT ' . self::ADMIN_LIST_FIELDS . ', CASE WHEN p.news_id IS NULL THEN 0 ELSE 1 END AS is_pinned '
            . 'FROM news n LEFT JOIN news_pins p ON p.news_id = n.id '
            . 'ORDER BY is_pinned DESC, n.updated_at DESC, n.id DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $sql = $this->pinTableExists()
            ? 'SELECT n.*, CASE WHEN p.news_id IS NULL THEN 0 ELSE 1 END AS is_pinned '
                . 'FROM news n LEFT JOIN news_pins p ON p.news_id = n.id WHERE n.id = :id'
            : 'SELECT n.*, 0 AS is_pinned FROM news n WHERE n.id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        $sql = $this->pinTableExists()
            ? "SELECT n.*, CASE WHEN p.news_id IS NULL THEN 0 ELSE 1 END AS is_pinned "
                . "FROM news n LEFT JOIN news_pins p ON p.news_id = n.id "
                . "WHERE n.slug = :slug AND n.status = 'published'"
            : "SELECT n.*, 0 AS is_pinned FROM news n WHERE n.slug = :slug AND n.status = 'published'";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['slug' => strtolower($slug)]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function setPinned(int $id, bool $pinned): void
    {
        if ($pinned) {
            $this->ensurePinTable();
            $statement = $this->pdo->prepare(
                'INSERT INTO news_pins (news_id, pinned_at) VALUES (:news_id, :pinned_at) '
                . 'ON CONFLICT(news_id) DO UPDATE SET pinned_at = excluded.pinned_at'
            );
            $statement->execute([
                'news_id' => $id,
                'pinned_at' => gmdate('Y-m-d H:i:s'),
            ]);
            return;
        }
        if (!$this->pinTableExists()) {
            return;
        }
        $statement = $this->pdo->prepare('DELETE FROM news_pins WHERE news_id = :news_id');
        $statement->execute(['news_id' => $id]);
    }

    /** @param list<string> $reserved */
    public function uniqueSlug(string $base, array $reserved, ?int $exceptId = null): string
    {
        $reserved = array_fill_keys(array_map('strtolower', $reserved), true);
        $candidate = strtolower($base);
        foreach (array_keys($reserved) as $reservedSlug) {
            if ($candidate === $reservedSlug || str_starts_with($candidate, $reservedSlug . '-')) {
                $candidate = 'actualite-' . $candidate;
                $base = $candidate;
                break;
            }
        }
        $suffix = 2;
        while (isset($reserved[$candidate]) || $this->slugExists($candidate, $exceptId)) {
            $candidate = substr($base, 0, 170) . '-' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    public function create(
        string $title,
        string $slug,
        string $createdBy,
        string $intro,
        string $body,
        string $status,
        ?string $publishedAt,
        string $now
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO news '
            . '(title, slug, created_by, intro, body, status, published_at, created_at, updated_at) '
            . 'VALUES (:title, :slug, :created_by, :intro, :body, :status, :published_at, :created_at, :updated_at)'
        );
        $statement->execute([
            'title' => $title,
            'slug' => $slug,
            'created_by' => $createdBy,
            'intro' => $intro,
            'body' => $body,
            'status' => $status,
            'published_at' => $publishedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        string $title,
        string $slug,
        string $intro,
        string $body,
        string $status,
        ?string $publishedAt,
        string $updatedAt
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE news SET title = :title, slug = :slug, intro = :intro, body = :body, '
            . 'status = :status, published_at = :published_at, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'intro' => $intro,
            'body' => $body,
            'status' => $status,
            'published_at' => $publishedAt,
            'updated_at' => $updatedAt,
        ]);
    }

    public function delete(int $id): void
    {
        if ($this->pinTableExists()) {
            $pin = $this->pdo->prepare('DELETE FROM news_pins WHERE news_id = :id');
            $pin->execute(['id' => $id]);
        }
        $statement = $this->pdo->prepare('DELETE FROM news WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function pinTableExists(): bool
    {
        return $this->tableExists('news_pins');
    }

    private function ensurePinTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS news_pins ('
            . 'news_id INTEGER PRIMARY KEY, pinned_at TEXT NOT NULL)'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS module_schema_tables ('
            . 'module_prefix TEXT NOT NULL, table_name TEXT PRIMARY KEY, installed_at TEXT NOT NULL)'
        );
        $statement = $this->pdo->prepare(
            'INSERT INTO module_schema_tables (module_prefix, table_name, installed_at) '
            . 'VALUES (:module_prefix, :table_name, :installed_at) '
            . 'ON CONFLICT(table_name) DO UPDATE SET '
            . 'module_prefix = excluded.module_prefix, installed_at = excluded.installed_at'
        );
        $statement->execute([
            'module_prefix' => 'news',
            'table_name' => 'news_pins',
            'installed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function slugExists(string $slug, ?int $exceptId): bool
    {
        $sql = 'SELECT 1 FROM news WHERE slug = :slug';
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
        if (!$this->tableExists('articles')) {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM articles WHERE slug = :slug');
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
}
