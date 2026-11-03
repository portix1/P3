<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;

final class GroupRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM groups ORDER BY name, id')->fetchAll();
    }

    public function create(string $name, string $memo): int
    {
        $statement = $this->pdo->prepare('INSERT INTO groups (name, memo) VALUES (:name, :memo)');
        $statement->execute(compact('name', 'memo'));
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM groups WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
