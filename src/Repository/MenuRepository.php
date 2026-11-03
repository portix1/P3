<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;
use Portix\Infrastructure\Database;
use Portix\Support\Slugger;

final class MenuRepository
{
    public function __construct(private readonly PDO $pdo, private readonly Database $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function allOrdered(): array
    {
        return $this->pdo->query(
            'SELECT id, name, description, url, position FROM menu_items ORDER BY position, id'
        )->fetchAll();
    }

    public function exists(string $name): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM menu_items WHERE name = :name');
        $statement->execute(['name' => $name]);
        return $statement->fetchColumn() !== false;
    }

    public function create(string $name, string $description, string $url): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO menu_items (name, description, url, position) '
            . 'VALUES (:name, :description, :url, (SELECT COALESCE(MAX(position), 0) + 1 FROM menu_items))'
        );
        $statement->execute(['name' => $name, 'description' => $description, 'url' => $url]);
    }

    public function update(string $name, string $description, string $url): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE menu_items SET description = :description, url = :url WHERE name = :name'
        );
        $statement->execute(['name' => $name, 'description' => $description, 'url' => $url]);
    }

    public function delete(string $name): void
    {
        $statement = $this->pdo->prepare('DELETE FROM menu_items WHERE name = :name');
        $statement->execute(['name' => $name]);
    }

    public function localUrlFromName(string $name, ?string $exceptName = null): string
    {
        $base = Slugger::fromText($name, 'page');
        $candidate = $base;
        $suffix = 2;
        while ($this->slugIsUsed($candidate, $exceptName)) {
            $candidate = substr($base, 0, 170) . '-' . $suffix++;
        }
        return $candidate . '.html';
    }

    private function slugIsUsed(string $slug, ?string $exceptName): bool
    {
        foreach (['news', 'articles'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $statement = $this->pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE lower(slug) = :slug');
            $statement->execute(['slug' => strtolower($slug)]);
            if ($statement->fetchColumn() !== false) {
                return true;
            }
        }

        $statement = $this->pdo->query('SELECT name, url FROM menu_items');
        foreach ($statement->fetchAll() as $row) {
            if ($exceptName !== null && (string) $row['name'] === $exceptName) {
                continue;
            }
            $path = (string) parse_url(trim((string) $row['url']), PHP_URL_PATH);
            $existing = strtolower((string) preg_replace('/\.html$/i', '', basename($path)));
            if ($existing === strtolower($slug)) {
                return true;
            }
        }
        return false;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $table]);
        return $statement->fetchColumn() !== false;
    }

    /** @param list<string> $names */
    public function reorder(array $names): void
    {
        $rows = $this->allOrdered();
        $available = [];
        foreach ($rows as $row) {
            $available[(string) $row['name']] = true;
        }
        $ordered = [];
        foreach ($names as $name) {
            if (isset($available[$name]) && !in_array($name, $ordered, true)) {
                $ordered[] = $name;
            }
        }
        foreach (array_keys($available) as $name) {
            if (!in_array($name, $ordered, true)) {
                $ordered[] = $name;
            }
        }

        $this->database->transaction(function (PDO $unused) use ($ordered): void {
            $statement = $this->pdo->prepare(
                'UPDATE menu_items SET position = :position WHERE name = :name'
            );
            foreach ($ordered as $index => $name) {
                $statement->execute(['position' => $index + 1, 'name' => $name]);
            }
        });
    }
}
