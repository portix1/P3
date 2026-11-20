<?php
declare(strict_types=1);

namespace PortixModule\Download2;

use PDO;
use RuntimeException;

/** Private storage metadata. No public method exposes download counters. */
final class Library
{
    public function __construct(private readonly PDO $pdo) {}

    public function allForAdmin(): array
    {
        return $this->pdo->query('SELECT f.*, c.name AS category_name FROM dm2_files f '
            . 'LEFT JOIN dm2_categories c ON c.id = f.category_id ORDER BY f.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function published(?int $categoryId = null): array
    {
        $sql = 'SELECT f.id, f.title, f.description, f.original_name, f.mime_type, f.size_bytes, '
            . 'c.name AS category_name FROM dm2_files f '
            . 'LEFT JOIN dm2_categories c ON c.id = f.category_id WHERE f.status = :status';
        $args = ['status' => 'published'];
        if ($categoryId !== null) {
            $sql .= ' AND f.category_id = :category_id';
            $args['category_id'] = $categoryId;
        }
        $sql .= ' ORDER BY f.title COLLATE NOCASE, f.id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Public library listing; article-only files remain downloadable and selectable in Quill. */
    public function publicLibrary(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT f.id, f.title, f.description, f.original_name, f.mime_type, f.size_bytes, '
            . 'c.name AS category_name FROM dm2_files f '
            . 'LEFT JOIN dm2_categories c ON c.id = f.category_id '
            . "WHERE f.status = 'published' AND f.show_in_library = 1 "
            . 'ORDER BY f.title COLLATE NOCASE, f.id'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, bool $publishedOnly = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM dm2_files WHERE id = :id'
            . ($publishedOnly ? " AND status = 'published'" : ''));
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) ? $result : null;
    }

    public function categories(): array
    {
        return $this->pdo->query('SELECT * FROM dm2_categories ORDER BY name COLLATE NOCASE, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findCategory(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM dm2_categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function categoryUsage(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM dm2_files WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public function updateCategory(int $id, string $name, string $description): void
    {
        $name = trim($name);
        if ($this->findCategory($id) === null || $name === '' || mb_strlen($name) > 120
            || mb_strlen($description) > 5000) {
            throw new RuntimeException('dl2_invalid_category');
        }
        $stmt = $this->pdo->prepare('UPDATE dm2_categories SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, trim($description), $id]);
    }

    public function deleteCategory(int $id): void
    {
        if ($this->findCategory($id) === null || $this->categoryUsage($id) > 0) {
            throw new RuntimeException('dl2_category_in_use');
        }
        $stmt = $this->pdo->prepare('DELETE FROM dm2_categories WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function createCategory(string $name, string $slug, string $description = ''): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new RuntimeException('dl2_invalid_category');
        }
        $stmt = $this->pdo->prepare('INSERT INTO dm2_categories(name,slug,description) VALUES(?,?,?)');
        $stmt->execute([$name, $slug, trim($description)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function create(array $data): int
    {
        self::validate($data);
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO dm2_files '
            . '(category_id,title,description,original_name,stored_name,mime_type,size_bytes,sha256,status,show_in_library,created_at,updated_at) '
            . 'VALUES (:category_id,:title,:description,:original_name,:stored_name,:mime_type,:size_bytes,:sha256,:status,:show_in_library,:created_at,:updated_at)');
        $stmt->execute([
            'category_id' => $data['category_id'], 'title' => trim($data['title']),
            'description' => trim($data['description']), 'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'], 'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'], 'sha256' => $data['sha256'],
            'status' => $data['status'], 'show_in_library' => $data['show_in_library'] ?? 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        if ($current === null) { throw new RuntimeException('dl2_missing'); }
        $next = array_replace($current, $data);
        self::validate($next);
        $next['updated_at'] = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE dm2_files SET category_id=:category_id,title=:title,'
            . 'description=:description,original_name=:original_name,stored_name=:stored_name,'
            . 'mime_type=:mime_type,size_bytes=:size_bytes,sha256=:sha256,status=:status,show_in_library=:show_in_library,'
            . 'updated_at=:updated_at WHERE id=:id');
        $stmt->execute([
            'id' => $id, 'category_id' => $next['category_id'], 'title' => trim($next['title']),
            'description' => trim($next['description']), 'original_name' => $next['original_name'],
            'stored_name' => $next['stored_name'], 'mime_type' => $next['mime_type'],
            'size_bytes' => $next['size_bytes'], 'sha256' => $next['sha256'],
            'status' => $next['status'], 'show_in_library' => $next['show_in_library'], 'updated_at' => $next['updated_at'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM dm2_files WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Count only successful GET transfers; HEAD requests never increment. */
    public function increment(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE dm2_files SET download_count = download_count + 1 WHERE id = ? AND status = 'published'");
        $stmt->execute([$id]);
    }

    private static function validate(array $data): void
    {
        if (trim((string) ($data['title'] ?? '')) === '' || mb_strlen((string) $data['title']) > 200
            || mb_strlen((string) ($data['description'] ?? '')) > 5000
            || !in_array($data['status'] ?? null, ['draft','published'], true)
            || !in_array($data['show_in_library'] ?? 1, [0, 1], true)
            || !is_numeric($data['size_bytes'] ?? null)
            || (int) $data['size_bytes'] < 1
            || preg_match('/^[a-f0-9]{64}$/D', (string) ($data['sha256'] ?? '')) !== 1
            || !is_string($data['stored_name'] ?? null)
            || preg_match('/^[a-f0-9]{48}\.[a-z0-9]{1,6}$/D', $data['stored_name']) !== 1
            || !is_string($data['original_name'] ?? null)
            || trim($data['original_name']) === ''
            || !is_string($data['mime_type'] ?? null)) {
            throw new RuntimeException('dl2_invalid_file');
        }
        if (($data['category_id'] ?? null) !== null
            && filter_var($data['category_id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false) {
            throw new RuntimeException('dl2_invalid_category');
        }
    }
}
