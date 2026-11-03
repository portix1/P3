<?php
declare(strict_types=1);
namespace Portix\Insights;
use PDO;
use RuntimeException;

/** Module-owned audience measurements, independent of legacy visit estimates. */
final class AudienceMetrics
{
    public function __construct(private readonly PDO $pdo) {}

    public function ready(): bool
    {
        $query = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('analytics_unique_visitors','analytics_visitor_days','analytics_audience_daily')");
        $query->execute();
        return (int) $query->fetchColumn() === 3;
    }

    public function record(string $day, string $key): void
    {
        if (!$this->ready()) { throw new RuntimeException('Schéma de mesure d’audience incomplet. Réinstallez le module Statistiques et journaux.'); }
        $own = !$this->pdo->inTransaction();
        if ($own) { $this->pdo->beginTransaction(); }
        try {
            $q = $this->pdo->prepare('INSERT INTO analytics_unique_visitors (visitor_key,first_seen_day,last_seen_day) VALUES (:key,:day,:day) ON CONFLICT(visitor_key) DO UPDATE SET last_seen_day=MAX(last_seen_day,excluded.last_seen_day)');
            $q->execute(['key'=>$key,'day'=>$day]);
            $q = $this->pdo->prepare('INSERT OR IGNORE INTO analytics_visitor_days (entry_key,day,visitor_key) VALUES (:entry,:day,:key)');
            $q->execute(['entry'=>$day.'|'.$key,'day'=>$day,'key'=>$key]);
            $q = $this->pdo->prepare('INSERT INTO analytics_audience_daily (entry_key,day,visitor_key,page_views) VALUES (:entry,:day,:key,1) ON CONFLICT(entry_key) DO UPDATE SET page_views=page_views+1');
            $q->execute(['entry'=>$day.'|'.$key,'day'=>$day,'key'=>$key]);
            if ($own) { $this->pdo->commit(); }
        } catch (\Throwable $error) {
            if ($own && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $error;
        }
    }

    /** @return array{available:bool,unique:int,new:int,known:int,visitor_days:int,pages:?int,ratio:?float,first_day:string} */
    public function summary(int $days): array
    {
        $result=['available'=>false,'unique'=>0,'new'=>0,'known'=>0,'visitor_days'=>0,'pages'=>null,'ratio'=>null,'first_day'=>''];
        if (!$this->ready()) { return $result; }
        $cutoff = gmdate('Y-m-d', strtotime('-'.max(0,$days-1).' days'));
        $q=$this->pdo->prepare('SELECT COUNT(DISTINCT d.visitor_key),COUNT(DISTINCT CASE WHEN u.first_seen_day >= :cutoff THEN d.visitor_key END),COUNT(*),SUM(d.page_views) FROM analytics_audience_daily d JOIN analytics_unique_visitors u ON u.visitor_key=d.visitor_key WHERE d.day >= :cutoff');
        $q->execute(['cutoff'=>$cutoff]);$row=$q->fetch(PDO::FETCH_NUM);
        $result['unique']=(int)$row[0];$result['new']=(int)$row[1];$result['known']=$result['unique']-$result['new'];$result['visitor_days']=(int)$row[2];
        $result['available']=$result['unique']>0;
        $result['first_day']=(string)($this->pdo->query('SELECT MIN(day) FROM analytics_audience_daily')->fetchColumn()?:'');
        // Both numerator and denominator come from the same detailed measurements.
        if($result['available']){$result['pages']=(int)$row[3];$result['ratio']=(float)($result['pages']/$result['unique']);}
        return $result;
    }

    public function purge(int $days): void
    {
        if (!$this->ready()) { return; }
        $q=$this->pdo->prepare('DELETE FROM analytics_audience_daily WHERE day<:cutoff');
        $q->execute(['cutoff'=>gmdate('Y-m-d',strtotime('-'.max(1,$days).' days'))]);
    }
}
