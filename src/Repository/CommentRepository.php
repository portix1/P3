<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;

final class CommentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countForArticle(int $articleId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM comments WHERE article_id = :article_id');
        $statement->execute(['article_id' => $articleId]);
        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function forArticle(int $articleId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM comments WHERE article_id = :article_id ORDER BY id'
        );
        $statement->execute(['article_id' => $articleId]);
        return $statement->fetchAll();
    }

    public function create(int $articleId, string $author, string $body, string $publishedAt): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO comments (article_id, author, body, published_at) '
            . 'VALUES (:article_id, :author, :body, :published_at)'
        );
        $statement->execute([
            'article_id' => $articleId,
            'author' => $author,
            'body' => $body,
            'published_at' => $publishedAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM comments WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
