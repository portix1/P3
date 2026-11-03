<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,4);require $root.'/bootstrap/autoload.php';require dirname(__DIR__).'/src/ArticleRepository.php';require dirname(__DIR__).'/src/NumberedSeriesBatch.php';
use PortixModule\Articles\NumberedSeriesBatch as Batch;
function numberedAssert(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
try{
 $db=new Portix\Infrastructure\Database(':memory:');(new Portix\Infrastructure\Migrator($db))->migrate();$pdo=$db->connection();(new Portix\Module\ModuleInstaller($db,new Portix\Repository\ModuleRepository($pdo)))->installDirectory(dirname(__DIR__));$a=new PortixModule\Articles\ArticleRepository($pdo);$r=$a->editorial();$cat=$a->createCategory('Category','category',null);$serial=0;
 $make=static function(string $title,string $status='published',?int $category=null)use($a,&$serial):int{return $a->createArticle($category,$title,'numbered-'.++$serial,'<p>Original</p>',$status,'test',null,'2026-01-01');};
 $fr2=$make('Dessiner — partie 2 — fin','draft',$cat);$fr1=$make('Dessiner — partie 1 — début');
 $en3=$make('Build - part 3 - final');$en1=$make('Build - part 1 - first');$en2=$make('Build - part 2 - middle');
 foreach(['Gap — partie 1 — a','Gap — partie 3 — b','Duplicate — partie 1 — a','Duplicate — partie 1 — b','Duplicate — partie 2 — c','Single — partie 1 — a','Orphan — partie 2 — a','Orphan — partie 3 — b','Ordinary article'] as $title)$make($title);
 $owned1=$make('Assigned — partie 1 — a');$owned2=$make('Assigned — partie 2 — b');$manual=$r->saveSeries(null,'Manual','Keep',false,[$owned1]);$same1=$make('Existing — partie 1 — a');$same2=$make('Existing — partie 2 — b');$r->saveSeries(null,'Existing','Keep',false,[]);
 $r->saveReading($fr1,true,[$owned2]);$before=$pdo->query('SELECT * FROM articles ORDER BY id')->fetchAll();$relations=$pdo->query('SELECT * FROM article_relations ORDER BY id')->fetchAll();$options=$pdo->query('SELECT * FROM article_reading_options ORDER BY article_id')->fetchAll();$existing=$pdo->query('SELECT * FROM article_series ORDER BY id')->fetchAll();$manualParts=$r->parts($manual);
 $plan=Batch::plan($pdo);numberedAssert(count($plan['plans'])===2&&$plan['skipped']===6,'only complete unassigned sequences');
 $tmp=sys_get_temp_dir().'/p3-numbered-'.bin2hex(random_bytes(6));$result=Batch::applyOnce($db,$tmp);numberedAssert($result['created']===2&&$result['linked']===5,'batch counts');
 $created=$result['series_ids'];numberedAssert(array_map('intval',array_column($r->parts($created[0]),'id'))===[$fr1,$fr2],'number ordering independent of categories/creation');numberedAssert(array_map('intval',array_column($r->parts($created[1]),'id'))===[$en1,$en2,$en3],'English sequence');numberedAssert(count($r->publicSeries($fr1)['parts'])===1&&$r->publicSeries($fr2)===null,'draft excluded publicly');
 numberedAssert($r->parts($manual)===$manualParts,'manual membership retained');numberedAssert($before===$pdo->query('SELECT * FROM articles ORDER BY id')->fetchAll()&&$relations===$pdo->query('SELECT * FROM article_relations ORDER BY id')->fetchAll()&&$options===$pdo->query('SELECT * FROM article_reading_options ORDER BY article_id')->fetchAll(),'content URLs relations and TOC unchanged');
 $backup=json_decode(file_get_contents($tmp.'/'.$result['backup']),true,512,JSON_THROW_ON_ERROR);numberedAssert($backup['existing_series']===$existing,'existing series backed up');$r->deleteSeries($created[0]);numberedAssert(Batch::applyOnce($db,$tmp)===null,'never recreates manually removed series');
 // Prove all-or-nothing behavior if a later series insert fails.
 $pdo->exec("DELETE FROM article_settings WHERE setting_key='numbered_article_series_batch_1'");$pdo->exec('DELETE FROM article_series');$pdo->exec("CREATE TRIGGER fail_series BEFORE INSERT ON article_series WHEN NEW.title='Build' BEGIN SELECT RAISE(ABORT,'test failure'); END");
 try{Batch::applyOnce($db,$tmp);throw new LogicException('missing failure');}catch(RuntimeException|PDOException $e){}
 numberedAssert((int)$pdo->query('SELECT COUNT(*) FROM article_series')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM article_series_parts')->fetchColumn()===0,'all series rolled back');numberedAssert((int)$pdo->query("SELECT COUNT(*) FROM article_settings WHERE setting_key='numbered_article_series_batch_1'")->fetchColumn()===0,'failed batch unmarked');
 numberedAssert($pdo->query('PRAGMA integrity_check')->fetchColumn()==='ok'&&$pdo->query('PRAGMA foreign_key_check')->fetch()===false,'integrity');
 echo "Numbered series: FR/EN, order, incomplete/duplicate/conflicting sequences, drafts, preservation, backup, idempotence and atomic rollback PASS\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
