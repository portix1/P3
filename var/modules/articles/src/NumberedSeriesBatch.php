<?php
declare(strict_types=1);
namespace PortixModule\Articles;
use Portix\Infrastructure\Database;
use PDO;
use RuntimeException;

/** Generic one-time grouping of explicitly numbered parts; no editorial inference. */
final class NumberedSeriesBatch
{
    private const MARKER='numbered_article_series_batch_1';
    private static function key(string $title): string
    {
        $title=preg_replace('/\s+/u',' ',trim($title));
        return function_exists('mb_strtolower')?mb_strtolower($title,'UTF-8'):strtolower($title);
    }
    /** @return array{plans:array,skipped:int} */
    public static function plan(PDO $pdo): array
    {
        $groups=[];
        foreach($pdo->query('SELECT id,title FROM articles ORDER BY id')->fetchAll() as $article){
            if(!preg_match('/^(.+?)\s+[—–-]\s*(?:partie|part)\s+([1-9][0-9]*)\s*(?:[—–-]\s*.*)?$/iu',(string)$article['title'],$match))continue;
            $title=trim($match[1]);$key=self::key($title);$part=(int)$match[2];
            if(!isset($groups[$key]))$groups[$key]=['title'=>$title,'parts'=>[],'duplicate'=>false];
            if(isset($groups[$key]['parts'][$part]))$groups[$key]['duplicate']=true;
            $groups[$key]['parts'][$part]=(int)$article['id'];
        }
        $owned=array_fill_keys(array_map('intval',$pdo->query('SELECT article_id FROM article_series_parts')->fetchAll(PDO::FETCH_COLUMN)),true);
        $titles=[];foreach($pdo->query('SELECT title FROM article_series')->fetchAll(PDO::FETCH_COLUMN) as $title)$titles[self::key((string)$title)]=true;
        $plans=[];$skipped=0;
        foreach($groups as $key=>$group){
            ksort($group['parts'],SORT_NUMERIC);$parts=$group['parts'];
            if($group['duplicate']||count($parts)<2||array_keys($parts)!==range(1,count($parts))||isset($titles[$key])){$skipped++;continue;}
            if(array_filter($parts,static fn(int $id):bool=>isset($owned[$id]))!==[]){$skipped++;continue;}
            $plans[]=['title'=>$group['title'],'article_ids'=>array_values($parts)];
        }
        return ['plans'=>$plans,'skipped'=>$skipped];
    }
    /** @return array{created:int,linked:int,skipped:int,series_ids:array,backup:string}|null */
    public static function applyOnce(Database $database,string $root): ?array
    {
        return $database->transaction(static function(PDO $pdo) use($root): ?array{
            $check=$pdo->prepare('SELECT setting_value FROM article_settings WHERE setting_key=?');$check->execute([self::MARKER]);if($check->fetchColumn()!==false)return null;
            $plan=self::plan($pdo);$backup='';
            if($plan['plans']!==[]){
                $directory=rtrim($root,'/\\').'/var/private/numbered-series-backups';
                if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('articles_series_batch_backup_failed');
                if(is_link($directory))throw new RuntimeException('articles_series_batch_backup_failed');
                $backup='var/private/numbered-series-backups/before-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json';
                $snapshot=['created_at'=>gmdate('c'),'existing_series'=>$pdo->query('SELECT * FROM article_series ORDER BY id')->fetchAll(),'existing_parts'=>$pdo->query('SELECT * FROM article_series_parts ORDER BY id')->fetchAll(),'plans'=>$plan['plans']];
                if(file_put_contents(rtrim($root,'/\\').'/'.$backup,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX)===false)throw new RuntimeException('articles_series_batch_backup_failed');
                chmod(rtrim($root,'/\\').'/'.$backup,0600);
            }
            $repository=new EditorialRepository($pdo);$ids=[];$linked=0;
            foreach($plan['plans'] as $series){$ids[]=$repository->saveSeries(null,$series['title'],'',true,$series['article_ids']);$linked+=count($series['article_ids']);}
            $result=['created'=>count($ids),'linked'=>$linked,'skipped'=>$plan['skipped'],'series_ids'=>$ids,'backup'=>$backup];
            $mark=$pdo->prepare('INSERT INTO article_settings(setting_key,setting_value) VALUES(?,?)');$mark->execute([self::MARKER,json_encode($result,JSON_THROW_ON_ERROR)]);return $result;
        });
    }
}
