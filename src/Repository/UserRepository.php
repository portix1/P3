<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;
use Portix\Domain\UserAccount;
use RuntimeException;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function saveArticlePermissions(string $pseudo, array $permissions): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET article_permissions = :permissions, auth_version = auth_version + 1 WHERE pseudo = :pseudo');
        $statement->execute(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'pseudo' => $pseudo]);
    }
    public function articleCategories(): array
    {
        $exists = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='article_categories'")->fetchColumn();
        if (!$exists) { return []; }
        $rows = $this->pdo->query('SELECT id, name, parent_id FROM article_categories ORDER BY name, id')->fetchAll();
        $byId = [];
        foreach ($rows as $row) { $byId[(int)$row['id']] = $row; }
        foreach ($rows as &$row) {
            $path = [(string)$row['name']]; $seen = [(int)$row['id'] => true]; $parent = $row['parent_id'];
            while ($parent !== null && isset($byId[(int)$parent]) && !isset($seen[(int)$parent])) {
                $seen[(int)$parent] = true; array_unshift($path, (string)$byId[(int)$parent]['name']);
                $parent = $byId[(int)$parent]['parent_id'];
            }
            $row['name'] = implode(' / ', $path);
        }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $rows;
    }
    public function find(string $pseudo): ?UserAccount
    {
        if ($pseudo === '') {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE pseudo = :pseudo');
        $statement->execute(['pseudo' => $pseudo]);
        $row = $statement->fetch();
        return is_array($row) ? new UserAccount($row) : null;
    }

    public function findCaseInsensitive(string $pseudo): ?UserAccount
    {
        if ($pseudo === '') {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM users WHERE pseudo COLLATE NOCASE = :pseudo LIMIT 1'
        );
        $statement->execute(['pseudo' => $pseudo]);
        $row = $statement->fetch();
        return is_array($row) ? new UserAccount($row) : null;
    }

    public function findUniqueByEmail(string $email): ?UserAccount
    {
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM users WHERE email COLLATE NOCASE = :email LIMIT 2'
        );
        $statement->execute(['email' => $email]);
        $rows = $statement->fetchAll();
        return count($rows) === 1 && is_array($rows[0]) ? new UserAccount($rows[0]) : null;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM users ORDER BY pseudo')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function searchForAdministration(string $query = '', string $role = 'all'): array
    {
        $query = trim($query);
        if (!in_array($role, ['all', 'admin', 'redactor'], true)) {
            $role = 'all';
        }

        $conditions = [];
        $parameters = [];
        if ($role === 'admin') {
            $conditions[] = 'is_admin = 1';
        } elseif ($role === 'redactor') {
            $conditions[] = 'is_redactor = 1';
        }
        if ($query !== '') {
            $escaped = strtr($query, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
            $conditions[] = '('
                . "pseudo COLLATE NOCASE LIKE :pattern ESCAPE '\\' "
                . "OR email COLLATE NOCASE LIKE :pattern ESCAPE '\\' "
                . "OR first_name COLLATE NOCASE LIKE :pattern ESCAPE '\\' "
                . "OR last_name COLLATE NOCASE LIKE :pattern ESCAPE '\\')";
            $parameters['pattern'] = '%' . $escaped . '%';
        }

        $sql = 'SELECT pseudo, email, first_name, last_name, is_admin, is_redactor FROM users';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY last_name COLLATE NOCASE, first_name COLLATE NOCASE, pseudo COLLATE NOCASE';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function countAdmins(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
    }

    public function countRedactors(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_redactor = 1')->fetchColumn();
    }

    public function create(
        string $pseudo,
        string $passwordHash,
        string $email,
        string $lastName,
        string $firstName,
        bool $admin,
        bool $redactor
    ): void {
        if (!$admin && !$redactor) {
            throw new RuntimeException('users_role_required');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO users '
            . '(pseudo, password_hash, email, last_name, first_name, is_admin, is_redactor) '
            . 'VALUES (:pseudo, :password_hash, :email, :last_name, :first_name, :is_admin, :is_redactor)'
        );
        $statement->execute([
            'pseudo' => $pseudo,
            'password_hash' => $passwordHash,
            'email' => $email,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'is_admin' => (int) $admin,
            'is_redactor' => (int) $redactor,
        ]);
    }

    public function updateProfile(string $pseudo, string $lastName, string $firstName, string $email): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET last_name = :last_name, first_name = :first_name, email = :email '
            . 'WHERE pseudo = :pseudo'
        );
        $statement->execute([
            'pseudo' => $pseudo,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => $email,
        ]);
    }

    public function updatePassword(string $pseudo, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, '
            . 'auth_version = auth_version + 1 WHERE pseudo = :pseudo'
        );
        $statement->execute(['pseudo' => $pseudo, 'password_hash' => $passwordHash]);
    }

    public function updateAccount(
        string $pseudo,
        string $lastName,
        string $firstName,
        string $email,
        bool $admin,
        bool $redactor
    ): void {
        if (!$admin && !$redactor) {
            throw new RuntimeException('users_role_required');
        }
        $statement = $this->pdo->prepare(
            'UPDATE users SET last_name = :last_name, first_name = :first_name, '
            . 'email = :email, is_admin = :is_admin, '
            . 'is_redactor = :is_redactor WHERE pseudo = :pseudo'
        );
        $statement->execute([
            'pseudo' => $pseudo,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => $email,
            'is_admin' => (int) $admin,
            'is_redactor' => (int) $redactor,
        ]);
    }

    public function delete(string $pseudo): void
    {
        $statement = $this->pdo->prepare('DELETE FROM users WHERE pseudo = :pseudo');
        $statement->execute(['pseudo' => $pseudo]);
    }
}
