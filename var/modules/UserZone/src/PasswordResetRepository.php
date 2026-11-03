<?php
declare(strict_types=1);

namespace PortixModule\UserZone;

use PDO;
use RuntimeException;
use Throwable;

final class PasswordResetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function purgeExpired(): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM password_reset_tokens WHERE expires_at <= :now'
        );
        $statement->execute(['now' => gmdate('Y-m-d H:i:s')]);
    }

    public function issue(string $pseudo, string $tokenHash, int $ttlSeconds = 3600): void
    {
        if ($pseudo === '' || preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1) {
            throw new RuntimeException('password_reset_invalid_token');
        }
        $ttlSeconds = max(300, min(86400, $ttlSeconds));
        $created = time();

        $this->transaction(function () use ($pseudo, $tokenHash, $ttlSeconds, $created): void {
            $delete = $this->pdo->prepare(
                'DELETE FROM password_reset_tokens WHERE pseudo = :pseudo OR expires_at <= :now'
            );
            $delete->execute([
                'pseudo' => $pseudo,
                'now' => gmdate('Y-m-d H:i:s', $created),
            ]);

            $insert = $this->pdo->prepare(
                'INSERT INTO password_reset_tokens (pseudo, token_hash, created_at, expires_at) '
                . 'VALUES (:pseudo, :token_hash, :created_at, :expires_at)'
            );
            $insert->execute([
                'pseudo' => $pseudo,
                'token_hash' => $tokenHash,
                'created_at' => gmdate('Y-m-d H:i:s', $created),
                'expires_at' => gmdate('Y-m-d H:i:s', $created + $ttlSeconds),
            ]);
        });
    }

    public function isValid(string $tokenHash): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM password_reset_tokens '
            . 'WHERE token_hash = :token_hash AND expires_at > :now LIMIT 1'
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);
        return $statement->fetchColumn() !== false;
    }

    public function resetPassword(string $tokenHash, string $passwordHash): ?string
    {
        if (preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1 || $passwordHash === '') {
            return null;
        }

        return $this->transaction(function () use ($tokenHash, $passwordHash): ?string {
            $select = $this->pdo->prepare(
                'SELECT pseudo FROM password_reset_tokens '
                . 'WHERE token_hash = :token_hash AND expires_at > :now LIMIT 1'
            );
            $select->execute([
                'token_hash' => $tokenHash,
                'now' => gmdate('Y-m-d H:i:s'),
            ]);
            $pseudo = $select->fetchColumn();
            if (!is_string($pseudo) || $pseudo === '') {
                return null;
            }

            $update = $this->pdo->prepare(
                'UPDATE users SET password_hash = :password_hash, auth_version = auth_version + 1 '
                . 'WHERE pseudo = :pseudo'
            );
            $update->execute([
                'password_hash' => $passwordHash,
                'pseudo' => $pseudo,
            ]);
            if ($update->rowCount() !== 1) {
                return null;
            }

            $delete = $this->pdo->prepare(
                'DELETE FROM password_reset_tokens WHERE pseudo = :pseudo'
            );
            $delete->execute(['pseudo' => $pseudo]);
            return $pseudo;
        });
    }

    public function revoke(string $pseudo): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM password_reset_tokens WHERE pseudo = :pseudo'
        );
        $statement->execute(['pseudo' => $pseudo]);
    }

    private function transaction(callable $operation): mixed
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
