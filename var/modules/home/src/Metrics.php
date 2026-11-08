<?php
declare(strict_types=1);
namespace PortixModule\Home;
use PDO;
use InvalidArgumentException;

final class Metrics
{
    public function __construct(private readonly PDO $pdo, private readonly string $secret) {}
    public function ticket(array $slots, int $now): string
    {
        $data=base64_encode(json_encode(['expires'=>$now+7200,'slots'=>$slots],JSON_THROW_ON_ERROR));
        return $data.'.'.hash_hmac('sha256',$data,$this->secret);
    }
    public function accept(array $input, int $now): void
    {
        $ticket=(string)($input['ticket']??'');
        if (strlen($ticket)>20000 || strlen($this->secret)<32) { throw new InvalidArgumentException('ticket'); }
        $parts=explode('.',$ticket);
        if (count($parts)!==2 || !hash_equals(hash_hmac('sha256',$parts[0],$this->secret),$parts[1])) { throw new InvalidArgumentException('ticket'); }
        $data=json_decode((string)base64_decode($parts[0],true),true);
        if (!is_array($data) || ($data['expires']??0)<$now || ($data['expires']??0)>$now+7200 || !is_array($data['slots']??null)) { throw new InvalidArgumentException('expired'); }
        $page=(string)($input['page']??''); $events=$input['events']??null;
        if (!preg_match('/^[a-f0-9]{32}$/D',$page) || !is_array($events) || count($events)>61) { throw new InvalidArgumentException('events'); }
        $rows=[];
        foreach ($events as $event) {
            if (!is_array($event) || !in_array($event['kind']??'', ['page','visible','click'],true)) { throw new InvalidArgumentException('event'); }
            $kind=$event['kind'];
            if ($kind==='page') { $rows[]=['kind'=>'page','section'=>0,'article'=>0,'position'=>0]; continue; }
            $slot=(string)($event['slot']??'');
            if (!isset($data['slots'][$slot])) { throw new InvalidArgumentException('slot'); }
            [$section,$article,$position]=$data['slots'][$slot];
            $rows[]=['kind'=>$kind,'section'=>(int)$section,'article'=>(int)$article,'position'=>(int)$position];
        }
        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                // Deduplication is short lived, scoped to this page load, and stores no persistent browser ID.
                $dedup=hash_hmac('sha256',$page.'|'.$ticket.'|'.$row['kind'].'|'.$row['section'].'|'.$row['article'].'|'.$row['position'],$this->secret);
                $q=$this->pdo->prepare('INSERT OR IGNORE INTO analytics_home_events(entry_key,expires_at) VALUES(?,?)');
                $q->execute([$dedup,$now+172800]);
                if ($q->rowCount()===0) { continue; }
                $this->increment($row,$now);
                if ($row['kind']==='click') {
                    $key=hash_hmac('sha256',$page.'|'.$ticket.'|converted',$this->secret);
                    $q->execute([$key,$now+172800]);
                    if ($q->rowCount()>0) { $this->increment(['kind'=>'click','section'=>0,'article'=>0,'position'=>0],$now); }
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }
    private function increment(array $row,int $now): void
    {
        $day=gmdate('Y-m-d',$now);
        $q=$this->pdo->prepare('INSERT INTO analytics_home_metrics(entry_key,day,section_id,article_id,position,visible,clicks) VALUES(?,?,?,?,?,?,?) ON CONFLICT(entry_key) DO UPDATE SET visible=visible+excluded.visible,clicks=clicks+excluded.clicks');
        $q->execute([implode('|',[$day,$row['section'],$row['article'],$row['position']]),$day,$row['section'],$row['article'],$row['position'],$row['kind']==='click'?0:1,$row['kind']==='click'?1:0]);
    }
    public function report(int $days=28): array
    {
        $q=$this->pdo->prepare('SELECT section_id,article_id,position,SUM(visible) AS visible,SUM(clicks) AS clicks FROM analytics_home_metrics WHERE day>=? GROUP BY section_id,article_id,position ORDER BY section_id,clicks DESC');
        $q->execute([gmdate('Y-m-d',time()-($days-1)*86400)]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
