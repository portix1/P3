<?php
declare(strict_types=1);

namespace Portix\Insights;

use PDO;
use RuntimeException;

/** Only incoming cookies identify browsers. Older estimates remain separate. */
final class ConfirmedAudienceMetrics
{
    public function __construct(private readonly PDO $pdo) {}

    public function ready(): bool
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('analytics_confirmed_visitors','analytics_confirmed_daily','analytics_collection_daily')")->fetchColumn() === 3;
    }

    public function record(string $day, ?string $key): void
    {
        if (!$this->ready()) {
            throw new RuntimeException('Schéma de mesure confirmée incomplet. Réinstallez le module Statistiques et journaux.');
        }
        $own = !$this->pdo->inTransaction();
        if ($own) { $this->pdo->beginTransaction(); }
        try {
            $q = $this->pdo->prepare('INSERT INTO analytics_collection_daily (day,confirmed_pages,unconfirmed_pages) VALUES (:day,:confirmed,:unconfirmed) ON CONFLICT(day) DO UPDATE SET confirmed_pages=confirmed_pages+excluded.confirmed_pages,unconfirmed_pages=unconfirmed_pages+excluded.unconfirmed_pages');
            $q->execute(['day' => $day, 'confirmed' => $key !== null ? 1 : 0, 'unconfirmed' => $key === null ? 1 : 0]);
            if ($key !== null) {
                $q = $this->pdo->prepare('INSERT INTO analytics_confirmed_visitors (visitor_key,first_seen_day,last_seen_day) VALUES (:key,:day,:day) ON CONFLICT(visitor_key) DO UPDATE SET first_seen_day=MIN(first_seen_day,excluded.first_seen_day),last_seen_day=MAX(last_seen_day,excluded.last_seen_day)');
                $q->execute(['key' => $key, 'day' => $day]);
                $q = $this->pdo->prepare('INSERT INTO analytics_confirmed_daily (entry_key,day,visitor_key,page_views) VALUES (:entry,:day,:key,1) ON CONFLICT(entry_key) DO UPDATE SET page_views=page_views+1');
                $q->execute(['entry' => $day . '|' . $key, 'day' => $day, 'key' => $key]);
            }
            if ($own) { $this->pdo->commit(); }
        } catch (\Throwable $error) {
            if ($own && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $error;
        }
    }

    /** @return array{available:bool,unique:int,new:int,known:int,visitor_days:int,pages:?int,ratio:?float,first_day:string,confirmed_pages:int,unconfirmed_pages:int} */
    public function summary(int $days): array
    {
        $result = ['available' => false, 'unique' => 0, 'new' => 0, 'known' => 0, 'visitor_days' => 0, 'pages' => null, 'ratio' => null, 'first_day' => '', 'confirmed_pages' => 0, 'unconfirmed_pages' => 0];
        if (!$this->ready()) { return $result; }
        $cutoff = gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
        $q = $this->pdo->prepare('SELECT COUNT(DISTINCT d.visitor_key),COUNT(DISTINCT CASE WHEN u.first_seen_day >= :cutoff THEN d.visitor_key END),COUNT(*),COALESCE(SUM(d.page_views),0) FROM analytics_confirmed_daily d JOIN analytics_confirmed_visitors u ON u.visitor_key=d.visitor_key WHERE d.day >= :cutoff');
        $q->execute(['cutoff' => $cutoff]);
        $row = $q->fetch(PDO::FETCH_NUM);
        $result['unique'] = (int) $row[0];
        $result['new'] = (int) $row[1];
        $result['known'] = $result['unique'] - $result['new'];
        $result['visitor_days'] = (int) $row[2];
        $result['available'] = $result['unique'] > 0;
        if ($result['available']) {
            $result['pages'] = (int) $row[3];
            $result['ratio'] = $result['pages'] / $result['unique'];
        }
        $q = $this->pdo->prepare('SELECT COALESCE(SUM(confirmed_pages),0),COALESCE(SUM(unconfirmed_pages),0) FROM analytics_collection_daily WHERE day >= :cutoff');
        $q->execute(['cutoff' => $cutoff]);
        $counts = $q->fetch(PDO::FETCH_NUM);
        $result['confirmed_pages'] = (int) $counts[0];
        $result['unconfirmed_pages'] = (int) $counts[1];
        $result['first_day'] = (string) ($this->pdo->query('SELECT MIN(day) FROM analytics_collection_daily')->fetchColumn() ?: '');
        return $result;
    }

    public function daily(int $days): array
    {
        if (!$this->ready()) { return []; }
        $q = $this->pdo->prepare('SELECT day,confirmed_pages,unconfirmed_pages FROM analytics_collection_daily WHERE day >= :cutoff ORDER BY day');
        $q->execute(['cutoff' => gmdate('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'))]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function purge(int $days): void
    {
        if (!$this->ready()) { return; }
        $cutoff = gmdate('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
        foreach (['analytics_confirmed_daily' => 'day', 'analytics_collection_daily' => 'day', 'analytics_confirmed_visitors' => 'last_seen_day'] as $table => $column) {
            $q = $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $column . ' < :cutoff');
            $q->execute(['cutoff' => $cutoff]);
        }
    }
}
