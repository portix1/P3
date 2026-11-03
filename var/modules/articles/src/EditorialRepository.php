<?php
declare(strict_types=1);
namespace PortixModule\Articles;
use PDO;
use RuntimeException;
use Throwable;

final class EditorialRepository
{
    private array $tables = [];
    public function __construct(private readonly PDO $pdo) {}
    public function available(string $table): bool
    {
        if (!array_key_exists($table, $this->tables)) {
            $q=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);
            $this->tables[$table]=$q->fetchColumn()!==false;
        }
        return $this->tables[$table];
    }
    public function series(): array { return $this->pdo->query('SELECT * FROM article_series ORDER BY title COLLATE NOCASE,id')->fetchAll(); }
    public function findSeries(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT * FROM article_series WHERE id=?');$q->execute([$id]);return $q->fetch()?:null;
    }
    public function parts(int $seriesId, bool $publishedOnly=false): array
    {
        $q=$this->pdo->prepare('SELECT a.id,a.title,a.slug,a.status,p.position FROM article_series_parts p JOIN articles a ON a.id=p.article_id WHERE p.series_id=?'
            .($publishedOnly ? " AND a.status='published'" : '').' ORDER BY p.position,p.id');
        $q->execute([$seriesId]);return $q->fetchAll();
    }
    public function publicSeries(int $articleId): ?array
    {
        if(!$this->available('article_series_parts'))return null;
        $q=$this->pdo->prepare('SELECT s.* FROM article_series s JOIN article_series_parts p ON p.series_id=s.id JOIN articles a ON a.id=p.article_id WHERE p.article_id=? AND a.status=\'published\'');
        $q->execute([$articleId]);$series=$q->fetch();if(!$series)return null;
        $series['parts']=$this->parts((int)$series['id'],true);
        $series['index']=array_search($articleId,array_map(static fn(array $a): int=>(int)$a['id'],$series['parts']),true);
        return $series;
    }
    public function saveSeries(?int $id,string $title,string $description,bool $showTitle,array $articleIds): int
    {
        $title=trim(strip_tags($title));$description=trim(strip_tags($description));
        $length=static fn(string $text): int=>function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):strlen($text);
        if($title===''||$length($title)>250||$length($description)>2000)throw new RuntimeException('articles_series_invalid');
        if(count($articleIds)!==count(array_unique($articleIds)))throw new RuntimeException('articles_invalid_order');
        $ownsTransaction=!$this->pdo->inTransaction();if($ownsTransaction)$this->pdo->beginTransaction();
        try {
            if($id!==null && $this->findSeries($id)===null)throw new RuntimeException('articles_series_missing');
            foreach($articleIds as $articleId){
                if(!is_int($articleId)||$articleId<1)throw new RuntimeException('articles_invalid_id');
                $q=$this->pdo->prepare('SELECT id FROM articles WHERE id=?');$q->execute([$articleId]);if(!$q->fetchColumn())throw new RuntimeException('articles_not_found');
                $q=$this->pdo->prepare('SELECT series_id FROM article_series_parts WHERE article_id=?');$q->execute([$articleId]);$owner=$q->fetchColumn();
                if($owner!==false && (int)$owner!==$id)throw new RuntimeException('articles_series_conflict');
            }
            if($id===null){$q=$this->pdo->prepare('INSERT INTO article_series(title,description,show_title) VALUES(?,?,?)');$q->execute([$title,$description,$showTitle?1:0]);$id=(int)$this->pdo->lastInsertId();}
            else {$q=$this->pdo->prepare('UPDATE article_series SET title=?,description=?,show_title=? WHERE id=?');$q->execute([$title,$description,$showTitle?1:0,$id]);}
            $q=$this->pdo->prepare('DELETE FROM article_series_parts WHERE series_id=?');$q->execute([$id]);
            $q=$this->pdo->prepare('INSERT INTO article_series_parts(series_id,article_id,position) VALUES(?,?,?)');
            foreach($articleIds as $position=>$articleId)$q->execute([$id,$articleId,$position]);
            if($ownsTransaction)$this->pdo->commit();return $id;
        }catch(Throwable $e){if($ownsTransaction)$this->pdo->rollBack();throw $e;}
    }
    public function deleteSeries(int $id): void
    {
        $q=$this->pdo->prepare('DELETE FROM article_series WHERE id=?');$q->execute([$id]);
    }
    /** One bounded query; options are absent until the author opts in. */
    public function reading(int $articleId, bool $publishedOnly=false): array
    {
        $empty=['toc_enabled'=>false,'related'=>[]];
        if(!$this->available('article_reading_options'))return $empty;
        $q=$this->pdo->prepare('SELECT o.toc_enabled,a.id,a.title,a.slug,a.status FROM article_reading_options o '
            .'LEFT JOIN article_relations r ON r.article_id=o.article_id '
            .'LEFT JOIN articles a ON a.id=r.target_id'.($publishedOnly?" AND a.status='published'":'')
            .' WHERE o.article_id=? ORDER BY r.position,r.id');
        $q->execute([$articleId]);$rows=$q->fetchAll();
        foreach($rows as $row){$empty['toc_enabled']=(bool)$row['toc_enabled'];if($row['id']!==null)$empty['related'][]=$row;}
        return $empty;
    }
    public function saveReading(int $articleId,bool $tocEnabled,array $relatedIds): void
    {
        if(count($relatedIds)!==count(array_unique($relatedIds))||in_array($articleId,$relatedIds,true))throw new RuntimeException('articles_related_invalid');
        $ownsTransaction=!$this->pdo->inTransaction();if($ownsTransaction)$this->pdo->beginTransaction();
        try{
            foreach($relatedIds as $id){
                if(!is_int($id)||$id<1)throw new RuntimeException('articles_invalid_id');
                $q=$this->pdo->prepare('SELECT 1 FROM articles WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('articles_not_found');
            }
            $q=$this->pdo->prepare('INSERT INTO article_reading_options(article_id,toc_enabled) VALUES(?,?) ON CONFLICT(article_id) DO UPDATE SET toc_enabled=excluded.toc_enabled');$q->execute([$articleId,$tocEnabled?1:0]);
            $q=$this->pdo->prepare('DELETE FROM article_relations WHERE article_id=?');$q->execute([$articleId]);
            $q=$this->pdo->prepare('INSERT INTO article_relations(article_id,target_id,position) VALUES(?,?,?)');foreach($relatedIds as $position=>$id)$q->execute([$articleId,$id,$position]);
            if($ownsTransaction)$this->pdo->commit();
        }catch(Throwable $e){if($ownsTransaction)$this->pdo->rollBack();throw $e;}
    }

}
