<?php
declare(strict_types=1);

namespace Portix\Infrastructure;

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly string $file)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('Portix 3 nécessite l’extension PDO_SQLITE.');
        }

        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier de la base SQLite.');
        }

        $this->connection = new PDO('sqlite:' . $this->file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $this->connection->exec('PRAGMA foreign_keys = ON');
        $this->connection->exec('PRAGMA busy_timeout = 5000');
        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA synchronous = NORMAL');

        return $this->connection;
    }

    public function transaction(callable $operation): mixed
    {
        $pdo = $this->connection();
        if ($pdo->inTransaction()) {
            return $operation($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $operation($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public function file(): string
    {
        return $this->file;
    }

    public function close(): void
    {
        $this->connection = null;
    }
}
