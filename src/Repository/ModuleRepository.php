<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;

final class ModuleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function allOrdered(): array
    {
        return $this->pdo->query('SELECT * FROM modules ORDER BY priority, name')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByPrefix(string $prefix): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM modules WHERE prefix = :prefix');
        $statement->execute(['prefix' => $prefix]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function setEnabled(string $prefix, bool $enabled): void
    {
        $statement = $this->pdo->prepare('UPDATE modules SET enabled = :enabled WHERE prefix = :prefix');
        $statement->execute(['prefix' => $prefix, 'enabled' => (int) $enabled]);
    }

    public function deleteByPrefix(string $prefix): void
    {
        $statement = $this->pdo->prepare('DELETE FROM modules WHERE prefix = :prefix');
        $statement->execute(['prefix' => $prefix]);
    }

    /** @param array<string, mixed> $module */
    public function synchronizeDefinition(array $module): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO modules (name, prefix, type, priority, default_language, enabled, path) '
            . 'VALUES (:name, :prefix, :type, :priority, :default_language, :enabled, :path) '
            . 'ON CONFLICT(prefix) DO UPDATE SET name = excluded.name, type = excluded.type, '
            . 'priority = excluded.priority, default_language = excluded.default_language, path = excluded.path'
        );
        $statement->execute([
            'name' => $module['name'],
            'prefix' => $module['prefix'],
            'type' => $module['type'],
            'priority' => (int) $module['priority'],
            'default_language' => $module['default_language'],
            'enabled' => (int) (bool) $module['enabled'],
            'path' => $module['path'],
        ]);
    }

    /** @param array<string, mixed> $module */
    public function upsert(array $module): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO modules (name, prefix, type, priority, default_language, enabled, path) '
            . 'VALUES (:name, :prefix, :type, :priority, :default_language, :enabled, :path) '
            . 'ON CONFLICT(prefix) DO UPDATE SET name = excluded.name, type = excluded.type, '
            . 'priority = excluded.priority, default_language = excluded.default_language, '
            . 'enabled = excluded.enabled, path = excluded.path'
        );
        $statement->execute([
            'name' => $module['name'],
            'prefix' => $module['prefix'],
            'type' => $module['type'],
            'priority' => (int) $module['priority'],
            'default_language' => $module['default_language'],
            'enabled' => (int) (bool) $module['enabled'],
            'path' => $module['path'],
        ]);
    }
}
