<?php
declare(strict_types=1);

namespace PortixModule\Articles;

use PDO;
use RuntimeException;

/**
 * Private recovery snapshots. Never modifies the article or publication status.
 * The auxiliary table is created on demand and contains no public content.
 */
final class ArticleAutosaveStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS article_autosaves (
            account_key TEXT NOT NULL,
            article_id INTEGER NOT NULL,
            payload TEXT NOT NULL,
            base_updated_at TEXT NOT NULL DEFAULT "",
            saved_at TEXT NOT NULL,
            PRIMARY KEY (account_key, article_id)
        )');
    }

    /** @param array<string, mixed> $fields */
    public function save(string $account, int $articleId, array $fields, string $baseUpdatedAt): void
    {
        if ($articleId < 0) { throw new RuntimeException('articles_invalid_id'); }
        $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 1048576) {
            throw new RuntimeException('articles_autosave_too_large');
        }
        $statement = $this->pdo->prepare('INSERT INTO article_autosaves
            (account_key, article_id, payload, base_updated_at, saved_at)
            VALUES (:account, :id, :payload, :base, :saved)
            ON CONFLICT(account_key, article_id) DO UPDATE SET
                payload=excluded.payload,
                base_updated_at=excluded.base_updated_at,
                saved_at=excluded.saved_at');
        $statement->execute([
            'account' => $account,
            'id' => $articleId,
            'payload' => $json,
            'base' => $baseUpdatedAt,
            'saved' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{fields:array<string,mixed>,saved_at:string,base_updated_at:string}|null */
    public function load(string $account, int $articleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT payload, saved_at, base_updated_at
            FROM article_autosaves WHERE account_key=:account AND article_id=:id');
        $stmt->execute(['account' => $account, 'id' => $articleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { return null; }
        $fields = json_decode((string) $row['payload'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($fields)) { return null; }
        return ['fields' => $fields, 'saved_at' => (string) $row['saved_at'],
            'base_updated_at' => (string) $row['base_updated_at']];
    }

    public function delete(string $account, int $articleId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM article_autosaves WHERE account_key=:account AND article_id=:id');
        $stmt->execute(['account' => $account, 'id' => $articleId]);
    }
}
