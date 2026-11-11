<?php
declare(strict_types=1);

namespace Portix\Insights;

use PDO;
use Portix\Infrastructure\Database;

final class DailyAudience
{
    public function __construct(private readonly Database $database)
    {
    }

    public function record(
        string $page,
        string $title,
        ?string $article,
        string $referrer,
        string $ip,
        string $agent,
        array $retention,
        ?int $now = null
    ): void {
        $now ??= time();
        $day = gmdate('Y-m-d', $now);
        $network = @inet_pton($ip);
        if ($network !== false) {
            if (str_starts_with($network, str_repeat("\0", 10) . "\xff\xff")) {
                $network = substr($network, 12);
            }
            $network = substr($network, 0, strlen($network) === 4 ? 3 : 6);
        }

        $this->database->transaction(function (PDO $pdo) use ($day, $now, $page, $title, $article, $referrer, $network, $agent, $retention): void {
            // Start with a write so concurrent first requests share the same daily key.
            $insert = $pdo->prepare(
                'INSERT OR IGNORE INTO analytics_v2_daily (day, secret) VALUES (?, lower(hex(randomblob(32))))'
            );
            $insert->execute([$day]);
            if ($insert->rowCount() === 1) {
                $this->purge((int) $retention['analytics_retention_days'], $now);
                $logs = [
                    'application_logs' => (int) $retention['application_retention_days'],
                    'audit_logs' => (int) $retention['audit_retention_days'],
                ];
                foreach ($logs as $table => $days) {
                    $pdo->prepare("DELETE FROM $table WHERE created_at < ?")
                        ->execute([gmdate('Y-m-d H:i:s', $now - max(1, $days) * 86400)]);
                }
            }

            $newVisitor = false;
            if ($network !== false && $agent !== '') {
                $query = $pdo->prepare('SELECT secret FROM analytics_v2_daily WHERE day = ?');
                $query->execute([$day]);
                $secret = $query->fetchColumn();
                if (is_string($secret) && $secret !== '') {
                    $fingerprint = hash_hmac('sha256', bin2hex($network) . '|' . $agent, $secret);
                    $seen = $pdo->prepare('INSERT OR IGNORE INTO analytics_v2_seen (entry_key, day) VALUES (?, ?)');
                    $seen->execute([$day . '|' . $fingerprint, $day]);
                    $newVisitor = $seen->rowCount() === 1;
                }
            }

            $pdo->prepare('UPDATE analytics_v2_daily SET views = views + 1, visitors = visitors + ? WHERE day = ?')
                ->execute([(int) $newVisitor, $day]);

            if ($article !== null) {
                $this->increment($day, 'article', $article, $title);
                // Feed the existing Home module's article popularity counters.
                $pdo->prepare(
                    'INSERT INTO analytics_pages (entry_key, day, route, title, views) VALUES (?, ?, ?, ?, 1)
                    ON CONFLICT(entry_key) DO UPDATE SET views = views + 1, title = excluded.title'
                )->execute([$day . '|' . hash('sha256', $article), $day, $article, $title]);
            }
            if ($newVisitor) {
                $this->increment($day, 'entry', $page, $title);
                $this->increment($day, 'referrer', $referrer, '');
            }
        });
    }

    private function increment(string $day, string $kind, string $value, string $title): void
    {
        $this->database->connection()->prepare(
            'INSERT INTO analytics_v2_counts (entry_key, day, kind, dimension, title, total) VALUES (?, ?, ?, ?, ?, 1)
            ON CONFLICT(entry_key) DO UPDATE SET total = total + 1, title = excluded.title'
        )->execute([$day . '|' . $kind . '|' . hash('sha256', $value), $day, $kind, $value, $title]);
    }

    public function purge(int $retention, ?int $now = null): void
    {
        $now ??= time();
        $day = gmdate('Y-m-d', $now);
        $cutoff = gmdate('Y-m-d', $now - (max(1, $retention) - 1) * 86400);
        $pdo = $this->database->connection();
        $pdo->prepare('DELETE FROM analytics_v2_seen WHERE day < ?')->execute([$day]);
        $pdo->prepare('UPDATE analytics_v2_daily SET secret = NULL WHERE day < ? AND secret IS NOT NULL')->execute([$day]);
        $pdo->prepare('DELETE FROM analytics_v2_daily WHERE day < ?')->execute([$cutoff]);
        $pdo->prepare('DELETE FROM analytics_v2_counts WHERE day < ?')->execute([$cutoff]);
        $pdo->prepare('DELETE FROM analytics_pages WHERE day < ?')->execute([$cutoff]);
    }

    public function daily(int $days): array
    {
        $query = $this->database->connection()->prepare(
            'SELECT day, views, visitors FROM analytics_v2_daily WHERE day >= ? ORDER BY day DESC'
        );
        $query->execute([gmdate('Y-m-d', time() - ($days - 1) * 86400)]);
        return $query->fetchAll();
    }

    public function ranking(string $kind, int $days): array
    {
        $query = $this->database->connection()->prepare(
            'SELECT dimension, MAX(title) AS title, SUM(total) AS total FROM analytics_v2_counts
            WHERE kind = ? AND day >= ? GROUP BY dimension ORDER BY total DESC, dimension LIMIT 20'
        );
        $query->execute([$kind, gmdate('Y-m-d', time() - ($days - 1) * 86400)]);
        return $query->fetchAll();
    }
}
