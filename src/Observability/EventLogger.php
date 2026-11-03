<?php
declare(strict_types=1);

namespace Portix\Observability;

use PDO;
use Throwable;

final class EventLogger
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $context */
    public function application(
        string $level,
        string $event,
        string $message,
        array $context = [],
        string $requestId = ''
    ): void {
        try {
            if (!$this->tableExists('application_logs')) {
                return;
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO application_logs '
                . '(created_at, level, event, message, context_json, request_id) '
                . 'VALUES (:created_at, :level, :event, :message, :context_json, :request_id)'
            );
            $statement->execute([
                'created_at' => gmdate('Y-m-d H:i:s'),
                'level' => in_array($level, ['debug', 'info', 'warning', 'error', 'critical'], true) ? $level : 'info',
                'event' => mb_substr($event, 0, 120),
                'message' => mb_substr($message, 0, 2000),
                'context_json' => $this->json($context),
                'request_id' => mb_substr($requestId, 0, 64),
            ]);
        } catch (Throwable) {
            // Logging must never replace the original application error.
        }
    }

    /** @param array<string, mixed> $context */
    public function audit(
        string $actor,
        string $action,
        string $target,
        array $context = [],
        string $requestId = ''
    ): void {
        try {
            if (!$this->tableExists('audit_logs')) {
                return;
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO audit_logs '
                . '(created_at, actor, action, target, context_json, request_id) '
                . 'VALUES (:created_at, :actor, :action, :target, :context_json, :request_id)'
            );
            $statement->execute([
                'created_at' => gmdate('Y-m-d H:i:s'),
                'actor' => mb_substr($actor, 0, 100),
                'action' => mb_substr($action, 0, 120),
                'target' => mb_substr($target, 0, 250),
                'context_json' => $this->json($context),
                'request_id' => mb_substr($requestId, 0, 64),
            ]);
        } catch (Throwable) {
        }
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $table]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $context */
    private function json(array $context): string
    {
        foreach (['password', 'token', 'csrf_token', 'message', 'body'] as $sensitive) {
            unset($context[$sensitive]);
        }
        return (string) json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
