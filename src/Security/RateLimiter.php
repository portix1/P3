<?php
declare(strict_types=1);

namespace Portix\Security;

use Closure;
use PDO;
use RuntimeException;
use Throwable;

/** Persistent request limiter backed by Portix's SQLite database. */
final class RateLimiter
{
    private readonly Closure $clock;

    public function __construct(private readonly PDO $pdo, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function remaining(string $key): int
    {
        $statement = $this->pdo->prepare(
            'SELECT blocked_until FROM rate_limits WHERE rate_key = :rate_key'
        );
        $statement->execute(['rate_key' => $this->validKey($key)]);
        $blockedUntil = $statement->fetchColumn();

        return $blockedUntil === false ? 0 : max(0, (int) $blockedUntil - $this->now());
    }

    /** @param list<string> $keys */
    public function maxRemaining(array $keys): int
    {
        $remaining = 0;
        foreach ($this->validKeys($keys) as $key) {
            $remaining = max($remaining, $this->remaining($key));
        }

        return $remaining;
    }

    /**
     * Records one failed action for every supplied key in a single transaction.
     * Failure history is forgotten after an inactive period.
     *
     * @param list<string> $keys
     */
    public function recordFailures(
        array $keys,
        int $maxFailures = 10,
        int $baseDelay = 1,
        int $maxDelay = 30,
        int $resetAfter = 900
    ): int {
        $keys = $this->validKeys($keys);
        $maxFailures = max(1, $maxFailures);
        $baseDelay = max(1, $baseDelay);
        $maxDelay = max($baseDelay, $maxDelay);
        $resetAfter = max(1, $resetAfter);
        $now = $this->now();
        $maximumAppliedDelay = 0;

        return $this->immediateTransaction(function () use (
            $keys,
            $maxFailures,
            $baseDelay,
            $maxDelay,
            $resetAfter,
            $now,
            &$maximumAppliedDelay
        ): int {
            $select = $this->pdo->prepare(
                'SELECT failures, updated_at FROM rate_limits WHERE rate_key = :rate_key'
            );

            foreach ($keys as $key) {
                $select->execute(['rate_key' => $key]);
                $row = $select->fetch();
                $isStale = $row === false || (int) $row['updated_at'] + $resetAfter <= $now;
                $failures = min(
                    $maxFailures,
                    ($isStale ? 0 : (int) $row['failures']) + 1
                );
                $delay = min(
                    $maxDelay,
                    $baseDelay * (2 ** min(30, max(0, $failures - 1)))
                );
                $maximumAppliedDelay = max($maximumAppliedDelay, $delay);
                $this->store($key, $failures, $now + $delay, $now, $row !== false);
            }

            return $maximumAppliedDelay;
        });
    }

    public function clear(string $key): void
    {
        $statement = $this->pdo->prepare('DELETE FROM rate_limits WHERE rate_key = :rate_key');
        $statement->execute(['rate_key' => $this->validKey($key)]);
    }

    /**
     * Atomically consumes one unit from every fixed-window quota.
     * Returns zero when accepted, otherwise the number of seconds to wait.
     * No key is changed when one of the quotas is already exhausted.
     *
     * @param list<string> $keys
     */
    public function consumeMany(array $keys, int $windowSeconds = 60, int $maxAttempts = 1): int
    {
        $keys = $this->validKeys($keys);
        $windowSeconds = max(1, $windowSeconds);
        $maxAttempts = max(1, $maxAttempts);
        $now = $this->now();

        return $this->immediateTransaction(function () use (
            $keys,
            $windowSeconds,
            $maxAttempts,
            $now
        ): int {
            $select = $this->pdo->prepare(
                'SELECT failures, updated_at FROM rate_limits WHERE rate_key = :rate_key'
            );
            $rows = [];

            foreach ($keys as $key) {
                $select->execute(['rate_key' => $key]);
                $row = $select->fetch();
                $rows[$key] = $row;
                if ($row !== false
                    && (int) $row['updated_at'] + $windowSeconds > $now
                    && (int) $row['failures'] >= $maxAttempts) {
                    return max(1, (int) $row['updated_at'] + $windowSeconds - $now);
                }
            }

            foreach ($keys as $key) {
                $row = $rows[$key];
                $freshWindow = $row === false
                    || (int) $row['updated_at'] + $windowSeconds <= $now;
                $attempts = $freshWindow ? 1 : (int) $row['failures'] + 1;
                $this->store($key, $attempts, $now + $windowSeconds, $now, $row !== false);
            }

            return 0;
        });
    }

    public function purge(int $olderThanSeconds = 86400): int
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM rate_limits WHERE updated_at <= :cutoff'
        );
        $statement->execute(['cutoff' => $this->now() - max(0, $olderThanSeconds)]);

        return $statement->rowCount();
    }

    private function store(
        string $key,
        int $failures,
        int $blockedUntil,
        int $updatedAt,
        bool $exists
    ): void {
        $statement = $exists
            ? $this->pdo->prepare(
                'UPDATE rate_limits SET failures = :failures, blocked_until = :blocked_until, '
                . 'updated_at = :updated_at WHERE rate_key = :rate_key'
            )
            : $this->pdo->prepare(
                'INSERT INTO rate_limits (rate_key, failures, blocked_until, updated_at) '
                . 'VALUES (:rate_key, :failures, :blocked_until, :updated_at)'
            );
        $statement->execute([
            'rate_key' => $key,
            'failures' => $failures,
            'blocked_until' => $blockedUntil,
            'updated_at' => $updatedAt,
        ]);
    }

    private function now(): int
    {
        return (int) ($this->clock)();
    }

    /** @param list<string> $keys @return list<string> */
    private function validKeys(array $keys): array
    {
        $validated = [];
        foreach ($keys as $key) {
            $validated[$this->validKey($key)] = true;
        }
        if ($validated === []) {
            throw new RuntimeException('Rate-limit keys cannot be empty.');
        }

        return array_keys($validated);
    }

    private function validKey(string $key): string
    {
        if ($key === '' || strlen($key) > 255) {
            throw new RuntimeException('Invalid rate-limit key.');
        }

        return $key;
    }

    private function immediateTransaction(Closure $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Rate limiter cannot run inside another transaction.');
        }

        $active = false;
        try {
            $this->pdo->exec('BEGIN IMMEDIATE');
            $active = true;
            $result = $operation();
            $this->pdo->exec('COMMIT');
            $active = false;

            return $result;
        } catch (Throwable $error) {
            if ($active) {
                try {
                    $this->pdo->exec('ROLLBACK');
                } catch (Throwable) {
                }
            }
            throw $error;
        }
    }
}
