<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;

final class ContactRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        // Additive upgrade: no historical conversation is removed or reclassified.
        foreach (['contact_conversations' => ['archived' => 'INTEGER NOT NULL DEFAULT 0', 'unread' => 'INTEGER NOT NULL DEFAULT 0', 'deleted_at' => 'TEXT DEFAULT NULL'], 'contact_messages' => ['deleted_at' => 'TEXT DEFAULT NULL']] as $table => $columns) {
            $known = array_column($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
            if ($known === []) { continue; }
            foreach ($columns as $name => $definition) {
                if (!in_array($name, $known, true)) {
                    $this->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
                }
            }
        }
    }

    public function createConversation(string $name, string $email, string $subject, string $message): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $conversation = $this->pdo->prepare(
                'INSERT INTO contact_conversations '
                . '(public_token, visitor_name, visitor_email, subject, status, assigned_to, created_at, updated_at) '
                . 'VALUES (:token, :name, :email, :subject, \'open\', \'\', :created_at, :updated_at)'
            );
            $conversation->execute([
                'token' => bin2hex(random_bytes(24)),
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->addMessage($id, 'incoming', $message, $name, 'received');
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function addMessage(
        int $conversationId,
        string $direction,
        string $body,
        string $author,
        string $deliveryStatus
    ): int {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO contact_messages '
            . '(conversation_id, direction, body, author_name, sent_at, delivery_status) '
            . 'VALUES (:conversation_id, :direction, :body, :author_name, :sent_at, :delivery_status)'
        );
        $statement->execute([
            'conversation_id' => $conversationId,
            'direction' => $direction,
            'body' => $body,
            'author_name' => $author,
            'sent_at' => $now,
            'delivery_status' => $deliveryStatus,
        ]);
        $messageId = (int) $this->pdo->lastInsertId();
        $state = $direction === 'incoming' ? ", status = 'open', archived = 0, unread = 1" : ", status = 'progress', unread = 0";
        $update = $this->pdo->prepare(
            'UPDATE contact_conversations SET updated_at = :updated_at' . $state . ' WHERE id = :id'
        );
        $update->execute(['updated_at' => $now, 'id' => $conversationId]);
        return $messageId;
    }

    public function updateMessageDelivery(int $messageId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE contact_messages SET delivery_status = :status WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $messageId]);
        if ($status === 'failed') {
            $this->pdo->prepare("UPDATE contact_conversations SET status = 'open', unread = 1 WHERE id = (SELECT conversation_id FROM contact_messages WHERE id = ?)")->execute([$messageId]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function conversations(string $folder = 'inbox'): array
    {
        $where = match ($folder) {
            'trash' => 'c.deleted_at IS NOT NULL',
            'archives' => 'c.deleted_at IS NULL AND c.archived = 1',
            'closed' => "c.deleted_at IS NULL AND c.status = 'closed'",
            'all' => 'c.deleted_at IS NULL',
            default => "c.deleted_at IS NULL AND c.archived = 0 AND c.status != 'closed'",
        };
        return $this->pdo->query('SELECT c.*, (SELECT COUNT(*) FROM contact_messages m WHERE m.conversation_id = c.id AND m.deleted_at IS NULL) AS message_count FROM contact_conversations c WHERE ' . $where . ' ORDER BY c.updated_at DESC, c.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findConversation(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM contact_conversations WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** The random bearer token is required; an email address alone never grants access. */
    public function findPublicConversation(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{48}$/D', $token) !== 1) { return null; }
        $statement = $this->pdo->prepare('SELECT * FROM contact_conversations WHERE public_token = ? AND deleted_at IS NULL');
        $statement->execute([$token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function messages(int $conversationId, bool $trashed = false): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM contact_messages WHERE conversation_id = :id AND deleted_at IS ' . ($trashed ? 'NOT NULL' : 'NULL') . ' ORDER BY id'
        );
        $statement->execute(['id' => $conversationId]);
        return $statement->fetchAll();
    }

    public function setStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE contact_conversations SET status = :status, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'status' => $status === 'closed' ? 'closed' : 'open',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function markRead(int $id): void
    {
        $this->pdo->prepare('UPDATE contact_conversations SET unread = 0 WHERE id = ?')->execute([$id]);
    }

    /** @param list<int> $ids */
    public function applyAction(array $ids, string $action): int
    {
        $set = match ($action) {
            'archive' => 'archived = 1',
            'unarchive' => 'archived = 0',
            'close' => "status = 'closed'",
            'reopen' => "status = 'open', archived = 0",
            'trash' => 'deleted_at = :now',
            'restore' => 'deleted_at = NULL',
            'read' => 'unread = 0',
            'unread' => 'unread = 1',
            default => throw new \InvalidArgumentException('Invalid contact action'),
        };
        $statement = $this->pdo->prepare('UPDATE contact_conversations SET ' . $set . ' WHERE id = :id AND deleted_at IS ' . ($action === 'restore' ? 'NOT NULL' : 'NULL'));
        $count = 0;
        $this->pdo->beginTransaction();
        try {
            foreach (array_unique($ids) as $id) {
                if ($id < 1) { throw new \InvalidArgumentException('Invalid conversation'); }
                $params = ['id' => $id];
                if ($action === 'trash') { $params['now'] = gmdate('Y-m-d H:i:s'); }
                $statement->execute($params);
                $count += $statement->rowCount();
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
        return $count;
    }

    public function messageAction(int $conversationId, int $messageId, bool $restore): void
    {
        $statement = $this->pdo->prepare('UPDATE contact_messages SET deleted_at = :deleted WHERE id = :id AND conversation_id = :conversation AND EXISTS (SELECT 1 FROM contact_conversations c WHERE c.id = :conversation AND c.deleted_at IS NULL)');
        $statement->execute(['deleted' => $restore ? null : gmdate('Y-m-d H:i:s'), 'id' => $messageId, 'conversation' => $conversationId]);
    }

    /** Only trash is eligible. Archived and closed conversations never expire. */
    public function purgeTrash(int $retentionDays, bool $empty = false): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $retentionDays) * 86400);
        $this->pdo->beginTransaction();
        try {
            $condition = 'deleted_at IS NOT NULL' . ($empty ? '' : ' AND deleted_at <= :cutoff');
            $params = $empty ? [] : ['cutoff' => $cutoff];
            $messages = $this->pdo->prepare('DELETE FROM contact_messages WHERE ' . $condition . ' OR conversation_id IN (SELECT id FROM contact_conversations WHERE ' . $condition . ')');
            $messages->execute($params);
            $count = $messages->rowCount();
            $conversations = $this->pdo->prepare('DELETE FROM contact_conversations WHERE ' . $condition);
            $conversations->execute($params);
            $count += $conversations->rowCount();
            $this->pdo->commit();
            return $count;
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }
}
