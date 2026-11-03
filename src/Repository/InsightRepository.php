<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;

final class InsightRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordView(
        string $day,
        string $route,
        string $title,
        string $referrerHost,
        string $device,
        bool $bot,
        ?string $visitorKey = null
    ): void {
        $daily = $this->pdo->prepare(
            'INSERT INTO analytics_daily (day, page_views, visits_estimated, bot_views) '
            . 'VALUES (:day, :views, 0, :bots) ON CONFLICT(day) DO UPDATE SET '
            . 'page_views = page_views + excluded.page_views, bot_views = bot_views + excluded.bot_views'
        );
        $daily->execute(['day' => $day, 'views' => $bot ? 0 : 1, 'bots' => $bot ? 1 : 0]);
        if ($bot) {
            return;
        }

        $this->incrementDimension(
            'analytics_pages',
            $day . '|' . hash('sha256', $route),
            $day,
            'route',
            $route,
            ['title' => $title]
        );
        $this->incrementDimension(
            'analytics_referrers',
            $day . '|' . hash('sha256', $referrerHost),
            $day,
            'host',
            $referrerHost
        );
        $this->incrementDimension(
            'analytics_devices',
            $day . '|' . $device,
            $day,
            'device',
            $device
        );

        if ($visitorKey !== null && $visitorKey !== ''
            && $this->tableExists('analytics_unique_visitors')
            && $this->tableExists('analytics_visitor_days')) {
            $unique = $this->pdo->prepare(
                'INSERT INTO analytics_unique_visitors (visitor_key, first_seen_day, last_seen_day) '
                . 'VALUES (:visitor_key, :day, :day) ON CONFLICT(visitor_key) DO UPDATE SET last_seen_day = excluded.last_seen_day'
            );
            $unique->execute(['visitor_key' => $visitorKey, 'day' => $day]);
            $entryKey = $day . '|' . $visitorKey;
            $visitorDay = $this->pdo->prepare(
                'INSERT OR IGNORE INTO analytics_visitor_days (entry_key, day, visitor_key) '
                . 'VALUES (:entry_key, :day, :visitor_key)'
            );
            $visitorDay->execute(['entry_key' => $entryKey, 'day' => $day, 'visitor_key' => $visitorKey]);
            if ($visitorDay->rowCount() > 0) {
                $increment = $this->pdo->prepare(
                    'UPDATE analytics_daily SET visits_estimated = visits_estimated + 1 WHERE day = :day'
                );
                $increment->execute(['day' => $day]);
            }
        }
    }

    /** @return array{page_views:int,visits_estimated:int,bot_views:int} */
    public function totals(int $days): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(page_views), 0) AS page_views, '
            . 'COALESCE(SUM(visits_estimated), 0) AS visits_estimated, '
            . 'COALESCE(SUM(bot_views), 0) AS bot_views FROM analytics_daily WHERE day >= :day'
        );
        $statement->execute(['day' => gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'))]);
        $row = $statement->fetch() ?: [];
        return [
            'page_views' => (int) ($row['page_views'] ?? 0),
            'visits_estimated' => (int) ($row['visits_estimated'] ?? 0),
            'bot_views' => (int) ($row['bot_views'] ?? 0),
        ];
    }

    /** @return array{unique_visitors:int,new_visitors:int,returning_visitors:int,page_views_per_visitor:float,avg_unique_per_day:float,avg_views_per_day:float} */
    public function audience(int $days): array
    {
        $days = max(1, $days);
        if (!$this->tableExists('analytics_unique_visitors') || !$this->tableExists('analytics_visitor_days')) {
            $totals=$this->totals($days);
            return ['unique_visitors'=>0,'new_visitors'=>0,'returning_visitors'=>0,'page_views_per_visitor'=>0.0,'avg_unique_per_day'=>0.0,'avg_views_per_day'=>$totals['page_views']/$days];
        }
        $cutoff = gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
        $st = $this->pdo->prepare('SELECT COUNT(DISTINCT visitor_key) FROM analytics_visitor_days WHERE day >= :day');
        $st->execute(['day'=>$cutoff]);
        $unique=(int)$st->fetchColumn();
        $st = $this->pdo->prepare('SELECT COUNT(DISTINCT d.visitor_key) FROM analytics_visitor_days d JOIN analytics_unique_visitors u ON u.visitor_key=d.visitor_key WHERE d.day >= :day AND u.first_seen_day >= :day2');
        $st->execute(['day'=>$cutoff,'day2'=>$cutoff]);
        $new=(int)$st->fetchColumn();
        $totals=$this->totals($days);
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM analytics_visitor_days WHERE day >= :day');
        $st->execute(['day'=>$cutoff]);
        $visitorDays=(int)$st->fetchColumn();
        return [
            'unique_visitors'=>$unique,
            'new_visitors'=>$new,
            'returning_visitors'=>max(0,$unique-$new),
            'page_views_per_visitor'=>$unique>0 ? $totals['page_views']/$unique : 0.0,
            'avg_unique_per_day'=>$visitorDays/$days,
            'avg_views_per_day'=>$totals['page_views']/$days,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function daily(int $days): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM analytics_daily WHERE day >= :day ORDER BY day'
        );
        $statement->execute(['day' => gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'))]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function topPages(int $days, int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT route, MAX(title) AS title, SUM(views) AS views FROM analytics_pages '
            . 'WHERE day >= :day GROUP BY route ORDER BY views DESC, route LIMIT :limit'
        );
        $statement->bindValue(':day', gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days')));
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function topReferrers(int $days, int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT host, SUM(views) AS views FROM analytics_referrers WHERE day >= :day '
            . 'GROUP BY host ORDER BY views DESC, host LIMIT :limit'
        );
        $statement->bindValue(':day', gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days')));
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function devices(int $days): array
    {
        $statement = $this->pdo->prepare(
            'SELECT device, SUM(views) AS views FROM analytics_devices WHERE day >= :day '
            . 'GROUP BY device ORDER BY views DESC, device'
        );
        $statement->execute(['day' => gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'))]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function recentApplicationLogs(int $limit = 100): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM application_logs ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function recentAuditLogs(int $limit = 100): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM audit_logs ORDER BY id DESC LIMIT :limit');
        $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function purge(int $analyticsDays, int $applicationDays, int $auditDays): void
    {
        foreach ([
            ['analytics_daily', 'day', $analyticsDays],
            ['analytics_pages', 'day', $analyticsDays],
            ['analytics_referrers', 'day', $analyticsDays],
            ['analytics_devices', 'day', $analyticsDays],
            ['analytics_visitor_days', 'day', $analyticsDays],
            ['analytics_unique_visitors', 'last_seen_day', $analyticsDays],
            ['application_logs', 'created_at', $applicationDays],
            ['audit_logs', 'created_at', $auditDays],
        ] as [$table, $column, $days]) {
            if (!$this->tableExists($table)) { continue; }
            $statement = $this->pdo->prepare(
                'DELETE FROM ' . $table . ' WHERE ' . $column . ' < :cutoff'
            );
            $statement->execute([
                'cutoff' => str_contains($column, 'day')
                    ? gmdate('Y-m-d', strtotime('-' . max(1, (int) $days) . ' days'))
                    : gmdate('Y-m-d H:i:s', strtotime('-' . max(1, (int) $days) . ' days')),
            ]);
        }
    }

    private function tableExists(string $table): bool
    {
        $statement=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $statement->execute(['name'=>$table]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, string> $additional */
    private function incrementDimension(
        string $table,
        string $key,
        string $day,
        string $dimension,
        string $value,
        array $additional = []
    ): void {
        $columns = ['entry_key', 'day', $dimension, ...array_keys($additional), 'views'];
        $parameters = ['entry_key' => $key, 'day' => $day, $dimension => $value, ...$additional, 'views' => 1];
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $updates = 'views = views + 1';
        foreach (array_keys($additional) as $column) {
            $updates .= ', ' . $column . ' = excluded.' . $column;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') '
            . 'VALUES (' . implode(', ', $placeholders) . ') '
            . 'ON CONFLICT(entry_key) DO UPDATE SET ' . $updates
        );
        $statement->execute($parameters);
    }
}
